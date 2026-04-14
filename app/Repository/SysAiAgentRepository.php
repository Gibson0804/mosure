<?php

namespace App\Repository;

use App\Models\SysAiAgent;

class SysAiAgentRepository extends BaseRepository
{
    public function __construct()
    {
        $this->mainModel = new SysAiAgent;
    }

    public function findEnabled(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->newQuery()
            ->where('enabled', true)
            ->get();
    }

    public function findByIds(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        return $this->newQuery()
            ->whereIn('id', $ids)
            ->get();
    }

    public function findByUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->newQuery()
            ->where('user_id', $userId)
            ->where('enabled', true)
            ->get();
    }

    public function findByTypeAndIdentifier(string $type, string $identifier)
    {
        return $this->newQuery()
            ->where('type', $type)
            ->where('identifier', $identifier)
            ->first();
    }

    public function findEnabledByIds(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        return $this->newQuery()
            ->whereIn('id', $ids)
            ->where('enabled', true)
            ->get();
    }

    public function createAgent(array $data): SysAiAgent
    {
        return $this->mainModel->create($data);
    }

    public function updateAgent(int $id, array $data): bool
    {
        return $this->mainModel->where('id', $id)->update($data) > 0;
    }

    public function deleteAgent(int $id): bool
    {
        return $this->mainModel->where('id', $id)->delete();
    }
}
