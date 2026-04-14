<?php

namespace App\Services\TaskProcessors;

use App\Adapter\Prompts;
use App\Models\SysTask;
use App\Repository\ContentRepository;
use App\Repository\SysTaskRepository;
use App\Services\GptService;
use Illuminate\Support\Facades\Log;

/**
 * 模型建议任务处理器
 */
class MoldSuggestTaskProcessor implements TaskProcessorInterface
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
        $question = (string) ($payload['suggest'] ?? '');

        if ($question === '') {
            $this->taskRepository->markFailed($task, '缺少必要参数：suggest');

            return;
        }

        $finalPrompt = Prompts::getSchemaPrompt($question);
        $userId = $task->requested_by ?? null;

        $result = $this->gptService->chat('', [
            ['role' => 'user', 'content' => $finalPrompt],
        ], $userId, $question);

        // 直接透传生成结构，由前端进行 TransformChildren 适配
        $this->taskRepository->markSuccess($task, $result);

        if ($payload['isCreate'] ?? false) {
            try {
                $contentInfo = [];
                foreach ($result as $one) {
                    $contentInfo[] = [
                        $one['id'] => $one['value'],
                    ];
                }
                ContentRepository::buildContent($task->related_id)->create($contentInfo);
            } catch (\Exception $e) {
                Log::error('MoldSuggest创建内容失败: '.$e->getMessage(), [
                    'task_id' => $task->id,
                ]);
            }
        }
    }
}
