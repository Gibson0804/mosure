<?php

namespace App\Http\Controllers;

use App\Models\ProjectPage;
use App\Services\PageHostingService;
use Illuminate\Support\Facades\Log;

/**
 * 前端托管页面公开访问控制器
 */
class PageHostingController extends Controller
{
    private PageHostingService $service;

    public function __construct(PageHostingService $service)
    {
        $this->service = $service;
    }

    /**
     * 公开访问托管页面
     *
     * @param  string  $projectPrefix  项目前缀
     * @param  string  $slug  页面标识
     * @param  string  $path  子路径（SPA 模式）
     */
    public function serve(string $projectPrefix, string $slug, string $path = '')
    {
        try {
            // 设置项目前缀到 session
            session(['current_project_prefix' => $projectPrefix]);

            $page = ProjectPage::where('slug', $slug)
                ->where('status', 'published')
                ->first();

            if (! $page) {
                abort(404, '页面未找到或未发布');
            }

            // 单页面模式：直接返回 DB 中的 HTML
            if ($page->page_type === 'single') {
                $html = $this->service->renderSinglePage($page, $projectPrefix);

                return response($html)
                    ->header('Content-Type', 'text/html; charset=utf-8')
                    ->header('Cache-Control', 'no-cache');
            }

            // SPA 模式：从文件系统读取
            $distDir = storage_path("app/sites/{$projectPrefix}/{$slug}/dist");

            // 如果 path 为空，重定向到 index.html
            if (empty($path)) {
                return redirect()->to("/sites/{$projectPrefix}/{$slug}/index.html");
            }

            $filePath = "{$distDir}/{$path}";

            // 如果是目录，重定向到目录下的 index.html
            if (is_dir($filePath)) {
                return redirect()->to("/sites/{$projectPrefix}/{$slug}/{$path}/index.html");
            }

            // SPA fallback: 不存在的路径回退到 index.html
            if (! file_exists($filePath)) {
                return redirect()->to("/sites/{$projectPrefix}/{$slug}/index.html");
            }

            if (! file_exists($filePath) || is_dir($filePath)) {
                abort(404, '文件未找到');
            }

            // 安全检查：防止路径遍历
            $realPath = realpath($filePath);
            $realDist = realpath($distDir);
            if (! $realPath || ! $realDist || strpos($realPath, $realDist) !== 0) {
                abort(403, '访问被拒绝');
            }

            $mimeType = $this->getMimeType($filePath);

            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=86400',
            ]);
        } catch (\Exception $e) {
            Log::error('PageHosting serve error: '.$e->getMessage());
            if ($e->getCode() >= 400 && $e->getCode() < 500) {
                throw $e;
            }
            abort(500, '服务器错误');
        }
    }

    private function getMimeType(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $map = [
            'html' => 'text/html; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
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
        ];

        return $map[$ext] ?? 'application/octet-stream';
    }
}
