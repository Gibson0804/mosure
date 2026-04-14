<?php

namespace App\Http\Controllers;

use App\Constants\CacheKeys;
use App\Services\GiteePluginRepository;
use App\Services\PluginMarketplaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 插件市场控制器
 */
class PluginMarketplaceController extends Controller
{
    private PluginMarketplaceService $marketplaceService;

    public function __construct(PluginMarketplaceService $marketplaceService)
    {
        $this->marketplaceService = $marketplaceService;
    }

    /**
     * 获取插件市场列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $plugins = $this->marketplaceService->listPlugins();

            return response()->json([
                'code' => 0,
                'message' => 'success',
                'data' => $plugins,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * 获取插件详情
     *
     * @param  string  $pluginId  插件ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail(string $pluginId)
    {
        try {
            $detail = $this->marketplaceService->getPluginDetail($pluginId);

            if (isset($detail['error'])) {
                return response()->json([
                    'code' => $detail['code'] ?? 404,
                    'message' => $detail['error'],
                    'data' => null,
                ], $detail['code'] ?? 404);
            }

            return response()->json([
                'code' => 0,
                'message' => 'success',
                'data' => $detail,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * 搜索插件
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        try {
            $keyword = $request->input('keyword', '');
            $plugins = $this->marketplaceService->searchPlugins($keyword);

            return response()->json([
                'code' => 0,
                'message' => 'success',
                'data' => $plugins,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * 检查更新
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkUpdates(Request $request)
    {
        try {
            $pluginId = $request->input('plugin_id');
            $updates = $this->marketplaceService->checkUpdates($pluginId);

            return response()->json([
                'code' => 0,
                'data' => $updates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 清除插件市场缓存
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCache()
    {
        try {
            // 清除插件列表缓存
            Cache::forget(CacheKeys::GITEE_PLUGIN_LIST);

            // 清除所有插件详情缓存
            $cacheKeys = Cache::get(CacheKeys::GITEE_PLUGIN_DETAIL_KEYS, []);

            foreach ($cacheKeys as $pluginId) {
                Cache::forget(CacheKeys::getPluginDetailKey($pluginId));
            }

            // 清除缓存 key 记录
            Cache::forget(CacheKeys::GITEE_PLUGIN_DETAIL_KEYS);

            return response()->json([
                'code' => 0,
                'message' => '缓存清除成功',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 下载插件
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function download(Request $request)
    {
        $pluginId = $request->input('plugin_id');
        $version = $request->input('version');

        if (empty($pluginId)) {
            return response()->json([
                'code' => 1,
                'message' => '插件ID不能为空',
            ], 400);
        }

        $result = $this->marketplaceService->downloadPlugin($pluginId, $version);

        if ($result['success']) {
            return response()->json([
                'code' => 0,
                'message' => $result['message'],
                'data' => [
                    'path' => $result['path'] ?? null,
                    'files_count' => $result['files_count'] ?? 0,
                    'already_exists' => $result['already_exists'] ?? false,
                ],
            ]);
        } else {
            return response()->json([
                'code' => 1,
                'message' => $result['message'],
            ]);
        }
    }

    /**
     * 获取插件截图图片（代理方式）
     *
     * @return \Illuminate\Http\Response
     */
    public function getSnapshotImage(Request $request)
    {
        $pluginId = $request->input('plugin_id');
        $version = $request->input('version');
        $imageName = $request->input('image');

        if (empty($pluginId) || empty($version) || empty($imageName)) {
            return response('Bad Request', 400);
        }

        try {
            $repository = app(GiteePluginRepository::class);
            $imageContent = $repository->getSnapshotImageContent($pluginId, $version, $imageName);

            if (empty($imageContent)) {
                return response('Not Found', 404);
            }

            // 检测图片类型
            $extension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
            $mimeTypes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
            ];
            $mimeType = $mimeTypes[$extension] ?? 'image/jpeg';

            return response($imageContent)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=86400');
        } catch (\Exception $e) {
            Log::error('Error getting snapshot image', [
                'plugin_id' => $pluginId,
                'version' => $version,
                'image' => $imageName,
                'error' => $e->getMessage(),
            ]);

            return response('Internal Server Error', 500);
        }
    }

    /**
     * 安装插件
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function install(Request $request)
    {
        try {
            $pluginId = $request->input('plugin_id');
            $version = $request->input('version');

            if (empty($pluginId)) {
                return response()->json([
                    'code' => 400,
                    'message' => 'plugin_id is required',
                    'data' => null,
                ], 400);
            }

            $result = $this->marketplaceService->installPlugin($pluginId, $version);

            return response()->json([
                'code' => $result['code'] ?? 0,
                'message' => $result['message'] ?? 'success',
                'data' => $result['data'] ?? null,
            ], $result['code'] ?? 200);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    /**
     * 更新插件
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        try {
            $pluginId = $request->input('plugin_id');
            $version = $request->input('version');

            if (empty($pluginId)) {
                return response()->json([
                    'code' => 400,
                    'message' => 'plugin_id is required',
                    'data' => null,
                ], 400);
            }

            $result = $this->marketplaceService->updatePlugin($pluginId, $version);

            return response()->json([
                'code' => $result['code'] ?? 0,
                'message' => $result['message'] ?? 'success',
                'data' => $result['data'] ?? null,
            ], $result['code'] ?? 200);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}
