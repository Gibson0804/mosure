<?php

namespace App\Mcp\Tools;

use App\Models\Mold;
use App\Services\TriggerService;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Trigger Tool')]
class TriggerTool extends Tool
{
    public TriggerService $triggerService;

    public function __construct(TriggerService $triggerService)
    {
        $this->triggerService = $triggerService;
    }

    public function description(): string
    {
        return '触发器管理。action 参数说明：list(获取触发器列表)|create(创建触发器)|update(修改触发器)|example(获取触发参数示例)。触发器用于在特定事件发生时自动执行触发函数。';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('action')
            ->description('操作类型：list|create|update|example')
            ->required();
        // list 参数
        $schema->integer('page')->description('页码（list，默认1）');
        $schema->integer('page_size')->description('每页条数（list，默认15）');
        $schema->string('keyword')->description('按名称搜索（list）');
        $schema->raw('enabled', ['type' => 'boolean', 'description' => '是否启用（list筛选）']);
        $schema->string('trigger_type')->description('触发器类型筛选（list）');
        $schema->integer('mold_id')->description('内容模型ID（list筛选/create/update）');
        // create/update 参数
        $schema->integer('id')->description('触发器ID（update 时必填）');
        $schema->string('name')->description('触发器名称（create/update）');
        $schema->raw('events', ['type' => 'array', 'description' => '触发时机数组：before_create, after_create, before_update, after_update, before_delete, after_delete']);
        $schema->integer('action_function_id')->description('触发后执行的函数ID（create/update）');
        $schema->string('remark')->description('备注');
        // example 参数
        $schema->string('event')->description('触发事件（example）：before_create, after_create, before_update, after_update, before_delete, after_delete');

        return $schema;
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        $action = $arguments['action'] ?? 'list';

        return match ($action) {
            'list' => $this->handleList($arguments),
            'create' => $this->handleCreate($arguments),
            'update' => $this->handleUpdate($arguments),
            'example' => $this->handleExample($arguments),
            default => ToolResult::text(json_encode([
                'success' => false,
                'error' => "未知操作类型: {$action}，支持的操作：list|create|update|example",
            ], JSON_UNESCAPED_UNICODE)),
        };
    }

    /**
     * 获取触发器列表
     */
    private function handleList(array $arguments): ToolResult
    {
        try {
            $page = $arguments['page'] ?? 1;
            $pageSize = $arguments['page_size'] ?? 15;
            $filters = [];

            if (isset($arguments['keyword'])) {
                $filters['keyword'] = $arguments['keyword'];
            }
            if (isset($arguments['enabled'])) {
                $filters['enabled'] = $arguments['enabled'];
            }
            if (isset($arguments['trigger_type'])) {
                $filters['trigger_type'] = $arguments['trigger_type'];
            }
            if (isset($arguments['mold_id'])) {
                $filters['mold_id'] = $arguments['mold_id'];
            }

            $result = $this->triggerService->list($page, $pageSize, $filters);

            return ToolResult::text(json_encode([
                'success' => true,
                'data' => $result['data'],
                'meta' => $result['meta'],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 创建触发器
     */
    private function handleCreate(array $arguments): ToolResult
    {
        try {
            $data = [
                'name' => $arguments['name'],
                'enabled' => $arguments['enabled'] ?? true,
                'events' => $arguments['events'],
                'action_function_id' => $arguments['action_function_id'],
            ];

            if (isset($arguments['mold_id'])) {
                $data['mold_id'] = $arguments['mold_id'];
            }
            if (isset($arguments['remark'])) {
                $data['remark'] = $arguments['remark'];
            }
            if (isset($arguments['events'])) {
                $data['events'] = is_array($arguments['events'])
                    ? $arguments['events']
                    : [$arguments['events']];
            }

            $trigger = $this->triggerService->create($data);

            return ToolResult::text(json_encode([
                'success' => true,
                'data' => $trigger,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 修改触发器
     */
    private function handleUpdate(array $arguments): ToolResult
    {
        try {
            $id = $arguments['id'];
            $data = [];

            if (isset($arguments['name'])) {
                $data['name'] = $arguments['name'];
            }
            if (isset($arguments['enabled'])) {
                $data['enabled'] = $arguments['enabled'];
            }
            if (isset($arguments['events'])) {
                $data['events'] = is_array($arguments['events'])
                    ? $arguments['events']
                    : [$arguments['events']];
            }
            if (isset($arguments['mold_id'])) {
                $data['mold_id'] = $arguments['mold_id'];
            }
            if (isset($arguments['action_function_id'])) {
                $data['action_function_id'] = $arguments['action_function_id'];
            }
            if (isset($arguments['remark'])) {
                $data['remark'] = $arguments['remark'];
            }

            $trigger = $this->triggerService->update($id, $data);

            return ToolResult::text(json_encode([
                'success' => true,
                'data' => $trigger,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 获取触发参数示例
     */
    private function handleExample(array $arguments): ToolResult
    {
        try {
            $moldId = (int) ($arguments['mold_id'] ?? 0);
            $event = $arguments['event'] ?? '';

            if ($moldId <= 0) {
                return ToolResult::text(json_encode([
                    'success' => false,
                    'error' => '模型ID必须大于0',
                ], JSON_UNESCAPED_UNICODE));
            }

            if (empty($event)) {
                return ToolResult::text(json_encode([
                    'success' => false,
                    'error' => '触发事件不能为空',
                ], JSON_UNESCAPED_UNICODE));
            }

            // 获取模型字段
            $fields = [];
            $mold = Mold::find($moldId);
            if ($mold && ! empty($mold->fields)) {
                $fields = array_map(function ($field) {
                    return $field['field'] ?? '';
                }, $mold->fields);
                $fields = array_filter($fields);
            }

            // 生成字段示例数据
            $dataFields = [];
            foreach ($fields as $field) {
                $dataFields[$field] = 'xxx';
            }

            // 根据触发事件生成不同的示例
            $example = [
                'mold_id' => $moldId,
                'id' => rand(1, 1000),
            ];

            switch ($event) {
                case 'before_create':
                    $example['data'] = $dataFields;
                    $example['before'] = [];
                    $example['after'] = [];
                    break;
                case 'after_create':
                    $example['data'] = $dataFields;
                    $example['before'] = [];
                    $example['after'] = $dataFields;
                    break;
                case 'before_update':
                    $example['data'] = $dataFields;
                    $example['before'] = $dataFields;
                    $example['after'] = [];
                    break;
                case 'after_update':
                    $example['data'] = [];
                    $example['before'] = $dataFields;
                    $example['after'] = $dataFields;
                    break;
                case 'before_delete':
                case 'after_delete':
                    $example['data'] = [];
                    $example['before'] = $dataFields;
                    $example['after'] = [];
                    break;
                default:
                    return ToolResult::text(json_encode([
                        'success' => false,
                        'error' => '无效的触发事件',
                    ], JSON_UNESCAPED_UNICODE));
            }

            return ToolResult::text(json_encode([
                'success' => true,
                'data' => $example,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }
}
