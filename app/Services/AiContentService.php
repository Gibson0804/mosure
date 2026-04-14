<?php

namespace App\Services;

use App\Ai\Attributes\AiTool;
use App\Repository\ContentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AiContentService
{
    #[AiTool(
        name: 'content_list',
        description: '查询内容列表：支持按标题模糊搜索、状态筛选、排序。简化参数设计，适合 AI 使用。',
        params: [
            'moldId' => ['type' => 'integer', 'required' => true, 'desc' => '模型ID'],
            'search' => ['type' => 'string', 'required' => false, 'desc' => '标题模糊搜索关键词'],
            'status' => ['type' => 'string', 'required' => false, 'desc' => '内容状态：pending/published/disabled，不传则查所有'],
            'page' => ['type' => 'integer', 'required' => false, 'desc' => '页码，默认1'],
            'pageSize' => ['type' => 'integer', 'required' => false, 'desc' => '每页条数，默认10'],
            'orderBy' => ['type' => 'string', 'required' => false, 'desc' => '排序字段，默认id_desc'],
        ]
    )]
    public function listContent(
        int $moldId,
        ?string $search = null,
        ?string $status = null,
        int $page = 1,
        int $pageSize = 10,
        string $orderBy = 'id_desc'
    ): array {
        $tableName = $this->getTableName($moldId);

        $query = DB::table($tableName);

        if ($search) {
            $hasTitle = Schema::hasColumn($tableName, 'title');
            if ($hasTitle) {
                $query->where('title', 'like', '%'.$search.'%');
            }
        }

        if ($status && in_array($status, ['pending', 'published', 'disabled'])) {
            $query->where('content_status', $status);
        }

        $orderParts = explode('_', $orderBy);
        $orderField = $orderParts[0] ?? 'id';
        $orderDir = $orderParts[1] ?? 'desc';
        if (Schema::hasColumn($tableName, $orderField)) {
            $query->orderBy($orderField, $orderDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $total = $query->count();

        $items = $query
            ->limit($pageSize)
            ->offset(($page - 1) * $pageSize)
            ->get(['id', 'title', 'content_status', 'created_at', 'updated_at'])
            ->toArray();

        return [
            'success' => true,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'items' => $items,
            'moldId' => $moldId,
        ];
    }

    #[AiTool(
        name: 'content_detail',
        description: '查询内容详情：支持按ID或标题查询，获取完整内容信息。',
        params: [
            'moldId' => ['type' => 'integer', 'required' => true, 'desc' => '模型ID'],
            'id' => ['type' => 'integer', 'required' => false, 'desc' => '内容ID，与title二选一'],
            'title' => ['type' => 'string', 'required' => false, 'desc' => '内容标题，与id二选一，模糊匹配'],
        ]
    )]
    public function getContent(
        int $moldId,
        ?int $id = null,
        ?string $title = null
    ): array {
        $tableName = $this->getTableName($moldId);

        if ($id) {
            $item = DB::table($tableName)->where('id', $id)->first();
            if (! $item) {
                return ['success' => false, 'error' => '未找到内容，ID: '.$id];
            }

            return ['success' => true, 'item' => $item];
        }

        if ($title) {
            $hasTitle = Schema::hasColumn($tableName, 'title');
            if (! $hasTitle) {
                return ['success' => false, 'error' => '该模型没有 title 字段，无法按标题查询'];
            }
            $item = DB::table($tableName)
                ->where('title', 'like', '%'.$title.'%')
                ->first();
            if (! $item) {
                return ['success' => false, 'error' => '未找到标题包含"'.$title.'"的内容'];
            }

            return ['success' => true, 'item' => $item];
        }

        return ['success' => false, 'error' => '请提供 id 或 title'];
    }

    #[AiTool(
        name: 'content_create',
        description: '创建内容：根据模型ID和内容信息创建新内容，返回创建结果。',
        params: [
            'moldId' => ['type' => 'integer', 'required' => true, 'desc' => '模型ID'],
            'contentInfo' => ['type' => 'object', 'required' => true, 'desc' => '内容字段键值对，如 {title: "标题", content: "正文"}'],
        ]
    )]
    public function createContent(int $moldId, array $contentInfo): array
    {
        if (isset($contentInfo['tags']) && is_array($contentInfo['tags'])) {
            $contentInfo['tags'] = implode(',', $contentInfo['tags']);
        }

        $tableName = $this->getTableName($moldId);

        $contentInfo['created_at'] = now()->toDateTimeString();
        $contentInfo['updated_at'] = now()->toDateTimeString();
        $contentInfo['content_status'] = ContentRepository::STATUS_PENDING;

        try {
            $id = DB::table($tableName)->insertGetId($contentInfo);

            $created = DB::table($tableName)->where('id', $id)->first();

            return [
                'success' => true,
                'id' => $id,
                'title' => $contentInfo['title'] ?? '',
                'status' => ContentRepository::STATUS_PENDING,
                'item' => $created,
                'message' => '内容创建成功，ID: '.$id,
            ];
        } catch (\Throwable $e) {
            Log::error('AiContentService: create failed', [
                'moldId' => $moldId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => '创建失败: '.$e->getMessage(),
            ];
        }
    }

    #[AiTool(
        name: 'content_update',
        description: '更新内容：根据模型ID和内容ID更新内容字段。',
        params: [
            'moldId' => ['type' => 'integer', 'required' => true, 'desc' => '模型ID'],
            'id' => ['type' => 'integer', 'required' => true, 'desc' => '内容ID'],
            'contentInfo' => ['type' => 'object', 'required' => true, 'desc' => '要更新的字段键值对'],
        ]
    )]
    public function updateContent(int $moldId, int $id, array $contentInfo): array
    {
        $tableName = $this->getTableName($moldId);

        $contentInfo['updated_at'] = now()->toDateTimeString();

        try {
            $updated = DB::table($tableName)->where('id', $id)->update($contentInfo);

            if (! $updated) {
                return [
                    'success' => false,
                    'error' => '未找到内容或无需更新，ID: '.$id,
                ];
            }

            $item = DB::table($tableName)->where('id', $id)->first();

            return [
                'success' => true,
                'id' => $id,
                'title' => $item->title ?? '',
                'updated_fields' => array_keys($contentInfo),
                'item' => $item,
                'message' => '内容更新成功',
            ];
        } catch (\Throwable $e) {
            Log::error('AiContentService: update failed', [
                'moldId' => $moldId,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => '更新失败: '.$e->getMessage(),
            ];
        }
    }

    #[AiTool(
        name: 'content_publish',
        description: '上架内容：将内容状态改为已发布。',
        params: [
            'moldId' => ['type' => 'integer', 'required' => true, 'desc' => '模型ID'],
            'id' => ['type' => 'integer', 'required' => true, 'desc' => '内容ID'],
        ]
    )]
    public function publishContent(int $moldId, int $id): array
    {
        $tableName = $this->getTableName($moldId);

        $item = DB::table($tableName)->where('id', $id)->first();

        if (! $item) {
            return ['success' => false, 'error' => '未找到内容，ID: '.$id];
        }

        if (($item->content_status ?? '') === ContentRepository::STATUS_PUBLISHED) {
            return [
                'success' => true,
                'id' => $id,
                'title' => $item->title ?? '',
                'status' => ContentRepository::STATUS_PUBLISHED,
                'message' => '内容已是发布状态，无需重复发布',
            ];
        }

        try {
            DB::table($tableName)->where('id', $id)->update([
                'content_status' => ContentRepository::STATUS_PUBLISHED,
                'updated_at' => now()->toDateTimeString(),
            ]);

            return [
                'success' => true,
                'id' => $id,
                'title' => $item->title ?? '',
                'status' => ContentRepository::STATUS_PUBLISHED,
                'message' => '内容发布成功: '.($item->title ?? 'ID:'.$id),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => '发布失败: '.$e->getMessage(),
            ];
        }
    }

    #[AiTool(
        name: 'content_unpublish',
        description: '下架内容：将内容状态改为已下线。',
        params: [
            'moldId' => ['type' => 'integer', 'required' => true, 'desc' => '模型ID'],
            'id' => ['type' => 'integer', 'required' => true, 'desc' => '内容ID'],
        ]
    )]
    public function unpublishContent(int $moldId, int $id): array
    {
        $tableName = $this->getTableName($moldId);

        $item = DB::table($tableName)->where('id', $id)->first();

        if (! $item) {
            return ['success' => false, 'error' => '未找到内容，ID: '.$id];
        }

        if (($item->content_status ?? '') === ContentRepository::STATUS_DISABLED) {
            return [
                'success' => true,
                'id' => $id,
                'title' => $item->title ?? '',
                'status' => ContentRepository::STATUS_DISABLED,
                'message' => '内容已是下线状态',
            ];
        }

        try {
            DB::table($tableName)->where('id', $id)->update([
                'content_status' => ContentRepository::STATUS_DISABLED,
                'updated_at' => now()->toDateTimeString(),
            ]);

            return [
                'success' => true,
                'id' => $id,
                'title' => $item->title ?? '',
                'status' => ContentRepository::STATUS_DISABLED,
                'message' => '内容下架成功: '.($item->title ?? 'ID:'.$id),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => '下架失败: '.$e->getMessage(),
            ];
        }
    }

    #[AiTool(
        name: 'content_delete',
        description: '删除内容：根据ID删除内容。',
        params: [
            'moldId' => ['type' => 'integer', 'required' => true, 'desc' => '模型ID'],
            'id' => ['type' => 'integer', 'required' => true, 'desc' => '内容ID'],
        ]
    )]
    public function deleteContent(int $moldId, int $id): array
    {
        $tableName = $this->getTableName($moldId);

        $item = DB::table($tableName)->where('id', $id)->first();

        if (! $item) {
            return ['success' => false, 'error' => '未找到内容，ID: '.$id];
        }

        try {
            DB::table($tableName)->where('id', $id)->delete();

            return [
                'success' => true,
                'id' => $id,
                'title' => $item->title ?? '',
                'message' => '内容删除成功: '.($item->title ?? 'ID:'.$id),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => '删除失败: '.$e->getMessage(),
            ];
        }
    }

    #[AiTool(
        name: 'content_count',
        description: '统计内容数量：按模型ID统计内容数量。',
        params: [
            'moldId' => ['type' => 'integer', 'required' => true, 'desc' => '模型ID'],
            'status' => ['type' => 'string', 'required' => false, 'desc' => '内容状态筛选：pending/published/disabled'],
        ]
    )]
    public function countContent(int $moldId, ?string $status = null): array
    {
        $tableName = $this->getTableName($moldId);

        $query = DB::table($tableName);

        if ($status && in_array($status, ['pending', 'published', 'disabled'])) {
            $query->where('content_status', $status);
        }

        $count = $query->count();

        return [
            'success' => true,
            'moldId' => $moldId,
            'count' => $count,
            'status' => $status ?? 'all',
        ];
    }

    private function getTableName(int $moldId): string
    {
        $mold = \App\Models\Mold::find($moldId);
        if (! $mold) {
            throw new \InvalidArgumentException('模型不存在，ID: '.$moldId);
        }

        return $mold->table_name;
    }
}
