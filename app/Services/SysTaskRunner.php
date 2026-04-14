<?php

namespace App\Services;

use App\Jobs\ProcessSysTaskJob;
use App\Models\SysTask;
use App\Repository\SysTaskRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SysTaskRunner
{
    private SysTaskRepository $taskRepository;

    private TaskProcessor $taskProcessor;

    public function __construct(
        SysTaskRepository $taskRepository,
        TaskProcessor $taskProcessor
    ) {
        $this->taskRepository = $taskRepository;
        $this->taskProcessor = $taskProcessor;
    }

    public function run(int $taskId): void
    {
        $task = $this->taskRepository->findById($taskId);
        Log::info('task2222', [
            'task_id' => $task ? $task->id : null,
            'task_type' => $task ? $task->type : null,
            'task_status' => $task ? $task->status : null,
        ]);
        if (! $task) {
            return;
        }

        // 让队列/CLI 场景也能串联日志
        Log::withContext([
            'task_id' => $task->id,
            'task_type' => $task->type,
            'task_domain' => $task->domain,
            'project_prefix' => $task->project_prefix,
        ]);

        if ($task->canceled_at) {
            return;
        }

        $now = now();
        if ($task->run_at && $task->run_at->gt($now)) {
            return;
        }

        // 默认仅执行 pending；但批量父任务需要在 processing 状态下被 watchdog 巡检推进
        $allowProcessing = in_array((string) $task->type, [
            SysTask::TYPE_CONTENT_BATCH,
            SysTask::TYPE_RICH_TEXT_EDIT,
        ], true);
        if ($task->status !== SysTask::STATUS_PENDING && ! ($allowProcessing && $task->status === SysTask::STATUS_PROCESSING)) {
            return;
        }

        $lockToken = 'L-'.str_replace('-', '', (string) Str::uuid());
        $lockedAt = $task->locked_at;
        if ($lockedAt && $lockedAt->gt($now->copy()->subSeconds(120))) {
            return;
        }

        $ok = SysTask::query()
            ->where('id', $task->id)
            ->where(function ($q) use ($now) {
                $q->whereNull('locked_at')->orWhere('locked_at', '<', $now->copy()->subSeconds(120));
            })
            ->update([
                'locked_at' => $now,
                'lock_token' => $lockToken,
                'locked_by' => 'queue',
            ]);

        if (! $ok) {
            return;
        }

        $task->refresh();

        try {
            Log::info('SysTask_run_start', [
                'task_id' => $task->id,
                'type' => $task->type,
                'domain' => $task->domain,
                'status' => $task->status,
                'attempts' => (int) ($task->attempts ?? 0),
                'max_attempts' => (int) ($task->max_attempts ?? 0),
            ]);

            // 某些父任务类型会在 TaskService 内部自行管理 processing 状态（首次执行要拆分子任务）
            // 若在这里提前 markProcessing，会导致 TaskService 误判为“已进入巡检阶段”，从而不创建子任务。
            $selfManagedProcessing = in_array((string) $task->type, [
                SysTask::TYPE_CONTENT_BATCH,
            ], true);

            if (! $selfManagedProcessing) {
                $this->taskRepository->markProcessing($task);
            }

            $ctx = data_get($task->payload, 'session', []);
            $prefix = (string) data_get($ctx, 'current_project_prefix', '');
            $userId = data_get($ctx, 'user_id');

            if ($prefix !== '') {
                $rid = 'J-'.str_replace('-', '', (string) Str::uuid());
                session([
                    'current_project_id' => data_get($ctx, 'current_project_id', 0),
                    'current_project_name' => data_get($ctx, 'current_project_name', ''),
                    'current_project_prefix' => $prefix,
                    'X-Request-ID' => $rid,
                ]);

                Log::withContext([
                    'request_id' => $rid,
                    'current_project_prefix' => $prefix,
                ]);
            }
            if ($userId) {
                Auth::guard('web')->onceUsingId((int) $userId);
            }

            // 直接使用 TaskProcessor 处理异步任务
            $this->taskProcessor->handleSysTask($task);

            $task->refresh();
            Log::info('SysTask_run_end', [
                'task_id' => $task->id,
                'type' => $task->type,
                'domain' => $task->domain,
                'status' => $task->status,
                'progress_total' => $task->progress_total,
                'progress_done' => $task->progress_done,
                'progress_failed' => $task->progress_failed,
                'error_message' => $task->error_message,
            ]);
        } catch (\Throwable $e) {
            Log::error('SysTask_run_failure', [
                'task_id' => $task->id,
                'type' => $task->type,
                'domain' => $task->domain,
                'status' => $task->status,
                'exception' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ]);
            $this->handleFailure($task, $e);
        } finally {
            SysTask::query()->where('id', $task->id)->update([
                'locked_at' => null,
                'lock_token' => null,
                'locked_by' => null,
            ]);

            session()->forget(['current_project_id', 'current_project_name', 'current_project_prefix', 'X-Request-ID']);
            Auth::logout();

            // 清理上下文，避免污染后续日志
            Log::withContext([]);
        }
    }

    private function handleFailure(SysTask $task, \Throwable $e): void
    {
        $maxAttempts = (int) ($task->max_attempts ?? 0);
        $attempts = (int) ($task->attempts ?? 0);

        if ($maxAttempts > 0 && $attempts < $maxAttempts) {
            $nextAttempts = $attempts + 1;
            $delay = min(300, (int) pow(2, max(0, $nextAttempts - 1)) * 5);

            $task->update([
                'status' => SysTask::STATUS_PENDING,
                'attempts' => $nextAttempts,
                'error_message' => $e->getMessage(),
                'error_code' => 'exception',
                'error_detail' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
                'run_at' => now()->addSeconds($delay),
            ]);

            // 通过队列延迟重试（仅写 run_at 不会自动再次执行）
            ProcessSysTaskJob::dispatch((int) $task->id)->delay($delay);

            return;
        }

        $this->taskRepository->markFailed($task, '任务执行失败，请稍后重试');

        Log::error('SysTask failed '.json_encode([
            'task_id' => $task->id,
            'type' => $task->type,
            'status' => $task->status,
            'payload_keys' => array_keys((array) $task->payload),
            'exception' => [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ],
        ]));

        report($e);
    }
}
