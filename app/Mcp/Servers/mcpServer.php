<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\ModelGeneratePrompt;
use App\Mcp\Resources\ModelJsonSpecResource;
use App\Mcp\Resources\OpenApiDocResource;
use App\Mcp\Tools\ContentCrudTool;
use App\Mcp\Tools\ContentSinglePageTool;
use App\Mcp\Tools\GetApiEnvTool;
use App\Mcp\Tools\HookFunctionTool;
use App\Mcp\Tools\ListOpenApisTool;
use App\Mcp\Tools\MediaManageTool;
use App\Mcp\Tools\ModelTool;
use App\Mcp\Tools\ScheduleTool;
use App\Mcp\Tools\TriggerTool;
use App\Mcp\Tools\WebFunctionTool;
use Laravel\Mcp\Server;

class mcpServer extends Server
{
    public string $serverName = 'Mosure / 模枢 MCP Server';

    public string $serverVersion = '0.0.1';

    public string $instructions = 'Mosure / 模枢 MCP 服务';

    public array $supportedProtocolVersion = [
        '2025-11-25',
        '2025-06-18',
        '2025-03-26',
        '2024-11-05',
        '2024-10-01',
        '2024-08-01',
    ];

    public array $tools = [
        // 内容模型相关
        ModelTool::class,
        // 内容相关
        ContentCrudTool::class,
        ContentSinglePageTool::class,
        MediaManageTool::class,
        // 云函数相关
        WebFunctionTool::class,
        HookFunctionTool::class,
        // 触发器相关
        TriggerTool::class,
        // 定时任务相关
        ScheduleTool::class,
        // API相关
        GetApiEnvTool::class,
        ListOpenApisTool::class,
    ];

    public array $resources = [
        ModelJsonSpecResource::class,
        OpenApiDocResource::class,
    ];

    public array $prompts = [
        ModelGeneratePrompt::class,
    ];
}
