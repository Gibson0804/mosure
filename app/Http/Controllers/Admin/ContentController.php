<?php

namespace App\Http\Controllers\Admin;

use App\Services\ContentService;
use App\Services\MoldService;
use App\Services\ProjectConfigService;
use App\Services\SystemConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;

class ContentController extends BaseAdminController
{
    private $contentService;

    private $moldService;

    private ProjectConfigService $projectConfigService;

    private SystemConfigService $systemConfigService;

    public function __construct(
        ContentService $contentService, MoldService $moldService, ProjectConfigService $projectConfigService, SystemConfigService $systemConfigService
    ) {
        $this->contentService = $contentService;
        $this->moldService = $moldService;
        $this->projectConfigService = $projectConfigService;
        $this->systemConfigService = $systemConfigService;
    }

    public function count(Request $request, int $moldId)
    {
        $res = $this->contentService->getContentCount($moldId);

        return success($res);
    }

    public function contentAdd(Request $request, int $moldId)
    {

        if ($request->isMethod('post')) {

            $contentInfo = $request->input();

            // 防重复提交：使用请求唯一标识或基于用户+内容的哈希
            $requestId = $request->header('X-Request-ID');
            if (! $requestId) {
                // 如果没有请求ID，使用用户ID+时间戳+内容哈希作为唯一标识
                $userId = Auth::id() ?? 'guest';
                $contentHash = md5(json_encode($contentInfo));
                $requestId = "auto_{$userId}_{$contentHash}_".floor(time() / 2); // 2秒内相同内容视为重复
            }

            $cacheKey = 'content_add:'.$moldId.':'.$requestId;
            if (Cache::has($cacheKey)) {
                return back()->withErrors(['message' => '请勿重复提交']);
            }
            Cache::put($cacheKey, true, 10); // 10秒内不允许重复提交

            try {
                $this->contentService->addContent($contentInfo, $moldId);
            } catch (\Exception $e) {
                // 删除缓存，允许重试
                Cache::forget($cacheKey);

                return back()->withErrors(['message' => $e->getMessage()]);
            }

            return redirect()->route('content.list', ['moldId' => $moldId]);
        }

        $data = $this->moldService->getMoldInfo($moldId);

        return viewShow('Content/ContentAdd', [
            'moldId' => $moldId,
            'schema' => json_decode($data['fields'], true),
            'pageId' => $data['id'],
            'pageName' => $data['name'],
            'tableName' => $data['table_name'],
        ]);
    }

    public function contentEdit(Request $request, int $moldId, int $id)
    {

        if ($request->isMethod('post')) {
            $data = $request->input();
            $this->contentService->editContent($data, $moldId, $id);
        }

        $info = $this->moldService->getMoldInfo($moldId);
        $subjectContent = $info['subject_content_arr'];
        $fields = $this->contentService->getContentDetailForEdit($moldId, $id);

        return viewShow('Content/ContentEdit', [
            'moldId' => $moldId,
            'schema' => $fields,
            'pageId' => $id,
            'pageName' => $info['name'],
            'tableName' => $info['table_name'],
            'subjectContent' => $subjectContent,
        ]);
    }

    public function contentList(Request $request, int $moldId): Response
    {

        $filters = $request->input('filters', []);
        $page = $request->input('page', 1);
        $pageSize = $request->input('page_size', 15);
        $sort = $request->input('sort');

        $params = [
            'filter' => is_array($filters) ? $filters : [],
            'page' => $page,
            'page_size' => $pageSize,
        ];

        // 只有在请求中提供了排序参数时才使用，否则由 ContentService 使用默认排序
        if ($sort !== null) {
            $params['sort'] = $sort;
        }

        $result = $this->contentService->getContentList($moldId, $params);

        $moldInfo = $this->moldService->getMoldInfo($moldId);

        return viewShow('Content/ContentList', [
            'allListTitle' => $result['allListTitle'],
            'columns' => $result['fieldListTitle'],
            'dataSource' => $result['list'],
            'pagination' => $result['pagination'],
            'moldId' => $moldId,
            'listShowFields' => $moldInfo['list_show_fields'],
            'filterShowFields' => $moldInfo['filter_show_fields'] ?? null,
            'schema' => $moldInfo['fields_arr'] ?? [],
            'filters' => $params['filter'],
        ]);
    }

    public function contentDetail(Request $request, int $moldId, $id)
    {

        $detail = $this->contentService->getContentDetail($moldId, $id);

        return success($detail);
    }

