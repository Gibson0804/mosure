// 插件前端配置（由后台在安装/更新插件时自动注入真实值）
// 占位符 {$domain} 和 {$apiKey} 会在安装时被 PluginService::installFrontend 替换。
const API_CONFIG = {
    BASE_URL: '{$domain}',
    API_KEY: '{$apiKey}',
};

