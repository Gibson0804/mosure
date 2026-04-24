<?php

namespace App\Mcp\Tools;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Project;
use Generator;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Knowledge Base Project Tool')]
class KnowledgeBaseProjectTool extends Tool
{
    public function description(): string
    {
        return '项目知识库文档管理。仅允许操作当前 MCP 令牌所属项目目录下的文档。action: list_categories|list_docs|get_doc|create_doc|update_doc|delete_doc';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('action')->description('操作类型：list_categories|list_docs|get_doc|create_doc|update_doc|delete_doc')->required();
        $schema->integer('id')->description('文档 ID（get_doc/update_doc/delete_doc）');
        $schema->integer('category_id')->description('分类 ID（list_docs/create_doc/update_doc 可选）');
        $schema->string('title')->description('文档标题（create_doc/update_doc）');
        $schema->string('summary')->description('文档摘要（create_doc/update_doc）');
        $schema->string('content')->description('文档 Markdown 内容（create_doc/update_doc）');
        $schema->string('content_html')->description('文档 HTML 内容（create_doc/update_doc）');
        $schema->string('status')->description('文档状态：private|public（create_doc/update_doc）');
        $schema->string('keyword')->description('关键词（list_docs 可选）');
        $schema->integer('page')->description('页码（list_docs 可选，默认 1）');
        $schema->integer('page_size')->description('每页数量（list_docs 可选，默认 20，最大 100）');
        $schema->raw('tags', ['type' => 'array', 'description' => '标签数组（create_doc/update_doc 可选）']);

        return $schema;
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        try {
            $ctx = $this->resolveContext();
            $action = (string) ($arguments['action'] ?? '');

            return match ($action) {
                'list_categories' => $this->handleListCategories($ctx),
                'list_docs' => $this->handleListDocs($ctx, $arguments),
                'get_doc' => $this->handleGetDoc($ctx, $arguments),
                'create_doc' => $this->handleCreateDoc($ctx, $arguments),
                'update_doc' => $this->handleUpdateDoc($ctx, $arguments),
                'delete_doc' => $this->handleDeleteDoc($ctx, $arguments),
                default => ToolResult::text(json_encode([
                    'success' => false,
                    'error' => '未知 action，支持：list_categories|list_docs|get_doc|create_doc|update_doc|delete_doc',
                ], JSON_UNESCAPED_UNICODE)),
            };
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    private function handleListCategories(array $ctx): ToolResult
    {
        $categories = KbCategory::query()
            ->where('user_id', $ctx['user_id'])
            ->whereIn('id', $ctx['category_ids'])
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'title', 'slug', 'sort_order', 'created_at', 'updated_at']);

        return ToolResult::text(json_encode([
            'success' => true,
            'project_prefix' => $ctx['project_prefix'],
            'root_category' => $ctx['root_category']->only(['id', 'title', 'slug']),
            'categories' => $categories,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function handleListDocs(array $ctx, array $arguments): ToolResult
    {
        $page = max(1, (int) ($arguments['page'] ?? 1));
        $pageSize = min(100, max(1, (int) ($arguments['page_size'] ?? 20)));
        $keyword = trim((string) ($arguments['keyword'] ?? ''));
        $categoryId = isset($arguments['category_id']) ? (int) $arguments['category_id'] : null;

        $allowedCategoryIds = $ctx['category_ids'];
        if ($categoryId !== null) {
            if (! in_array($categoryId, $allowedCategoryIds, true)) {
                return ToolResult::text(json_encode([
                    'success' => false,
                    'error' => 'category_id 不属于当前项目知识库目录',
                ], JSON_UNESCAPED_UNICODE));
            }
            $allowedCategoryIds = $this->getDescendantCategoryIds($ctx['user_id'], $categoryId);
        }

        $query = KbArticle::query()
            ->where('user_id', $ctx['user_id'])
            ->whereIn('category_id', $allowedCategoryIds);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('summary', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%");
            });
        }

        $total = (clone $query)->count();
        $items = $query->orderByDesc('updated_at')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get(['id', 'category_id', 'title', 'summary', 'slug', 'status', 'tags', 'created_at', 'updated_at']);

        return ToolResult::text(json_encode([
            'success' => true,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function handleGetDoc(array $ctx, array $arguments): ToolResult
    {
        $id = (int) ($arguments['id'] ?? 0);
        if ($id <= 0) {
            return ToolResult::text(json_encode(['success' => false, 'error' => '缺少有效的 id'], JSON_UNESCAPED_UNICODE));
        }

        $article = KbArticle::query()
            ->where('id', $id)
            ->where('user_id', $ctx['user_id'])
            ->first();

        if (! $article || ! in_array((int) $article->category_id, $ctx['category_ids'], true)) {
            return ToolResult::text(json_encode(['success' => false, 'error' => '文档不存在或无访问权限'], JSON_UNESCAPED_UNICODE));
        }

        return ToolResult::text(json_encode([
            'success' => true,
            'item' => $article,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function handleCreateDoc(array $ctx, array $arguments): ToolResult
    {
        $title = trim((string) ($arguments['title'] ?? ''));
        if ($title === '') {
            return ToolResult::text(json_encode(['success' => false, 'error' => 'title 不能为空'], JSON_UNESCAPED_UNICODE));
        }

        $categoryId = isset($arguments['category_id']) ? (int) $arguments['category_id'] : (int) $ctx['root_category']->id;
        if (! in_array($categoryId, $ctx['category_ids'], true)) {
            return ToolResult::text(json_encode(['success' => false, 'error' => 'category_id 不属于当前项目知识库目录'], JSON_UNESCAPED_UNICODE));
        }

        $tags = $arguments['tags'] ?? [];
        if (! is_array($tags)) {
            $tags = [];
        }

        $article = KbArticle::query()->create([
            'user_id' => $ctx['user_id'],
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => $this->generateSlug($title),
            'summary' => (string) ($arguments['summary'] ?? ''),
            'content' => (string) ($arguments['content'] ?? ''),
            'content_html' => (string) ($arguments['content_html'] ?? ''),
            'tags' => array_values(array_map('strval', $tags)),
            'status' => in_array(($arguments['status'] ?? 'private'), ['private', 'public'], true) ? (string) $arguments['status'] : 'private',
        ]);

        return ToolResult::text(json_encode([
            'success' => true,
            'id' => $article->id,
            'item' => $article,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function handleUpdateDoc(array $ctx, array $arguments): ToolResult
    {
        $id = (int) ($arguments['id'] ?? 0);
        if ($id <= 0) {
            return ToolResult::text(json_encode(['success' => false, 'error' => '缺少有效的 id'], JSON_UNESCAPED_UNICODE));
        }

        $article = KbArticle::query()
            ->where('id', $id)
            ->where('user_id', $ctx['user_id'])
            ->first();
        if (! $article || ! in_array((int) $article->category_id, $ctx['category_ids'], true)) {
            return ToolResult::text(json_encode(['success' => false, 'error' => '文档不存在或无访问权限'], JSON_UNESCAPED_UNICODE));
        }

        $payload = [];
        foreach (['title', 'summary', 'content', 'content_html'] as $field) {
            if (array_key_exists($field, $arguments)) {
                $payload[$field] = (string) $arguments[$field];
            }
        }
        if (array_key_exists('status', $arguments)) {
            $status = (string) $arguments['status'];
            if (in_array($status, ['private', 'public'], true)) {
                $payload['status'] = $status;
            }
        }
        if (array_key_exists('category_id', $arguments)) {
            $categoryId = (int) $arguments['category_id'];
            if (! in_array($categoryId, $ctx['category_ids'], true)) {
                return ToolResult::text(json_encode(['success' => false, 'error' => 'category_id 不属于当前项目知识库目录'], JSON_UNESCAPED_UNICODE));
            }
            $payload['category_id'] = $categoryId;
        }
        if (array_key_exists('tags', $arguments)) {
            $tags = is_array($arguments['tags']) ? $arguments['tags'] : [];
            $payload['tags'] = array_values(array_map('strval', $tags));
        }
        if (isset($payload['title']) && trim($payload['title']) !== '') {
            $payload['slug'] = $this->generateSlug($payload['title'], $article->id);
        }

        if (! empty($payload)) {
            $article->update($payload);
            $article->refresh();
        }

        return ToolResult::text(json_encode(['success' => true, 'item' => $article], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function handleDeleteDoc(array $ctx, array $arguments): ToolResult
    {
        $id = (int) ($arguments['id'] ?? 0);
        if ($id <= 0) {
            return ToolResult::text(json_encode(['success' => false, 'error' => '缺少有效的 id'], JSON_UNESCAPED_UNICODE));
        }

        $article = KbArticle::query()
            ->where('id', $id)
            ->where('user_id', $ctx['user_id'])
            ->first();
        if (! $article || ! in_array((int) $article->category_id, $ctx['category_ids'], true)) {
            return ToolResult::text(json_encode(['success' => false, 'error' => '文档不存在或无访问权限'], JSON_UNESCAPED_UNICODE));
        }

        $article->delete();

        return ToolResult::text(json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE));
    }

    private function resolveContext(): array
    {
        $prefix = (string) session('current_project_prefix', '');
        if ($prefix === '') {
            throw new \RuntimeException('缺少当前项目上下文');
        }

        $project = Project::query()->where('prefix', $prefix)->first();
        if (! $project) {
            throw new \RuntimeException('当前项目不存在');
        }

        $userId = 0;
        $rootSlug = 'project-'.$prefix;
        $root = KbCategory::query()
            ->where('user_id', $userId)
            ->where('slug', $rootSlug)
            ->first();

        if (! $root) {
            $root = KbCategory::query()->create([
                'user_id' => $userId,
                'parent_id' => null,
                'title' => $project->name,
                'slug' => $rootSlug,
                'sort_order' => 0,
            ]);
        }

        $categoryIds = $this->getDescendantCategoryIds($userId, (int) $root->id);

        return [
            'project_prefix' => $prefix,
            'project' => $project,
            'user_id' => $userId,
            'root_category' => $root,
            'category_ids' => $categoryIds,
        ];
    }

    private function getDescendantCategoryIds(int $userId, int $rootId): array
    {
        $rows = KbCategory::query()
            ->where('user_id', $userId)
            ->get(['id', 'parent_id'])
            ->toArray();
        $ids = [$rootId];
        $queue = [$rootId];

        while ($queue !== []) {
            $parent = array_shift($queue);
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $parentId = isset($row['parent_id']) ? (int) $row['parent_id'] : null;
                if ($parentId === $parent && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                    $queue[] = $id;
                }
            }
        }

        return $ids;
    }

    private function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = Str::lower(Str::random(8));
        }
        $slug = $base;
        $counter = 1;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $q = KbArticle::query()->where('slug', $slug);
        if ($ignoreId !== null) {
            $q->where('id', '!=', $ignoreId);
        }

        return $q->exists();
    }
}
