<?php

namespace App\Services;

use App\Jobs\ProcessSysTaskJob;
use App\Models\ClientAiConversation;
use App\Models\SysAiSession;
use App\Models\SysTask;
use App\Models\SysTaskStep;
use App\Repository\SysTaskRepository;

class AiTaskService extends BaseService
{
    private SysTaskRepository $taskRepository;

    public function __construct(SysTaskRepository $taskRepository)
    {
        $this->taskRepository = $taskRepository;
    }

    public function createTask(string $question, ?int $userId = null, array $options = [], ?int $sessionId = null): array
    {
        $prefix = (string) session('current_project_prefix');
        if (! $prefix) {
            throw new \InvalidArgumentException('Missing current_project_prefix');
        }

        // 自动创建或验证 session
        $sessionId = $this->resolveSessionId($sessionId, $userId, $question);

        $taskMaxAttempts = (int) ($options['task_max_attempts'] ?? 0);

        $task = $this->taskRepository->createTask([
            'domain' => 'ai_agent',
            'type' => SysTask::TYPE_AI_AGENT_RUN,
            'title' => 'AI 智能任务',
            'status' => SysTask::STATUS_PENDING,
            'progress_total' => 0,
            'progress_done' => 0,
            'progress_failed' => 0,
            'max_attempts' => $taskMaxAttempts,
            'requested_by' => $userId,
            'payload' => [
                'question' => $question,
                'options' => $options,
                'session_id' => $sessionId,
            ],
        ]);

        ProcessSysTaskJob::dispatch((int) $task->id);

        return [
            'task_id' => (int) $task->id,
            'session_id' => $sessionId,
        ];
    }

    private function resolveSessionId(?int $sessionId, ?int $userId, string $question): int
    {
        if ($sessionId) {
            $session = SysAiSession::where('id', $sessionId)
                ->where('user_id', $userId)
                ->first();
            if ($session) {
                return (int) $session->id;
            }
        }

        // 自动创建新 session
        $title = mb_substr($question, 0, 30);
        $session = SysAiSession::create([
            'user_id' => $userId ?? 0,
            'title' => $title ?: '新对话',
            'last_message_at' => now(),
            'message_count' => 0,
        ]);

        return (int) $session->id;
    }

    public function getTask(int $id): ?array
    {
        $prefix = (string) session('current_project_prefix');
        $q = SysTask::query()->where('id', $id)->where('type', SysTask::TYPE_AI_AGENT_RUN);
        if ($prefix) {
            $q->where('project_prefix', $prefix);
        }
        $row = $q->first();
        if (! $row) {
            return null;
        }

        $data = $row->toArray();

        // 附加当前步骤信息
        if (in_array($row->status, [SysTask::STATUS_PROCESSING, SysTask::STATUS_PENDING])) {
            $latestStep = SysTaskStep::query()
                ->where('task_id', $id)
                ->orderByDesc('seq')
                ->first();
            if ($latestStep) {
                $data['current_step'] = [
                    'seq' => $latestStep->seq,
                    'title' => $latestStep->title,
                    'status' => $latestStep->status,
                ];
            }
        }

        return $data;
    }

    public function getSteps(int $taskId): array
    {
        return SysTaskStep::query()
            ->where('task_id', $taskId)
            ->orderBy('seq')
            ->get()
            ->toArray();
    }

    public function retryTask(int $id): bool
    {
        $prefix = (string) session('current_project_prefix');
        $q = SysTask::query()->where('id', $id)->where('type', SysTask::TYPE_AI_AGENT_RUN);
        if ($prefix) {
            $q->where('project_prefix', $prefix);
        }
        $task = $q->first();
        if (! $task) {
            return false;
        }
        if ($task->status === SysTask::STATUS_PROCESSING) {
            return true;
        }
        if ($task->status === SysTask::STATUS_SUCCESS) {
            return false;
        }

        $task->update([
            'status' => SysTask::STATUS_PENDING,
            'attempts' => (int) ($task->attempts ?? 0) + 1,
            'error_message' => null,
            'error_code' => null,
            'error_detail' => null,
            'finished_at' => null,
            'run_at' => null,
            'canceled_at' => null,
            'cancel_reason' => null,
        ]);

        ProcessSysTaskJob::dispatch((int) $task->id);

        return true;
    }

    public function retryStep(int $taskId, int $idx): bool
    {
        $step = SysTaskStep::query()->where('task_id', $taskId)->where('seq', $idx)->first();
        if (! $step) {
            return false;
        }
        $step->update([
            'status' => 'pending',
            'attempts' => 0,
            'error_message' => null,
            'error_code' => null,
            'error_detail' => null,
            'finished_at' => null,
        ]);

        return $this->retryTask($taskId);
    }

    public function cancelTask(int $id): bool
    {
        $prefix = (string) session('current_project_prefix');
        $q = SysTask::query()->where('id', $id)->where('type', SysTask::TYPE_AI_AGENT_RUN);
        if ($prefix) {
            $q->where('project_prefix', $prefix);
        }
        $task = $q->first();
        if (! $task) {
            return false;
        }

        $now = now();
        $task->update([
            'status' => SysTask::STATUS_CANCELED,
            'canceled_at' => $now,
            'cancel_reason' => '取消',
            'finished_at' => $now,
        ]);

        return true;
    }

    public function listTasks(int $page = 1, int $pageSize = 20, ?string $status = null): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $prefix = (string) session('current_project_prefix');
        $query = SysTask::query()->where('type', SysTask::TYPE_AI_AGENT_RUN);
        if ($prefix) {
            $query->where('project_prefix', $prefix);
        }
        if ($status) {
            $query->where('status', $status);
        }
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->limit($pageSize)->offset(($page - 1) * $pageSize)->get()->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'page_count' => (int) ceil($total / $pageSize),
        ];
    }

    // ==================== Session 管理 ====================

    public function listSessions(?int $userId, int $page = 1, int $pageSize = 20): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $query = SysAiSession::query();
        if ($userId) {
            $query->where('user_id', $userId);
        }
        $query->where('message_count', '>', 0);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('last_message_at')
            ->limit($pageSize)
            ->offset(($page - 1) * $pageSize)
            ->get(['id', 'title', 'last_message_at', 'message_count', 'created_at'])
            ->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'page_count' => (int) ceil($total / $pageSize),
        ];
    }

    public function getSessionMessages(int $sessionId, ?int $userId, int $page = 1, int $pageSize = 20): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $session = SysAiSession::where('id', $sessionId);
        if ($userId) {
            $session->where('user_id', $userId);
        }
        if (! $session->exists()) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'page_size' => $pageSize, 'page_count' => 0];
        }

        $query = ClientAiConversation::where('session_id', $sessionId);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')
            ->limit($pageSize)
            ->offset(($page - 1) * $pageSize)
            ->get(['id', 'session_id', 'task_id', 'question', 'answer', 'created_at'])
            ->toArray();
        // 返回时反转为时间正序
        $items = array_reverse($items);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'page_count' => (int) ceil($total / $pageSize),
        ];
    }

    public function deleteSession(int $sessionId, ?int $userId): bool
    {
        $query = SysAiSession::where('id', $sessionId);
        if ($userId) {
            $query->where('user_id', $userId);
        }
        $session = $query->first();
        if (! $session) {
            return false;
        }

        ClientAiConversation::where('session_id', $sessionId)->delete();
        $session->delete();

        return true;
    }
}
