<?php

namespace App\Services\TaskProcessors;

use App\Adapter\Prompts;
use App\Models\SysTask;
use App\Repository\SysTaskRepository;
use App\Services\GptService;

/**
 * Markdown 文本编辑任务处理器
 */
class MarkdownEditTaskProcessor implements TaskProcessorInterface
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
        $markdown = (string) ($payload['markdown'] ?? '');

        if ($instruction === '' || $markdown === '') {
            $this->taskRepository->markFailed($task, '缺少必要参数：instruction 或 markdown');

            return;
        }

        $finalPrompt = Prompts::getMarkdownEditPrompt($instruction, $markdown);
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
            $newMarkdown = (string) ($ret['markdown'] ?? '');
            $this->taskRepository->markSuccess($task, [
                'result_text' => $resultText,
                'markdown' => $newMarkdown !== '' ? $newMarkdown : $markdown,
            ]);

            return;
        }

        $this->taskRepository->markSuccess($task, [
            'result_text' => 'AI 返回格式不符合预期，已忽略改写。',
            'markdown' => $markdown,
        ]);
    }
}
