<?php

namespace App\Providers;

use App\Repositories\PluginInstallRecordRepository;
use App\Repositories\PluginRepository;
use App\Repository\ApiKeyRepository;
use App\Services\PluginService;
use Illuminate\Support\ServiceProvider;

/**
 * 插件服务提供者
 */
class PluginServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 注册插件仓库
        $this->app->singleton(PluginRepository::class, function ($app) {
            return new PluginRepository;
        });

        // 注册插件安装记录仓库
        $this->app->singleton(PluginInstallRecordRepository::class, function ($app) {
            return new PluginInstallRecordRepository;
        });

        // 注册插件服务为单例
        $this->app->singleton(PluginService::class, function ($app) {
            $service = new PluginService(
                $app->make(PluginRepository::class),
                $app->make(PluginInstallRecordRepository::class),
                $app->make(ApiKeyRepository::class)
            );
            $service->discover(); // 自动发现插件

            return $service;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 这里可以添加插件路由注册等启动逻辑
        $pluginService = $this->app->make(PluginService::class);

        foreach ($pluginService->all() as $plugin) {
            // 注册插件路由
            $plugin->registerRoutes();

            // 注册插件事件监听
            $plugin->registerListeners();
        }
    }
}
