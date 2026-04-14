<?php

namespace Plugins;

/**
 * 插件接口
 * 所有插件必须实现此接口
 */
interface PluginInterface
{
    /**
     * 获取插件ID（唯一标识）
     */
    public function getId(): string;

    /**
     * 获取插件名称
     */
    public function getName(): string;

    /**
     * 获取插件版本
     */
    public function getVersion(): string;

    /**
     * 获取插件完整配置
     */
    public function getConfig(): array;

    /**
     * 安装插件到指定项目
     *
     * @param  string  $projectPrefix  项目前缀
     */
    public function install(string $projectPrefix): bool;

    /**
     * 卸载插件
     *
     * @param  string  $projectPrefix  项目前缀
     */
    public function uninstall(string $projectPrefix): bool;

    /**
     * 启用插件
     *
     * @param  string  $projectPrefix  项目前缀
     */
    public function enable(string $projectPrefix): bool;

    /**
     * 禁用插件
     *
     * @param  string  $projectPrefix  项目前缀
     */
    public function disable(string $projectPrefix): bool;

    /**
     * 升级插件
     *
     * @param  string  $fromVersion  当前版本
     */
    public function upgrade(string $fromVersion): bool;

    /**
     * 注册路由
     */
    public function registerRoutes(): void;

    /**
     * 注册事件监听器
     */
    public function registerListeners(): void;

    /**
     * 插件安装前钩子
     */
    public function onBeforeInstall(string $projectPrefix): void;

    /**
     * 插件安装后钩子
     */
    public function onAfterInstall(string $projectPrefix): void;

    /**
     * 插件卸载前钩子
     */
    public function onBeforeUninstall(string $projectPrefix): void;

    /**
     * 插件卸载后钩子
     */
    public function onAfterUninstall(string $projectPrefix): void;
}