    public function delete(Request $request, int $moldId, $id)
    {
        try {
            $res = $this->contentService->contentDelete($moldId, $id);
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * 上架（publish）：pending -> published
     */
    public function publish(Request $request, int $moldId, int $id)
    {
        try {
            $ok = $this->contentService->publish($moldId, $id);
            if (! $ok) {
                return error([], '上架失败');
            }

            return success([], '上架成功');
        } catch (\Throwable $e) {
            return error([], '上架失败: '.$e->getMessage());
        }
    }

    /**
     * 下架（unpublish）：pending/published -> disabled
     */
    public function unpublish(Request $request, int $moldId, int $id)
    {
        try {
            $ok = $this->contentService->unpublish($moldId, $id);
            if (! $ok) {
                return error([], '下架失败');
            }

            return success([], '下架成功');
        } catch (\Throwable $e) {
            return error([], '下架失败: '.$e->getMessage());
        }
    }

    /**
     * 批量删除
     * 请求体：{ ids: number[] }
     */
    public function deleteBatch(Request $request, int $moldId)
    {
        $ids = $request->input('ids');
        if (! is_array($ids) || empty($ids)) {
            return error([], '参数错误：缺少 ids 数组');
        }

        $failedIds = $this->contentService->deleteByIds($moldId, $ids);

        return success([
            'success' => count($ids) - count($failedIds),
            'failed' => count($failedIds),
            'failed_ids' => $failedIds,
        ], '批量删除完成');
    }

    /**
     * 批量上架
     * 请求体：{ ids: number[] }
     */
    public function publishBatch(Request $request, int $moldId)
    {
        $ids = $request->input('ids');
        if (! is_array($ids) || empty($ids)) {
            return error([], '参数错误：缺少 ids 数组');
        }

        $failedIds = $this->contentService->publishByIds($moldId, $ids);

        return success([
            'success' => count($ids) - count($failedIds),
            'failed' => count($failedIds),
            'failed_ids' => $failedIds,
        ], '批量上架完成');
    }

    /**
     * 批量下架
     * 请求体：{ ids: number[] }
     */
    public function unpublishBatch(Request $request, int $moldId)
    {
        $ids = $request->input('ids');
        if (! is_array($ids) || empty($ids)) {
            return error([], '参数错误：缺少 ids 数组');
        }

        $failedIds = $this->contentService->unpublishByIds($moldId, $ids);

        return success([
            'success' => count($ids) - count($failedIds),
            'failed' => count($failedIds),
            'failed_ids' => $failedIds,
        ], '批量下架完成');
    }

    /**
     * 根据模型ID和字段名，返回用于下拉框的内容选项
     * 请求参数：field (string)
     * 返回：[{ value: id, label: 字段值 }]
     */
    public function fieldOptions(Request $request, int $moldId)
    {
        $field = $request->input('field');
        if (! $field) {
            return success([]);
        }

        $moldInfo = $this->moldService->getMoldInfo($moldId);
        $tableName = $moldInfo['table_name'] ?? null;
        if (! $tableName) {
            return success([]);
        }

        $list = $this->contentService->getListApi($tableName, [], ['id', $field], 1, 500);

        if (empty($list['items'] ?? [])) {
            return success([]);
        }

        $items = $list['items'] ?? [];

        $options = [];
        $seen = [];
        foreach ($items as $row) {
            $id = $row->id ?? null;
            $label = $row->$field ?? '';
            if (! $id || $label === '' || $label === null) {
                continue;
            }
            if (isset($seen[(string) $label])) {
                continue;
            }
            $seen[(string) $label] = true;
            $options[] = [
                'value' => (string) $id,
                'label' => (string) $label,
            ];
        }

        return success($options);
    }

    /**
     * 模拟：根据提示词生成内容并返回各字段建议值
     * 请求体：{ prompt?: string, only_empty?: bool, current_values?: object }
     */
    public function aiGenerate(Request $request, int $moldId)
    {
        $prompt = (string) ($request->input('prompt') ?? '');
        $onlyEmpty = (bool) $request->input('only_empty', false);
        $currentValues = (array) ($request->input('current_values') ?? []);
        $requestedBy = optional($request->user())->id;

        $task = $this->contentService->aiGenerate($moldId, $prompt, $onlyEmpty, $requestedBy, $currentValues);

        return success([
            'task_id' => $task->id,
            'status' => $task->status,
        ]);
    }

    /**
     * 模拟：根据提示词批量生成内容，返回多条假数据
     * 请求体：{ prompt?: string, count?: int }
     * 响应：[{ field => value, ... }]
     */
    public function aiGenerateBatch(Request $request, int $moldId)
    {
        $prompt = (string) ($request->input('prompt') ?? '');
        $onlyEmpty = (bool) $request->input('only_empty', false);
        $requestedBy = optional($request->user())->id;
        $count = (int) ($request->input('count') ?? 1);
        $count = max(1, min(20, $count));

        $tasks = [];
        for ($i = 0; $i < $count; $i++) {
            $suffix = $count > 1 ? (' #'.($i + 1)) : '';
            $task = $this->contentService->aiGenerate(
                $moldId,
                trim($prompt.$suffix),
                $onlyEmpty,
                $requestedBy
            );

            $tasks[] = [
                'task_id' => $task->id,
                'status' => $task->status,
            ];
        }

        return success($tasks);
    }

    /**
     * 异步批量：创建父任务，前端轮询父任务状态
     */
    public function aiGenerateBatchStart(Request $request, int $moldId)
    {
        $prompt = (string) ($request->input('prompt') ?? '');
        $count = (int) ($request->input('count') ?? 1);
        $requestedBy = optional($request->user())->id;

        $task = $this->contentService->createContentBatchTask($moldId, $prompt, $count, $requestedBy);

        return success([
            'task_id' => $task->id,
            'status' => $task->status,
        ]);
    }
}
