<?php

namespace App\Repository;

use App\Models\FunctionEnv;

class FunctionEnvRepository
{
    public function paginate(int $page = 1, int $pageSize = 15, string $keyword = ''): array
    {
        $q = FunctionEnv::query();
        if ($keyword !== '') {
            $q->where(function ($qq) use ($keyword) {
                $kw = "%{$keyword}%";
                $qq->where('name', 'like', $kw)
                    ->orWhere('remark', 'like', $kw);
            });
        }
        $total = $q->count();
        $data = $q->orderBy('id', 'desc')
            ->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get();

        return [
            'data' => $data,
            'total' => $total,
        ];
    }

    public function getById(int $id): FunctionEnv
    {
        return FunctionEnv::findOrFail($id);
    }

    public function create(array $data): FunctionEnv
    {
        return FunctionEnv::create($data);
    }

    public function update(int $id, array $data): FunctionEnv
    {
        $row = $this->getById($id);
        $row->update($data);

        return $row->fresh();
    }

    public function delete(int $id): bool
    {
        $row = $this->getById($id);

        return (bool) $row->delete();
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $q = FunctionEnv::query()->where('name', $name);
        if ($excludeId) {
            $q->where('id', '!=', $excludeId);
        }

        return $q->exists();
    }
}
