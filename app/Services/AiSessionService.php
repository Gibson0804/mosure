<?php

namespace App\Services;

use App\Events\MessageReceived;
use App\Models\Project;
use App\Models\SysAiAgent;
use App\Repository\SysAiSessionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiSessionService
{
    public function __construct(private SysAiSessionRepository $sessionRepo) {}

    public function ensureGlobalGroup(int $userId): object
    {
        $existing = $this->sessionRepo->findGlobalDefaultSession($userId);
        if ($existing) {
            if (($existing->title ?? '') !== '总群') {
                $this->sessionRepo->editById(['title' => '总群'], $existing->id);
                $existing = $this->sessionRepo->find($existing->id);
            }
            $this->syncGlobalGroupMembers($existing);

            return $existing->fresh();
        }

        $memberIds = $this->listGlobalAgentIds();
        $sessionId = $this->sessionRepo->createSession([
            'user_id' => $userId,
            'project_id' => null,
            'title' => '总群',
            'session_type' => 'group',
            'is_default' => 1,
            'member_ids' => json_encode($memberIds, JSON_UNESCAPED_UNICODE),
            'last_message_at' => now(),
            'message_count' => 0,
        ]);

        return $this->sessionRepo->find($sessionId);
    }

    public function listAgents(int $projectId): array
    {
        $this->ensureProjectAgent($projectId);

        return SysAiAgent::query()
            ->where('enabled', true)
            ->where(function ($query) use ($projectId) {
                $query->where('type', 'secretary')
                    ->orWhere(function ($q) use ($projectId) {
                        $q->where('project_id', $projectId);
                    });
            })
            ->orderByRaw("case when type = 'secretary' then 0 when type = 'project' then 1 else 2 end")
            ->orderBy('id')
            ->get()
            ->map(fn (SysAiAgent $agent) => [
                'id' => $agent->id,
                'type' => $agent->type,
                'identifier' => $agent->identifier,
                'name' => $agent->name,
                'avatar' => $agent->avatar,
                'description' => $agent->description,
                'enabled' => (bool) $agent->enabled,
                'project_id' => $agent->project_id,
            ])
            ->values()
            ->all();
    }

    public function getSessionsForUser(int $userId, int $projectId): array
    {
        $sessions = $this->sessionRepo
            ->findByUser($userId)
            ->filter(fn ($session) => $session->project_id === null || (int) $session->project_id === $projectId)
            ->values()
            ->reduce(function ($carry, $session) {
                if ((int) ($session->is_default ?? 0) === 1) {
                    $key = $session->project_id === null ? 'global_default' : 'project_default_'.$session->project_id;
                    if (isset($carry['default_keys'][$key])) {
                        return $carry;
                    }
                    $carry['default_keys'][$key] = true;
                }

                $carry['items'][] = $session;

                return $carry;
            }, ['default_keys' => [], 'items' => []])['items'];

        return collect($sessions)
            ->map(fn ($session) => $this->formatSession($session))
            ->sortByDesc(fn ($session) => strtotime((string) ($session['last_message_at'] ?? '1970-01-01 00:00:00')))
            ->values()
            ->all();
    }

    public function ensureAllProjectGroups(int $userId): void
    {
        $this->ensureGlobalGroup($userId);

        Project::query()
            ->orderByDesc('created_at')
            ->get(['id'])
            ->each(fn (Project $project) => $this->ensureProjectGroup($userId, (int) $project->id));
    }

    public function getAdminSessionsForUser(int $userId, int $currentProjectId): array
    {
        $projects = Project::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name']);

        $projectMap = $projects->keyBy('id');
        $projectIds = $projects->pluck('id')->map(fn ($id) => (int) $id)->all();

        $sessions = $this->sessionRepo
            ->findByUser($userId)
            ->filter(fn ($session) => $session->project_id === null || in_array((int) $session->project_id, $projectIds, true))
            ->values()
            ->reduce(function ($carry, $session) {
                if ((int) ($session->is_default ?? 0) === 1) {
                    $key = $session->project_id === null ? 'global_default' : 'project_default_'.$session->project_id;
                    if (isset($carry['default_keys'][$key])) {
                        return $carry;
                    }
                    $carry['default_keys'][$key] = true;
                }

                $carry['items'][] = $session;

                return $carry;
            }, ['default_keys' => [], 'items' => []])['items'];

        return collect($sessions)
            ->map(function ($session) use ($projectMap) {
                $item = $this->formatSession($session);

                if ((int) ($session->is_default ?? 0) === 1) {
                    if ($session->project_id === null) {
                        $item['title'] = '总群';
                    } else {
                        $projectName = $projectMap->get((int) $session->project_id)?->name ?? '未知项目';
                        $item['title'] = $projectName.' · 项目协作群';
                    }
                }

                return $item;
            })
            ->sortBy([
                fn (array $session) => $session['project_id'] === null && ($session['is_default'] ?? false) ? 0 : 1,
                fn (array $session) => ($session['project_id'] ?? 0) === $currentProjectId && ($session['is_default'] ?? false) ? 0 : 1,
                fn (array $session) => ! ($session['is_default'] ?? false) ? 1 : 0,
                fn (array $session) => -strtotime((string) ($session['last_message_at'] ?? '1970-01-01 00:00:00')),
            ])
            ->values()
            ->all();
    }

    public function ensureProjectGroup(int $userId, int $projectId): object
    {
        $existing = $this->sessionRepo->findDefaultSession($projectId, $userId);
        if ($existing) {
            if (($existing->title ?? '') !== '项目协作群') {
                $this->sessionRepo->editById(['title' => '项目协作群'], $existing->id);
                $existing = $this->sessionRepo->find($existing->id);
            }
            $this->syncGroupMembers($existing, $projectId);

            return $existing->fresh();
        }

        $memberIds = array_column($this->listAgents($projectId), 'id');
        $sessionId = $this->sessionRepo->createSession([
            'user_id' => $userId,
            'project_id' => $projectId,
            'title' => '项目协作群',
            'session_type' => 'group',
            'is_default' => 1,
            'member_ids' => json_encode($memberIds, JSON_UNESCAPED_UNICODE),
            'last_message_at' => now(),
            'message_count' => 0,
        ]);

        return $this->sessionRepo->find($sessionId);
    }

    public function createSession(int $userId, string $title, string $sessionType, array $memberIds, ?int $projectId = null): ?object
    {
        $projectId = $projectId ?? (int) session('current_project_id', 0);
        if ($projectId <= 0) {
            return null;
        }

        $memberIds = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($memberIds)) {
            return null;
        }

        $payload = [
            'user_id' => $userId,
            'project_id' => $projectId,
            'title' => $title !== '' ? $title : '新会话',
            'session_type' => $sessionType,
            'is_default' => 0,
            'member_ids' => json_encode($memberIds, JSON_UNESCAPED_UNICODE),
            'last_message_at' => now(),
            'message_count' => 0,
        ];

        if ($sessionType === 'private' && count($memberIds) === 1) {
            $agent = SysAiAgent::query()->find($memberIds[0]);
            if ($agent) {
                $payload['agent_type'] = $agent->type;
                $payload['agent_identifier'] = $agent->identifier;
                $payload['title'] = $title !== '' ? $title : $agent->name;
            }
        }

        $sessionId = $this->sessionRepo->createSession($payload);

        return $this->sessionRepo->find($sessionId);
    }

    public function ensurePrivateSession(int $userId, int $projectId, string $type, string $identifier): ?object
    {
        $agent = SysAiAgent::query()
            ->where('enabled', true)
            ->where('type', $type)
            ->where('identifier', $identifier)
            ->where(function ($query) use ($projectId, $type) {
                if ($type === 'secretary') {
                    $query->whereNull('project_id')->orWhere('project_id', $projectId);

                    return;
                }
                $query->where('project_id', $projectId);
            })
            ->first();

        if (! $agent) {
            return null;
        }

        $existing = $this->sessionRepo->findPrivateSession($userId, $projectId, $type, $identifier);
        if ($existing) {
            return $existing;
        }

        $sessionId = $this->sessionRepo->createSession([
            'user_id' => $userId,
            'project_id' => $projectId,
            'title' => $agent->name,
            'session_type' => 'private',
            'is_default' => 0,
            'member_ids' => json_encode([$agent->id], JSON_UNESCAPED_UNICODE),
            'agent_type' => $type,
            'agent_identifier' => $identifier,
            'last_message_at' => now(),
            'message_count' => 0,
        ]);

        return $this->sessionRepo->find($sessionId);
    }

    public function updateSession(int $sessionId, int $userId, array $data): ?object
    {
        $session = $this->sessionRepo->findByUserAndId($sessionId, $userId);
        if (! $session) {
            return null;
        }

        $updateData = [];
        if (array_key_exists('title', $data)) {
            $updateData['title'] = (string) $data['title'];
        }

        if (array_key_exists('avatar', $data) && Schema::hasColumn('sys_ai_sessions', 'avatar')) {
            $updateData['avatar'] = (string) $data['avatar'];
        }

        if ($updateData === []) {
            return $session;
        }

        $this->sessionRepo->editById($updateData, $sessionId);

        return $this->sessionRepo->find($sessionId);
    }

    public function deleteSession(int $sessionId, int $userId): bool
    {
        $session = $this->sessionRepo->findByUserAndId($sessionId, $userId);
        if (! $session || $session->is_default) {
            return false;
        }

        DB::table('sys_ai_messages')->where('session_id', $sessionId)->delete();

        return $this->sessionRepo->deleteSession($sessionId, $userId);
    }

    public function clearSessionMessages(int $sessionId, int $userId): bool
    {
        $session = $this->sessionRepo->findByUserAndId($sessionId, $userId);
        if (! $session) {
            return false;
        }

        DB::table('sys_ai_messages')->where('session_id', $sessionId)->delete();
        $this->sessionRepo->editById([
            'message_count' => 0,
            'last_message_at' => null,
        ], $sessionId);

        return true;
    }

    public function getSessionMessages(int $sessionId, int $userId, int $lastId = 0, int $limit = 20): array
    {
        return $this->getSessionMessagesWithReadOption($sessionId, $userId, $lastId, $limit, false);
    }

    public function getSessionMessagesWithReadOption(int $sessionId, int $userId, int $lastId = 0, int $limit = 20, bool $markRead = false): array
    {
        $session = $this->sessionRepo->findByUserAndId($sessionId, $userId);
        if (! $session) {
            return ['error' => '会话不存在', 'code' => 404];
        }

        $query = DB::table('sys_ai_messages')
            ->where('session_id', $sessionId)
            ->where('is_system', 0)
            ->orderByDesc('id');

        if ($lastId > 0) {
            $query->where('id', '>', $lastId);
        }

        $rows = $query->limit($limit)->get()->reverse()->values();

        if ($markRead) {
            $this->markSessionAsRead($sessionId, $userId);
            $session = $this->sessionRepo->findByUserAndId($sessionId, $userId);
        }

        return [
            'items' => $rows->map(fn ($msg) => [
                'id' => $msg->id,
                'session_id' => $msg->session_id,
                'role' => $msg->sender_type === 'user' ? 'user' : 'assistant',
                'sender_type' => $msg->sender_type,
                'sender_name' => $msg->sender_name,
                'content' => $msg->content,
                'mentions' => $msg->mentions ? json_decode($msg->mentions, true) : [],
                'status' => $msg->status,
                'created_at' => $msg->created_at,
            ])->values()->all(),
            'last_id' => $rows->max('id') ?: $lastId,
            'unread_count' => $this->countUnreadMessages((int) $session->id, (int) ($session->last_read_message_id ?? 0)),
        ];
    }

    public function sendMessage(int $sessionId, int $userId, string $content, ?array $mentions = null): array
    {
        $session = $this->sessionRepo->findByUserAndId($sessionId, $userId);
        if (! $session) {
            return ['error' => '会话不存在', 'code' => 404];
        }

        $user = DB::table('users')->where('id', $userId)->first();

        $messageId = DB::table('sys_ai_messages')->insertGetId([
            'session_id' => $sessionId,
            'sender_id' => $userId,
            'sender_type' => 'user',
            'sender_name' => $user->name ?? '用户',
            'content' => $content,
            'mentions' => json_encode($mentions ?? [], JSON_UNESCAPED_UNICODE),
            'is_system' => 0,
            'is_meaningless' => 0,
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->sessionRepo->updateMessageCount($sessionId);
        event(new MessageReceived($messageId, $sessionId, 'user', $mentions));

        return [
            'message_id' => $messageId,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function markSessionAsRead(int $sessionId, int $userId): void
    {
        $session = $this->sessionRepo->findByUserAndId($sessionId, $userId);
        if (! $session) {
            return;
        }

        $lastAgentMessageId = (int) (DB::table('sys_ai_messages')
            ->where('session_id', $sessionId)
            ->where('is_system', 0)
            ->where('sender_type', 'agent')
            ->max('id') ?? 0);

        if ($lastAgentMessageId > 0) {
            $this->sessionRepo->markRead($sessionId, $lastAgentMessageId);
        }
    }

    public function formatSession(object $session): array
    {
        $memberIds = $session->member_ids ? json_decode($session->member_ids, true) : [];
        $lastMessage = DB::table('sys_ai_messages')
            ->where('session_id', $session->id)
            ->where('is_system', 0)
            ->latest('id')
            ->first();

        return [
            'id' => $session->id,
            'project_id' => $session->project_id,
            'title' => $session->title,
            'avatar' => $session->avatar ?? null,
            'session_type' => $session->session_type,
            'member_ids' => is_array($memberIds) ? $memberIds : [],
            'is_default' => (bool) $session->is_default,
            'last_message_at' => $session->last_message_at,
            'message_count' => (int) $session->message_count,
            'last_read_message_id' => (int) ($session->last_read_message_id ?? 0),
            'unread_count' => $this->countUnreadMessages((int) $session->id, (int) ($session->last_read_message_id ?? 0)),
            'last_message_preview' => $lastMessage?->content ?? '',
            'agent_type' => $session->agent_type,
            'agent_identifier' => $session->agent_identifier,
            'agent_name' => $this->resolveAgentName($session->agent_type, $session->agent_identifier),
        ];
    }

    private function resolveAgentName(?string $type, ?string $identifier): string
    {
        if (! $type || ! $identifier) {
            return '';
        }

        return SysAiAgent::query()
            ->where('type', $type)
            ->where('identifier', $identifier)
            ->value('name') ?? '';
    }

    private function ensureProjectAgent(int $projectId): void
    {
        $project = Project::query()->find($projectId);
        if (! $project) {
            return;
        }

        $existing = SysAiAgent::query()
            ->where('type', 'project')
            ->where('project_id', $projectId)
            ->first();

        if ($existing) {
            return;
        }

        SysAiAgent::create([
            'type' => 'project',
            'identifier' => $project->prefix,
            'user_id' => $project->user_id,
            'project_id' => $project->id,
            'name' => $project->name.'助手',
            'description' => '项目 '.$project->name.' 的 AI 助手',
            'avatar' => '',
            'personality' => [
                'tone' => 'professional',
                'traits' => ['专业', '高效', '严谨'],
                'greeting' => '你好！我是'.$project->name.'助手，有什么可以帮你的？',
            ],
            'dialogue_style' => [
                'length' => 'medium',
                'format' => 'markdown',
                'emoji_usage' => 'normal',
            ],
            'core_prompt' => '你是'.$project->name.'项目的专业助手，帮助用户处理与该项目相关的问题。',
            'enabled' => true,
        ]);
    }

    private function syncGroupMembers(object $session, int $projectId): void
    {
        $memberIds = array_column($this->listAgents($projectId), 'id');
        $current = $session->member_ids ? json_decode($session->member_ids, true) : [];
        sort($memberIds);
        sort($current);
        if ($current === $memberIds) {
            return;
        }

        $this->sessionRepo->editById([
            'member_ids' => json_encode($memberIds, JSON_UNESCAPED_UNICODE),
        ], $session->id);
    }

    private function syncGlobalGroupMembers(object $session): void
    {
        $memberIds = $this->listGlobalAgentIds();
        $current = $session->member_ids ? json_decode($session->member_ids, true) : [];
        sort($memberIds);
        sort($current);
        if ($current === $memberIds) {
            return;
        }

        $this->sessionRepo->editById([
            'member_ids' => json_encode($memberIds, JSON_UNESCAPED_UNICODE),
        ], $session->id);
    }

    private function listGlobalAgentIds(): array
    {
        return SysAiAgent::query()
            ->where('enabled', true)
            ->where('type', '!=', 'secretary')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function countUnreadMessages(int $sessionId, int $lastReadMessageId): int
    {
        return DB::table('sys_ai_messages')
            ->where('session_id', $sessionId)
            ->where('is_system', 0)
            ->where('sender_type', 'agent')
            ->where('id', '>', $lastReadMessageId)
            ->count();
    }
}
