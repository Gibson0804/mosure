<?php

namespace App\Repositories;

use App\Models\PluginInstallRecord;

/**
 * 插件安装记录仓库
 */
class PluginInstallRecordRepository
{
    /**
     * 创建安装记录
     */
    public function create(array $data): PluginInstallRecord
    {
        return PluginInstallRecord::create($data);
    }

    /**
     * 更新记录状态
     */
    public function updateStatus(int $id, string $status, ?string $errorMessage = null): bool
    {
        $data = ['status' => $status];
        if ($errorMessage) {
            $data['error_message'] = $errorMessage;
        }

        return PluginInstallRecord::where('id', $id)->update($data) > 0;
    }

    /**
     * 获取插件的所有安装记录
     */
    public function getByPluginId(string $pluginId)
    {
        return PluginInstallRecord::where('plugin_id', $pluginId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 获取插件的成功记录（用于卸载）
     */
    public function getSuccessRecords(string $pluginId)
    {
        return PluginInstallRecord::where('plugin_id', $pluginId)
            ->where('status', PluginInstallRecord::STATUS_SUCCESS)
            ->where('operation', PluginInstallRecord::OP_CREATE)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 删除插件的所有记录
     */
    public function deleteByPluginId(string $pluginId): bool
    {
        return PluginInstallRecord::where('plugin_id', $pluginId)->delete() > 0;
    }

    /**
     * 批量创建记录
     */
    public function createBatch(array $records): bool
    {
        return PluginInstallRecord::insert($records);
    }
}
