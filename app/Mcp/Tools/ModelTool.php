<?php

namespace App\Mcp\Tools;

use App\Constants\ProjectConstants;
use App\Repository\MoldRepository;
use App\Services\MoldService;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Model Tool')]
class ModelTool extends Tool
{
    public MoldService $moldService;

    public function __construct(MoldService $moldService)
    {
        $this->moldService = $moldService;
    }

    public function description(): string
    {
        return '内容模型管理（增删改查、校验）。action 参数说明：list(获取模型列表)|get(获取模型详情)|create(新建模型)|update(修改模型)|validate(校验模型定义)';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('action')
            ->description('操作类型：list|get|create|update|validate')
            ->required();
        $schema->integer('mold_id')
            ->description('模型 ID（get/update/validate 时可选，与 model_key 至少提供一个）');
        $schema->string('model_key')
            ->description('模型表名短标识，不含项目前缀，例如 article（get/update/validate 时可选，与 mold_id 至少提供一个）');
        $schema->string('name')
            ->description('模型名称（create/update 时使用）');
        $schema->string('table_name')
            ->description('表名短标识，不含项目前缀，例如 article（create/update 时使用）');
        $schema->string('mold_type')
            ->description('模型类型：list(内容模型，多条数据) 或 single(单页模型，单条数据)');
        $schema->raw('fields', [
            'type' => 'array',
            'description' => '字段定义数组。每个元素必须包含 field(字段名)、label(字段标题)、type(字段类型)。支持的type：input(单行文本)、textarea(多行文本)、radio(单选)、switch(开关)、checkbox(复选)、select(下拉选择)、numInput(数字输入)、colorPicker(颜色选择器)、dateTimePicker(日期时间选择器)、datePicker(日期选择器)、timePicker(时间选择器)、fileUpload(文件上传)、picUpload(图片上传)、picGallery(图片集)、richText(富文本编辑器)、dividingLine(分割线)、slider(滑块)、rate(评分)、cascader(级联选择器)、dateRangePicker(日期范围选择器)、tags(标签)。格式示例：[{"field":"title","label":"标题","type":"input"}]',
        ]);

        return $schema;
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        $action = $arguments['action'] ?? 'list';

        return match ($action) {
            'list' => $this->handleList(),
            'get' => $this->handleGet($arguments),
            'create' => $this->handleCreate($arguments),
            'update' => $this->handleUpdate($arguments),
            'validate' => $this->handleValidate($arguments),
            default => ToolResult::text(json_encode([
                'success' => false,
                'error' => "未知操作类型: {$action}，支持的操作：list|get|create|update|validate",
            ], JSON_UNESCAPED_UNICODE)),
        };
    }

    /**
     * 获取模型列表
     */
    private function handleList(): ToolResult
    {
        $models = $this->moldService->getAllMold();
        $projectPrefix = session('current_project_prefix', 'default');
        $prefix = $projectPrefix.ProjectConstants::MODEL_CONTENT_PREFIX;
        $result = [];
        foreach ($models as $model) {
            $tableName = $model->table_name;
            $modelKey = $tableName;
            if (strpos($tableName, $prefix) === 0) {
                $modelKey = substr($tableName, strlen($prefix));
            }
            $result[] = [
                'id' => $model->id,
                'name' => $model->name,
                'table_name' => $model->table_name,
                'model_key' => $modelKey,
                'mold_type' => $model->mold_type,
                'created_at' => $model->created_at,
                'updated_at' => $model->updated_at,
            ];
        }

        return ToolResult::text(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * 获取单个模型详情
     */
    private function handleGet(array $arguments): ToolResult
    {
        $moldInfo = null;

        if (isset($arguments['mold_id'])) {
            $moldInfo = $this->moldService->getMoldInfo($arguments['mold_id']);
        } elseif (! empty($arguments['model_key'])) {
            $moldInfo = $this->moldService->getMoldInfoByTableName($arguments['model_key']);
        } else {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => 'mold_id 或 model_key 至少提供一个用于定位模型',
            ], JSON_UNESCAPED_UNICODE));
        }

