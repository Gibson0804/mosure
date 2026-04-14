<?php

namespace App\Mcp\Tools;

use App\Models\Mold;
use App\Services\SubjectService;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Content Single Page Tool')]
class ContentSinglePageTool extends Tool
{
    public function __construct(
        private SubjectService $subjectService
    ) {}

    public function description(): string
    {
        return '内容单页的获取与修改。适用于 mold_type 为 single 的模型（如关于我们、联系方式等），每个模型只有一条记录，通过 mold_id 指定模型后执行 get_page 或 update_page。';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('action')->description('操作类型：get_page 获取单页内容，update_page 更新单页内容')->required();
        $schema->integer('mold_id')->description('内容单页模型 ID（mold_type 为 single 的模型）')->required();
        $schema->raw('data', ['type' => 'object', 'description' => '要更新的字段键值对（仅 update_page 时必填），对应 subject_content']);

        return $schema;
    }

    public function handle(array $arguments): ToolResult
    {
        try {
            $action = $arguments['action'];
            $moldId = (int) $arguments['mold_id'];

            $mold = Mold::query()->where('id', $moldId)->first();
            if (! $mold) {
                return ToolResult::text(json_encode([
                    'success' => false,
                    'error' => '模型不存在',
                ], JSON_UNESCAPED_UNICODE));
            }
            if ($mold->mold_type !== 'single') {
                return ToolResult::text(json_encode([
                    'success' => false,
                    'error' => '该模型不是内容单页类型（mold_type 需为 single）',
                ], JSON_UNESCAPED_UNICODE));
            }

            $tableName = $mold->table_name;
            if (empty($tableName)) {
                return ToolResult::text(json_encode([
                    'success' => false,
                    'error' => '模型缺少 table_name',
                ], JSON_UNESCAPED_UNICODE));
            }

            switch ($action) {
                case 'get_page':
                    $result = $this->subjectService->getSubjectByTable($tableName);
                    if (empty($result)) {
                        return ToolResult::text(json_encode([
                            'success' => true,
                            'data' => [],
                            'message' => '单页不存在或无内容',
                        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    }

                    return ToolResult::text(json_encode([
                        'success' => true,
                        'data' => $result['subject_content'] ?? [],
                        'meta' => [
                            'id' => $result['id'],
                            'name' => $result['name'],
                            'table_name' => $result['table_name'],
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                case 'update_page':
                    $data = $arguments['data'] ?? null;
                    if (! is_array($data)) {
                        return ToolResult::text(json_encode([
                            'success' => false,
                            'error' => 'update_page 需要提供 data 对象（字段键值对）',
                        ], JSON_UNESCAPED_UNICODE));
                    }
                    $ok = $this->subjectService->updateSubjectByTable($tableName, $data);

                    return ToolResult::text(json_encode([
                        'success' => $ok,
                        'message' => $ok ? '单页内容已更新' : '更新失败',
                    ], JSON_UNESCAPED_UNICODE));

                default:
                    return ToolResult::text(json_encode([
                        'success' => false,
                        'error' => '无效的 action，仅支持 get_page 或 update_page',
                    ], JSON_UNESCAPED_UNICODE));
            }
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }
}
