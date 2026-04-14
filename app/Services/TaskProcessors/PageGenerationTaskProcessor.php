<?php

namespace App\Services\TaskProcessors;

use App\Adapter\Prompts;
use App\Models\Mold;
use App\Models\ProjectPage;
use App\Models\SysTask;
use App\Repository\SysTaskRepository;
use App\Services\GptService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 前端页面生成任务处理器
 * 独立 prompt + GptService 直接调用，不走 AiAgentRunner
 */
class PageGenerationTaskProcessor implements TaskProcessorInterface
{
    private SysTaskRepository $taskRepository;

    private GptService $gptService;

    public function __construct(
        SysTaskRepository $taskRepository,
        GptService $gptService
    ) {
        $this->taskRepository = $taskRepository;
        $this->gptService = $gptService;
    }

    public function process(SysTask $task): void
    {
        Log::info('[PageGenerationTaskProcessor] 开始处理', [
            'task_id' => $task->id,
        ]);

        $payload = $task->payload ?? [];
        $prompt = (string) ($payload['prompt'] ?? '');

        if ($prompt === '') {
            $this->taskRepository->markFailed($task, '缺少页面描述');

            return;
        }

        try {
            // 获取当前项目的内容模型信息
            $models = $this->getProjectModelsInfo();

            // 构建提示词
            $finalPrompt = Prompts::getPageGenerationPrompt($prompt, $models);

            // 调用 AI（使用 text 模式，自行解析 JSON，避免 adapter 内部解析失败）
            $userId = $task->requested_by ?? null;
            $result = $this->gptService->chat('', [
                ['role' => 'user', 'content' => $finalPrompt],
            ], $userId, $prompt, true, 'text', true);

            Log::info('[PageGenerationTaskProcessor] AI 返回', [
                'task_id' => $task->id,
                'result_type' => gettype($result),
            ]);

            // 解析结果
            $parsed = $this->parseResult($result);
            if ($parsed === null) {
                $this->taskRepository->markFailed($task, 'AI 返回格式无法解析');

                return;
            }

            $slug = $parsed['slug'] ?? '';
            $title = $parsed['title'] ?? '';
            $htmlContent = $parsed['html_content'] ?? '';

            if ($slug === '' || $htmlContent === '') {
                $this->taskRepository->markFailed($task, 'AI 返回缺少 slug 或 html_content');

                return;
            }

            // 确保表存在
            $this->ensureTableExists();

            // 处理 slug 冲突：自动追加后缀
            $slug = $this->resolveSlugConflict($slug);

            // 创建页面
            $page = ProjectPage::create([
                'slug' => $slug,
                'title' => $title ?: $slug,
                'description' => $parsed['description'] ?? null,
                'page_type' => 'single',
                'status' => 'published',
                'html_content' => $htmlContent,
                'created_by' => $userId,
            ]);

            $prefix = session('current_project_prefix', '');
            $appUrl = rtrim(config('app.url', ''), '/');
            $accessUrl = "{$appUrl}/sites/{$prefix}/{$slug}";

            $this->taskRepository->markSuccess($task, [
                'page_id' => $page->id,
                'slug' => $slug,
                'title' => $page->title,
                'access_url' => $accessUrl,
                'message' => "页面已生成并发布，访问地址: {$accessUrl}",
            ]);

            Log::info('[PageGenerationTaskProcessor] 页面创建成功', [
                'task_id' => $task->id,
                'slug' => $slug,
                'access_url' => $accessUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error('[PageGenerationTaskProcessor] 处理失败', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
            $this->taskRepository->markFailed($task, '页面生成失败: '.$e->getMessage());
        }
    }

    /**
     * 解析 AI 返回结果（兼容 markdown 包裹、text 模式等）
     */
    private function parseResult($result): ?array
    {
        // 直接是数组（json 模式解析成功时）
        if (is_array($result) && isset($result['slug'])) {
            return $result;
        }

        // 从 text 字段提取原始文本
        $text = '';
        if (is_array($result) && isset($result['text'])) {
            $text = (string) $result['text'];
        } elseif (is_string($result)) {
            $text = $result;
        }

        if ($text === '') {
            Log::warning('[PageGenerationTaskProcessor] parseResult: 空文本');

            return null;
        }

        // 1) 去掉 markdown 代码块包裹 ```json ... ```
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $text, $m)) {
            $text = $m[1];
        }

        // 2) 尝试直接 JSON 解析
        $decoded = json_decode($text, true);
        if (is_array($decoded) && isset($decoded['slug'])) {
            return $decoded;
        }

        // 3) 提取第一个 { 到最后一个 } 之间的内容
        $first = strpos($text, '{');
        $last = strrpos($text, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $jsonStr = substr($text, $first, $last - $first + 1);
            $decoded = json_decode($jsonStr, true);
            if (is_array($decoded) && isset($decoded['slug'])) {
                return $decoded;
            }
        }

        Log::warning('[PageGenerationTaskProcessor] parseResult: 无法解析', [
            'text_len' => strlen($text),
            'text_head' => mb_substr($text, 0, 200),
        ]);

        return null;
    }

    /**
     * 解决 slug 冲突
     */
    private function resolveSlugConflict(string $slug): string
    {
        $original = $slug;
        $suffix = 1;
        while (ProjectPage::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * 确保 pages 表存在
     */
    private function ensureTableExists(): void
    {
        $prefix = session('current_project_prefix', '');
        if ($prefix === '') {
            return;
        }

        $tableName = ProjectPage::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, ProjectPage::getTableSchema());
        }
    }

    /**
     * 获取当前项目的内容模型摘要
     */
    private function getProjectModelsInfo(): array
    {
        try {
            $molds = Mold::all();

            return $molds->map(function ($mold) {
                $fields = json_decode($mold->fields ?? '[]', true) ?: [];
                $fieldsSummary = array_values(array_filter(array_map(function ($f) {
                    if (! is_array($f) || ($f['type'] ?? '') === 'dividingLine') {
                        return null;
                    }

                    return [
                        'field' => $f['field'] ?? '',
                        'label' => $f['label'] ?? '',
                        'type' => $f['type'] ?? '',
                    ];
                }, $fields)));

                return [
                    'name' => $mold->name,
                    'table_name' => $mold->table_name,
                    'fields' => $fieldsSummary,
                ];
            })->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
