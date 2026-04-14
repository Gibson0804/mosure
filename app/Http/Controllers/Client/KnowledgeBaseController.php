<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\KnowledgeBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    private KnowledgeBaseService $service;

    public function __construct(KnowledgeBaseService $service)
    {
        $this->service = $service;
    }

    /**
     * 分类树
     */
    public function categoryTree(): JsonResponse
    {
        $tree = $this->service->getCategoryTree();

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => $tree,
        ]);
    }

    /**
     * 创建分类
     */
    public function createCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'parent_id' => 'nullable|integer',
        ]);

        try {
            $category = $this->service->createCategory($data);

            return response()->json([
                'code' => 200,
                'message' => '创建成功',
                'data' => $category->toArray(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => '创建分类失败: '.$e->getMessage(),
                'data' => null,
            ]);
        }
    }

    /**
     * 更新分类
     */
    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'parent_id' => 'nullable|integer',
        ]);

        try {
            $category = $this->service->updateCategory($id, $data);

            return response()->json([
                'code' => 200,
                'message' => '更新成功',
                'data' => $category->toArray(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => '更新分类失败: '.$e->getMessage(),
                'data' => null,
            ]);
        }
    }

    /**
     * 删除分类
     */
    public function deleteCategory(int $id): JsonResponse
    {
        try {
            $this->service->deleteCategory($id);

            return response()->json([
                'code' => 200,
                'message' => '删除成功',
                'data' => null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => '删除分类失败: '.$e->getMessage(),
                'data' => null,
            ]);
        }
    }

    /**
     * 文章列表
     */
    public function articleList(Request $request): JsonResponse
    {
        try {
            $params = $request->only(['category_id', 'status', 'keyword', 'tag', 'page', 'page_size']);
            $result = $this->service->getArticleList($params);

            return response()->json([
                'code' => 200,
                'message' => 'success',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => '加载文章失败: '.$e->getMessage(),
                'data' => null,
            ]);
        }
    }

    /**
     * 文章详情
     */
    public function articleDetail(int $id): JsonResponse
    {
        $article = $this->service->getArticle($id);
        if (! $article) {
            return response()->json([
                'code' => 404,
                'message' => '文章不存在',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => $article->toArray(),
        ]);
    }

    /**
     * 创建文章
     */
    public function createArticle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'nullable|integer',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'tags' => 'nullable|array',
            'status' => 'nullable|string|in:private,public',
        ]);

        try {
            $article = $this->service->createArticle($data);

            return response()->json([
                'code' => 200,
                'message' => '创建成功',
                'data' => $article->toArray(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => '创建文章失败: '.$e->getMessage(),
                'data' => null,
            ]);
        }
    }

    /**
     * 更新文章
     */
    public function updateArticle(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'category_id' => 'nullable|integer',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'tags' => 'nullable|array',
            'status' => 'nullable|string|in:private,public',
        ]);

        try {
            $article = $this->service->updateArticle($id, $data);

            return response()->json([
                'code' => 200,
                'message' => '更新成功',
                'data' => $article->toArray(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => '更新文章失败: '.$e->getMessage(),
                'data' => null,
            ]);
        }
    }

    /**
     * 删除文章
     */
    public function deleteArticle(int $id): JsonResponse
    {
        try {
            $this->service->deleteArticle($id);

            return response()->json([
                'code' => 200,
                'message' => '删除成功',
                'data' => null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => '删除文章失败: '.$e->getMessage(),
                'data' => null,
            ]);
        }
    }

    /**
     * 切换文章状态
     */
    public function toggleArticle(int $id): JsonResponse
    {
        try {
            $article = $this->service->toggleArticleStatus($id);

            return response()->json([
                'code' => 200,
                'message' => '状态已更新',
                'data' => $article->toArray(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => '状态切换失败: '.$e->getMessage(),
                'data' => null,
            ]);
        }
    }

    /**
     * 上传图片
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|max:10240',
        ]);

        try {
            $mediaService = app(\App\Services\MediaService::class);
            $media = $mediaService->createMedia($request->file('file'), 'KB图片上传');

            return response()->json([
                'code' => 200,
                'message' => '上传成功',
                'data' => ['url' => $media->url],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => '上传失败: '.$e->getMessage(),
                'data' => null,
            ]);
        }
    }
}