        if ($moldInfo) {
            if (isset($moldInfo['fields']) && is_string($moldInfo['fields'])) {
                $moldInfo['fields'] = json_decode($moldInfo['fields'], true);
            }

            return ToolResult::text(json_encode($moldInfo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return ToolResult::text(json_encode([
            'success' => false,
            'error' => 'Model not found',
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * 新建模型
     */
    private function handleCreate(array $arguments): ToolResult
    {
        try {
            $fields = $arguments['fields'] ?? [];
            if (! is_array($fields)) {
                $fields = json_decode((string) $fields, true) ?? [];
            }

            $normalizedFields = $this->moldService->normalizeFields($fields);

            if (empty($normalizedFields)) {
                $normalizedFields[] = [
                    'field' => 'title',
                    'label' => '标题',
                    'type' => 'string',
                ];
            }

            $data = [
                'name' => $arguments['name'],
                'table_name' => $arguments['table_name'],
                'mold_type' => $this->moldService->normalizeMoldType($arguments['mold_type'] ?? MoldRepository::CONTENT_MOLD_TYPE),
                'fields' => $normalizedFields,
            ];

            $moldId = $this->moldService->addForm($data);

            return ToolResult::text(json_encode([
                'success' => true,
                'mold_id' => $moldId,
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 修改模型
     */
    private function handleUpdate(array $arguments): ToolResult
    {
        try {
            $moldId = $arguments['mold_id'] ?? null;

            if (! $moldId && ! empty($arguments['model_key'])) {
                $moldInfo = $this->moldService->getMoldInfoByTableName($arguments['model_key']);
                if ($moldInfo && isset($moldInfo['id'])) {
                    $moldId = $moldInfo['id'];
                }
            }

            if (! $moldId) {
                return ToolResult::text(json_encode([
                    'success' => false,
                    'error' => 'mold_id 或 model_key 至少提供一个有效值',
                ], JSON_UNESCAPED_UNICODE));
            }

            $updateData = [];
            foreach (['name', 'table_name'] as $field) {
                if (isset($arguments[$field])) {
                    $updateData[$field] = $arguments[$field];
                }
            }

            if (isset($arguments['mold_type'])) {
                $updateData['mold_type'] = $this->moldService->normalizeMoldType($arguments['mold_type']);
            }

            if (isset($arguments['fields'])) {
                $fields = $arguments['fields'];
                if (! is_array($fields)) {
                    $fields = json_decode((string) $fields, true) ?? [];
                }
                $updateData['fields'] = $this->moldService->normalizeFields($fields);
            }

            if (! empty($updateData)) {
                $this->moldService->editFormById($updateData, $moldId);

                if (isset($updateData['mold_type']) && $updateData['mold_type'] === MoldRepository::CONTENT_MOLD_TYPE) {
                    $this->moldService->getTableByField($updateData['table_name'] ?? '', $updateData['fields'] ?? []);
                }
            }

            return ToolResult::text(json_encode([
                'success' => true,
                'mold_id' => $moldId,
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 校验模型定义
     */
    private function handleValidate(array $arguments): ToolResult
    {
        $mode = $arguments['mode'] ?? null;
        $hasTarget = ! empty($arguments['mold_id']) || ! empty($arguments['model_key']);

        if ($mode !== 'create' && $mode !== 'update') {
            $mode = $hasTarget ? 'update' : 'create';
        }

        $errors = [];
        $warnings = [];
        $fieldDiff = null;
        $target = [];

        $name = $arguments['name'] ?? null;
        $tableName = $arguments['table_name'] ?? null;
        $fields = $arguments['fields'] ?? null;

        if ($mode === 'create') {
            if (! $name) {
                $errors[] = [
                    'code' => 'missing_field',
                    'field' => 'name',
                    'message' => '创建模型时必须提供 name',
                ];
            }
            if (! $tableName) {
                $errors[] = [
                    'code' => 'missing_field',
                    'field' => 'table_name',
                    'message' => '创建模型时必须提供 table_name（短标识）',
                ];
            }
            if (! is_array($fields) || empty($fields)) {
                $errors[] = [
                    'code' => 'missing_field',
                    'field' => 'fields',
                    'message' => '创建模型时必须提供至少一个字段定义',
                ];
            }
        } else {
            if (! $hasTarget) {
                $errors[] = [
                    'code' => 'missing_target',
                    'field' => 'mold_id/model_key',
                    'message' => '更新模式下必须提供 mold_id 或 model_key 用于定位模型',
                ];
            }
        }

        if ($tableName !== null && $tableName !== '') {
            if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $tableName)) {
                $errors[] = [
                    'code' => 'invalid_table_name',
                    'field' => 'table_name',
                    'message' => 'table_name 只能以字母开头，且仅包含字母、数字和下划线',
                ];
            }
        }

        $reservedFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'updated_by', 'created_by', 'content_status'];
        $supportedTypes = [
            'text', 'textarea', 'richText',
            'number', 'int', 'integer',
            'float', 'decimal', 'double',
            'boolean',
            'date', 'datetime', 'timestamp',
            'json', 'array',
            'select', 'check_box', 'radio',
            'string',
        ];

        if (is_array($fields)) {
            $seenFieldNames = [];
            foreach ($fields as $index => $fieldDef) {
                if (! is_array($fieldDef)) {
                    $errors[] = [
                        'code' => 'invalid_field_definition',
                        'field' => "fields[{$index}]",
                        'message' => '字段定义必须是对象/字典结构',
                    ];

                    continue;
                }

                $fieldName = $fieldDef['field'] ?? null;
                if (! $fieldName) {
                    $errors[] = [
                        'code' => 'missing_field_name',
                        'field' => "fields[{$index}].field",
                        'message' => '每个字段必须包含 field(字段名)',
                    ];

                    continue;
                }

                if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $fieldName)) {
                    $errors[] = [
                        'code' => 'invalid_field_name',
                        'field' => "fields[{$index}].field",
                        'message' => '字段名必须以字母开头，并仅包含字母、数字和下划线',
                    ];
                }

                if (in_array($fieldName, $reservedFields, true)) {
                    $errors[] = [
                        'code' => 'reserved_field_name',
                        'field' => "fields[{$index}].field",
                        'message' => '字段名与系统保留字段冲突：'.$fieldName,
                    ];
                }

                if (isset($seenFieldNames[$fieldName])) {
                    $errors[] = [
                        'code' => 'duplicate_field_name',
                        'field' => "fields[{$index}].field",
                        'message' => '字段名重复：'.$fieldName,
                    ];
                }
                $seenFieldNames[$fieldName] = true;

                $type = $fieldDef['type'] ?? 'string';
                if (! in_array($type, $supportedTypes, true)) {
                    $warnings[] = [
                        'code' => 'unknown_field_type',
                        'field' => "fields[{$index}].type",
                        'message' => '字段类型不在内置列表中，将按 string 处理：'.$type,
                    ];
                }
            }
        }

        $existingMold = null;
        if ($mode === 'update' && empty($errors)) {
            if (! empty($arguments['mold_id'])) {
                $existingMold = $this->moldService->getMoldInfo($arguments['mold_id']);
            } elseif (! empty($arguments['model_key'])) {
                $existingMold = $this->moldService->getMoldInfoByTableName($arguments['model_key']);
            }

            if ($existingMold) {
                $target['mold_id'] = $existingMold['id'] ?? null;
                $target['current_name'] = $existingMold['name'] ?? null;
                $target['current_table_name'] = $existingMold['table_name'] ?? null;

                $oldFields = $existingMold['fields_arr'] ?? null;
                if ($oldFields === null && isset($existingMold['fields']) && is_string($existingMold['fields'])) {
                    $decoded = json_decode($existingMold['fields'], true);
                    if (is_array($decoded)) {
                        $oldFields = $decoded;
                    }
                }

                if (is_array($oldFields) && is_array($fields)) {
                    $fieldDiff = [
                        'added' => [],
                        'removed' => [],
                        'changed' => [],
                    ];

                    $oldByName = [];
                    foreach ($oldFields as $f) {
                        if (! is_array($f) || empty($f['field'])) {
                            continue;
                        }
                        $oldByName[$f['field']] = $f;
                    }

                    $newByName = [];
                    foreach ($fields as $f) {
                        if (! is_array($f) || empty($f['field'])) {
                            continue;
                        }
                        $newByName[$f['field']] = $f;
                    }

                    foreach ($newByName as $fieldName => $newDef) {
                        if (! isset($oldByName[$fieldName])) {
                            $fieldDiff['added'][] = $fieldName;
                        } else {
                            $oldDef = $oldByName[$fieldName];
                            $oldType = $oldDef['type'] ?? 'string';
                            $newType = $newDef['type'] ?? 'string';
                            $oldLabel = $oldDef['label'] ?? '';
                            $newLabel = $newDef['label'] ?? '';

                            if ($oldType !== $newType || $oldLabel !== $newLabel) {
                                $fieldDiff['changed'][] = [
                                    'field' => $fieldName,
                                    'from' => [
                                        'type' => $oldType,
                                        'label' => $oldLabel,
                                    ],
                                    'to' => [
                                        'type' => $newType,
                                        'label' => $newLabel,
                                    ],
                                ];
                            }
                        }
                    }

                    foreach ($oldByName as $fieldName => $oldDef) {
                        if (! isset($newByName[$fieldName])) {
                            $fieldDiff['removed'][] = $fieldName;
                        }
                    }
                }
            } else {
                $errors[] = [
                    'code' => 'model_not_found',
                    'field' => 'mold_id/model_key',
                    'message' => '根据提供的 mold_id 或 model_key 未找到对应模型',
                ];
            }
        }

        $result = [
            'valid' => empty($errors),
            'mode' => $mode,
            'errors' => $errors,
            'warnings' => $warnings,
        ];

        if (! empty($target)) {
            $result['target'] = $target;
        }

        if ($fieldDiff !== null) {
            $result['field_diff'] = $fieldDiff;
        }

        return ToolResult::text(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
