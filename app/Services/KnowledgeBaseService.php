<?php

namespace App\Services;

use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Support\Str;

class KnowledgeBaseService
{
    // ========== 分类 ==========

    /**
     * 获取当前用户的分类树
     */
    public function getCategoryTree(): array
    {
        $categories = KbCategory::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->toArray();

        return $this->buildTree($categories);
    }

    /**
     * 构建分类树
     */
    private function buildTree(array $items, ?int $parentId = null): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ($item['parent_id'] === $parentId) {
                $children = $this->buildTree($items, $item['id']);
                $item['children'] = $children;
                $tree[] = $item;
            }
        }

        return $tree;
    }

    /**
     * 创建分类
     */
    public function createCategory(array $data): KbCategory
    {
        $data['user_id'] = 0;
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        return KbCategory::create($data);
    }

    /**
     * 更新分类
     */
    public function updateCategory(int $id, array $data): KbCategory
    {
        $category = KbCategory::query()->where('id', $id)->firstOrFail();

        $category->update($data);

        return $category->fresh();
    }

    /**
     * 删除分类（同时将该分类下的文章设为未分类）
     */
    public function deleteCategory(int $id): void
    {
        $category = KbCategory::query()->where('id', $id)->firstOrFail();

        // 将子分类提升到父级
        KbCategory::where('parent_id', $id)
            ->update(['parent_id' => $category->parent_id]);

        // 将该分类下的文章设为未分类
        KbArticle::where('category_id', $id)
            ->update(['category_id' => null]);

        $category->delete();
    }

    /**
     * 获取指定分类及其所有子孙分类的 ID 列表
     */
    private function getCategoryWithDescendantIds(int $categoryId): array
    {
        $allCategories = KbCategory::query()
            ->select('id', 'parent_id')
            ->get()
            ->toArray();

        $ids = [$categoryId];
        $queue = [$categoryId];

        while (! empty($queue)) {
            $parentId = array_shift($queue);
            foreach ($allCategories as $cat) {
                if ($cat['parent_id'] === $parentId && ! in_array($cat['id'], $ids)) {
                    $ids[] = $cat['id'];
                    $queue[] = $cat['id'];
                }
            }
        }

        return $ids;
    }

    // ========== 文章 ==========

    /**
     * 获取文章列表
     */
    public function getArticleList(array $params = []): array
    {
        $query = KbArticle::query();

        // 分类筛选（包含子分类）
        if (! empty($params['category_id'])) {
            $categoryIds = $this->getCategoryWithDescendantIds((int) $params['category_id']);
            $query->whereIn('category_id', $categoryIds);
        }

        // 状态筛选
        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        // 标签筛选
        if (! empty($params['tag'])) {
            $tag = $params['tag'];
            $query->whereJsonContains('tags', $tag);
        }

        // 关键词搜索
        if (! empty($params['keyword'])) {
            $keyword = $params['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('summary', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%");
            });
        }

        // 排序
        $sortField = $params['sort_field'] ?? 'updated_at';
        $sortOrder = $params['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        // 分页
        $page = (int) ($params['page'] ?? 1);
        $pageSize = (int) ($params['page_size'] ?? 20);

        $total = $query->count();
        $items = $query->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'category_id' => $item->category_id,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'summary' => $item->summary,
                    'tags' => $item->tags ?? [],
                    'status' => $item->status,
                    'sort_order' => $item->sort_order,
                    'view_count' => $item->view_count,
                    'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $item->updated_at?->format('Y-m-d H:i:s'),
                ];
            })
            ->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 获取文章详情
     */
    public function getArticle(int $id): ?KbArticle
    {
        return KbArticle::query()->where('id', $id)->first();
    }

    /**
     * 创建文章
     */
    public function createArticle(array $data): KbArticle
    {
        $data['user_id'] = 0;

        if (empty($data['slug'])) {
            $baseSlug = Str::random(8);
            $slug = $baseSlug;
            $counter = 1;

            // 确保 slug 唯一
            while (KbArticle::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter++;
            }

            $data['slug'] = $slug;
        }

        if (empty($data['status'])) {
            $data['status'] = KbArticle::STATUS_PRIVATE;
        }

        return KbArticle::create($data);
    }

    /**
     * 更新文章
     */
    public function updateArticle(int $id, array $data): KbArticle
    {
        $article = KbArticle::query()->where('id', $id)->firstOrFail();

        $article->update($data);

        return $article->fresh();
    }

    /**
     * 删除文章
     */
    public function deleteArticle(int $id): void
    {
        $article = KbArticle::query()->where('id', $id)->firstOrFail();

        $article->delete();
    }

    /**
     * 切换文章状态
     */
    public function toggleArticleStatus(int $id): KbArticle
    {
        $article = KbArticle::query()->where('id', $id)->firstOrFail();

        $article->status = $article->status === KbArticle::STATUS_PUBLIC
            ? KbArticle::STATUS_PRIVATE
            : KbArticle::STATUS_PUBLIC;
        $article->save();

        return $article;
    }

    /**
     * 通过 id 获取当前用户的文章（不限状态）
     */
    public function getArticleById(int $id): ?KbArticle
    {
        return KbArticle::query()->where('id', $id)->first();
    }

    /**
     * 通过 slug 获取公开文章（无需登录）
     */
    public function getPublicArticleBySlug(string $slug): ?KbArticle
    {
        return KbArticle::where('slug', $slug)
            ->where('status', KbArticle::STATUS_PUBLIC)
            ->first();
    }
}
