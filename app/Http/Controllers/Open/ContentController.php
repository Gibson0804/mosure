<?php

namespace App\Http\Controllers\Open;

use App\Models\Mold;
use App\Services\ContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContentController extends BaseOpenController
{
    private ContentService $contentService;

    public function __construct(ContentService $contentService)
    {
        $this->contentService = $contentService;
    }

    /**
     * 获取内容列表
     * GET /open/content/list/{tableName}
     */
    public function getList(Request $request, string $tableName): JsonResponse
    {
        try {
            // 规范化并验证表名
            $tableName = $this->normalizeAndValidateTableName($tableName);
            if (! $tableName) {
                return $this->error('无效的表名', 400);
            }

            // 构建查询参数
            $params = $request->all();
            $fields = $request->input('fields', ['*']);
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 15);

            if (! $this->applyProjectUserContentFilter($request, $tableName, $params)) {
                return $this->success([
                    'items' => [],
                    'total' => 0,
                    'page' => (int) $page,
                    'page_size' => (int) $pageSize,
                    'page_count' => 0,
                    'fields' => is_array($fields) ? $fields : array_filter(array_map('trim', explode(',', (string) $fields))),
                ]);
            }

            // 获取数据（标准结构：items/total/page/page_size/page_count/fields）
            $list = $this->contentService->getListApi($tableName, $params, $fields, $page, $pageSize);

            return $this->success($list);

        } catch (\Exception $e) {
            return $this->error('获取列表失败: '.$e->getMessage(), 500);
        }
    }

    /**
     * 获取内容详情
     * GET /open/content/detail/{tableName}/{id}
     */
    public function getDetail(Request $request, string $tableName, int $id): JsonResponse
    {
        try {
            // 规范化并验证表名
            $tableName = $this->normalizeAndValidateTableName($tableName);
            if (! $tableName) {
                return $this->error('无效的表名', 400);
            }

            if ($ownershipError = $this->assertProjectUserOwnsContent($request, $tableName, $id)) {
                return $ownershipError;
            }

            // 获取详情
            $detail = $this->contentService->getDetailApi($tableName, $id);

            if (! $detail) {
                return $this->error('内容不存在', 404);
            }

            return $this->success($detail);

        } catch (\Exception $e) {
            return $this->error('获取详情失败: '.$e->getMessage(), 500);
        }
    }

    /**
     * 获取内容数量
     * GET /open/content/count/{tableName}
     */
    public function getCount(Request $request, string $tableName): JsonResponse
    {
        try {
            // 规范化并验证表名
            $tableName = $this->normalizeAndValidateTableName($tableName);
            if (! $tableName) {
                return $this->error('无效的表名', 400);
            }

            // 获取数量
            $params = $request->all();
            if (! $this->applyProjectUserContentFilter($request, $tableName, $params)) {
                return $this->success(['count' => 0]);
            }

            $count = $this->contentService->getCountApi($tableName);
            if ($this->projectUserId($request) !== null) {
                $count = DB::table($tableName)->where('created_by', $this->projectUserId($request))->count();
            }

            return $this->success(['count' => $count]);

        } catch (\Exception $e) {
            return $this->error('获取数量失败: '.$e->getMessage(), 500);
        }
    }

    /**
     * 创建内容
     * POST /open/content/create/{tableName}
     */
    public function create(Request $request, string $tableName): JsonResponse
    {
        try {
            // 规范化并验证表名
            $tableName = $this->normalizeAndValidateTableName($tableName);
            if (! $tableName) {
                return $this->error('无效的表名', 400);
            }

            // 获取模型定义以确定模型ID
            $mold = Mold::where('table_name', $tableName)->first();
            if (! $mold) {
                return $this->error('模型不存在', 404);
            }
            $moldId = $mold->id;

            // 获取数据
            $data = $request->all();

            // 调用 ContentService 创建内容（会触发触发器）
            $this->contentService->addContent($data, $moldId);

            return $this->success(['message' => '创建成功']);

        } catch (\Exception $e) {
            return $this->error('创建失败: '.$e->getMessage(), 500);
        }
    }

    /**
     * 更新内容
     * PUT /open/content/update/{tableName}/{id}
     */
    public function update(Request $request, string $tableName, int $id): JsonResponse
    {
        try {
            // 规范化并验证表名
            $tableName = $this->normalizeAndValidateTableName($tableName);
            if (! $tableName) {
                return $this->error('无效的表名', 400);
            }

            // 获取模型定义以确定模型ID
            $mold = Mold::where('table_name', $tableName)->first();
            if (! $mold) {
                return $this->error('模型不存在', 404);
            }
            $moldId = $mold->id;

            if ($ownershipError = $this->assertProjectUserOwnsContent($request, $tableName, $id)) {
                return $ownershipError;
            }

            // 获取数据
            $data = $request->all();

            if (Schema::hasColumn($tableName, 'updated_at') && (! array_key_exists('updated_at', $data) || empty($data['updated_at']))) {
                $data['updated_at'] = now();
            }
            if (Schema::hasColumn($tableName, 'updated_by') && (! array_key_exists('updated_by', $data) || empty($data['updated_by']))) {
                $data['updated_by'] = $request->attributes->get('project_user_id') ?? $request->input('project_user_id') ?? 'api';
            }
            // 调用 ContentService 更新内容（会触发触发器）
            $this->contentService->editContent($data, $moldId, $id);

            return $this->success(['message' => '更新成功']);

        } catch (\Exception $e) {
            return $this->error('更新失败: '.$e->getMessage(), 500);
        }
    }

    /**
     * 删除内容
     * DELETE /open/content/delete/{tableName}/{id}
     */
    public function delete(Request $request, string $tableName, int $id): JsonResponse
    {
        try {
            // 规范化并验证表名
            $tableName = $this->normalizeAndValidateTableName($tableName);
            if (! $tableName) {
                return $this->error('无效的表名', 400);
            }

            // 获取模型定义以确定模型ID
            $mold = Mold::where('table_name', $tableName)->first();
            if (! $mold) {
                return $this->error('模型不存在', 404);
            }
            $moldId = $mold->id;

            if ($ownershipError = $this->assertProjectUserOwnsContent($request, $tableName, $id)) {
                return $ownershipError;
            }

            // 调用 ContentService 删除内容（会触发触发器）
            $this->contentService->contentDelete($moldId, $id);

            return $this->success(['message' => '删除成功']);

        } catch (\Exception $e) {
            return $this->error('删除失败: '.$e->getMessage(), 500);
        }
    }

    private function projectUserId(Request $request): ?string
    {
        if ($request->attributes->get('auth_subject_type') !== 'project_user') {
            return null;
        }

        $id = $request->attributes->get('project_user_id');

        return $id === null || $id === '' ? null : (string) $id;
    }

    private function applyProjectUserContentFilter(Request $request, string $tableName, array &$params): bool
    {
        $projectUserId = $this->projectUserId($request);
        if ($projectUserId === null) {
            return true;
        }

        if (! Schema::hasColumn($tableName, 'created_by')) {
            return false;
        }

        $params['filter'] = is_array($params['filter'] ?? null) ? $params['filter'] : [];
        $params['filter']['created_by'] = $projectUserId;

        return true;
    }

    private function assertProjectUserOwnsContent(Request $request, string $tableName, int $id): ?JsonResponse
    {
        $projectUserId = $this->projectUserId($request);
        if ($projectUserId === null) {
            return null;
        }

        if (! Schema::hasColumn($tableName, 'created_by')) {
            return $this->error('没有权限访问该内容', 403);
        }

        $row = DB::table($tableName)->where('id', $id)->first(['id', 'created_by']);
        if (! $row) {
            return $this->error('内容不存在', 404);
        }

        if ((string) $row->created_by !== $projectUserId) {
            return $this->error('没有权限访问该内容', 403);
        }

        return null;
    }
}
