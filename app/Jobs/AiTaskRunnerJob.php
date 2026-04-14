<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AiTaskRunnerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $taskId;

    private string $projectPrefix;

    private ?int $userId;

    private array $options;

    public function __construct(int $taskId, string $projectPrefix, ?int $userId = null, array $options = [])
    {
        $this->taskId = $taskId;
        $this->projectPrefix = $projectPrefix;
        $this->userId = $userId;
        $this->options = $options;
    }

    public function handle(): void
    {
        // 已迁移到统一任务中心（sys_tasks + sys_task_steps + ProcessSysTaskJob + AiAgentRunner）

    }
}
