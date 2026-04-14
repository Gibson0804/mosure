<?php

namespace App\Mcp\Tools;

use App\Services\CloudCronService;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Schedule Tool')]
class ScheduleTool extends Tool
{
    public CloudCronService $cloudCronService;

    public function __construct(CloudCronService $cloudCronService)
    {
        $this->cloudCronService = $cloudCronService;
    }

    public function description(): string
    {
        return '定时任务管理。action 参数说明：list(获取任务列表)|create(创建任务)|update(修改任务)。定时任务用于按计划自动执行触发函数，新建时默认关闭需手动开启。';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('action')
            ->description('操作类型：list|create|update')
            ->required();
        // create/update 参数
        $schema->integer('id')->description('定时任务ID（update 时必填）');
        $schema->string('name')->description('任务名称（create 时必填）');
        $schema->integer('function_id')->description('触发函数ID（create 时必填）');
        $schema->string('schedule_type')->description('调度类型：once(单次)|cron(周期)');
        $schema->string('run_at')->description('运行时间（schedule_type为once时使用）');
        $schema->string('cron_expr')->description('Cron表达式（schedule_type为cron时使用，如：0 0 * * *）');
        $schema->string('timezone')->description('时区（默认系统时区）');
        $schema->raw('payload', ['type' => 'object', 'description' => '传递给函数的参数']);
        $schema->integer('timeout_ms')->description('超时时间（毫秒）');
        $schema->integer('max_mem_mb')->description('最大内存（MB）');
        $schema->string('remark')->description('备注');

        return $schema;
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        $action = $arguments['action'] ?? 'list';

        return match ($action) {
            'list' => $this->handleList(),
            'create' => $this->handleCreate($arguments),
            'update' => $this->handleUpdate($arguments),
            default => ToolResult::text(json_encode([
                'success' => false,
                'error' => "未知操作类型: {$action}，支持的操作：list|create|update",
            ], JSON_UNESCAPED_UNICODE)),
        };
    }

    /**
     * 获取所有定时任务
     */
    private function handleList(): ToolResult
    {
        try {
            $result = $this->cloudCronService->getAll();

            return ToolResult::text(json_encode([
                'success' => true,
                'data' => $result['data'],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 创建定时任务
     */
    private function handleCreate(array $arguments): ToolResult
    {
        try {
            $data = [
                'name' => $arguments['name'],
                'enabled' => false, // 默认关闭状态
                'function_id' => $arguments['function_id'],
                'schedule_type' => $arguments['schedule_type'],
            ];

            if (isset($arguments['run_at'])) {
                $data['run_at'] = $arguments['run_at'];
            }
            if (isset($arguments['cron_expr'])) {
                $data['cron_expr'] = $arguments['cron_expr'];
            }
            if (isset($arguments['timezone'])) {
                $data['timezone'] = $arguments['timezone'];
            }
            if (isset($arguments['payload'])) {
                $data['payload'] = $arguments['payload'];
            }
            if (isset($arguments['timeout_ms'])) {
                $data['timeout_ms'] = $arguments['timeout_ms'];
            }
            if (isset($arguments['max_mem_mb'])) {
                $data['max_mem_mb'] = $arguments['max_mem_mb'];
            }
            if (isset($arguments['remark'])) {
                $data['remark'] = $arguments['remark'];
            }

            $schedule = $this->cloudCronService->create($data);

            return ToolResult::text(json_encode([
                'success' => true,
                'data' => $schedule,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 修改定时任务
     */
    private function handleUpdate(array $arguments): ToolResult
    {
        try {
            $id = $arguments['id'];
            $data = [];

            if (isset($arguments['schedule_type'])) {
                $data['schedule_type'] = $arguments['schedule_type'];
            }
            if (isset($arguments['run_at'])) {
                $data['run_at'] = $arguments['run_at'];
            }
            if (isset($arguments['cron_expr'])) {
                $data['cron_expr'] = $arguments['cron_expr'];
            }
            if (isset($arguments['timezone'])) {
                $data['timezone'] = $arguments['timezone'];
            }
            if (isset($arguments['payload'])) {
                $data['payload'] = $arguments['payload'];
            }
            if (isset($arguments['timeout_ms'])) {
                $data['timeout_ms'] = $arguments['timeout_ms'];
            }
            if (isset($arguments['max_mem_mb'])) {
                $data['max_mem_mb'] = $arguments['max_mem_mb'];
            }
            if (isset($arguments['remark'])) {
                $data['remark'] = $arguments['remark'];
            }

            $schedule = $this->cloudCronService->update($id, $data);

            return ToolResult::text(json_encode([
                'success' => true,
                'data' => $schedule,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }
}
