<?php

namespace App\Jobs;

use App\Services\SysTaskRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSysTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $taskId;

    public $tries = 1;

    public function __construct(int $taskId)
    {
        $this->taskId = $taskId;
    }

    public function handle(SysTaskRunner $runner): void
    {
        Log::info('ProcessSysTaskJob_start', [
            'task_id' => $this->taskId,
            'job' => static::class,
        ]);
        $runner->run($this->taskId);
        Log::info('ProcessSysTaskJob_end', [
            'task_id' => $this->taskId,
            'job' => static::class,
        ]);
    }
}
