<?php

namespace App\Repository;

use App\Models\SysTask;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SysTaskRepository extends BaseRepository
{
    public function __construct(SysTask $model)
    {
        $this->mainModel = $model;
    }

    public function createTask(array $data): SysTask
    {
        $prefix = (string) session('current_project_prefix', '');
        if ($prefix !== '' && ! isset($data['project_prefix'])) {
            $data['project_prefix'] = $prefix;
        }

        if (! isset($data['domain'])) {
            $data['domain'] = 'content';
        }

        if (! isset($data['title'])) {
            $data['title'] = (string) ($data['type'] ?? 'task');
        }

        if (! isset($data['request_id'])) {
            $data['request_id'] = 'T-'.str_replace('-', '', (string) Str::uuid());
        }

        $data['payload']['session'] = [
            'current_project_id' => session('current_project_id', 0),
            'current_project_name' => session('current_project_name', ''),
            'current_project_prefix' => session('current_project_prefix', ''),
            'user_id' => Auth::user() ? Auth::user()->id : null,
        ];

        return $this->mainModel->create($data);
    }

    public function findById(int $id): ?SysTask
    {
        return $this->mainModel->find($id);
    }

    public function markProcessing(SysTask $task): void
    {
        $startedAt = $task->started_at ?: now();
        $task->update([
            'status' => SysTask::STATUS_PROCESSING,
            'started_at' => $startedAt,
            'retry_count' => ($task->retry_count ?? 0) + 1,
        ]);
    }

    public function markSuccess(SysTask $task, array $result): void
    {
        Log::info('[SysTaskRepository] 准备标记任务成功', [
            'task_id' => $task->id,
            'result_to_save' => $result,
        ]);

        $task->update([
            'status' => SysTask::STATUS_SUCCESS,
            'result' => $result,
            'finished_at' => now(),
            'error_message' => null,
        ]);

        Log::info('[SysTaskRepository] 任务已标记成功', [
            'task_id' => $task->id,
            'task_status' => $task->status,
            'task_result' => $task->result,
            'task_finished_at' => $task->finished_at,
        ]);
    }

    public function markFailed(SysTask $task, string $error): void
    {
        $task->update([
            'status' => SysTask::STATUS_FAILED,
            'finished_at' => now(),
            'error_message' => $error,
        ]);
    }
}
