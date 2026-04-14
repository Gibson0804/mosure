<?php

namespace App\Constants;

class CacheKeys
{
    /**
     * Gitee 插件市场相关缓存 key
     */
    const GITEE_PLUGIN_LIST = 'gitee_plugin_list';

    const GITEE_PLUGIN_DETAIL_PREFIX = 'gitee_plugin_detail_';

    const GITEE_PLUGIN_DETAIL_KEYS = 'gitee_plugin_detail_keys';

    /**
     * 获取插件详情缓存 key
     */
    public static function getPluginDetailKey(string $pluginId): string
    {
        return self::GITEE_PLUGIN_DETAIL_PREFIX.$pluginId;
    }
}
