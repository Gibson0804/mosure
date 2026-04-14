<?php

namespace App\Http\Controllers;

use App\Services\PluginService;
use Illuminate\Support\Facades\Log;

/**
 * 前端页面访问控制器
 */
class FrontendController extends Controller
{
    /**
     * 插件服务
     */
    private PluginService $pluginService;

    /**
     * 构造函数
     */
    public function __construct(PluginService $pluginService)
    {
        $this->pluginService = $pluginService;
    }

    /**
     * 服务前端文件
     *
     * @param  string  $projectPrefix  项目前缀
     * @param  string  $plugin  插件名称
     * @param  string  $path  文件路径
     * @return \Illuminate\Http\Response
     */
    public function serve(string $projectPrefix, string $plugin, string $path = '')
    {
        try {
            // 设置项目前缀到 session
            session(['current_project_prefix' => $projectPrefix]);

            // 验证插件是否存在
            $pluginInstance = $this->pluginService->get($plugin);
            if (! $pluginInstance) {
                abort(404, '插件未找到');
            }

            // 获取插件配置
            $config = $pluginInstance->getConfig();

            // 检查插件是否有前端
            if (! ($config['has_frontend'] ?? false)) {
                abort(404, '插件未提供前端页面');
            }

            // 检查插件是否已安装
            $installedPlugins = $this->pluginService->getInstalledPlugins();
            $isInstalled = false;
            foreach ($installedPlugins as $installed) {
                if ($installed['plugin_id'] === $plugin) {
                    $isInstalled = true;
                    break;
                }
            }

            if (! $isInstalled) {
                abort(404, '插件未安装');
            }

            // 获取前端配置
            $frontendConfig = $config['frontend'] ?? [];
            $routePrefix = $plugin;

            // 构建前端根目录（包含 dist 和 static）
            $frontendRootPath = storage_path("app/frontend/{$projectPrefix}/{$plugin}");

            // 如果路径为空，默认访问 dist/index.html
            if (empty($path)) {
                $indexPath = "{$frontendRootPath}/dist/index.html";
                if (file_exists($indexPath)) {
                    return redirect("/frontend/{$projectPrefix}/{$plugin}/dist/index.html");
                }
                abort(404, '首页未找到');
            }

            // 向后兼容：如果路径不以 dist/ 或 static/ 开头，自动添加 dist/ 前缀
            if (! preg_match('/^(dist|static)\//', $path)) {
                $distPath = "dist/{$path}";
                $distFilePath = "{$frontendRootPath}/{$distPath}";
                if (file_exists($distFilePath)) {
                    $path = $distPath;
                }
            }

            // 构建文件路径（支持 dist 和 static 目录）
            $filePath = "{$frontendRootPath}/{$path}";

            // 如果路径是目录，尝试访问 index.html
            if (is_dir($filePath)) {
                $indexPath = "{$filePath}/index.html";
                if (file_exists($indexPath)) {
                    return redirect("/frontend/{$projectPrefix}/{$plugin}/{$path}/dist/index.html");
                }
                abort(404, '目录未找到');
            }

            // 验证文件是否存在
            if (! file_exists($filePath)) {
                Log::info("File not found: {$filePath}");
                abort(404, '文件未找到');
            }

            // 安全检查：防止路径遍历
            $realPath = realpath($filePath);
            $realFrontendPath = realpath($frontendRootPath);
            if (! $realPath || ! $realFrontendPath || strpos($realPath, $realFrontendPath) !== 0) {
                abort(403, '访问被拒绝');
            }

            // 获取文件类型
            $mimeType = $this->getMimeType($filePath);

            // 返回文件
            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=86400', // 1天缓存
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            if ($e->getCode() >= 400 && $e->getCode() < 500) {
                throw $e;
            }
            abort(500, '服务器错误: '.$e->getMessage());
        }
    }

    /**
     * 获取文件 MIME 类型
     *
     * @param  string  $filePath  文件路径
     */
    private function getMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $mimeTypes = [
            'html' => 'text/html; charset=utf-8',
            'htm' => 'text/html; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'xml' => 'application/xml; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'otf' => 'font/otf',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'txt' => 'text/plain; charset=utf-8',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
