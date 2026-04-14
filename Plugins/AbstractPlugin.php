<?php

namespace Plugins;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * 插件抽象基类
 * 提供默认实现，子类可选择性覆盖
 */
abstract class AbstractPlugin implements PluginInterface
{
    /**
     * 插件配置
     */
    protected array $config;

    /**
     * 插件基础路径
     */
    protected string $basePath;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->basePath = $this->getBasePath();
        $this->config = $this->loadConfig();

        if (! $this->validateConfig()) {
            throw new \RuntimeException("Invalid plugin config for {$this->basePath}");
        }
    }

    /**
     * 获取插件基础路径
     */
    protected function getBasePath(): string
    {
        $reflection = new \ReflectionClass($this);

        return dirname($reflection->getFileName());
    }

    /**
     * 加载插件配置
     *
     * @throws \RuntimeException
     */
    protected function loadConfig(): array
    {
        $configPath = $this->basePath.'/plugin.json';

        if (! File::exists($configPath)) {
            throw new \RuntimeException("Plugin config not found: {$configPath}");
        }

        $content = File::get($configPath);
        $config = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid plugin config JSON: '.json_last_error_msg());
        }

        return $config;
    }

    /**
     * {@inheritDoc}
     */
    public function getId(): string
    {
        return $this->config['id'] ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->config['name'] ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(): string
    {
        return $this->config['version'] ?? 'v1';
    }

    /**
     * {@inheritDoc}
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * {@inheritDoc}
     * 默认实现：返回成功
     */
    public function install(string $projectPrefix): bool
    {
        Log::info("Plugin {$this->getId()} installed to project {$projectPrefix}");

        return true;
    }

    /**
     * {@inheritDoc}
     * 默认实现：返回成功
     */
    public function uninstall(string $projectPrefix): bool
    {
        Log::info("Plugin {$this->getId()} uninstalled from project {$projectPrefix}");

        return true;
    }

    /**
     * {@inheritDoc}
     * 默认实现：返回成功
     */
    public function enable(string $projectPrefix): bool
    {
        Log::info("Plugin {$this->getId()} enabled for project {$projectPrefix}");

        return true;
    }

    /**
     * {@inheritDoc}
     * 默认实现：返回成功
     */
    public function disable(string $projectPrefix): bool
    {
        Log::info("Plugin {$this->getId()} disabled for project {$projectPrefix}");

        return true;
    }

    /**
     * {@inheritDoc}
     * 默认实现：返回成功
     */
    public function upgrade(string $fromVersion): bool
    {
        Log::info("Plugin {$this->getId()} upgraded from {$fromVersion} to {$this->getVersion()}");

        return true;
    }

    /**
     * {@inheritDoc}
     * 默认实现：空操作
     */
    public function registerRoutes(): void
    {
        // 子类可覆盖此方法注册路由
    }

    /**
     * {@inheritDoc}
     * 默认实现：空操作
     */
    public function registerListeners(): void
    {
        // 子类可覆盖此方法注册事件监听
    }

    /**
     * {@inheritDoc}
     * 默认实现：空操作
     */
    public function onBeforeInstall(string $projectPrefix): void
    {
        // 子类可覆盖此方法
    }

    /**
     * {@inheritDoc}
     * 默认实现：空操作
     */
    public function onAfterInstall(string $projectPrefix): void
    {
        // 子类可覆盖此方法
    }

    /**
     * {@inheritDoc}
     * 默认实现：空操作
     */
    public function onBeforeUninstall(string $projectPrefix): void
    {
        // 子类可覆盖此方法
    }

    /**
     * {@inheritDoc}
     * 默认实现：空操作
     */
    public function onAfterUninstall(string $projectPrefix): void
    {
        // 子类可覆盖此方法
    }

    /**
     * 获取插件资源路径
     *
     * @param  string  $path  相对路径
     */
    protected function getResourcePath(string $path = ''): string
    {
        return $this->basePath.($path ? '/'.ltrim($path, '/') : '');
    }

    /**
     * 检查插件配置有效性
     */
    protected function validateConfig(): bool
    {
        $required = ['id', 'name', 'version'];

        foreach ($required as $field) {
            if (empty($this->config[$field])) {
                Log::error("Plugin config missing required field: {$field}");

                return false;
            }
        }

        $version = (string) ($this->config['version'] ?? '');
        if (! preg_match('/^v\d+$/', $version)) {
            Log::error('Plugin version must use vN format', ['path' => $this->basePath, 'version' => $version]);

            return false;
        }

        $pluginDirName = basename(dirname($this->basePath));
        $expectedId = $pluginDirName.'_'.$version;
        $actualId = (string) ($this->config['id'] ?? '');

        if ($actualId !== $expectedId) {
            Log::error('Plugin id does not match directory/version convention', [
                'path' => $this->basePath,
                'expected_id' => $expectedId,
                'actual_id' => $actualId,
            ]);

            return false;
        }

        return true;
    }
}
