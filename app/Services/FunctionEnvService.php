<?php

namespace App\Services;

use App\Exceptions\PageNoticeException;
use App\Repository\FunctionEnvRepository;

class FunctionEnvService
{
    public function __construct(private FunctionEnvRepository $repo) {}

    public function paginate(int $page = 1, int $pageSize = 15, string $keyword = ''): array
    {
        $ret = $this->repo->paginate($page, $pageSize, $keyword);

        return [
            'data' => $ret['data'],
            'meta' => [
                'total' => $ret['total'],
                'page' => $page,
                'page_size' => $pageSize,
                'page_count' => (int) ceil(($ret['total'] ?? 0) / max(1, $pageSize)),
            ],
        ];
    }

    public function create(array $data)
    {
        if ($this->repo->existsByName($data['name'] ?? '')) {
            throw new PageNoticeException('变量名已存在，请更换名称');
        }

        return $this->repo->create($data);
    }

    public function update(int $id, array $data)
    {
        if ($this->repo->existsByName($data['name'] ?? '', $id)) {
            throw new PageNoticeException('变量名已存在，请更换名称');
        }

        return $this->repo->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
