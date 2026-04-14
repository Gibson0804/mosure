<?php

namespace App\Services;

use App\Constants\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gitee 插件仓库服务
 * 用于从 Gitee 仓库读取插件信息
 */
class GiteePluginRepository
{
    private string $apiUrl;

    private ?string $accessToken;

    private int $cacheTtl;

    private string $owner;

    private string $repo;

    private string $branch;

    private bool $enabled;

    public function __construct()
    {
        $config = config('plugin.marketplace.repository', []);

        $this->enabled = (bool) config('plugin.marketplace.enabled', false);
        $this->owner = trim((string) ($config['owner'] ?? ''));
        $this->repo = trim((string) ($config['repo'] ?? ''));
        $this->branch = $config['branch'] ?? 'master';

        $this->apiUrl = $this->owner !== '' && $this->repo !== ''
            ? "https://gitee.com/api/v5/repos/{$this->owner}/{$this->repo}/contents"
            : '';
        $this->accessToken = $config['access_token'] ?? null;
        $this->cacheTtl = config('plugin.marketplace.cache.index_ttl', 3600);
    }

    /**
     * 获取插件列表
     */
    public function getPluginList(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return Cache::remember(CacheKeys::GITEE_PLUGIN_LIST, $this->cacheTtl, function () {
            try {
                $response = $this->request('GET', '/');

                Log::info('Gitee plugin list response', ['response' => $response]);

                if (! $response['success']) {
                    Log::error('Failed to get plugin list from Gitee', [
                        'error' => $response['error'] ?? 'Unknown error',
                    ]);

                    return [];
                }

                // 过滤出文件夹（插件目录）
                $plugins = array_filter($response['data'], function ($item) {
                    return ($item['type'] ?? '') === 'dir';
                });

                return array_values($plugins);
            } catch (\Throwable $e) {
                Log::error('Error fetching plugin list from Gitee', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return [];
            }
        });
    }

    private function isConfigured(): bool
    {
        return $this->enabled && $this->owner !== '' && $this->repo !== '';
    }

    /**
     * 获取插件详情
     *
     * @param  string  $pluginId  插件ID
     */
    public function getPluginDetail(string $pluginId): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $cacheKey = CacheKeys::getPluginDetailKey($pluginId);

        Log::info('Getting plugin detail', ['plugin_id' => $pluginId]);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($pluginId) {
            try {
                // 记录缓存 key 用于后续清除
                $detailKeys = Cache::get(CacheKeys::GITEE_PLUGIN_DETAIL_KEYS, []);
                if (! in_array($pluginId, $detailKeys)) {
                    $detailKeys[] = $pluginId;
                    Cache::put(CacheKeys::GITEE_PLUGIN_DETAIL_KEYS, $detailKeys, $this->cacheTtl * 10);
                }
                // 读取插件根目录的文件列表
                $response = $this->request('GET', "/{$pluginId}");

                Log::info('Gitee plugin root response', [
                    'plugin_id' => $pluginId,
                    'response' => $response,
                ]);

                if (! $response['success']) {
                    Log::error('Failed to get plugin root from Gitee', [
                        'plugin_id' => $pluginId,
                        'error' => $response['error'] ?? 'Unknown error',
                    ]);

                    return [];
                }

                // 查找版本目录（如 v1）
                $versionDir = null;
                foreach ($response['data'] as $item) {
                    if (($item['type'] ?? '') === 'dir' && preg_match('/^v\d+$/', $item['name'] ?? '')) {
                        $versionDir = $item;
                        break;
                    }
                }

                if (! $versionDir) {
                    Log::warning('No version directory found', ['plugin_id' => $pluginId]);

                    return [];
                }

                $versionName = $versionDir['name'] ?? '';
                Log::info('Found version directory', [
                    'plugin_id' => $pluginId,
                    'version' => $versionName,
                ]);

                // 读取版本目录的文件列表
                $versionPath = "{$pluginId}/{$versionName}";
                $versionResponse = $this->request('GET', "/{$versionPath}");

                Log::info('Gitee plugin version response', [
                    'plugin_id' => $pluginId,
                    'version' => $versionName,
                    'response' => $versionResponse,
                ]);

                if (! $versionResponse['success']) {
                    Log::error('Failed to get version directory from Gitee', [
                        'plugin_id' => $pluginId,
                        'version' => $versionName,
                        'error' => $versionResponse['error'] ?? 'Unknown error',
                    ]);

                    return [];
                }

                // 查找 plugin.json 文件
                $pluginJson = null;
                foreach ($versionResponse['data'] as $item) {
                    if (($item['name'] ?? '') === 'plugin.json') {
                        $pluginJson = $item;
                        break;
                    }
                }

                if (! $pluginJson) {
                    Log::warning('plugin.json not found in version directory', [
                        'plugin_id' => $pluginId,
                        'version' => $versionName,
                    ]);

                    return [];
                }

                Log::info('Found plugin.json', [
                    'plugin_id' => $pluginId,
                    'version' => $versionName,
                    'path' => $pluginJson['path'] ?? $versionPath,
                ]);

                // 读取 plugin.json 内容
                $content = $this->getFileContent($pluginJson['path'] ?? $versionPath);

                if (! $content) {
                    Log::error('Failed to read plugin.json', [
                        'plugin_id' => $pluginId,
                        'version' => $versionName,
                    ]);

                    return [];
                }

                Log::info('Read plugin.json content', [
                    'plugin_id' => $pluginId,
                    'version' => $versionName,
                    'content_length' => strlen($content),
                ]);

                $config = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error('Invalid plugin.json', [
                        'plugin_id' => $pluginId,
                        'version' => $versionName,
                        'error' => json_last_error_msg(),
                    ]);

                    return [];
                }

                Log::info('Parsed plugin config', [
                    'plugin_id' => $pluginId,
                    'version' => $versionName,
                    'config' => $config,
                ]);

                // 获取版本列表
                $versions = $this->getPluginVersions($pluginId);

                Log::info('Got plugin versions', [
                    'plugin_id' => $pluginId,
                    'versions' => $versions,
                ]);

                $detail = [
                    'id' => $pluginId,
                    'name' => $config['name'] ?? $pluginId,
                    'description' => $config['description'] ?? '',
                    'author' => $config['author'] ?? '',
                    'version' => $config['version'] ?? 'v1',
                    'versions' => $versions,
                    'latest' => $versions[0] ?? 'v1',
                    'icon' => $config['icon'] ?? null,
                    'homepage' => $config['homepage'] ?? null,
                    'tags' => $config['tags'] ?? [],
                    'has_frontend' => $config['has_frontend'] ?? false,
                    'has_src' => $config['has_src'] ?? false,
                    'snapshot' => $config['snapshot'] ?? false,
                    'snapshot_images' => [],
                    'provides' => [
                        'models_count' => isset($config['provides']['models']) ? count($config['provides']['models']) : 0,
                        'web_functions_count' => isset($config['provides']['functions']['endpoints']) ? count($config['provides']['functions']['endpoints']) : 0,
                        'trigger_functions_count' => isset($config['provides']['functions']['hooks']) ? count($config['provides']['functions']['hooks']) : 0,
                        'menus' => isset($config['provides']['menus']) && ! empty($config['provides']['menus']),
                        'variables' => isset($config['provides']['functions']['variables']) && $config['provides']['functions']['variables'],
                        'schedules' => isset($config['provides']['functions']['schedules']) && $config['provides']['functions']['schedules'],
                    ],
                    'config' => $config,
                ];

                // 如果有 snapshot 字段，读取 snapshot 目录下的图片
                if (! empty($detail['snapshot']) && ! empty($detail['version'])) {
                    $snapshotImages = $this->getSnapshotImages($pluginId, $detail['version']);
                    $detail['snapshot_images'] = $snapshotImages;
                }

                Log::info('Returning plugin detail', [
                    'plugin_id' => $pluginId,
                    'detail' => $detail,
                ]);

                return $detail;
            } catch (\Throwable $e) {
                Log::error('Error fetching plugin detail from Gitee', [
                    'plugin_id' => $pluginId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return [];
            }
        });
    }

