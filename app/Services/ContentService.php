<?php

namespace App\Services;

use App\Ai\Attributes\AiTool;
use App\Repository\ContentRepository;
use App\Repository\MoldRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ContentService extends BaseService
{
    private $moldRepository;

    private $taskService;

    private ContentVersionService $versionService;

    private RelationDisplayService $relationDisplayService;

    public function __construct(
        MoldRepository $moldRepository,
        TaskService $taskService,
        ContentVersionService $versionService,
        RelationDisplayService $relationDisplayService,
    ) {
        $this->moldRepository = $moldRepository;
        $this->taskService = $taskService;
        $this->versionService = $versionService;
        $this->relationDisplayService = $relationDisplayService;

    }

    public function getAllContentMenu()
    {

        $moldList = $this->moldRepository->getAllContentBase();

        return $moldList;
    }

    public function getContentCount($moldId)
    {

        $count = ContentRepository::buildContent($moldId)->count();

        return $count;
    }

    public function getContentDetail($moldId, $id)
    {

        $detailInfo = ContentRepository::buildContent($moldId)->find($id);

        $moldInfo = $this->moldRepository->getMoldInfo($moldId);

        $fieldArr = $moldInfo['fields_arr'];
        $res = [];
        foreach ($fieldArr as $one) {
            if ($one['type'] == 'dividingLine') {
                continue;
            }
            $field = $one['field'];
            $one['curValue'] = $this->formatContentInfo($detailInfo, $field, $moldInfo);
            $one['key'] = $field;
            $res[] = $one;
        }

        $result = [
            'format_info' => $res,
            'raw_info' => $detailInfo,
        ];

        return $result;
    }

    public function getContentDetailForEdit($moldId, $id)
    {

        $detailInfo = ContentRepository::buildContent($moldId)->find($id);

        $moldInfo = $this->moldRepository->getMoldInfo($moldId);

        $fieldArr = $moldInfo['fields_arr'];
        foreach ($fieldArr as &$one) {
            $field = $one['field'];
            $one['curValue'] = $detailInfo->$field ?? '';
            $one['key'] = $field;
        }

        return $fieldArr;
    }

    public function contentDelete($moldId, $id)
    {
        $repo = ContentRepository::buildContent($moldId);
        $before = $repo->find($id);
        try {
            app(TriggerService::class)->dispatch('content.before_delete', [
                'mold_id' => (int) $moldId,
                'id' => (int) $id,
                'before' => $before ? (array) $before : [],
                'after' => [],
            ]);
        } catch (\Throwable $e) { /* ignore */
        }
        $deleteRes = $repo->where('id', $id)->delete();
        try {
            app(TriggerService::class)->dispatch('content.after_delete', [
                'mold_id' => (int) $moldId,
                'id' => (int) $id,
                'before' => $before ? (array) $before : [],
                'after' => [],
            ]);
        } catch (\Throwable $e) { /* ignore */
        }

        return $deleteRes;
    }

    /**
     * 批量删除
     */
    public function deleteByIds(int $moldId, array $ids): array
    {
        $repo = ContentRepository::buildContent($moldId);
        $failedIds = [];

        foreach ($ids as $id) {
            $before = $repo->find($id);
            if (! $before) {
                $failedIds[] = $id;

                continue;
            }

            try {
                app(TriggerService::class)->dispatch('content.before_delete', [
                    'mold_id' => (int) $moldId,
                    'id' => (int) $id,
                    'before' => $before ? (array) $before : [],
                    'after' => [],
                ]);
            } catch (\Throwable $e) { /* ignore */
            }

            $deleteRes = $repo->where('id', $id)->delete();

            if (! $deleteRes) {
                $failedIds[] = $id;
            } else {

                try {
                    app(TriggerService::class)->dispatch('content.after_delete', [
                        'mold_id' => (int) $moldId,
                        'id' => (int) $id,
                        'before' => $before ? (array) $before : null,
                        'after' => [],
                    ]);
                } catch (\Throwable $e) { /* ignore */
                }

            }
        }

        return $failedIds;
    }

    public function getContentList($moldId, $params)
    {
        $params = is_array($params) ? $params : [];

        // 设置默认值
        if (! isset($params['page'])) {
            $params['page'] = 1;
        }
        if (! isset($params['page_size'])) {
            $params['page_size'] = 15;
        }
        if (! isset($params['fields'])) {
            $params['fields'] = ['*'];
        }

        $result = ContentRepository::buildContent($moldId)->getList($params, $params['fields'] ?? ['*'], $params['page'] ?? 1, $params['page_size'] ?? 15);
        $list = $result['items'] ?? [];
        $pagination = [
            'total' => $result['total'] ?? 0,
            'page' => $result['page'] ?? 1,
            'page_size' => $result['page_size'] ?? 15,
            'page_count' => $result['page_count'] ?? 1,
        ];

        $moldInfo = $this->moldRepository->getMoldInfo($moldId);
        $moldField = $moldInfo['fields_arr'];
        $listShowFields = $moldInfo['list_show_fields_arr'];

        $allListTitle = [];
        foreach ($moldField as $one) {
            if ($one['type'] == 'dividingLine') {
                continue;
            }

            $allListTitle[] = [
                'key' => $one['field'],
                'dataIndex' => $one['field'],
                'title' => $one['label'],
            ];
        }

        $fieldListTitle = array_values(array_filter($allListTitle, function ($one) use ($listShowFields) {
            if (! $listShowFields) {
                return true;
            }

            return in_array($one['key'], $listShowFields);
        }));

        array_unshift($fieldListTitle, [
            'key' => 'id',
            'dataIndex' => 'id',
            'title' => 'ID',
        ]);

        array_push($fieldListTitle, [
            'key' => 'created_at',
            'dataIndex' => 'created_at',
            'title' => '创建时间',
        ], [
            'key' => 'updated_at',
            'dataIndex' => 'updated_at',
            'title' => '修改时间',
        ], [
            'key' => 'content_status',
            'dataIndex' => 'content_status',
            'title' => '状态',
        ]);

        // 确保fieldListTitle中没有重复的key
        $fieldListTitle = array_unique($fieldListTitle, SORT_REGULAR);

        $resList = [];
        foreach ($list as $item) {
            $resOne = [];
            foreach ($fieldListTitle as $oneField) {
                $key = $oneField['key'];
                $resOne[$key] = $this->formatContentInfo($item, $key, $moldInfo);
            }
            $resOne['key'] = $item->id;
            $resList[] = $resOne;
        }

        // $resTitle = array_column($fieldListTitle, 'title');

        return [
            'allListTitle' => $allListTitle,
            'fieldListTitle' => $fieldListTitle,
            'list' => $resList,
            'pagination' => $pagination,
        ];

    }

    private function formatContentInfo($item, $key, $moldInfo)
    {
        $moldField = $moldInfo['fields_arr'];

        // 快速索引：field => def
        $fieldDefMap = [];
        foreach ($moldField as $def) {
            if (! isset($def['field'])) {
                continue;
            }
            $fieldDefMap[$def['field']] = $def;
        }

        $typeMap = array_column($moldField, 'type', 'field');
        $res = $item->$key;

        // 空值直接返回
        if (! isset($typeMap[$key]) || $res === null || $res === '') {
            return $res;
        }

        // 文件/图片/颜色等特殊类型
        if ($typeMap[$key] == 'fileUpload') {
            $res = [
                'type' => 'file',
                'content' => $res, // 字符串URL
            ];
        }

        if ($typeMap[$key] == 'picUpload') {
            $res = [
                'type' => 'image',
                'content' => $res, // 字符串URL
            ];
        }

        if ($typeMap[$key] == 'picGallery') {
            $decoded = is_string($res) ? json_decode($res, true) : $res;
            $res = [
                'type' => 'imageGallery',
                'content' => is_array($decoded) ? $decoded : [],
            ];
        }

        if ($typeMap[$key] == 'colorPicker') {
            $res = [
                'type' => 'color',
                'content' => $res,
            ];
        }

        if ($typeMap[$key] == 'richText') {
            $res = [
                'type' => 'richText',
                'content' => $res,
            ];
        }

        // 选择来源为“模型”的单选/多选/下拉，在展示时把已保存的 id 转为对应的文本值
        if (in_array($typeMap[$key], ['select', 'radio', 'checkbox'])) {
            $def = $fieldDefMap[$key] ?? null;
            $isModelSource = $def && (($def['optionsSource'] ?? '') === 'model')
                && ! empty($def['sourceModelId']) && ! empty($def['sourceFieldName']);
            if ($isModelSource) {
                try {
                    $relatedMold = $this->moldRepository->getMoldInfo((int) $def['sourceModelId']);
                    $tableName = $relatedMold['table_name'] ?? null;
                    $labelField = $def['sourceFieldName'];
                    if ($tableName && $labelField) {
                        $ids = array_values(array_filter(array_map('trim', explode(',', (string) $item->$key))));
                        if (! empty($ids)) {
                            $rows = DB::table($tableName)
                                ->whereIn('id', $ids)
                                ->get(['id', $labelField]);
                            $idToLabel = [];
                            foreach ($rows as $r) {
                                $idToLabel[(string) $r->id] = (string) ($r->$labelField ?? '');
                            }
                            $labels = [];
                            foreach ($ids as $id) {
                                if ($id !== '' && isset($idToLabel[$id]) && $idToLabel[$id] !== '') {
                                    $labels[] = $idToLabel[$id];
                                }
                            }

                            return implode(',', $labels);
                        }
                    }
                } catch (\Throwable $e) {
                    // 回退为原值（id）
                    return $item->$key;
                }
            }
        }

        return $res;
    }

    #[AiTool(
        name: 'content_update',
        description: '修改内容：根据模型ID和内容ID更新内容字段。',
        params: [
            'moldId' => ['type' => 'integer', 'required' => true, 'desc' => '模型ID'],
            'contentId' => ['type' => 'integer', 'required' => true, 'desc' => '内容ID'],
            'contentInfo' => ['type' => 'object', 'required' => true, 'desc' => '内容字段键值对'],
        ]
    )]
    public function editContent($contentInfo, $moldId, $contentId)
    {
        $repo = ContentRepository::buildContent($moldId);
        $before = $repo->find($contentId);

        try {
            Log::info('内容服务: 分发 content.before_update 事件', [
                'mold_id' => (int) $moldId,
                'content_id' => (int) $contentId,
                'content_info' => (array) $contentInfo,
            ]);
            $res = app(TriggerService::class)->dispatch('content.before_update', [
                'mold_id' => (int) $moldId,
                'id' => (int) $contentId,
                'data' => $contentInfo,
                'before' => $before ? (array) $before : [],
                'after' => [],
            ]);
            Log::info('内容服务: content.before_update 事件结果', [
                'result' => $res,
            ]);
            $contentInfo = $res['data'] ?? $contentInfo;
        } catch (\Throwable $e) {
            Log::warning('content.before_update failed: '.$e->getMessage());
        }

        $contentInfo = $this->validateContentInfo((int) $moldId, (array) $contentInfo, $before);

        $operatorId = $this->resolveOpenApiOperator();
        if (Schema::hasColumn($repo->getTableName(), 'updated_by') && $operatorId !== null) {
            $contentInfo['updated_by'] = $operatorId;
        }

        $repo->editById($contentInfo, $contentId);
        try {
            $after = $repo->find($contentId);
            $this->versionService->recordUpdate((int) $moldId, (int) $contentId, $before, $after, Auth::id());
            try {
                Log::info('内容服务: 分发 content.after_update 事件', [
                    'mold_id' => (int) $moldId,
                    'content_id' => (int) $contentId,
                ]);
                app(TriggerService::class)->dispatch('content.after_update', [
                    'mold_id' => (int) $moldId,
                    'id' => (int) $contentId,
                    'before' => $before ? (array) $before : [],
                    'after' => $after ? (array) $after : [],
                ]);
                Log::info('内容服务: content.after_update 事件分发成功');

            } catch (\Throwable $e) {
                Log::warning('content.after_update failed: '.$e->getMessage());
            }
        } catch (\Throwable $e) {
            Log::warning('recordUpdate failed: '.$e->getMessage());
        }

    }

    #[AiTool(
        name: 'content_create',
        description: '新增内容：根据模型ID创建一条内容记录。',
        params: [
            'moldId' => ['type' => 'integer', 'required' => true, 'desc' => '模型ID'],
            'contentInfo' => ['type' => 'object', 'required' => true, 'desc' => '内容字段键值对'],
        ]
    )]
    public function addContent($contentInfo, $moldId)
    {
        if (isset($contentInfo['tags']) && is_array($contentInfo['tags'])) {
            $contentInfo['tags'] = implode(',', $contentInfo['tags']);
        }

        $repo = ContentRepository::buildContent($moldId);
        try {
            $res = app(TriggerService::class)->dispatch('content.before_create', [
                'mold_id' => (int) $moldId,
                'id' => 0,
                'data' => $contentInfo,
                'before' => [],
                'after' => [],
            ]);

            $moldId = $res['mold_id'] ?? $moldId;
            $contentInfo = $res['data'] ?? $contentInfo;
        } catch (\Throwable $e) {
            Log::warning('content.before_create failed: '.$e->getMessage());
        }

        $rawInfo = $contentInfo;
        $contentInfo = $this->validateContentInfo((int) $moldId, (array) $contentInfo, null);

        $operatorId = $this->resolveOpenApiOperator();
        if ($operatorId !== null) {
            if (Schema::hasColumn($repo->getTableName(), 'created_by')) {
                $contentInfo['created_by'] = $operatorId;
            }
            if (Schema::hasColumn($repo->getTableName(), 'updated_by')) {
                $contentInfo['updated_by'] = $operatorId;
            }
        }
        if (Schema::hasColumn($repo->getTableName(), 'content_status') && (! array_key_exists('content_status', $contentInfo) || $this->isEmptyValue($contentInfo['content_status']))) {
            $contentInfo['content_status'] = $rawInfo['content_status'] ?? ContentRepository::STATUS_PUBLISHED;
        }

        $created = $repo->create($contentInfo);
        try {
            Log::info('内容服务: 分发 content.after_create 事件', [
                'mold_id' => (int) $moldId,
                'created' => (array) $created,
            ]);
            $row = is_object($created) ? $created : null;
            if (! $row) {
                throw new \RuntimeException('创建内容失败');
            }
            if ($row) {
                $this->versionService->recordCreate((int) $moldId, (int) $row->id, $row, Auth::id());
                try {
                    app(TriggerService::class)->dispatch('content.after_create', [
                        'mold_id' => (int) $moldId,
                        'id' => (int) $row->id,
                        'before' => [],
                        'after' => (array) $row,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('content.after_create failed: '.$e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('recordCreate failed: '.$e->getMessage());
        }

        // 返回创建的内容信息，供 AI 汇总使用
        return [
            'id' => $row->id ?? null,
            'title' => $row->title ?? null,
            'status' => $row->status ?? null,
        ];
    }

    #[AiTool(
        name: 'content_list',
        description: '查询内容列表：按表标识与筛选条件分页获取内容。isAllStatus为是否获取所有状态的内容，如果不是明确指定需要获取已发布内容，此字段要传true以获取所有状态的内容。',
        params: [
            'tableName' => ['type' => 'string', 'required' => true, 'desc' => '模型表标识，如 news'],
            'params' => ['type' => 'object', 'required' => true, 'desc' => '筛选条件，字段名=>值'],
            'fields' => ['type' => 'array|string', 'required' => true, 'desc' => '返回字段列表，数组或逗号分隔字符串'],
            'page' => ['type' => 'integer', 'required' => true, 'desc' => '页码，从1开始'],
            'pageSize' => ['type' => 'integer', 'required' => true, 'desc' => '每页条数'],
            'isAllStatus' => ['type' => 'boolean', 'required' => false, 'desc' => '是否获取所有状态的内容，默认false'],
        ]
    )]
    public function getListApi($tableName, $params, $fields, $page, $pageSize, $isAllStatus = false)
    {
        $tableName = $this->ensureMcTableName($tableName);
        $content = ContentRepository::getModel($tableName);

        if (! isset($params['filter'])) {
            $params['filter'] = [];
        }
        if (! $isAllStatus && (! isset($params['filter']['content_status']) || ! in_array($params['filter']['content_status'], [ContentRepository::STATUS_PUBLISHED, ContentRepository::STATUS_PENDING]))) {
            $params['filter']['content_status'] = ContentRepository::STATUS_PUBLISHED;
        }

        $list = $content->getList($params, $fields, $page, $pageSize);

        // optionsSource=model 的字段，展示时将 id 转为关联值（如 tag_name）
        if (is_array($list) && isset($list['items']) && is_array($list['items'])) {
            $list['items'] = $this->relationDisplayService->hydrateListItems($tableName, $list['items']);
        }

        return $list;

    }

    #[AiTool(
        name: 'content_detail',
        description: '查询内容详情：按表标识与主键ID获取单条内容。',
        params: [
            'tableName' => ['type' => 'string', 'required' => true, 'desc' => '模型表标识，如 news'],
            'id' => ['type' => 'integer', 'required' => true, 'desc' => '内容ID'],
        ]
    )]
    public function getDetailApi($tableName, $id)
    {
        $tableName = $this->ensureMcTableName($tableName);
        $content = ContentRepository::getModel($tableName);

        $detail = $content->getDetail($id);

        return $this->relationDisplayService->hydrateDetail($tableName, $detail);
    }

    #[AiTool(
        name: 'content_count',
        description: '统计内容数量：按表标识统计内容数量。',
        params: [
            'tableName' => ['type' => 'string', 'required' => true, 'desc' => '模型表标识，如 news'],
            'filters' => ['type' => 'object', 'required' => false, 'desc' => '可选筛选条件，字段名=>值'],
        ]
    )]
    public function getCountApi($tableName)
    {
        $tableName = $this->ensureMcTableName($tableName);
        $content = ContentRepository::getModel($tableName);

        $count = $content->count();

        return $count;
    }

    #[AiTool(
        name: 'content_publish',
        description: '上架内容：按模型表标识id与主键ID上架内容。',
        params: [
            'moldId' => ['type' => 'integer', 'required' => true, 'desc' => '模型表标识id'],
            'id' => ['type' => 'integer', 'required' => true, 'desc' => '内容ID'],
        ]
    )]
    public function publish(int $moldId, int $id): bool
    {
        $repo = ContentRepository::buildContent($moldId);
        $row = $repo->find($id);
        if (! $row) {
            throw new \InvalidArgumentException($id.'记录不存在'.json_encode($row));
        }
        $cur = (string) ($row->content_status ?? 'pending');
        if ($cur === ContentRepository::STATUS_PUBLISHED) {
            return true; // 已是已发布
        }
        // 取消流转限制，允许任意状态上架
        $ok = $repo->updateStatusById($id, ContentRepository::STATUS_PUBLISHED);
        try {
            $after = $repo->find($id);
            if ($after) {
                $this->versionService->recordPublish((int) $moldId, (int) $id, $after, Auth::id());
            }
        } catch (\Throwable $e) {
            Log::warning('recordPublish failed: '.$e->getMessage());
        }

        return $ok;
    }

    #[AiTool(
        name: 'content_publish_batch',
        description: '批量上架内容：按模型表标识id与主键ID列表批量上架内容。',
        params: [
            'moldId' => ['type' => 'integer', 'required' => true, 'desc' => '模型表标识id'],
            'ids' => ['type' => 'array', 'required' => true, 'desc' => '内容ID列表'],
        ]
    )]
    public function publishByIds(int $moldId, array $ids): array
    {
        $repo = ContentRepository::buildContent($moldId);
        $repo->updateStatusByIds($ids, ContentRepository::STATUS_PUBLISHED);
        $failedIds = $repo->whereIn('id', $ids)->where('content_status', '!=', ContentRepository::STATUS_PUBLISHED)->pluck('id')->toArray();
        // 记录成功项的版本历史
        try {
            $successIds = array_values(array_diff(array_map('intval', $ids), array_map('intval', $failedIds)));
            foreach ($successIds as $id) {
                $row = $repo->find($id);
                if ($row) {
                    $this->versionService->recordPublish((int) $moldId, (int) $id, $row, Auth::id());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('recordPublish(batch) failed: '.$e->getMessage());
        }

        return $failedIds;
    }

    #[AiTool(
        name: 'content_unpublish',
        description: '下架内容：按模型表标识id与主键ID下架内容。',
        params: [
            'moldId' => ['type' => 'integer', 'required' => true, 'desc' => '模型表标识id'],
            'id' => ['type' => 'integer', 'required' => true, 'desc' => '内容ID'],
        ]
    )]
    public function unpublish(int $moldId, int $id): bool
    {
        $repo = ContentRepository::buildContent($moldId);
        $row = $repo->find($id);
        if (! $row) {
            throw new \InvalidArgumentException('记录不存在');
        }
        $cur = (string) ($row->content_status ?? 'pending');
        if ($cur === ContentRepository::STATUS_DISABLED) {
            return true; // 已下线
        }
        // 取消流转限制，允许任意状态下架
        $ok = $repo->updateStatusById($id, ContentRepository::STATUS_DISABLED);
        try {
            $after = $repo->find($id);
            if ($after) {
                $this->versionService->recordUnpublish((int) $moldId, (int) $id, $after, Auth::id());
            }
        } catch (\Throwable $e) {
            Log::warning('recordUnpublish failed: '.$e->getMessage());
        }

        return $ok;
    }

    #[AiTool(
        name: 'content_unpublish_batch',
        description: '批量下架内容：按模型表标识id与主键ID列表批量下架内容。',
        params: [
            'moldId' => ['type' => 'integer', 'required' => true, 'desc' => '模型表标识id'],
            'ids' => ['type' => 'array', 'required' => true, 'desc' => '内容ID列表'],
        ]
    )]
    public function unpublishByIds(int $moldId, array $ids): array
    {
        $repo = ContentRepository::buildContent($moldId);
        $repo->updateStatusByIds($ids, ContentRepository::STATUS_DISABLED);
        $failedIds = $repo->whereIn('id', $ids)->where('content_status', '!=', ContentRepository::STATUS_DISABLED)->pluck('id')->toArray();
        // 记录成功项的版本历史
        try {
            $successIds = array_values(array_diff(array_map('intval', $ids), array_map('intval', $failedIds)));
            foreach ($successIds as $id) {
                $row = $repo->find($id);
                if ($row) {
                    $this->versionService->recordUnpublish((int) $moldId, (int) $id, $row, Auth::id());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('recordUnpublish(batch) failed: '.$e->getMessage());
        }

        return $failedIds;
    }

    public function aiGenerate($moldId, $prompt, $onlyEmpty = false, $requestedBy = null, array $currentValues = [])
    {
        return $this->taskService->createContentGenerationTask((int) $moldId, (string) $prompt, (bool) $onlyEmpty, $requestedBy, null, null, null, false, $currentValues);
    }

    public function createContentBatchTask(int $moldId, string $prompt, int $count = 1, $requestedBy = null)
    {
        return $this->taskService->createContentBatchTask((int) $moldId, (string) $prompt, (int) $count, $requestedBy);
    }

    public function createContentBatchDirectTask(int $moldId, string $prompt, int $count = 1, $requestedBy = null)
    {
        return $this->taskService->createContentBatchDirectTask((int) $moldId, (string) $prompt, (int) $count, $requestedBy);
    }

    private function validateContentInfo(int $moldId, array $contentInfo, $before = null): array
    {
        $moldInfo = $this->moldRepository->getMoldInfo($moldId);
        $fields = $moldInfo['fields_arr'] ?? [];
        if (! is_array($fields)) {
            return $contentInfo;
        }

        $allowedFields = [];
        foreach ($fields as $f) {
            if (! is_array($f)) {
                continue;
            }
            if (($f['type'] ?? '') === 'dividingLine') {
                continue;
            }

            $field = (string) ($f['field'] ?? '');
            if ($field !== '') {
                $allowedFields[] = $field;
            }
        }
        $allowedFields = array_values(array_unique($allowedFields));

        foreach ($contentInfo as $field => $value) {
            if (! is_string($field)) {
                continue;
            }
            if (! in_array($field, $allowedFields, true)) {
                unset($contentInfo[$field]);
                // throw new \InvalidArgumentException('字段不存在：'.$field);
            }
        }

        $data = $contentInfo;
        if ($before) {
            $base = is_object($before) ? (array) $before : (is_array($before) ? $before : []);
            $data = array_merge($base, $contentInfo);
        }

        foreach ($fields as $f) {
            if (! is_array($f)) {
                continue;
            }
            if (($f['type'] ?? '') === 'dividingLine') {
                continue;
            }

            $field = (string) ($f['field'] ?? '');
            if ($field === '') {
                continue;
            }

            $rules = $f['rules'] ?? null;
            $required = false;
            if (is_array($rules)) {
                foreach ($rules as $r) {
                    if (is_array($r) && ! empty($r['required'])) {
                        $required = true;
                        break;
                    }
                }
            }

            $value = $data[$field] ?? null;
            if ($required && $this->isEmptyValue($value)) {
                throw new \InvalidArgumentException('字段必填：'.$field);
            }

            // 未传值且非必填，跳过类型/长度校验
            if (! array_key_exists($field, $data) || $this->isEmptyValue($value)) {
                continue;
            }

            $type = (string) ($f['type'] ?? '');

            // 基本类型校验（按表单组件类型）
            if (in_array($type, ['input', 'textarea', 'richText'], true)) {
                if (! is_string($value)) {
                    throw new \InvalidArgumentException('字段类型错误(期望字符串)：'.$field);
                }
            } elseif ($type === 'numInput') {
                if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
                    throw new \InvalidArgumentException('字段类型错误(期望数字)：'.$field);
                }
            } elseif ($type === 'switch') {
                if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1'], true)) {
                    throw new \InvalidArgumentException('字段类型错误(期望布尔)：'.$field);
                }
            } elseif (in_array($type, ['checkbox', 'tags'], true)) {
                if (! is_array($value) && ! is_string($value)) {
                    throw new \InvalidArgumentException('字段类型错误(期望数组/字符串)：'.$field);
                }
            } elseif (in_array($type, ['fileUpload', 'picUpload'], true)) {
                // 单文件/单图：期望字符串URL
                if (! is_string($value)) {
                    throw new \InvalidArgumentException('字段类型错误(期望字符串URL)：'.$field);
                }
            } elseif ($type === 'picGallery') {
                // 图片集：期望JSON数组字符串或数组
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                        throw new \InvalidArgumentException('字段类型错误(期望JSON数组)：'.$field);
                    }
                } elseif (! is_array($value)) {
                    throw new \InvalidArgumentException('字段类型错误(期望数组/JSON)：'.$field);
                }
            }
        }

        return $contentInfo;
    }

    private function ensureMcTableName(string $tableName): string
    {
        $prefix = (string) session('current_project_prefix');
        if ($prefix !== '' && strpos($tableName, $prefix) === 0) {
            return $tableName;
        }

        return getMcTableName($tableName);
    }

    private function resolveOpenApiOperator(): ?string
    {
        $request = request();
        $projectUserId = $request->attributes->get('project_user_id');
        if ($projectUserId !== null && $projectUserId !== '') {
            return (string) $projectUserId;
        }

        return $request->is('open/*') ? 'api' : null;
    }

    private function isEmptyValue($val): bool
    {
        if ($val === null) {
            return true;
        }
        if (is_string($val)) {
            return trim($val) === '';
        }
        if (is_array($val)) {
            return empty($val);
        }

        return false;
    }
}
