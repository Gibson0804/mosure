<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * 插件市场服务
 * 用于管理插件市场的插件列表、详情和安装
 */
class PluginMarketplaceService
{
    private GiteePluginRepository $repository;

    private PluginService $pluginService;

    public function __construct(
        GiteePluginRepository $repository,
        PluginService $pluginService
    ) {
        $this->repository = $repository;
        $this->pluginService = $pluginService;
    }

    /**
     * 获取插件市场列表
     */
    public function listPlugins(): array
    {
        try {
            $pluginList = $this->repository->getPluginList();
            $result = [];

            Log::info('PluginMarketplaceService_listPlugins', ['pluginList' => $pluginList]);
            foreach ($pluginList as $plugin) {
                $pluginId = $plugin['name'] ?? '';
                if (empty($pluginId)) {
                    continue;
                }

                $detail = $this->repository->getPluginDetail($pluginId);
                if (empty($detail)) {
                    continue;
                }

                // 检查是否已安装
                $isInstalled = $this->pluginService->isInstalled($pluginId);
                $installedVersion = $isInstalled ? $this->pluginService->getInstalledVersion($pluginId) : null;

                // 检查是否已下载到 Plugins 目录
                $isDownloaded = $this->isPluginDownloaded($pluginId, $detail['latest']);
                $downloadedVersion = $this->getDownloadedVersion($pluginId);

                $result[] = array_merge($detail, [
                    'is_installed' => $isInstalled,
                    'installed_version' => $installedVersion,
                    'is_downloaded' => $isDownloaded,
                    'downloaded_version' => $downloadedVersion,
                    'can_update' => $installedVersion && version_compare($detail['latest'], $installedVersion, '>'),
                    'can_upgrade_download' => $downloadedVersion && version_compare($detail['latest'], $downloadedVersion, '>'),
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('Error listing marketplace plugins', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 获取插件详情
     *
     * @param  string  $pluginId  插件ID
     */
    public function getPluginDetail(string $pluginId): array
    {
        try {
            $detail = $this->repository->getPluginDetail($pluginId);

            if (empty($detail)) {
                return [
                    'error' => 'Plugin not found',
                    'code' => 404,
                ];
            }

            // 检查是否已安装
            $isInstalled = $this->pluginService->isInstalled($pluginId);
            $installedVersion = $isInstalled ? $this->pluginService->getInstalledVersion($pluginId) : null;

            return array_merge($detail, [
                'is_installed' => $isInstalled,
                'installed_version' => $installedVersion,
                'can_update' => $installedVersion && version_compare($detail['latest'], $installedVersion, '>'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error fetching plugin detail', [
                'plugin_id' => $pluginId,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    /**
     * 搜索插件
     *
     * @param  string  $keyword  关键词
     */
    public function searchPlugins(string $keyword): array
    {
        try {
            $plugins = $this->listPlugins();

            if (empty($keyword)) {
                return $plugins;
            }

            $keyword = strtolower($keyword);

            return array_filter($plugins, function ($plugin) use ($keyword) {
                $name = strtolower($plugin['name'] ?? '');
                $description = strtolower($plugin['description'] ?? '');
                $author = strtolower($plugin['author'] ?? '');

                return strpos($name, $keyword) !== false ||
                       strpos($description, $keyword) !== false ||
                       strpos($author, $keyword) !== false;
            });
        } catch (\Throwable $e) {
            Log::error('Error searching plugins', [
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 安装插件
     *
     * @param  string  $pluginId  插件ID
     * @param  string  $version  版本
     */
    public function installPlugin(string $pluginId, ?string $version = null): array
    {
        try {
            // 检查是否已安装
            if ($this->pluginService->isInstalled($pluginId)) {
                return [
                    'success' => false,
                    'message' => 'Plugin already installed',
                    'code' => 400,
                ];
            }

            // 获取插件详情
            $detail = $this->repository->getPluginDetail($pluginId);

            if (empty($detail)) {
                return [
                    'success' => false,
                    'message' => 'Plugin not found',
                    'code' => 404,
                ];
            }

            // 使用最新版本（如果未指定）
            $version = $version ?? $detail['latest'];

            if (! in_array($version, $detail['versions'] ?? [])) {
                return [
                    'success' => false,
                    'message' => 'Invalid version',
                    'code' => 400,
                ];
            }

            // TODO: 实现从 Gitee 下载并安装插件
            // 这里需要实现下载逻辑，然后调用 PluginService::install()

            return [
                'success' => false,
                'message' => 'Plugin installation not implemented yet',
                'code' => 501,
            ];
        } catch (\Throwable $e) {
            Log::error('Error installing plugin', [
                'plugin_id' => $pluginId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    /**
     * 更新插件
     *
     * @param  string  $pluginId  插件ID
     * @param  string  $version  版本
     */
    public function updatePlugin(string $pluginId, ?string $version = null): array
    {
        try {
            // 检查是否已安装
            if (! $this->pluginService->isInstalled($pluginId)) {
                return [
                    'success' => false,
                    'message' => 'Plugin not installed',
                    'code' => 400,
                ];
            }

            // 获取插件详情
            $detail = $this->repository->getPluginDetail($pluginId);

            if (empty($detail)) {
                return [
                    'success' => false,
                    'message' => 'Plugin not found',
                    'code' => 404,
                ];
            }

            $installedVersion = $this->pluginService->getInstalledVersion($pluginId);

            if (version_compare($detail['latest'], $installedVersion, '<=')) {
                return [
                    'success' => false,
                    'message' => 'Plugin is already up to date',
                    'code' => 400,
                ];
            }

            // 使用最新版本（如果未指定）
            $version = $version ?? $detail['latest'];

            // TODO: 实现更新逻辑

            return [
                'success' => false,
                'message' => 'Plugin update not implemented yet',
                'code' => 501,
            ];
        } catch (\Throwable $e) {
            Log::error('Error updating plugin', [
                'plugin_id' => $pluginId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 500,
            ];
        }
    }

    /**
     * 检查插件更新
     */
    public function checkUpdates(): array
    {
        try {
            $plugins = $this->listPlugins();
            $updates = [];

            foreach ($plugins as $plugin) {
                if ($plugin['is_installed'] && $plugin['can_update']) {
                    $updates[] = [
                        'id' => $plugin['id'],
                        'name' => $plugin['name'],
                        'current_version' => $plugin['installed_version'],
                        'latest_version' => $plugin['latest'],
                    ];
                }
            }

            return $updates;
        } catch (\Throwable $e) {
            Log::error('Error checking updates', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * 检查插件是否已下载到 Plugins 目录
     */
    private function isPluginDownloaded(string $pluginId, string $version): bool
    {
        $pluginPath = base_path("Plugins/{$pluginId}/{$version}");

        return is_dir($pluginPath) && file_exists($pluginPath.'/plugin.json');
    }

    /**
     * 获取已下载插件的版本
     */
    private function getDownloadedVersion(string $pluginId): ?string
    {
        $pluginDir = base_path("Plugins/{$pluginId}");

        if (! is_dir($pluginDir)) {
            return null;
        }

        // 获取所有版本目录
        $versions = [];
        $items = scandir($pluginDir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $versionPath = $pluginDir.'/'.$item;
            if (is_dir($versionPath) && file_exists($versionPath.'/plugin.json')) {
                $versions[] = $item;
            }
        }

        if (empty($versions)) {
            return null;
        }

        // 返回最新版本
        usort($versions, function ($a, $b) {
            return version_compare($b, $a);
        });

        return $versions[0];
    }

    /**
     * 下载插件到 Plugins 目录
     */
    public function downloadPlugin(string $pluginId, ?string $version = null): array
    {
        try {
            $detail = $this->repository->getPluginDetail($pluginId);

            if (empty($detail)) {
                return [
                    'success' => false,
                    'message' => 'Plugin not found',
                    'code' => 404,
                ];
            }

            // 使用最新版本（如果未指定）
            $version = $version ?? $detail['latest'];

            // 下载插件
            $result = $this->repository->downloadPlugin($pluginId, $version);

            return $result;
        } catch (\Throwable $e) {
            Log::error('Error downloading plugin', [
                'plugin_id' => $pluginId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 500,
            ];
        }
    }
}
