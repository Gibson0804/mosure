<?php

namespace App\Services;

use App\Models\SysTask;
use App\Repository\MoldRepository;
use App\Repository\SysTaskRepository;
use App\Services\TaskProcessors\ChromeCaptureAITaskProcessor;
use App\Services\TaskProcessors\ContentBatchTaskProcessor;
use App\Services\TaskProcessors\ContentGenerationTaskProcessor;
use App\Services\TaskProcessors\MarkdownEditTaskProcessor;
use App\Services\TaskProcessors\MediaCaptureTaskProcessor;
use App\Services\TaskProcessors\MoldSuggestTaskProcessor;
use App\Services\TaskProcessors\PageGenerationTaskProcessor;
use App\Services\TaskProcessors\RichTextEditTaskProcessor;
use App\Services\TaskProcessors\TaskProcessorInterface;
use Illuminate\Support\Facades\Log;

/**
 * 任务处理器 - 负责异步任务的实际执行逻辑
 * 从 TaskService 中分离出来，避免循环依赖
 */
class TaskProcessor
{
    private $taskRepository;

    private $moldRepository;

    public function __construct(
        SysTaskRepository $taskRepository,
        MoldRepository $moldRepository
    ) {
        $this->taskRepository = $taskRepository;
        $this->moldRepository = $moldRepository;
    }

    /**
     * 处理系统任务
     */
    public function handleSysTask(SysTask $task): void
    {
        $processor = $this->getProcessor($task->type);

        if ($processor === null) {
            $this->taskRepository->markFailed($task, '未知任务类型: '.$task->type);

            return;
        }

        $processor->process($task);
    }

    /**
     * 根据任务类型获取对应的处理器
     */
    private function getProcessor(string $taskType): ?TaskProcessorInterface
    {
        Log::info('taskType', ['task_type' => $taskType]);
        switch ($taskType) {
            case SysTask::TYPE_CONTENT_GENERATION:
                return app(ContentGenerationTaskProcessor::class);

            case SysTask::TYPE_MOLD_SUGGEST:
                return app(MoldSuggestTaskProcessor::class);

            case SysTask::TYPE_CONTENT_BATCH:
                return app(ContentBatchTaskProcessor::class);

            case SysTask::TYPE_AI_AGENT_RUN:
                $runner = app(AiAgentRunner::class);

                return new class($runner) implements TaskProcessorInterface
                {
                    private $runner;

                    public function __construct($runner)
                    {
                        $this->runner = $runner;
                    }

                    public function process(SysTask $task): void
                    {
                        $this->runner->run($task);
                    }
                };

            case SysTask::TYPE_RICH_TEXT_EDIT:
                return app(RichTextEditTaskProcessor::class);

            case SysTask::TYPE_MARKDOWN_EDIT:
                return app(MarkdownEditTaskProcessor::class);

            case 'chrome_capture_ai':
            case SysTask::TYPE_CHROME_CAPTURE_AI:
                return app(ChromeCaptureAITaskProcessor::class);

            case SysTask::TYPE_MEDIA_CAPTURE:
                return app(MediaCaptureTaskProcessor::class);

            case SysTask::TYPE_PAGE_GENERATION:
                return app(PageGenerationTaskProcessor::class);

            default:
                return null;
        }
    }
}
