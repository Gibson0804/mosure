<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Mold;
use App\Services\ContentService;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    private ContentService $contentService;

    public function __construct(ContentService $contentService)
    {
        $this->contentService = $contentService;
    }

    public function list(Request $request)
    {
        $data = $request->validate([
            'project_prefix' => ['required', 'string', 'max:100'],
            'model_id' => ['nullable', 'integer'],
            'table_name' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/'],
            'page' => ['integer', 'min:1', 'nullable'],
            'per_page' => ['integer', 'min:1', 'max:100', 'nullable'],
            'page_size' => ['integer', 'min:1', 'max:100', 'nullable'],
            'fields' => ['nullable'],
            'filters' => ['array', 'nullable'],
        ]);

        session(['current_project_prefix' => $data['project_prefix']]);

        // 如果提供了 model_id，获取对应的 table_name
        $mold = null;
        $tableName = $data['table_name'] ?? null;
        if (! $tableName && isset($data['model_id'])) {
            $mold = Mold::where('id', $data['model_id'])->first();
            if (! $mold) {
                return response()->json([
                    'code' => 404,
                    'message' => 'Model not found',
                    'data' => null,
                ], 404);
            }

            $tableName = $mold->table_name;
        }

        if (! $tableName) {
            return response()->json([
                'code' => 400,
                'message' => 'table_name or model_id is required',
                'data' => null,
            ], 400);
        }

        if (! $mold) {
            $mold = Mold::where('table_name', $tableName)->first();
        }

        $params = $data['filters'] ?? [];
        $fieldsInput = $request->input('fields', ['*']);
        if (is_string($fieldsInput)) {
            $fields = array_values(array_filter(array_map('trim', explode(',', $fieldsInput))));
            $fields = empty($fields) ? ['*'] : $fields;
        } elseif (is_array($fieldsInput) && ! empty($fieldsInput)) {
            $fields = $fieldsInput;
        } else {
            $fields = ['*'];
        }

        $page = $data['page'] ?? (int) $request->input('page', 1);
        $pageSize = $data['per_page']
            ?? $data['page_size']
            ?? (int) $request->input('per_page', (int) $request->input('page_size', 20));
        $pageSize = max(1, min(100, $pageSize));

        $list = $this->contentService->getListApi(
            $tableName,
            $params,
            $fields,
            $page,
            $pageSize
        );

        // 获取字段 label 映射和需要排除的大字段
        $fieldLabels = [];
        $switchFields = [];
        $excludeFields = [];
        if (isset($mold) && $mold->fields) {
            $fieldsData = is_string($mold->fields) ? json_decode($mold->fields, true) : $mold->fields;
            if (is_array($fieldsData)) {
                foreach ($fieldsData as $field) {
                    if (isset($field['field']) && isset($field['label'])) {
                        $fieldLabels[$field['field']] = $field['label'];
                    }
                    if (isset($field['field']) && isset($field['type']) && $field['type'] === 'switch') {
                        $switchFields[] = $field['field'];
                    }
                    // 排除大字段类型
                    if (isset($field['field']) && isset($field['type'])) {
                        $largeTypes = ['richText'];
                        if (in_array($field['type'], $largeTypes)) {
                            $excludeFields[] = $field['field'];
                        }
                    }
                }
            }
        }

        // 转换 switch 字段的值：1->是，0->否，并过滤大字段
        if (! empty($switchFields) && isset($list['items'])) {
            foreach ($list['items'] as &$item) {
                // 过滤大字段
                if (! empty($excludeFields)) {
                    foreach ($excludeFields as $excludeField) {
                        if (is_object($item)) {
                            unset($item->$excludeField);
                        } else {
                            unset($item[$excludeField]);
                        }
                    }
                }
                // 转换 switch 字段
                foreach ($switchFields as $field) {
                    $value = is_object($item) ? ($item->$field ?? null) : ($item[$field] ?? null);
                    if ($value !== null) {
                        if ($value === true || $value === 1 || $value === '1') {
                            if (is_object($item)) {
                                $item->$field = '是';
                            } else {
                                $item[$field] = '是';
                            }
                        } elseif ($value === false || $value === 0 || $value === '0') {
                            if (is_object($item)) {
                                $item->$field = '否';
                            } else {
                                $item[$field] = '否';
                            }
                        }
                    }
                }
            }
        }

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => array_merge($list, [
                'field_labels' => $fieldLabels,
            ]),
        ]);
    }

    public function detail(Request $request)
    {
        $data = $request->validate([
            'project_prefix' => ['required', 'string', 'max:100'],
            'model_id' => ['nullable', 'integer'],
            'table_name' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/'],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        session(['current_project_prefix' => $data['project_prefix']]);

        // 如果提供了 model_id，获取对应的 table_name
        $mold = null;
        $tableName = $data['table_name'] ?? null;
        if (! $tableName && isset($data['model_id'])) {
            $mold = Mold::where('id', $data['model_id'])->first();

            if (! $mold) {
                return response()->json([
                    'code' => 404,
                    'message' => 'Model not found',
                    'data' => null,
                ], 404);
            }

            $tableName = $mold->table_name;
        }

        if (! $tableName) {
            return response()->json([
                'code' => 400,
                'message' => 'table_name or model_id is required',
                'data' => null,
            ], 400);
        }

        if (! $mold) {
            $mold = Mold::where('table_name', $tableName)->first();
        }

        $detail = $this->contentService->getDetailApi($tableName, $data['id']);

        if (! $detail) {
            return response()->json([
                'code' => 404,
                'message' => '内容不存在',
                'data' => null,
            ], 404);
        }

        // 转换为数组，统一处理
        if (is_object($detail)) {
            $detail = (array) $detail;
        }

        // 获取字段 label 映射
        $fieldLabels = [
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
            'created_by' => '创建人',
            'updated_by' => '更新人',
            'content_status' => '内容状态',
        ];
        $switchFields = [];
        if (isset($mold) && $mold->fields) {
            $fieldsData = is_string($mold->fields) ? json_decode($mold->fields, true) : $mold->fields;
            if (is_array($fieldsData)) {
                foreach ($fieldsData as $field) {
                    if (isset($field['field']) && isset($field['label'])) {
                        $fieldLabels[$field['field']] = $field['label'];
                    }
                    if (isset($field['field']) && isset($field['type']) && $field['type'] === 'switch') {
                        $switchFields[] = $field['field'];
                    }
                }
            }
        }

        // 转换 switch 字段的值：1->是，0->否
        if (! empty($switchFields)) {
            foreach ($switchFields as $field) {
                $value = $detail[$field] ?? null;
                if ($value !== null) {
                    if ($value === true || $value === 1 || $value === '1') {
                        $detail[$field] = '是';
                    } elseif ($value === false || $value === 0 || $value === '0') {
                        $detail[$field] = '否';
                    }
                }
            }
        }

        // 转换 content_status 字段
        $statusValue = $detail['content_status'] ?? null;
        $statusMap = [
            'pending' => '待发布',
            'published' => '已发布',
            'disabled' => '已下线',
        ];
        if ($statusValue && isset($statusMap[$statusValue])) {
            $detail['content_status'] = $statusMap[$statusValue];
        } else {
            $detail['content_status'] = '待发布';
        }

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'detail' => $detail,
                'field_labels' => $fieldLabels,
            ],
        ]);
    }

    public function subject(Request $request)
    {
        $data = $request->validate([
            'project_prefix' => ['required', 'string', 'max:100'],
            'table_name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/'],
        ]);

        session(['current_project_prefix' => $data['project_prefix']]);

        $subject = Mold::query()
            ->where('table_name', $data['table_name'])
            ->where('mold_type', 'single')
            ->first();

        if (! $subject) {
            return response()->json([
                'code' => 404,
                'message' => '单页不存在',
                'data' => null,
            ], 404);
        }

        $content = $subject->subject_content;
        if (is_string($content)) {
            $decoded = json_decode($content, true);
            $content = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        // 获取字段 label 映射和 switch 字段
        $fieldLabels = [];
        $switchFields = [];
        $fieldsData = json_decode($subject->fields, true);
        if (is_array($fieldsData)) {
            foreach ($fieldsData as $field) {
                if (isset($field['field']) && isset($field['label'])) {
                    $fieldLabels[$field['field']] = $field['label'];
                }
                if (isset($field['field']) && isset($field['type']) && $field['type'] === 'switch') {
                    $switchFields[] = $field['field'];
                }
            }
        }

        // 转换 switch 字段的值：1->是，0->否
        if (! empty($switchFields) && is_array($content)) {
            foreach ($switchFields as $field) {
                if (isset($content[$field])) {
                    $value = $content[$field];
                    if ($value === true || $value === 1 || $value === '1') {
                        $content[$field] = '是';
                    } elseif ($value === false || $value === 0 || $value === '0') {
                        $content[$field] = '否';
                    }
                }
            }
        }

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'fields' => $fieldsData,
                'description' => $subject->description,
                'table_name' => $subject->table_name,
                'subject_content' => $content ?? [],
                'field_labels' => $fieldLabels,
            ],
        ]);
    }
}
