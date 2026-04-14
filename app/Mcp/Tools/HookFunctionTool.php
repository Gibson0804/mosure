<?php

namespace App\Mcp\Tools;

use App\Models\ProjectFunction;
use App\Services\CloudFunctionService;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Hook Function Tool')]
class HookFunctionTool extends Tool
{
    public CloudFunctionService $cloudFunctionService;

    public function __construct(CloudFunctionService $cloudFunctionService)
    {
        $this->cloudFunctionService = $cloudFunctionService;
    }

    public function description(): string
    {
        return 'Hook触发函数管理。action 参数说明：list(获取函数列表)|create(创建函数)|update(修改函数代码)。代码在PHP闭包环境中执行，可直接使用：$payload(事件数据)、$env(环境变量)、$ctx(上下文)、$event(事件类型)、$prefix(项目前缀)、$Http(HTTP客户端)、$db(数据库客户端)。必须return结果数组。$payload格式：{"mold_id":内容模型ID,"id":内容ID,"data":传入参数,"before":修改前内容,"after":修改后内容}';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('action')
            ->description('操作类型：list|create|update')
            ->required();
        // create/update 参数
        $schema->string('slug')
            ->description('函数 slug（create/update 时必填）');
        $schema->string('name')
            ->description('函数名称（create 时必填）');
        $schema->string('code')
            ->description('函数代码（php 运行时，create/update 时使用）');
        $schema->integer('timeout_ms')
            ->description('超时时间（毫秒，create 时使用）');
        $schema->integer('max_mem_mb')
            ->description('最大内存（MB，create 时使用）');
        $schema->integer('rate_limit')
            ->description('速率限制（每分钟，create 时使用）');

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
     * 获取所有 Hook 触发函数
     */
    private function handleList(): ToolResult
    {
        try {
            $functions = $this->cloudFunctionService->getAllHookFunctions();

            return ToolResult::text(json_encode([
                'success' => true,
                'data' => $functions,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 创建 Hook 触发函数
     */
    private function handleCreate(array $arguments): ToolResult
    {
        try {
            $createData = [
                'name' => $arguments['name'] ?? '',
                'type' => 'hook',
                'slug' => $arguments['slug'] ?? '',
                'code' => $arguments['code'] ?? '',
                'timeout_ms' => $arguments['timeout_ms'] ?? null,
                'max_mem_mb' => $arguments['max_mem_mb'] ?? null,
                'rate_limit' => $arguments['rate_limit'] ?? null,
            ];

            $fn = $this->cloudFunctionService->create($createData);

            return ToolResult::text(json_encode([
                'success' => true,
                'data' => $fn,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 更新 Hook 触发函数代码
     */
    private function handleUpdate(array $arguments): ToolResult
    {
        try {
            $slug = $arguments['slug'] ?? '';
            $code = $arguments['code'] ?? '';

            if (empty($slug)) {
                return ToolResult::text(json_encode([
                    'success' => false,
                    'error' => 'slug 参数必填',
                ], JSON_UNESCAPED_UNICODE));
            }

            if (empty($code)) {
                return ToolResult::text(json_encode([
                    'success' => false,
                    'error' => 'code 参数必填',
                ], JSON_UNESCAPED_UNICODE));
            }

            $fn = ProjectFunction::where('slug', $slug)->first();
            if (! $fn) {
                return ToolResult::text(json_encode([
                    'success' => false,
                    'error' => '函数不存在',
                ], JSON_UNESCAPED_UNICODE));
            }

            $fn->code = $code;
            $fn->save();

            return ToolResult::text(json_encode([
                'success' => true,
                'message' => '代码已更新',
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }
}
