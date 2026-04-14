<?php

namespace App\Http\Controllers\Admin;

use App\Services\KnowledgeBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeBaseController extends BaseAdminController
{
    private KnowledgeBaseService $service;

    public function __construct(KnowledgeBaseService $service)
    {
        $this->service = $service;
    }

    /**
     * 知识库列表页面
     */
    public function index()
    {
        return viewShow('KnowledgeBase/KnowledgeBase');
    }

    /**
     * 文章编辑页面
     */
    public function editor(int $id = 0)
    {
        return viewShow('KnowledgeBase/KbEditor', ['articleId' => $id]);
    }

    // ========== 分类 API ==========

    /**
     * 获取分类树
     */
    public function categoryTree(): JsonResponse
    {
        try {
            $tree = $this->service->getCategoryTree();

            return success(['tree' => $tree]);
        } catch (\Throwable $e) {
            return error([], '获取分类失败: '.$e->getMessage());
        }
    }

    /**
     * 创建分类
     */
    public function createCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'parent_id' => 'nullable|integer',
            'slug' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        try {
            $category = $this->service->createCategory($validated);

            return success($category->toArray(), '创建成功');
        } catch (\Throwable $e) {
            return error([], '创建分类失败: '.$e->getMessage());
        }
    }

    /**
     * 更新分类
     */
    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'parent_id' => 'nullable|integer',
            'slug' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        try {
            $category = $this->service->updateCategory($id, $validated);

            return success($category->toArray(), '更新成功');
        } catch (\Throwable $e) {
            return error([], '更新分类失败: '.$e->getMessage());
        }
    }

    /**
     * 删除分类
     */
    public function deleteCategory(int $id): JsonResponse
    {
        try {
            $this->service->deleteCategory($id);

            return success([], '删除成功');
        } catch (\Throwable $e) {
            return error([], '删除分类失败: '.$e->getMessage());
        }
    }

    // ========== 文章 API ==========

    /**
     * 文章列表
     */
    public function articleList(Request $request): JsonResponse
    {
        try {
            $params = $request->only(['category_id', 'status', 'keyword', 'tag', 'page', 'page_size', 'sort_field', 'sort_order']);
            $result = $this->service->getArticleList($params);

            return success($result);
        } catch (\Throwable $e) {
            return error([], '获取文章列表失败: '.$e->getMessage());
        }
    }

    /**
     * 文章详情
     */
    public function articleDetail(int $id): JsonResponse
    {
        try {
            $article = $this->service->getArticle($id);
            if (! $article) {
                return error([], '文章不存在');
            }

            return success($article->toArray());
        } catch (\Throwable $e) {
            return error([], '获取文章详情失败: '.$e->getMessage());
        }
    }

    /**
     * 创建文章
     */
    public function createArticle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'nullable|integer',
            'slug' => 'nullable|string|max:255',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'content_html' => 'nullable|string',
            'tags' => 'nullable|array',
            'status' => 'nullable|string|in:private,public',
        ]);

        try {
            $article = $this->service->createArticle($validated);

            return success($article->toArray(), '创建成功');
        } catch (\Throwable $e) {
            return error([], '创建文章失败: '.$e->getMessage());
        }
    }

    /**
     * 更新文章
     */
    public function updateArticle(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'category_id' => 'nullable|integer',
            'slug' => 'nullable|string|max:255',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'content_html' => 'nullable|string',
            'tags' => 'nullable|array',
            'status' => 'nullable|string|in:private,public',
        ]);

        try {
            $article = $this->service->updateArticle($id, $validated);

            return success($article->toArray(), '更新成功');
        } catch (\Throwable $e) {
            return error([], '更新文章失败: '.$e->getMessage());
        }
    }

    /**
     * 删除文章
     */
    public function deleteArticle(int $id): JsonResponse
    {
        try {
            $this->service->deleteArticle($id);

            return success([], '删除成功');
        } catch (\Throwable $e) {
            return error([], '删除文章失败: '.$e->getMessage());
        }
    }

    /**
     * 文章详情页（需登录，支持私有文章）
     */
    public function detailView(int $id)
    {
        $article = $this->service->getArticleById($id);
        if (! $article) {
            abort(404);
        }

        // 增加浏览量
        $article->increment('view_count');

        return viewShow('KnowledgeBase/KbPublicView', [
            'article' => $article->toArray(),
        ]);
    }

    /**
     * 公开文章查看（无需登录）
     */
    public function publicView(string $slug)
    {
        $article = $this->service->getPublicArticleBySlug($slug);
        if (! $article) {
            abort(404);
        }

        // 增加浏览量
        $article->increment('view_count');

        return viewShow('KnowledgeBase/KbPublicView', [
            'article' => $article->toArray(),
        ]);
    }

    /**
     * 移动端文章详情页（Token 认证，WebView 使用）
     */
    public function mobileView(int $id)
    {
        $article = $this->service->getArticleById($id);
        if (! $article) {
            abort(404);
        }

        $article->increment('view_count');

        return viewShow('KnowledgeBase/KbMobileView', [
            'article' => $article->toArray(),
            'token' => (string) session('token_web_auth_token', ''),
        ]);
    }

    /**
     * 移动端文章编辑页（Token 认证，WebView 使用）
     */
    public function mobileEditor(?int $id = null)
    {
        return viewShow('KnowledgeBase/KbMobileEditor', [
            'articleId' => $id,
            'token' => (string) session('token_web_auth_token', ''),
            'defaultCategoryId' => request()->query('category_id') ? (int) request()->query('category_id') : null,
        ]);
    }

    /**
     * 上传图片
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:10240', // 最大 10MB
        ]);

        try {
            $mediaService = app(\App\Services\MediaService::class);
            $media = $mediaService->createMedia($request->file('file'), 'KB图片上传');

            return success(['url' => $media->url], '上传成功');
        } catch (\Throwable $e) {
            return error([], '上传失败: '.$e->getMessage());
        }
    }

    /**
     * 切换文章状态
     */
    public function toggleArticle(int $id): JsonResponse
    {
        try {
            $article = $this->service->toggleArticleStatus($id);

            return success($article->toArray(), '状态已更新');
        } catch (\Throwable $e) {
            return error([], '状态切换失败: '.$e->getMessage());
        }
    }
}
