<?php

namespace App\Services;

use App\Ai\Attributes\AiTool;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Project;
use Illuminate\Support\Str;

class AgentKnowledgeBaseToolService
{
    private const USER_ID = 0;

    private const DEFAULT_CATEGORY_TITLE = '默认';

    private const DEFAULT_CATEGORY_SLUG = 'default';

    #[AiTool(
        name: 'kb_global_list_docs',
        description: '查询全部知识库文档列表。秘书使用；可看到默认目录和项目目录文档。',
        params: [
            'keyword' => ['type' => 'string', 'required' => false, 'desc' => '标题、摘要、正文关键词'],
            'page' => ['type' => 'integer', 'required' => false, 'desc' => '页码，默认1'],
            'pageSize' => ['type' => 'integer', 'required' => false, 'desc' => '每页数量，默认10，最大50'],
        ]
    )]
    public function listGlobalDocs(?string $keyword = null, int $page = 1, int $pageSize = 10): array
    {
        return $this->listDocs($this->globalScope(), $keyword, $page, $pageSize);
    }

    #[AiTool(
        name: 'kb_global_get_doc',
        description: '读取任意知识库文档详情。秘书使用；id 和 title 二选一。',
        params: [
            'id' => ['type' => 'integer', 'required' => false, 'desc' => '文档ID'],
            'title' => ['type' => 'string', 'required' => false, 'desc' => '文档标题，可模糊匹配'],
        ]
    )]
    public function getGlobalDoc(?int $id = null, ?string $title = null): array
    {
        return $this->getDoc($this->globalScope(), $id, $title);
    }

    #[AiTool(
        name: 'kb_global_create_doc',
        description: '在知识库中新建文档。秘书使用；可用 categoryId 或 categoryPath 指定目录，未指定则创建到“默认”目录。',
        params: [
            'title' => ['type' => 'string', 'required' => true, 'desc' => '文档标题'],
            'content' => ['type' => 'string', 'required' => false, 'desc' => 'Markdown正文'],
            'summary' => ['type' => 'string', 'required' => false, 'desc' => '摘要'],
            'tags' => ['type' => 'array', 'required' => false, 'desc' => '标签数组'],
            'status' => ['type' => 'string', 'required' => false, 'desc' => 'private 或 public，默认 private'],
            'categoryId' => ['type' => 'integer', 'required' => false, 'desc' => '目标目录ID，不传则使用“默认”目录'],
            'categoryPath' => ['type' => 'string', 'required' => false, 'desc' => '目标目录路径，如 /电子商务；categoryId 优先'],
        ]
    )]
    public function createGlobalDoc(string $title, ?string $content = '', ?string $summary = '', ?array $tags = null, string $status = 'private', ?int $categoryId = null, ?string $categoryPath = null): array
    {
        $scope = $this->globalScope();
        $category = $this->resolveTargetCategory($scope, $categoryId, $categoryPath);

        return $this->createDoc($scope, (int) $category->id, $title, $content ?? '', $summary ?? '', $tags ?? [], $status);
    }

    #[AiTool(
        name: 'kb_global_update_doc',
        description: '修改任意知识库文档信息。秘书使用；id 和 title 二选一；只修改传入字段，不会删除文档。',
        params: [
            'id' => ['type' => 'integer', 'required' => false, 'desc' => '文档ID'],
            'title' => ['type' => 'string', 'required' => false, 'desc' => '用于定位文档的标题，可模糊匹配'],
            'newTitle' => ['type' => 'string', 'required' => false, 'desc' => '新标题'],
            'content' => ['type' => 'string', 'required' => false, 'desc' => '新的完整 Markdown 正文'],
            'summary' => ['type' => 'string', 'required' => false, 'desc' => '新摘要'],
            'tags' => ['type' => 'array', 'required' => false, 'desc' => '新标签数组'],
            'status' => ['type' => 'string', 'required' => false, 'desc' => 'private 或 public'],
        ]
    )]
    public function updateGlobalDoc(?int $id = null, ?string $title = null, ?string $newTitle = null, ?string $content = null, ?string $summary = null, ?array $tags = null, ?string $status = null): array
    {
        return $this->updateDoc($this->globalScope(), $id, $title, $newTitle, $content, $summary, $tags ?? [], $status);
    }

    #[AiTool(
        name: 'kb_global_modify_doc_content',
        description: '通用修改知识库文档正文。秘书使用；支持完整改写、追加内容、按旧文本替换。id 和 title 二选一。',
        params: [
            'id' => ['type' => 'integer', 'required' => false, 'desc' => '文档ID'],
            'title' => ['type' => 'string', 'required' => false, 'desc' => '用于定位文档的标题，可模糊匹配'],
            'editInstruction' => ['type' => 'string', 'required' => false, 'desc' => '用户的修改要求说明'],
            'updatedContent' => ['type' => 'string', 'required' => false, 'desc' => '修改后的完整 Markdown 正文；适合复杂改写'],
            'appendContent' => ['type' => 'string', 'required' => false, 'desc' => '要追加到文档末尾的内容'],
            'oldText' => ['type' => 'string', 'required' => false, 'desc' => '局部替换时要被替换的原文'],
            'newText' => ['type' => 'string', 'required' => false, 'desc' => '局部替换时替换后的新文本'],
        ]
    )]
    public function modifyGlobalDocContent(?int $id = null, ?string $title = null, ?string $editInstruction = null, ?string $updatedContent = null, ?string $appendContent = null, ?string $oldText = null, ?string $newText = null): array
    {
        return $this->modifyDocContent($this->globalScope(), $id, $title, $editInstruction, $updatedContent, $appendContent, $oldText, $newText);
    }

    #[AiTool(
        name: 'kb_global_append_doc',
        description: '向任意知识库文档末尾追加记录。秘书使用；id 和 title 二选一；文档不存在时可设置 createIfMissing=true 自动创建到“默认”目录。',
        params: [
            'id' => ['type' => 'integer', 'required' => false, 'desc' => '文档ID'],
            'title' => ['type' => 'string', 'required' => false, 'desc' => '文档标题，可模糊匹配'],
            'appendContent' => ['type' => 'string', 'required' => true, 'desc' => '要追加的 Markdown 内容'],
            'createIfMissing' => ['type' => 'boolean', 'required' => false, 'desc' => '找不到文档时是否按 title 新建，默认 false'],
        ]
    )]
    public function appendGlobalDoc(?int $id = null, ?string $title = null, string $appendContent = '', bool $createIfMissing = false): array
    {
        $scope = $this->globalScope();

        return $this->appendDoc($scope, (int) $scope['default_category']->id, $id, $title, $appendContent, $createIfMissing);
    }

    #[AiTool(
        name: 'kb_global_create_category',
        description: '新建知识库目录。秘书使用；可在根目录或指定父目录下创建目录，不会删除或覆盖已有目录。',
        params: [
            'title' => ['type' => 'string', 'required' => true, 'desc' => '目录名称'],
            'parentId' => ['type' => 'integer', 'required' => false, 'desc' => '父目录ID，不传则创建在根目录'],
            'parentPath' => ['type' => 'string', 'required' => false, 'desc' => '父目录路径，如 /默认/子目录；parentId 优先'],
            'sortOrder' => ['type' => 'integer', 'required' => false, 'desc' => '排序值，默认0'],
        ]
    )]
    public function createGlobalCategory(string $title, ?int $parentId = null, ?string $parentPath = null, int $sortOrder = 0): array
    {
        $title = trim($title);
        if ($title === '') {
            return ['success' => false, 'error' => 'title 不能为空'];
        }

        $parent = $this->resolveCategoryParent($parentId, $parentPath);
        if ($parent instanceof KbCategory && str_starts_with((string) $parent->slug, 'project-')) {
            return ['success' => false, 'error' => '秘书的新建目录工具不能在项目知识库根目录下创建目录'];
        }

        $existing = KbCategory::query()
            ->where('user_id', self::USER_ID)
            ->where('parent_id', $parent?->id)
            ->where('title', $title)
            ->first();

        if ($existing) {
            return [
                'success' => false,
                'error' => '同级目录已存在',
                'item' => $this->formatCategory($existing),
            ];
        }

        $category = KbCategory::query()->create([
            'user_id' => self::USER_ID,
            'parent_id' => $parent?->id,
            'title' => $title,
            'slug' => $this->generateCategorySlug($title),
            'sort_order' => $sortOrder,
        ]);

        return [
            'success' => true,
            'id' => $category->id,
            'item' => $this->formatCategory($category),
        ];
    }

    #[AiTool(
        name: 'kb_project_list_docs',
        description: '查询当前项目知识库目录下的文档列表。只能访问当前项目目录及其子目录。',
        params: [
            'keyword' => ['type' => 'string', 'required' => false, 'desc' => '标题、摘要、正文关键词'],
            'page' => ['type' => 'integer', 'required' => false, 'desc' => '页码，默认1'],
            'pageSize' => ['type' => 'integer', 'required' => false, 'desc' => '每页数量，默认10，最大50'],
        ]
    )]
    public function listProjectDocs(?string $keyword = null, int $page = 1, int $pageSize = 10): array
    {
        return $this->listDocs($this->projectScope(), $keyword, $page, $pageSize);
    }

    #[AiTool(
        name: 'kb_project_get_doc',
        description: '读取当前项目知识库目录下的文档详情。id 和 title 二选一。',
        params: [
            'id' => ['type' => 'integer', 'required' => false, 'desc' => '文档ID'],
            'title' => ['type' => 'string', 'required' => false, 'desc' => '文档标题，可模糊匹配'],
        ]
    )]
    public function getProjectDoc(?int $id = null, ?string $title = null): array
    {
        return $this->getDoc($this->projectScope(), $id, $title);
    }

    #[AiTool(
        name: 'kb_project_create_doc',
        description: '在当前项目知识库根目录下新建文档。不能创建到其他项目或默认知识库。',
        params: [
            'title' => ['type' => 'string', 'required' => true, 'desc' => '文档标题'],
            'content' => ['type' => 'string', 'required' => false, 'desc' => 'Markdown正文'],
            'summary' => ['type' => 'string', 'required' => false, 'desc' => '摘要'],
            'tags' => ['type' => 'array', 'required' => false, 'desc' => '标签数组'],
            'status' => ['type' => 'string', 'required' => false, 'desc' => 'private 或 public，默认 private'],
        ]
    )]
    public function createProjectDoc(string $title, ?string $content = '', ?string $summary = '', ?array $tags = null, string $status = 'private'): array
    {
        $scope = $this->projectScope();

        return $this->createDoc($scope, (int) $scope['root']->id, $title, $content ?? '', $summary ?? '', $tags ?? [], $status);
    }

    #[AiTool(
        name: 'kb_project_update_doc',
        description: '修改当前项目知识库目录下的文档信息。id 和 title 二选一；只修改传入字段，不会删除文档。',
        params: [
            'id' => ['type' => 'integer', 'required' => false, 'desc' => '文档ID'],
            'title' => ['type' => 'string', 'required' => false, 'desc' => '用于定位文档的标题，可模糊匹配'],
            'newTitle' => ['type' => 'string', 'required' => false, 'desc' => '新标题'],
            'content' => ['type' => 'string', 'required' => false, 'desc' => '新的完整 Markdown 正文'],
            'summary' => ['type' => 'string', 'required' => false, 'desc' => '新摘要'],
            'tags' => ['type' => 'array', 'required' => false, 'desc' => '新标签数组'],
            'status' => ['type' => 'string', 'required' => false, 'desc' => 'private 或 public'],
        ]
    )]
    public function updateProjectDoc(?int $id = null, ?string $title = null, ?string $newTitle = null, ?string $content = null, ?string $summary = null, ?array $tags = null, ?string $status = null): array
    {
        return $this->updateDoc($this->projectScope(), $id, $title, $newTitle, $content, $summary, $tags ?? [], $status);
    }

    #[AiTool(
        name: 'kb_project_modify_doc_content',
        description: '通用修改当前项目知识库文档正文。支持完整改写、追加内容、按旧文本替换。id 和 title 二选一。',
        params: [
            'id' => ['type' => 'integer', 'required' => false, 'desc' => '文档ID'],
            'title' => ['type' => 'string', 'required' => false, 'desc' => '用于定位文档的标题，可模糊匹配'],
            'editInstruction' => ['type' => 'string', 'required' => false, 'desc' => '用户的修改要求说明'],
            'updatedContent' => ['type' => 'string', 'required' => false, 'desc' => '修改后的完整 Markdown 正文；适合复杂改写'],
            'appendContent' => ['type' => 'string', 'required' => false, 'desc' => '要追加到文档末尾的内容'],
            'oldText' => ['type' => 'string', 'required' => false, 'desc' => '局部替换时要被替换的原文'],
            'newText' => ['type' => 'string', 'required' => false, 'desc' => '局部替换时替换后的新文本'],
        ]
    )]
    public function modifyProjectDocContent(?int $id = null, ?string $title = null, ?string $editInstruction = null, ?string $updatedContent = null, ?string $appendContent = null, ?string $oldText = null, ?string $newText = null): array
    {
        return $this->modifyDocContent($this->projectScope(), $id, $title, $editInstruction, $updatedContent, $appendContent, $oldText, $newText);
    }

    #[AiTool(
        name: 'kb_project_append_doc',
        description: '向当前项目知识库目录下的文档末尾追加记录。id 和 title 二选一；文档不存在时可设置 createIfMissing=true 自动创建到当前项目根目录。',
        params: [
            'id' => ['type' => 'integer', 'required' => false, 'desc' => '文档ID'],
            'title' => ['type' => 'string', 'required' => false, 'desc' => '文档标题，可模糊匹配'],
            'appendContent' => ['type' => 'string', 'required' => true, 'desc' => '要追加的 Markdown 内容'],
            'createIfMissing' => ['type' => 'boolean', 'required' => false, 'desc' => '找不到文档时是否按 title 新建，默认 false'],
        ]
    )]
    public function appendProjectDoc(?int $id = null, ?string $title = null, string $appendContent = '', bool $createIfMissing = false): array
    {
        $scope = $this->projectScope();

        return $this->appendDoc($scope, (int) $scope['root']->id, $id, $title, $appendContent, $createIfMissing);
    }

    private function listDocs(array $scope, ?string $keyword, int $page, int $pageSize): array
    {
        $page = max(1, $page);
        $pageSize = min(50, max(1, $pageSize));
        $query = $this->baseArticleQuery($scope);

        $keyword = trim((string) $keyword);
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
            ->get(['id', 'category_id', 'title', 'summary', 'status', 'tags', 'created_at', 'updated_at'])
            ->toArray();

        return ['success' => true, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize, 'items' => $items];
    }

    private function getDoc(array $scope, ?int $id, ?string $title): array
    {
        $article = $this->findDoc($scope, $id, $title);
        if (is_array($article)) {
            return $article;
        }
        if (! $article) {
            return ['success' => false, 'error' => '未找到匹配文档'];
        }

        return ['success' => true, 'item' => $article->toArray()];
    }

    private function createDoc(array $scope, ?int $categoryId, string $title, string $content, string $summary, array $tags, string $status): array
    {
        $title = trim($title);
        if ($title === '') {
            return ['success' => false, 'error' => 'title 不能为空'];
        }

        $existing = $this->findExactDocByTitle($scope, $title);
        if ($existing) {
            return [
                'success' => false,
                'error' => '同名文档已存在，请改为追加或修改该文档，避免重复新建',
                'item' => $this->formatDocCandidate($existing),
            ];
        }

        $article = KbArticle::query()->create([
            'user_id' => self::USER_ID,
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => $this->generateSlug($title),
            'summary' => $summary,
            'content' => $content,
            'content_html' => '',
            'tags' => array_values(array_map('strval', $tags)),
            'status' => in_array($status, [KbArticle::STATUS_PRIVATE, KbArticle::STATUS_PUBLIC], true) ? $status : KbArticle::STATUS_PRIVATE,
        ]);

        return ['success' => true, 'id' => $article->id, 'item' => $article->toArray()];
    }

    private function updateDoc(array $scope, ?int $id, ?string $title, ?string $newTitle, ?string $content, ?string $summary, array $tags, ?string $status): array
    {
        $article = $this->findDoc($scope, $id, $title);
        if (is_array($article)) {
            return $article;
        }
        if (! $article) {
            return ['success' => false, 'error' => '未找到匹配文档'];
        }

        $payload = [];
        if ($newTitle !== null && trim($newTitle) !== '') {
            $payload['title'] = trim($newTitle);
            $payload['slug'] = $this->generateSlug($payload['title'], (int) $article->id);
        }
        if ($content !== null) {
            $payload['content'] = $content;
        }
        if ($summary !== null) {
            $payload['summary'] = $summary;
        }
        if ($tags !== []) {
            $payload['tags'] = array_values(array_map('strval', $tags));
        }
        if ($status !== null && in_array($status, [KbArticle::STATUS_PRIVATE, KbArticle::STATUS_PUBLIC], true)) {
            $payload['status'] = $status;
        }

        if ($payload === []) {
            return ['success' => false, 'error' => '没有可更新的字段'];
        }

        $article->update($payload);
        $article->refresh();

        return ['success' => true, 'item' => $article->toArray()];
    }

    private function modifyDocContent(array $scope, ?int $id, ?string $title, ?string $editInstruction, ?string $updatedContent, ?string $appendContent, ?string $oldText, ?string $newText): array
    {
        $article = $this->findDoc($scope, $id, $title);
        if (is_array($article)) {
            return $article;
        }
        if (! $article) {
            return ['success' => false, 'error' => '未找到匹配文档'];
        }

        $operation = '';
        $count = null;

        if ($updatedContent !== null) {
            $article->content = $updatedContent;
            $operation = 'rewrite';
        } elseif ($appendContent !== null && trim($appendContent) !== '') {
            $existing = rtrim((string) $article->content);
            $article->content = $existing === '' ? trim($appendContent) : $existing."\n\n".trim($appendContent);
            $operation = 'append';
        } elseif ($oldText !== null && trim($oldText) !== '' && $newText !== null) {
            $content = (string) $article->content;
            $updated = str_replace(trim($oldText), $newText, $content, $count);
            if ($count === 0) {
                return [
                    'success' => false,
                    'error' => '文档正文中未找到要修改的内容',
                    'item' => $this->formatDocCandidate($article),
                ];
            }
            $article->content = $updated;
            $operation = 'replace';
        } else {
            return [
                'success' => false,
                'error' => '缺少修改内容，请提供 updatedContent、appendContent 或 oldText+newText',
                'edit_instruction' => $editInstruction,
                'item' => $this->formatDocCandidate($article),
            ];
        }

        $article->save();

        $result = [
            'success' => true,
            'operation' => $operation,
            'item' => $article->fresh()->toArray(),
        ];

        if ($count !== null) {
            $result['modified_count'] = $count;
        }

        return $result;
    }

    private function appendDoc(array $scope, ?int $createCategoryId, ?int $id, ?string $title, string $appendContent, bool $createIfMissing): array
    {
        $appendContent = trim($appendContent);
        if ($appendContent === '') {
            return ['success' => false, 'error' => 'appendContent 不能为空'];
        }

        $article = $this->findDoc($scope, $id, $title);
        if (is_array($article)) {
            return $article;
        }
        if (! $article && $createIfMissing && trim((string) $title) !== '') {
            return $this->createDoc($scope, $createCategoryId, (string) $title, $appendContent, '', [], KbArticle::STATUS_PRIVATE);
        }
        if (! $article) {
            return ['success' => false, 'error' => '未找到匹配文档'];
        }

        $existing = rtrim((string) $article->content);
        $article->content = $existing === '' ? $appendContent : $existing."\n\n".$appendContent;
        $article->save();

        return ['success' => true, 'item' => $article->fresh()->toArray()];
    }

    private function findDoc(array $scope, ?int $id, ?string $title): KbArticle|array|null
    {
        $query = $this->baseArticleQuery($scope);

        if ($id !== null && $id > 0) {
            return $query->where('id', $id)->first();
        }

        $title = trim((string) $title);
        if ($title === '') {
            return null;
        }

        $exactMatches = (clone $query)->where('title', $title)->orderByDesc('updated_at')->limit(6)->get();
        if ($exactMatches->count() === 1) {
            return $exactMatches->first();
        }
        if ($exactMatches->count() > 1) {
            return $this->ambiguousResult($exactMatches->all());
        }

        $matches = $query->where('title', 'like', "%{$title}%")->orderByDesc('updated_at')->limit(6)->get();
        if ($matches->count() === 1) {
            return $matches->first();
        }
        if ($matches->count() > 1) {
            return $this->ambiguousResult($matches->all());
        }

        return null;
    }

    private function findExactDocByTitle(array $scope, string $title): ?KbArticle
    {
        $exact = $this->baseArticleQuery($scope)->where('title', $title)->orderByDesc('updated_at')->first();
        if ($exact) {
            return $exact;
        }

        return null;
    }

    private function ambiguousResult(array $articles): array
    {
        return [
            'success' => false,
            'need_user_selection' => true,
            'error' => '找到多个可能的文档，请让用户选择编号后再修改',
            'candidates' => array_values(array_map(
                fn (KbArticle $article) => $this->formatDocCandidate($article),
                $articles
            )),
        ];
    }

    private function formatDocCandidate(KbArticle $article): array
    {
        return [
            'id' => (int) $article->id,
            'path' => $this->docPath($article),
            'title' => (string) $article->title,
            'updated_at' => $article->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function docPath(KbArticle $article): string
    {
        $segments = [];
        $category = $article->category_id ? KbCategory::query()->find((int) $article->category_id) : null;
        while ($category) {
            array_unshift($segments, (string) $category->title);
            $category = $category->parent_id ? KbCategory::query()->find((int) $category->parent_id) : null;
        }

        $segments[] = (string) $article->title;

        return '/'.implode('/', array_filter($segments, fn ($segment) => $segment !== ''));
    }

    private function resolveCategoryParent(?int $parentId, ?string $parentPath): ?KbCategory
    {
        if ($parentId !== null && $parentId > 0) {
            $category = KbCategory::query()
                ->where('user_id', self::USER_ID)
                ->where('id', $parentId)
                ->first();

            if (! $category) {
                throw new \InvalidArgumentException('父目录不存在，ID: '.$parentId);
            }

            return $category;
        }

        $parentPath = trim((string) $parentPath);
        if ($parentPath === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim($parentPath, '/')), fn ($segment) => $segment !== ''));
        if ($segments === []) {
            return null;
        }

        $parentId = null;
        $category = null;
        foreach ($segments as $segment) {
            $category = KbCategory::query()
                ->where('user_id', self::USER_ID)
                ->where('parent_id', $parentId)
                ->where('title', $segment)
                ->first();

            if (! $category) {
                throw new \InvalidArgumentException('父目录不存在：'.$parentPath);
            }

            $parentId = (int) $category->id;
        }

        return $category;
    }

    private function resolveTargetCategory(array $scope, ?int $categoryId, ?string $categoryPath): KbCategory
    {
        if ($categoryId !== null && $categoryId > 0) {
            $category = KbCategory::query()
                ->where('user_id', self::USER_ID)
                ->where('id', $categoryId)
                ->first();
            if (! $category) {
                throw new \InvalidArgumentException('目标目录不存在，ID: '.$categoryId);
            }

            return $category;
        }

        $categoryPath = trim((string) $categoryPath);
        if ($categoryPath !== '') {
            $category = $this->resolveCategoryParent(null, $categoryPath);
            if (! $category) {
                throw new \InvalidArgumentException('目标目录不存在：'.$categoryPath);
            }

            return $category;
        }

        return $scope['default_category'];
    }

    private function formatCategory(KbCategory $category): array
    {
        return [
            'id' => (int) $category->id,
            'parent_id' => $category->parent_id,
            'title' => (string) $category->title,
            'path' => $this->categoryPath($category),
            'sort_order' => (int) $category->sort_order,
            'created_at' => $category->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $category->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function categoryPath(KbCategory $category): string
    {
        $segments = [];
        while ($category) {
            array_unshift($segments, (string) $category->title);
            $category = $category->parent_id ? KbCategory::query()->find((int) $category->parent_id) : null;
        }

        return '/'.implode('/', array_filter($segments, fn ($segment) => $segment !== ''));
    }

    private function baseArticleQuery(array $scope)
    {
        $query = KbArticle::query()->where('user_id', self::USER_ID);
        if (($scope['type'] ?? '') === 'project') {
            return $query->whereIn('category_id', $scope['category_ids']);
        }

        return $query;
    }

    private function globalScope(): array
    {
        return [
            'type' => 'global',
            'default_category' => $this->defaultCategory(),
        ];
    }

    private function defaultCategory(): KbCategory
    {
        return KbCategory::query()->firstOrCreate(
            ['user_id' => self::USER_ID, 'slug' => self::DEFAULT_CATEGORY_SLUG],
            ['parent_id' => null, 'title' => self::DEFAULT_CATEGORY_TITLE, 'sort_order' => 0]
        );
    }

    private function projectScope(): array
    {
        $prefix = (string) session('current_project_prefix', '');
        if ($prefix === '') {
            throw new \RuntimeException('缺少当前项目上下文');
        }

        $project = Project::query()->where('prefix', $prefix)->first();
        if (! $project) {
            throw new \RuntimeException('当前项目不存在');
        }

        $root = KbCategory::query()->firstOrCreate(
            ['user_id' => self::USER_ID, 'slug' => 'project-'.$prefix],
            ['parent_id' => null, 'title' => $project->name, 'sort_order' => 0]
        );

        return [
            'type' => 'project',
            'root' => $root,
            'category_ids' => $this->descendantCategoryIds((int) $root->id),
        ];
    }

    private function descendantCategoryIds(int $rootId): array
    {
        $rows = KbCategory::query()
            ->where('user_id', self::USER_ID)
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

    private function generateCategorySlug(string $title): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = Str::lower(Str::random(8));
        }

        $slug = $base;
        $counter = 1;
        while (KbCategory::query()->where('user_id', self::USER_ID)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = KbArticle::query()->where('slug', $slug);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
