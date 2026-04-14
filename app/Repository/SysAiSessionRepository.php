<?php

namespace App\Repository;

use App\Models\SysAiSession;
use Illuminate\Support\Facades\DB;

class SysAiSessionRepository extends BaseRepository
{
    public function __construct()
    {
        $this->mainModel = new SysAiSession;
    }

    public function findByUserAndId(int $sessionId, int $userId)
    {
        return $this->newQuery()
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->first();
    }

    public function findDefaultSession(?int $projectId = null, ?int $userId = null)
    {
        $query = $this->newQuery()->where('is_default', 1);
        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    public function findGlobalDefaultSession(?int $userId = null)
    {
        $query = $this->newQuery()
            ->where('is_default', 1)
            ->whereNull('project_id');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    public function findByUser(int $userId, ?int $projectId = null)
    {
        $query = $this->newQuery()->where('user_id', $userId);
        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        return $query->orderByDesc('last_message_at')->get();
    }

    public function findPrivateSession(int $userId, int $projectId, string $agentType, string $agentIdentifier)
    {
        return $this->newQuery()
            ->where('user_id', $userId)
            ->where('project_id', $projectId)
            ->where('session_type', 'private')
            ->where('agent_type', $agentType)
            ->where('agent_identifier', $agentIdentifier)
            ->first();
    }

    public function createSession(array $data): int
    {
        return $this->mainModel->insertGetId($data);
    }

    public function updateMessageCount(int $sessionId): void
    {
        $this->newQuery()->where('id', $sessionId)->update([
            'last_message_at' => now(),
            'message_count' => DB::raw('message_count + 1'),
        ]);
    }

    public function updateContext(int $sessionId, string $summary, int $tokenCount): void
    {
        $this->newQuery()->where('id', $sessionId)->update([
            'context_summary' => $summary,
            'context_token_count' => $tokenCount,
        ]);
    }

    public function markRead(int $sessionId, int $messageId): void
    {
        $this->newQuery()
            ->where('id', $sessionId)
            ->update([
                'last_read_message_id' => DB::raw('GREATEST(COALESCE(last_read_message_id, 0), '.(int) $messageId.')'),
            ]);
    }

    public function deleteSession(int $sessionId, int $userId): bool
    {
        return $this->newQuery()
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->where('is_default', 0)
            ->delete() > 0;
    }
}
