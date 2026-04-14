<?php

namespace App\Mcp\Tools;

use App\Models\ApiKey;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Get API Env Tool')]
class GetApiEnvTool extends Tool
{
    public function description(): string
    {
        return '获取当前项目的 API 环境配置（项目前缀、开放 API 基础地址、可用 API 密钥）';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        // 无需输入参数
        return $schema;
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        $prefix = session('current_project_prefix', '');
        if (! $prefix) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => '当前会话未选择项目，无法获取 API 环境信息',
            ], JSON_UNESCAPED_UNICODE));
        }

        $hostBase = rtrim(config('app.url') ?: url('/'), '/');

        $keys = ApiKey::query()
            ->where('is_active', true)
            ->orderBy('created_at', 'asc')
            ->get();

        $available = [];
        foreach ($keys as $keyModel) {
            if (method_exists($keyModel, 'isExpired') && $keyModel->isExpired()) {
                continue;
            }

            $available[] = [
                'id' => $keyModel->id,
                'name' => $keyModel->name,
                'key' => $keyModel->key,
                'description' => $keyModel->description,
                'rate_limit' => $keyModel->rate_limit,
                'expires_at' => $keyModel->expires_at ? $keyModel->expires_at->toDateTimeString() : null,
                'is_active' => (bool) $keyModel->is_active,
                'allowed_ips' => $keyModel->allowed_ips,
            ];
        }

        $payload = [
            'api_keys' => $available,
            'app_url' => $hostBase,
        ];

        return ToolResult::text(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
