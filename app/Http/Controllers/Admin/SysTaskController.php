<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\ProcessSysTaskJob;
use App\Models\SysTask;
use App\Models\SysTaskStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SysTaskController extends BaseAdminController
{
    public function index()
    {
        return viewShow('Manage/SysTasks');
    }

    public function list(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page' => ['integer', 'nullable', 'min:1'],
            'per_page' => ['integer', 'nullable', 'min:1', 'max:100'],
            'status' => ['string', 'nullable'],
            'type' => ['string', 'nullable'],
            'keyword' => ['string', 'nullable'],
            'created_at_start' => ['string', 'nullable'],
            'created_at_end' => ['string', 'nullable'],
        ]);

        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 20);
        $status = trim((string) ($data['status'] ?? ''));
        $type = trim((string) ($data['type'] ?? ''));
        $keyword = trim((string) ($data['keyword'] ?? ''));
        $createdAtStart = trim((string) ($data['created_at_start'] ?? ''));
        $createdAtEnd = trim((string) ($data['created_at_end'] ?? ''));

        $prefix = (string) session('current_project_prefix', '');

        $query = SysTask::query();
        if ($prefix !== '') {
            $query->where('project_prefix', $prefix);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($type !== '') {
            $query->where('type', $type);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', '%'.$keyword.'%')
                    ->orWhere('request_id', 'like', '%'.$keyword.'%')
                    ->orWhere('error_message', 'like', '%'.$keyword.'%');
            });
        }
        if ($createdAtStart !== '') {
            $query->where('created_at', '>=', $createdAtStart);
        }
        if ($createdAtEnd !== '') {
            $query->where('created_at', '<=', $createdAtEnd);
        }

        $total = (clone $query)->count();
        $items = $query
            ->orderByDesc('id')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get()
            ->toArray();

        return success([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $perPage,
            'page_count' => (int) ceil($total / $perPage),
        ]);
    }

    public function detail(Request $request, int $id): JsonResponse
    {
        $prefix = (string) session('current_project_prefix', '');
        $query = SysTask::query()->where('id', $id);
        if ($prefix !== '') {
            $query->where('project_prefix', $prefix);
        }

        $task = $query->first();
        if (! $task) {
            return error([], 'not found');
        }

        return success($task->toArray());
    }

    public function children(Request $request, int $id): JsonResponse
    {
        $prefix = (string) session('current_project_prefix', '');

        $query = SysTask::query()->where('parent_id', $id);
        if ($prefix !== '') {
            $query->where('project_prefix', $prefix);
        }

        $items = $query->orderBy('sort_no')->orderBy('id')->get()->toArray();

        return success(['items' => $items]);
    }

    public function steps(Request $request, int $id): JsonResponse
    {
        $prefix = (string) session('current_project_prefix', '');

        $taskQuery = SysTask::query()->where('id', $id);
        if ($prefix !== '') {
            $taskQuery->where('project_prefix', $prefix);
        }
        $task = $taskQuery->first();
        if (! $task) {
            return error([], 'not found');
        }

        $steps = SysTaskStep::query()
            ->where('task_id', $id)
            ->orderBy('seq')
            ->orderBy('id')
            ->get()
            ->toArray();

        return success(['items' => $steps]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['string', 'nullable', 'max:255'],
        ]);

        $prefix = (string) session('current_project_prefix', '');
        $query = SysTask::query()->where('id', $id);
        if ($prefix !== '') {
            $query->where('project_prefix', $prefix);
        }

        $task = $query->first();
        if (! $task) {
            return error([], 'not found');
        }

        if ($task->status === SysTask::STATUS_SUCCESS) {
            return error([], '任务已成功完成，无法取消');
        }

        $now = now();
        $reason = trim((string) ($data['reason'] ?? ''));

        $task->update([
            'status' => SysTask::STATUS_CANCELED,
            'canceled_at' => $now,
            'cancel_reason' => $reason ?: '手动取消',
            'finished_at' => $now,
        ]);

        // 批量父任务：立即级联取消子任务（不等待巡检）
        if (in_array((string) $task->type, [SysTask::TYPE_CONTENT_BATCH, SysTask::TYPE_CONTENT_BATCH_DIRECT], true)) {
            SysTask::query()
                ->where('parent_id', $task->id)
                ->whereNull('canceled_at')
                ->whereNotIn('status', [SysTask::STATUS_SUCCESS, SysTask::STATUS_FAILED, SysTask::STATUS_CANCELED])
                ->update([
                    'status' => SysTask::STATUS_CANCELED,
                    'canceled_at' => $now,
                    'cancel_reason' => '父任务取消',
                    'finished_at' => $now,
                ]);
        }

        return success(['ok' => true]);
    }

    public function retry(Request $request, int $id): JsonResponse
    {
        $prefix = (string) session('current_project_prefix', '');
        $query = SysTask::query()->where('id', $id);
        if ($prefix !== '') {
            $query->where('project_prefix', $prefix);
        }

        $task = $query->first();
        if (! $task) {
            return error([], 'not found');
        }

        if ($task->status === SysTask::STATUS_PROCESSING) {
            return success(['ok' => true, 'message' => '任务正在执行中']);
        }

        if ($task->status === SysTask::STATUS_SUCCESS) {
            return error([], '任务已成功完成，无法重试');
        }

        $task->update([
            'status' => SysTask::STATUS_PENDING,
            'error_message' => null,
            'error_code' => null,
            'error_detail' => null,
            'finished_at' => null,
            'run_at' => null,
            'locked_at' => null,
            'lock_token' => null,
            'locked_by' => null,
            'canceled_at' => null,
            'cancel_reason' => null,
        ]);

        ProcessSysTaskJob::dispatch((int) $task->id);

        return success(['ok' => true]);
    }
}
