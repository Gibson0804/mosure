<?php

namespace App\Mcp\Tools;

use App\Repository\ContentRepository;
use App\Services\ContentService;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Content CRUD Tool')]
class ContentCrudTool extends Tool
{
    public ContentService $contentService;

    public function __construct(ContentService $contentService)
    {
        $this->contentService = $contentService;
    }

    public function description(): string
    {
        return '内容 CRUD（增删改查）';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('action')->description('操作类型：list|create|update|delete|get')->required();
        $schema->integer('mold_id')->description('模型 ID')->required();
        $schema->integer('id')->description('内容 ID（用于 get/update/delete）');
        $schema->raw('data', ['type' => 'object', 'description' => '数据（用于 create/update）']);
        $schema->integer('page')->description('页码（list）');
        $schema->integer('page_size')->description('每页条数（list）');
        $schema->raw('filters', ['type' => 'object', 'description' => '筛选条件（list）']);

        return $schema;
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        try {
            $action = $arguments['action'];
            $moldId = $arguments['mold_id'];

            switch ($action) {
                case 'list':
                    $page = $arguments['page'] ?? 1;
                    $pageSize = $arguments['page_size'] ?? 15;
                    $filters = $arguments['filters'] ?? [];
                    $query = ContentRepository::buildContent($moldId);
                    // 简单筛选（可扩展）
                    if (! empty($filters)) {
                        foreach ($filters as $field => $value) {
                            if ($value !== null && $value !== '') {
                                $query->where($field, 'like', "%{$value}%");
                            }
                        }
                    }
                    $total = $query->count();
                    $items = $query->orderBy('id', 'desc')->skip(($page - 1) * $pageSize)->take($pageSize)->get();

                    return ToolResult::text(json_encode([
                        'data' => $items,
                        'meta' => [
                            'total' => $total,
                            'page' => $page,
                            'page_size' => $pageSize,
                            'page_count' => ceil($total / $pageSize),
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                case 'get':
                    $id = $arguments['id'];
                    $detail = $this->contentService->getContentDetail($moldId, $id);

                    return ToolResult::text(json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                case 'create':
                    $data = $arguments['data'];
                    $repo = ContentRepository::buildContent($moldId);
                    // 统一维护创建/更新时间及系统字段（仅在表存在对应字段时补齐）
                    try {
                        $tableName = $repo->getTableName();
                        $now = now()->format('Y-m-d H:i:s');
                        if (is_string($tableName) && $tableName !== '') {
                            // 允许调用方显式覆盖，但如果传了 null/'' 也视为未设置，由 MCP 补默认值
                            if (Schema::hasColumn($tableName, 'created_at') && (! array_key_exists('created_at', $data) || empty($data['created_at']))) {
                                $data['created_at'] = $now;
                            }
                            if (Schema::hasColumn($tableName, 'updated_at') && (! array_key_exists('updated_at', $data) || empty($data['updated_at']))) {
                                $data['updated_at'] = $now;
                            }
                            // MCP 创建：统一标记操作者与内容状态
                            if (Schema::hasColumn($tableName, 'created_by') && (! array_key_exists('created_by', $data) || empty($data['created_by']))) {
                                $data['created_by'] = 'mcp';
                            }
                            if (Schema::hasColumn($tableName, 'updated_by') && (! array_key_exists('updated_by', $data) || empty($data['updated_by']))) {
                                $data['updated_by'] = 'mcp';
                            }
                            if (Schema::hasColumn($tableName, 'content_status') && (! array_key_exists('content_status', $data) || empty($data['content_status']))) {
                                $data['content_status'] = ContentRepository::STATUS_PENDING;
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }

                    Log::info('create data', ['data' => $data]);
                    $id = $repo->insertGetId($data);

                    return ToolResult::text(json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE));

                case 'update':
                    $id = $arguments['id'];
                    $data = $arguments['data'];
                    $repo = ContentRepository::buildContent($moldId);
                    // 统一维护更新时间及更新人（仅在表存在对应字段时补齐）
                    try {
                        $tableName = $repo->getTableName();
                        $now = now()->format('Y-m-d H:i:s');
                        if (is_string($tableName) && $tableName !== '') {
                            if (Schema::hasColumn($tableName, 'updated_at') && (! array_key_exists('updated_at', $data) || empty($data['updated_at']))) {
                                $data['updated_at'] = $now;
                            }
                            if (Schema::hasColumn($tableName, 'updated_by') && (! array_key_exists('updated_by', $data) || empty($data['updated_by']))) {
                                $data['updated_by'] = 'mcp';
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                    $repo->where('id', $id)->update($data);

                    return ToolResult::text(json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE));

                case 'delete':
                    $id = $arguments['id'];
                    $this->contentService->contentDelete($moldId, $id);

                    return ToolResult::text(json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE));

                default:
                    return ToolResult::text(json_encode(['error' => 'Invalid action'], JSON_UNESCAPED_UNICODE));
            }
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
        }
    }
}
