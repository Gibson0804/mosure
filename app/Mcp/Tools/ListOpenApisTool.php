<?php

namespace App\Mcp\Tools;

use App\Services\ApiDocsService;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('List Open APIs Tool')]
class ListOpenApisTool extends Tool
{
    public ApiDocsService $apiDocsService;

    public function __construct(ApiDocsService $apiDocsService)
    {
        $this->apiDocsService = $apiDocsService;
    }

    public function description(): string
    {
        return '列出当前项目对外开放的 API 接口（基于 /open 路由），支持按类型、方法、关键字、接口操作过滤';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('kind')->description('接口类型：content|page|media|function|auth|all，默认 all');
        $schema->string('method')->description('HTTP 方法：GET|POST|PUT|DELETE，不区分大小写，可选');
        $schema->string('operation')->description('接口操作：list|detail|add|edit|delete，不区分大小写，可选');
        $schema->string('keyword')->description('按接口名称、描述、路径进行模糊搜索，可选');
        $schema->integer('limit')->description('返回条数，默认 50');
        $schema->integer('offset')->description('偏移量，默认 0');

        return $schema;
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        try {
            $filters = [
                'kind' => $arguments['kind'] ?? 'all',
                'method' => $arguments['method'] ?? null,
                'keyword' => $arguments['keyword'] ?? null,
                'operation' => $arguments['operation'] ?? null,
                'limit' => $arguments['limit'] ?? null,
                'offset' => $arguments['offset'] ?? null,
            ];

            $result = $this->apiDocsService->listApis($filters);
            $result['success'] = true;

            return ToolResult::text(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }
}
