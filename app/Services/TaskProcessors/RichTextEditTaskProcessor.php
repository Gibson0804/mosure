<?php

namespace App\Services\TaskProcessors;

use App\Adapter\Prompts;
use App\Jobs\ProcessSysTaskJob;
use App\Models\SysTask;
use App\Repository\SysTaskRepository;
use App\Services\GptService;

/**
 * 富文本编辑任务处理器
 */
class RichTextEditTaskProcessor implements TaskProcessorInterface
{
    private $taskRepository;

    private $gptService;

    public function __construct(
        SysTaskRepository $taskRepository,
        GptService $gptService
    ) {
        $this->taskRepository = $taskRepository;
        $this->gptService = $gptService;
    }

    public function process(SysTask $task): void
    {
        $payload = $task->payload ?? [];
        $instruction = (string) ($payload['instruction'] ?? '');
        $html = (string) ($payload['html'] ?? '');
        $targetLength = $payload['target_length'] ?? null;
        $targetLength = $targetLength === null ? null : (int) $targetLength;

        if ($instruction === '' || $html === '') {
            $this->taskRepository->markFailed($task, '缺少必要参数：instruction 或 html');

            return;
        }

        // 长文分段：按 step 串行生成纯文本，最后统一排版为 HTML
        $splitThreshold = 1200;
        if ($targetLength && $targetLength > $splitThreshold) {
            $this->processRichTextEditTaskBySteps($task, $instruction, $html, $targetLength);

            return;
        }

        $finalPrompt = Prompts::getRichTextEditPrompt($instruction, $html);
        $userId = $task->requested_by ?? null;

        try {
            $ret = $this->gptService->chat('', [
                ['role' => 'user', 'content' => $finalPrompt],
            ], $userId, $instruction, false, 'json', true);
        } catch (\Throwable $e) {
            $this->taskRepository->markFailed($task, 'AI 调用失败: '.$e->getMessage());

            return;
        }

        if (is_array($ret)) {
            $resultText = (string) ($ret['result_text'] ?? '');
            $newHtml = (string) ($ret['html'] ?? '');
            $this->taskRepository->markSuccess($task, [
                'result_text' => $resultText,
                'html' => $newHtml !== '' ? $newHtml : $html,
            ]);

            return;
        }

        $this->taskRepository->markSuccess($task, [
            'result_text' => 'AI 返回格式不符合预期，已忽略改写。',
            'html' => $html,
        ]);
    }

    private function processRichTextEditTaskBySteps(SysTask $task, string $instruction, string $html, int $targetLength): void
    {
        $chunkLen = 1000;
        $maxSteps = 12;
        $totalSteps = (int) ceil(max(1, $targetLength) / $chunkLen);
        if ($totalSteps > $maxSteps) {
            $totalSteps = $maxSteps;
        }

        // 首次执行：初始化状态并创建子任务
        if ($task->status !== SysTask::STATUS_PROCESSING) {
            $task->update([
                'status' => SysTask::STATUS_PROCESSING,
                'started_at' => now(),
                'result' => [
                    'stage' => 'initializing',
                    'total_steps' => $totalSteps,
                    'current_step' => 0,
                    'chunks' => [],
                ],
            ]);

            // 创建子任务
            $childTasks = [];
            for ($i = 0; $i < $totalSteps; $i++) {
                $child = $this->taskRepository->createTask([
                    'type' => SysTask::TYPE_RICH_TEXT_EDIT,
                    'status' => SysTask::STATUS_PENDING,
                    'title' => "改写第 {$i} 段",
                    'parent_id' => $task->id,
                    'root_id' => $task->id,
                    'sort_no' => $i,
                    'payload' => [
                        'instruction' => $instruction,
                        'html' => $html,
                        'step_index' => $i,
                        'total_steps' => $totalSteps,
                        'chunk_length' => $chunkLen,
                    ],
                    'requested_by' => $task->requested_by,
                ]);
                ProcessSysTaskJob::dispatch($child->id);
                $childTasks[] = $child->id;
            }

            $task->update([
                'result' => [
                    'stage' => 'processing',
                    'total_steps' => $totalSteps,
                    'current_step' => 0,
                    'child_tasks' => $childTasks,
                    'chunks' => [],
                ],
            ]);

            // 延迟巡检
            ProcessSysTaskJob::dispatch($task->id)->delay(10);

            return;
        }

        // 巡检阶段：检查子任务状态
        $result = $task->result ?? [];
        $childTaskIds = $result['child_tasks'] ?? [];
        $chunks = $result['chunks'] ?? [];
        $currentStep = $result['current_step'] ?? 0;

        // 查询子任务状态
        $childTasks = SysTask::query()
            ->whereIn('id', $childTaskIds)
            ->get(['id', 'status', 'result', 'sort_no'])
            ->keyBy('id');

        $done = 0;
        $failed = 0;

        foreach ($childTaskIds as $childId) {
            $child = $childTasks[$childId] ?? null;
            if (! $child) {
                continue;
            }

            if ($child->status === SysTask::STATUS_SUCCESS) {
                $done++;
                // 收集结果
                if (! isset($chunks[$child->sort_no])) {
                    $chunks[$child->sort_no] = $child->result ?? [];
                }
            } elseif ($child->status === SysTask::STATUS_FAILED) {
                $failed++;
            }
        }

        // 更新进度
        $task->update([
            'result' => [
                'stage' => 'processing',
                'total_steps' => $totalSteps,
                'current_step' => $done,
                'child_tasks' => $childTaskIds,
                'chunks' => $chunks,
                'done' => $done,
                'failed' => $failed,
            ],
        ]);

        // 如果全部完成，合并结果
        if ($done + $failed >= $totalSteps) {
            // 合并所有段落
            $fullText = '';
            $fullHtml = '';
            ksort($chunks);
            foreach ($chunks as $chunk) {
                $fullText .= $chunk['result_text'] ?? '';
                $fullHtml .= $chunk['html'] ?? '';
            }

            $this->taskRepository->markSuccess($task, [
                'result_text' => $fullText,
                'html' => $fullHtml,
            ]);

            return;
        }

        // 继续巡检
        ProcessSysTaskJob::dispatch($task->id)->delay(10);
    }
}