    /**
     * 获取插件的版本列表
     *
     * @param  string  $pluginId  插件ID
     */
    public function getPluginVersions(string $pluginId): array
    {
        try {
            $response = $this->request('GET', "/{$pluginId}");

            if (! $response['success']) {
                return [];
            }

            // 查找所有文件夹（版本目录）
            $versions = [];
            foreach ($response['data'] as $item) {
                if (($item['type'] ?? '') === 'dir') {
                    $name = $item['name'] ?? '';
                    // 版本目录格式：v1, v2 等
                    if (preg_match('/^v\d+$/', $name)) {
                        $versions[] = $name;
                    }
                }
            }

            // 按版本号降序排序
            usort($versions, function ($a, $b) {
                $aNum = (int) substr($a, 1);
                $bNum = (int) substr($b, 1);

                return $bNum <=> $aNum;
            });

            return $versions;
        } catch (\Throwable $e) {
            Log::error('Error fetching plugin versions', [
                'plugin_id' => $pluginId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 获取文件内容
     *
     * @param  string  $path  文件路径
     */
    public function getFileContent(string $path): ?string
    {
        try {
            $response = $this->request('GET', "/{$path}");

            if (! $response['success']) {
                return null;
            }

            $content = $response['data']['content'] ?? '';

            // Base64 解码
            return base64_decode($content);
        } catch (\Throwable $e) {
            Log::error('Error fetching file content', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 获取插件目录下的所有文件列表
     */
    private function getPluginFiles(string $pluginId, string $version, string $subPath = ''): array
    {
        try {
            $path = $subPath ? "{$pluginId}/{$version}/{$subPath}" : "{$pluginId}/{$version}";
            $response = $this->request('GET', "/{$path}");

            if (! $response['success']) {
                return [];
            }

            $items = $response['data'];
            $files = [];

            foreach ($items as $item) {
                $relativePath = $subPath ? "{$subPath}/{$item['name']}" : $item['name'];

                if ($item['type'] === 'file') {
                    $files[] = [
                        'path' => $relativePath,
                        'name' => $item['name'],
                    ];
                } elseif ($item['type'] === 'dir') {
                    // 递归获取子目录文件
                    $subFiles = $this->getPluginFiles($pluginId, $version, $relativePath);
                    $files = array_merge($files, $subFiles);
                }
            }

            return $files;
        } catch (\Throwable $e) {
            Log::error('Error getting plugin files', [
                'plugin_id' => $pluginId,
                'version' => $version,
                'sub_path' => $subPath,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 获取 snapshot 目录下的图片列表
     */
    private function getSnapshotImages(string $pluginId, string $version): array
    {
        try {
            $snapshotPath = "{$pluginId}/{$version}/snapshot";
            $response = $this->request('GET', "/{$snapshotPath}");

            if (! $response['success']) {
                return [];
            }

            $items = $response['data'];
            $images = [];

            foreach ($items as $item) {
                if ($item['type'] === 'file') {
                    $name = $item['name'];
                    // 检查是否是图片文件
                    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $images[] = [
                            'name' => $name,
                            'path' => "snapshot/{$name}",
                            'url' => $this->getSnapshotImageUrl($pluginId, $version, $name),
                        ];
                    }
                }
            }

            // 按文件名排序
            usort($images, function ($a, $b) {
                return strnatcmp($a['name'], $b['name']);
            });

            return $images;
        } catch (\Throwable $e) {
            Log::error('Error getting snapshot images', [
                'plugin_id' => $pluginId,
                'version' => $version,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 获取 snapshot 图片的 URL
     * 使用 Gitee API 获取文件内容并转换为 base64
     */
    private function getSnapshotImageUrl(string $pluginId, string $version, string $imageName): string
    {
        // 返回 API 路径而不是直接 URL
        return "/plugins/marketplace/snapshot-image?plugin_id={$pluginId}&version={$version}&image={$imageName}";
    }

    /**
     * 获取 snapshot 图片内容
     */
    public function getSnapshotImageContent(string $pluginId, string $version, string $imageName): ?string
    {
        try {
            $path = "{$pluginId}/{$version}/snapshot/{$imageName}";
            $response = $this->request('GET', "/{$path}");

            if (! $response['success']) {
                return null;
            }

            // Gitee API 返回 content 是 base64 编码的
            $content = $response['data']['content'] ?? null;
            if ($content) {
                // Gitee API 的 content 是 base64 编码的
                return base64_decode($content);
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('Error getting snapshot image content', [
                'plugin_id' => $pluginId,
                'version' => $version,
                'image' => $imageName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 下载插件到本地 Plugins 目录
     *
     * @param  string  $pluginId  插件ID
     * @param  string  $version  版本号
     * @return array ['success' => bool, 'message' => string, 'path' => string|null]
     */
    public function downloadPlugin(string $pluginId, string $version): array
    {
        try {
            $pluginPath = base_path("Plugins/{$pluginId}/{$version}");

            // 检查目录是否已存在
            if (is_dir($pluginPath)) {
                return [
                    'success' => true,
                    'message' => '插件已存在',
                    'path' => $pluginPath,
                    'already_exists' => true,
                ];
            }

            // 创建临时目录
            $tempDir = storage_path('app/temp/plugin_downloads');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempZipPath = $tempDir.'/'.$pluginId.'_'.$version.'_'.time().'.zip';

            // 下载插件目录的所有文件
            $files = $this->getPluginFiles($pluginId, $version);

            if (empty($files)) {
                return [
                    'success' => false,
                    'message' => '插件文件列表为空',
                ];
            }

            // 创建目标目录
            if (! is_dir($pluginPath)) {
                mkdir($pluginPath, 0755, true);
            }

            // 下载每个文件
            $downloadedCount = 0;
            foreach ($files as $file) {
                $filePath = "{$pluginId}/{$version}/{$file['path']}";
                $content = $this->getFileContent($filePath);

                if ($content !== null) {
                    $targetPath = $pluginPath.'/'.$file['path'];
                    $targetDir = dirname($targetPath);

                    if (! is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }

                    file_put_contents($targetPath, $content);
                    $downloadedCount++;
                }
            }

            Log::info('Plugin downloaded', [
                'plugin_id' => $pluginId,
                'version' => $version,
                'files_count' => count($files),
                'downloaded_count' => $downloadedCount,
            ]);

            return [
                'success' => true,
                'message' => "插件下载成功，共 {$downloadedCount} 个文件",
                'path' => $pluginPath,
                'files_count' => $downloadedCount,
            ];
        } catch (\Throwable $e) {
            Log::error('Error downloading plugin', [
                'plugin_id' => $pluginId,
                'version' => $version,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => '下载失败: '.$e->getMessage(),
            ];
        }
    }

    /**
     * 发送 HTTP 请求到 Gitee API
     *
     * @param  string  $method  HTTP 方法
     * @param  string  $path  API 路径
     */
    private function request(string $method, string $path): array
    {
        $url = $this->apiUrl.$path;

        $headers = [
            'Accept' => 'application/json',
        ];

        if ($this->accessToken) {
            $headers['Authorization'] = 'token '.$this->accessToken;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->get($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 清除缓存
     */
    public function clearCache(): void
    {
        Cache::forget('gitee_plugin_list');
    }
}
