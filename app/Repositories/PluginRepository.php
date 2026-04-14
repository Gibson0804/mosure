<?php

namespace App\Repositories;

use App\Models\PluginInstallation;

class PluginRepository
{
    /**
     * 获取项目已安装的插件列表
     *
     * @return \Illuminate\Support\Collection
     */
    public function getInstalledPlugins()
    {
        return PluginInstallation::all();
    }

    /**
     * 检查插件是否已安装
     */
    public function isInstalled(string $pluginId): bool
    {
        return PluginInstallation::where('plugin_id', $pluginId)->exists();
    }

    /**
     * 获取插件安装信息
     */
    public function getInstallation(string $pluginId): ?PluginInstallation
    {
        return PluginInstallation::where('plugin_id', $pluginId)->first();
    }

    /**
     * 记录插件安装
     */
    public function recordInstallation(string $pluginId, string $version, array $config = []): PluginInstallation
    {
        return PluginInstallation::create([
            'plugin_id' => $pluginId,
            'plugin_version' => $version,
            'status' => 'enabled',
            'config' => $config,
            'installed_at' => now(),
        ]);
    }

    /**
     * 删除插件安装记录
     */
    public function removeInstallation(string $pluginId): bool
    {
        return PluginInstallation::where('plugin_id', $pluginId)->delete() > 0;
    }

    /**
     * 更新插件状态
     */
    public function updateStatus(string $pluginId, string $status): bool
    {
        return PluginInstallation::where('plugin_id', $pluginId)
            ->update(['status' => $status, 'updated_at' => now()]) > 0;
    }

    /**
     * 更新插件配置
     */
    public function updateConfig(string $pluginId, array $config): bool
    {
        return PluginInstallation::where('plugin_id', $pluginId)
            ->update(['config' => $config, 'updated_at' => now()]) > 0;
    }
}
