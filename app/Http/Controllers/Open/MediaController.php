<?php

namespace App\Http\Controllers\Open;

use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends BaseOpenController
{
    private MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * 获取媒体资源详情（通过ID）
     * GET /open/media/detail/{id}
     */
    public function getDetail(Request $request, int $id): JsonResponse
    {
        try {
            if ($ownershipError = $this->assertProjectUserOwnsMedia($request, $id)) {
                return $ownershipError;
            }

            $media = $this->mediaService->getMediaById($id);

            if (! $media) {
                return $this->error('媒体资源不存在', 404);
            }

            // 只返回必要的公开信息，过滤敏感字段
            $safeData = $this->filterSensitiveFields($media->toArray());

            return $this->success(['data' => $safeData]);

        } catch (\Exception $e) {
            return $this->error('获取媒体资源失败: '.$e->getMessage(), 500);
        }
    }

    /**
     * 获取媒体资源列表
     * GET /open/media/list
     */
    public function getList(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            $pageSize = min(100, max(1, (int) $request->input('page_size', 15))); // 限制最大100条

            $filters = [
                'keyword' => $request->input('keyword'),
                'type' => $request->input('type'), // image, video, audio, document
                'folder_id' => $request->input('folder_id'),
            ];
            $this->applyProjectUserMediaFilter($request, $filters);

            $filters['per_page'] = $pageSize;
            $result = $this->mediaService->getMediaList($filters);

            // 过滤敏感信息
            /** @var \Illuminate\Pagination\LengthAwarePaginator $result */
            $safeData = $result->getCollection()->map(function ($item) {
                return $this->filterSensitiveFields($item->toArray());
            })->values()->toArray();

            $meta = [
                'total' => $result->total(),
                'page' => $result->currentPage(),
                'page_size' => $result->perPage(),
                'page_count' => $result->lastPage(),
            ];

            $links = [
                'self' => $request->fullUrl(),
                'first' => $this->buildPageUrl($request, 1),
                'last' => $this->buildPageUrl($request, max(1, $meta['page_count'])),
                'prev' => $meta['page'] > 1 ? $this->buildPageUrl($request, $meta['page'] - 1) : null,
                'next' => $meta['page'] < $meta['page_count'] ? $this->buildPageUrl($request, $meta['page'] + 1) : null,
            ];

            return $this->success([
                'data' => $safeData,
                'meta' => $meta,
                'links' => $links,
            ]);

        } catch (\Exception $e) {
            return $this->error('获取媒体列表失败: '.$e->getMessage(), 500);
        }
    }

    /**
     * 通过标签获取媒体资源
     * GET /open/media/by-tags
     */
    public function getByTags(Request $request): JsonResponse
    {
        try {
            $tags = $request->input('tags'); // 可以是数组或逗号分隔的字符串

            if (empty($tags)) {
                return $this->error('标签参数不能为空', 400);
            }

            // 处理标签参数
            if (is_string($tags)) {
                $tags = array_filter(array_map('trim', explode(',', $tags)));
            }

            if (! is_array($tags) || empty($tags)) {
                return $this->error('标签格式不正确', 400);
            }

            $page = max(1, (int) $request->input('page', 1));
            $pageSize = min(100, max(1, (int) $request->input('page_size', 15)));

            $filters = [
                'tags' => $tags,
                'type' => $request->input('type'),
                'per_page' => $pageSize,
            ];
            $this->applyProjectUserMediaFilter($request, $filters);

            $result = $this->mediaService->getMediaList($filters);

            // 过滤敏感信息
            /** @var \Illuminate\Pagination\LengthAwarePaginator $result */
            $safeData = $result->getCollection()->map(function ($item) {
                return $this->filterSensitiveFields($item->toArray());
            })->values()->toArray();

            $meta = [
                'total' => $result->total(),
                'page' => $result->currentPage(),
                'page_size' => $result->perPage(),
                'page_count' => $result->lastPage(),
                'tags' => $tags,
            ];

            $links = [
                'self' => $request->fullUrl(),
                'first' => $this->buildPageUrl($request, 1),
                'last' => $this->buildPageUrl($request, max(1, $meta['page_count'])),
                'prev' => $meta['page'] > 1 ? $this->buildPageUrl($request, $meta['page'] - 1) : null,
                'next' => $meta['page'] < $meta['page_count'] ? $this->buildPageUrl($request, $meta['page'] + 1) : null,
            ];

            return $this->success([
                'data' => $safeData,
                'meta' => $meta,
                'links' => $links,
            ]);

        } catch (\Exception $e) {
            return $this->error('获取媒体资源失败: '.$e->getMessage(), 500);
        }
    }

    /**
     * 通过文件夹获取媒体资源
     * GET /open/media/by-folder/{folderId}
     */
    public function getByFolder(Request $request, int $folderId): JsonResponse
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            $pageSize = min(100, max(1, (int) $request->input('page_size', 15)));

            $filters = [
                'folder_id' => $folderId,
                'type' => $request->input('type'),
                'per_page' => $pageSize,
            ];
            $this->applyProjectUserMediaFilter($request, $filters);

            $result = $this->mediaService->getMediaList($filters);

            // 过滤敏感信息
            /** @var \Illuminate\Pagination\LengthAwarePaginator $result */
            $safeData = $result->getCollection()->map(function ($item) {
                return $this->filterSensitiveFields($item->toArray());
            })->values()->toArray();

            $meta = [
                'total' => $result->total(),
                'page' => $result->currentPage(),
                'page_size' => $result->perPage(),
                'page_count' => $result->lastPage(),
                'folder_id' => $folderId,
            ];

            $links = [
                'self' => $request->fullUrl(),
                'first' => $this->buildPageUrl($request, 1),
                'last' => $this->buildPageUrl($request, max(1, $meta['page_count'])),
                'prev' => $meta['page'] > 1 ? $this->buildPageUrl($request, $meta['page'] - 1) : null,
                'next' => $meta['page'] < $meta['page_count'] ? $this->buildPageUrl($request, $meta['page'] + 1) : null,
            ];

            return $this->success([
                'data' => $safeData,
                'meta' => $meta,
                'links' => $links,
            ]);

        } catch (\Exception $e) {
            return $this->error('获取媒体资源失败: '.$e->getMessage(), 500);
        }
    }

    /**
     * 搜索媒体资源
     * GET /open/media/search
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $keyword = $request->input('keyword');

            if (empty($keyword)) {
                return $this->error('搜索关键词不能为空', 400);
            }

            $page = max(1, (int) $request->input('page', 1));
            $pageSize = min(100, max(1, (int) $request->input('page_size', 15)));

            $filters = [
                'search' => $keyword,
                'type' => $request->input('type'),
                'per_page' => $pageSize,
            ];
            $this->applyProjectUserMediaFilter($request, $filters);

            $result = $this->mediaService->getMediaList($filters);

            // 过滤敏感信息
            /** @var \Illuminate\Pagination\LengthAwarePaginator $result */
            $safeData = $result->getCollection()->map(function ($item) {
                return $this->filterSensitiveFields($item->toArray());
            })->values()->toArray();

            $meta = [
                'total' => $result->total(),
                'page' => $result->currentPage(),
                'page_size' => $result->perPage(),
                'page_count' => $result->lastPage(),
                'keyword' => $keyword,
            ];

            $links = [
                'self' => $request->fullUrl(),
                'first' => $this->buildPageUrl($request, 1),
                'last' => $this->buildPageUrl($request, max(1, $meta['page_count'])),
                'prev' => $meta['page'] > 1 ? $this->buildPageUrl($request, $meta['page'] - 1) : null,
                'next' => $meta['page'] < $meta['page_count'] ? $this->buildPageUrl($request, $meta['page'] + 1) : null,
            ];

            return $this->success([
                'data' => $safeData,
                'meta' => $meta,
                'links' => $links,
            ]);

        } catch (\Exception $e) {
            return $this->error('搜索失败: '.$e->getMessage(), 500);
        }
    }

    /**
     * 创建媒体资源
     * POST /open/media/create
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'file' => 'required|file|max:102400', // 100MB
                'title' => 'nullable|string|max:255',
                'alt' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'tags' => 'nullable|array',
                'folder_id' => 'nullable|integer',
            ]);

            $file = $request->file('file');
            $description = $validated['description'] ?? null;
            $options = [
                'title' => $validated['title'] ?? null,
                'alt' => $validated['alt'] ?? null,
                'tags' => $validated['tags'] ?? [],
                'folder_id' => $validated['folder_id'] ?? null,
            ];
            if ($projectUserId = $this->projectUserId($request)) {
                $options['created_by'] = $projectUserId;
                $options['updated_by'] = $projectUserId;
            }

            $media = $this->mediaService->createMedia($file, $description, null, $options);

            return $this->success([
                'data' => $this->filterSensitiveFields($media->toArray()),
                'message' => '媒体资源创建成功',
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('验证失败: '.json_encode($e->errors()), 422);
        } catch (\Exception $e) {
            return $this->error('创建媒体资源失败: '.$e->getMessage(), 500);
        }
    }

    /**
     * 更新媒体资源
     * PUT /open/media/update/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            if ($ownershipError = $this->assertProjectUserOwnsMedia($request, $id)) {
                return $ownershipError;
            }

            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'alt' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'tags' => 'nullable|array',
                'folder_id' => 'nullable|integer',
            ]);
            if ($projectUserId = $this->projectUserId($request)) {
                $validated['updated_by'] = $projectUserId;
            }

            $media = $this->mediaService->updateMedia($id, $validated);

            if (! $media) {
                return $this->error('媒体资源不存在', 404);
            }

            return $this->success([
                'data' => $this->filterSensitiveFields($media->toArray()),
                'message' => '媒体资源更新成功',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('验证失败: '.json_encode($e->errors()), 422);
        } catch (\Exception $e) {
            return $this->error('更新媒体资源失败: '.$e->getMessage(), 500);
        }
    }

    /**
     * 删除媒体资源
     * DELETE /open/media/delete/{id}
     */
    public function delete(Request $request, int $id): JsonResponse
    {
        try {
            if ($ownershipError = $this->assertProjectUserOwnsMedia($request, $id)) {
                return $ownershipError;
            }

            $result = $this->mediaService->deleteMedia($id);

            if (! $result) {
                return $this->error('媒体资源不存在或删除失败', 404);
            }

            return $this->success([
                'message' => '媒体资源删除成功',
            ]);

        } catch (\Exception $e) {
            return $this->error('删除媒体资源失败: '.$e->getMessage(), 500);
        }
    }

    /**
     * 过滤敏感字段，只返回必要的公开信息
     */
    private function filterSensitiveFields(array $media): array
    {
        // 只返回必要的公开字段
        return [
            'id' => $media['id'] ?? null,
            'filename' => $media['filename'] ?? null,
            'title' => $media['title'] ?? null,
            'alt' => $media['alt'] ?? null,
            'description' => $media['description'] ?? null,
            'url' => $media['url'] ?? null,
            'type' => $media['type'] ?? null,
            'mime_type' => $media['mime_type'] ?? null,
            'size' => $media['size'] ?? null,
            'width' => $media['width'] ?? null,
            'height' => $media['height'] ?? null,
            'duration' => $media['duration'] ?? null,
            'tags' => $media['tags'] ?? null,
            'folder_path' => $media['folder_path'] ?? null,
            'created_by' => $media['created_by'] ?? null,
            'updated_by' => $media['updated_by'] ?? null,
            'created_at' => $media['created_at'] ?? null,
        ];
    }

    private function projectUserId(Request $request): ?string
    {
        if ($request->attributes->get('auth_subject_type') !== 'project_user') {
            return null;
        }

        $id = $request->attributes->get('project_user_id');

        return $id === null || $id === '' ? null : (string) $id;
    }

    private function applyProjectUserMediaFilter(Request $request, array &$filters): void
    {
        $projectUserId = $this->projectUserId($request);
        if ($projectUserId === null) {
            return;
        }

        $filters['created_by'] = $projectUserId;
    }

    private function assertProjectUserOwnsMedia(Request $request, int $id): ?JsonResponse
    {
        $projectUserId = $this->projectUserId($request);
        if ($projectUserId === null) {
            return null;
        }

        $media = Media::query()->find($id);
        if (! $media) {
            return $this->error('媒体资源不存在', 404);
        }

        if ((string) ($media->created_by ?? '') !== $projectUserId) {
            return $this->error('没有权限访问该媒体资源', 403);
        }

        return null;
    }

    /**
     * 构建分页URL
     */
    private function buildPageUrl(Request $request, int $page): string
    {
        $query = $request->query();
        $query['page'] = $page;

        return $request->url().'?'.http_build_query($query);
    }
}
