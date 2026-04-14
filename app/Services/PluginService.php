<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\FunctionEnv;
use App\Models\Mold;
use App\Models\PluginInstallRecord;
use App\Models\ProjectCron;
use App\Models\ProjectFunction;
use App\Models\ProjectMenu;
use App\Models\ProjectTrigger;
use App\Repositories\PluginInstallRecordRepository;
use App\Repositories\PluginRepository;
use App\Repository\ApiKeyRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Plugins\PluginInterface;

/**
 * 插件服务
 * 负责插件的发现、加载、安装、卸载等核心功能
 */
class PluginService
{
    /**
     * 已加载的插件实例
     *
     * @var array<string, PluginInterface>
     */
    private array $plugins = [];

    /**
     * 插件仓库
     */
    private PluginRepository $repository;

    /**
     * 插件安装记录仓库
     */
    private PluginInstallRecordRepository $recordRepository;

    /**
     * API Key 仓库
     */
    private ApiKeyRepository $apiKeyRepository;

    /**
     * 插件缓存键前缀
     */
    private const CACHE_PREFIX = 'plugins:';

    /**
     * 构造函数
     */
    public function __construct(PluginRepository $repository, PluginInstallRecordRepository $recordRepository, ApiKeyRepository $apiKeyRepository)
    {
        $this->repository = $repository;
        $this->recordRepository = $recordRepository;
        $this->apiKeyRepository = $apiKeyRepository;
    }

    /**
     * 扫描并加载所有可用插件
     */
    public function discover(): void
    {
        $pluginDirs = File::directories(base_path('Plugins'));

        foreach ($pluginDirs as $pluginDir) {
            $pluginName = basename($pluginDir);

            // 检查是否是版本化目录结构
            $versionDirs = File::directories($pluginDir);

            if (! empty($versionDirs)) {
                // 版本化目录结构：{plugin_name}/{version}/
                foreach ($versionDirs as $versionDir) {
                    $pluginClass = $this->getPluginClass($versionDir);

                    if ($pluginClass && class_exists($pluginClass)) {
                        try {
                            /** @var PluginInterface $plugin */
                            $plugin = new $pluginClass;
                            $this->plugins[$plugin->getId()] = $plugin;

                            Log::debug("Discovered plugin: {$plugin->getId()}");
                        } catch (\Exception $e) {
                            Log::error("Failed to load plugin from {$versionDir}: ".$e->getMessage());
                        }
                    }
                }
            } else {
                // 旧式目录结构：{plugin_name}/
                $pluginClass = $this->getPluginClass($pluginDir);

                if ($pluginClass && class_exists($pluginClass)) {
                    try {
                        /** @var PluginInterface $plugin */
                        $plugin = new $pluginClass;
                        $this->plugins[$plugin->getId()] = $plugin;

                        Log::debug("Discovered plugin: {$plugin->getId()}");
                    } catch (\Exception $e) {
                        Log::error("Failed to load plugin from {$pluginDir}: ".$e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * 获取插件类名
     *
     * @param  string  $dir  插件目录
     */
    private function getPluginClass(string $dir): ?string
    {
        $dirName = basename($dir);
        $parentDir = dirname($dir);
        $parentName = basename($parentDir);

        // 检查是否是版本化目录结构
        if ($parentName !== 'Plugins') {
            // 版本化目录结构：{plugin_name}/{version}/
            $pluginName = $parentName;
            $version = $dirName;

            // 如果目录名已经是 v 开头的（如 v1_0_0），直接使用
            // 否则，添加 v 前缀并转换点号
            if (strpos($version, 'v') === 0) {
                $versionNamespace = $version;
            } else {
                $versionNamespace = 'v'.str_replace('.', '_', $version);
            }

            $namespace = "Plugins\\{$pluginName}\\{$versionNamespace}";
        } else {
            // 旧式目录结构：{plugin_name}/
            $pluginName = $dirName;
            $namespace = 'Plugins\\'.str_replace('.', '_', $dirName);
        }

        // 尝试多种可能的类名
        $possibleClassNames = [
            $namespace.'\\'.$pluginName,  // 小写
            $namespace.'\\'.ucfirst($pluginName),  // 首字母大写
            $namespace.'\\'.ucfirst($pluginName).'Plugin',  // 加上 Plugin 后缀
        ];

        foreach ($possibleClassNames as $className) {
            // 检查类文件是否存在
            $classFile = $this->getClassFile($className);
            if ($classFile && File::exists($classFile)) {
                if (class_exists($className)) {
                    return $className;
                }
            }
        }

        // 如果都找不到，尝试自动扫描目录中的 PHP 文件
        $phpFiles = File::files($dir);
        foreach ($phpFiles as $phpFile) {
            if ($phpFile->getExtension() === 'php') {
                $filename = $phpFile->getFilenameWithoutExtension();
                $className = $namespace.'\\'.$filename;
                if (File::exists($phpFile->getPathname()) && class_exists($className)) {
                    return $className;
                }
            }
        }

        return null;
    }

    /**
     * 获取类文件路径
     *
     * @param  string  $className  类名
     */
    private function getClassFile(string $className): ?string
    {
        $classPath = str_replace('\\', '/', $className).'.php';
        $possiblePaths = [
            base_path($classPath),
            base_path('Plugins/'.$classPath),
        ];

        foreach ($possiblePaths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * 获取所有可用插件
     *
     * @return array<string, PluginInterface>
     */
    public function all(): array
    {
        if (empty($this->plugins)) {
            $this->discover();
        }

        return $this->plugins;
    }

    /**
     * 删除插件目录
     *
     * @param  string  $pluginId  插件ID
     *
     * @throws \Exception
     */
    public function deletePlugin(string $pluginId): bool
    {
        $pluginPath = $this->resolvePluginDirectoryById($pluginId);

        if (! File::exists($pluginPath)) {
            throw new \RuntimeException("Plugin directory not found: {$pluginPath}");
        }

        try {
            File::deleteDirectory($pluginPath);
            Log::info("Deleted plugin: {$pluginId}", ['path' => $pluginPath]);

            // 清除插件缓存
            $this->clearCache();

            // 清除已加载的插件
            if (isset($this->plugins[$pluginId])) {
                unset($this->plugins[$pluginId]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete plugin directory: {$pluginPath}", [
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to delete plugin directory: '.$e->getMessage());
        }
    }

    /**
     * 清除插件缓存
     */
    private function clearCache(): void
    {
        try {
            // 清除应用缓存
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            Log::info('Plugin cache cleared successfully');
        } catch (\Exception $e) {
            Log::warning('Failed to clear plugin cache: '.$e->getMessage());
        }
    }

    /**
     * 获取指定插件
     */
    public function get(string $pluginId): ?PluginInterface
    {
        if (empty($this->plugins)) {
            $this->discover();
        }

        return $this->plugins[$pluginId] ?? null;
    }

    /**
     * 安装插件到当前项目
     *
     * @param  string  $pluginId  插件ID
     * @return array 返回安装进度日志
     *
     * @throws \Exception
     */
    public function install(string $pluginId, array $options = []): array
    {
        $plugin = $this->get($pluginId);
        $logs = [];

        if (! $plugin) {
            throw new \RuntimeException("Plugin not found: {$pluginId}");
        }

        // 检查是否已安装
        if ($this->repository->isInstalled($pluginId)) {
            throw new \RuntimeException("Plugin {$pluginId} is already installed");
        }

        // DB::beginTransaction();

        try {
            $this->emitProgress($pluginId, 'start', '开始安装插件');
            $logs[] = ['step' => 'start', 'message' => '开始安装插件', 'status' => 'success'];

            // 安装前钩子
            $plugin->onBeforeInstall('');

            // 1. 导入模型
            $this->emitProgress($pluginId, 'models', '开始创建模型');
            $modelLogs = $this->installModels($plugin);
            $logs = array_merge($logs, $modelLogs);

            // 2. 导入云函数
            $this->emitProgress($pluginId, 'functions', '开始创建云函数');
            $functionLogs = $this->installFunctions($plugin);
            $logs = array_merge($logs, $functionLogs);

            // 3. 创建菜单
            $this->emitProgress($pluginId, 'menus', '开始创建菜单');
            $menuLogs = $this->installMenus($plugin, $options);
            $logs = array_merge($logs, $menuLogs);

            // 4. 设置变量
            $this->emitProgress($pluginId, 'variables', '开始设置变量');
            $variableLogs = $this->installVariables($plugin);
            $logs = array_merge($logs, $variableLogs);

            // 5. 设置定时任务
            $this->emitProgress($pluginId, 'schedules', '开始设置定时任务');
            $scheduleLogs = $this->installSchedules($plugin);
            $logs = array_merge($logs, $scheduleLogs);

            // 6. 导入触发器
            $this->emitProgress($pluginId, 'triggers', '开始导入触发器');
            $triggerLogs = $this->installTriggers($plugin);
            $logs = array_merge($logs, $triggerLogs);

            // 7. 导入数据
            $this->emitProgress($pluginId, 'data', '开始导入数据');
            $dataLogs = $this->installData($plugin);
            $logs = array_merge($logs, $dataLogs);

            // 8. 复制 PHP 源代码文件（如果有）
            $this->emitProgress($pluginId, 'src', '开始复制 PHP 源代码');
            $srcLogs = $this->installSrc($plugin);
            $logs = array_merge($logs, $srcLogs);

            // 9. 复制前端文件（如果有）
            $this->emitProgress($pluginId, 'frontend', '开始复制前端文件');
            $frontendLogs = $this->installFrontend($plugin);
            $logs = array_merge($logs, $frontendLogs);

            // 10. 调用插件自定义安装逻辑
            $plugin->install('');

            // 11. 记录安装信息
            $config = [
                'has_frontend' => $plugin->getConfig()['has_frontend'] ?? false,
                'has_src' => $plugin->getConfig()['has_src'] ?? false,
            ];
            $this->repository->recordInstallation($pluginId, $plugin->getVersion(), $config);

            // 安装后钩子
            $plugin->onAfterInstall('');

            // DB::commit();

            $this->emitProgress($pluginId, 'complete', '插件安装完成');
            $logs[] = ['step' => 'complete', 'message' => '插件安装完成', 'status' => 'success'];

            Log::info("Plugin {$pluginId} installed successfully");

            return $logs;
        } catch (\Exception $e) {
            // DB::rollBack();

            $errorMsg = '安装失败: '.$e->getMessage();
            $this->emitProgress($pluginId, 'error', $errorMsg);
            $logs[] = ['step' => 'error', 'message' => $errorMsg, 'status' => 'failed'];

            Log::error("Plugin installation failed: {$pluginId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 发送安装进度事件
     */
    private function emitProgress(string $pluginId, string $step, string $message): void
    {
        Event::dispatch('plugin.install.progress', [
            'plugin_id' => $pluginId,
            'step' => $step,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * 卸载插件
     *
     * @param  bool  $removeData  是否删除数据
     *
     * @throws \Exception
     */
    public function uninstall(string $pluginId, bool $removeData = false): bool
    {
        $plugin = $this->get($pluginId);

        if (! $plugin) {
            throw new \RuntimeException("Plugin not found: {$pluginId}");
        }

        if (! $this->repository->isInstalled($pluginId)) {
            throw new \RuntimeException("Plugin {$pluginId} is not installed");
        }

        // DB::beginTransaction();

        try {
            // 卸载前钩子
            $plugin->onBeforeUninstall('');

            // 调用插件自定义卸载逻辑
            $plugin->uninstall('');

            if ($removeData) {
                // 根据安装记录删除插件创建的数据
                $this->removePluginDataByRecords($pluginId);
            }

            // 删除插件的 API Key
            $this->removePluginApiKey($pluginId);

            // 删除插件的前端页面
            $this->removePluginFrontend($pluginId);

            // 删除安装记录表中的记录
            $this->recordRepository->deleteByPluginId($pluginId);

            // 删除安装信息
            $this->repository->removeInstallation($pluginId);

            // 卸载后钩子
            $plugin->onAfterUninstall('');

            // DB::commit();

            Log::info("Plugin {$pluginId} uninstalled");

            return true;
        } catch (\Exception $e) {
            // DB::rollBack();
            Log::error("Plugin uninstallation failed: {$pluginId}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 获取已安装的插件列表
     */
    public function getInstalledPlugins(): array
    {
        return $this->repository->getInstalledPlugins()->toArray();
    }

    /**
     * 检查插件是否已安装
     *
     * @param  string  $pluginId  插件ID
     */
    public function isInstalled(string $pluginId): bool
    {
        return $this->repository->isInstalled($pluginId);
    }

    /**
     * 获取已安装插件的版本
     *
     * @param  string  $pluginId  插件ID
     */
    public function getInstalledVersion(string $pluginId): ?string
    {
        $installation = $this->repository->getInstallation($pluginId);
        if (! $installation) {
            return null;
        }

        return $installation->config['version'] ?? null;
    }

    private function resolvePluginDirectoryById(string $pluginId): string
    {
        $plugin = $this->get($pluginId);

        if ($plugin) {
            $reflection = new \ReflectionClass($plugin);

            return dirname($reflection->getFileName());
        }

        $pluginDirs = File::directories(base_path('Plugins'));

        foreach ($pluginDirs as $pluginDir) {
            $candidateDirs = File::directories($pluginDir);

            if (empty($candidateDirs)) {
                $candidateDirs = [$pluginDir];
            }

            foreach ($candidateDirs as $candidateDir) {
                $configPath = $candidateDir.'/plugin.json';
                if (! File::exists($configPath)) {
                    continue;
                }

                $config = json_decode((string) File::get($configPath), true);
                if (! is_array($config)) {
                    continue;
                }

                if (($config['id'] ?? null) === $pluginId) {
                    return $candidateDir;
                }
            }
        }

        throw new \RuntimeException("Plugin not found: {$pluginId}");
    }

    /**
     * 导入模型
     *
     * @return array 返回日志
     */
    private function installModels(PluginInterface $plugin): array
    {
        $logs = [];
        $reflection = new \ReflectionClass($plugin);
        $pluginDir = dirname($reflection->getFileName());

        // 检查 models 目录是否存在
        $modelsDir = $pluginDir.'/models';
        if (! File::exists($modelsDir)) {
            Log::info("Models directory not found: {$modelsDir}");

            return $logs;
        }

        // 读取 models 目录下的所有 JSON 文件
        $modelFiles = File::files($modelsDir);
        if (empty($modelFiles)) {
            Log::info("No model files found in: {$modelsDir}");

            return $logs;
        }

        $moldService = app(MoldService::class);
        $pluginId = $plugin->getId();

        foreach ($modelFiles as $modelFile) {
            if ($modelFile->getExtension() !== 'json') {
                continue;
            }

            $modelPath = $modelFile->getPathname();
            $modelData = json_decode(File::get($modelPath), true);

            if (! $modelData) {
                Log::warning("Invalid model data: {$modelPath}");

                continue;
            }

            $modelName = $modelData['name'] ?? 'Unnamed Model';

            try {
                $currentPrefix = session('current_project_prefix');
                $frameworkPrefix = \App\Constants\ProjectConstants::MODEL_CONTENT_PREFIX;
                $fullPrefix = $currentPrefix.$frameworkPrefix;

                $fields = $modelData['fields'] ?? [];
                $fields = $this->convertSourceModelSlugToId($fields, $fullPrefix);

                $payload = [
                    'name' => $modelName,
                    'table_name' => $modelData['table_name'] ?? 'model_'.uniqid(),
                    'mold_type' => $modelData['mold_type'] ?? 1,
                    'fields' => $fields,
                    'settings' => $modelData['settings'] ?? [],
                    'subject_content' => json_encode($modelData['subject_content'] ?? []),
                    'list_show_fields' => json_encode($modelData['list_show_fields'] ?? []),
                    'plugin_id' => $pluginId,
                ];

                $moldId = $moldService->addForm($payload);

                // 记录安装操作
                $this->recordRepository->create([
                    'plugin_id' => $pluginId,
                    'resource_type' => PluginInstallRecord::TYPE_MODEL,
                    'resource_name' => $modelName,
                    'resource_table' => Mold::tableName(),
                    'resource_id' => $moldId,
                    'operation' => PluginInstallRecord::OP_CREATE,
                    'status' => PluginInstallRecord::STATUS_SUCCESS,
                ]);

                $logs[] = [
                    'step' => 'model',
                    'message' => "创建模型: {$modelName}",
                    'status' => 'success',
                ];

                Log::info("Model installed: {$modelName} ({$payload['table_name']})");
            } catch (\Exception $e) {
                // 记录失败
                $this->recordRepository->create([
                    'plugin_id' => $pluginId,
                    'resource_type' => PluginInstallRecord::TYPE_MODEL,
                    'resource_name' => $modelName,
                    'operation' => PluginInstallRecord::OP_CREATE,
                    'status' => PluginInstallRecord::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);

                $logs[] = [
                    'step' => 'model',
                    'message' => "创建模型失败: {$modelName} - {$e->getMessage()}",
                    'status' => 'failed',
                ];

                throw $e;
            }
        }

        return $logs;
    }

    /**
     * 导入云函数
     *
     * @return array 返回日志
     */
    private function installFunctions(PluginInterface $plugin): array
    {
        $logs = [];
        $reflection = new \ReflectionClass($plugin);
        $pluginDir = dirname($reflection->getFileName());

        // 检查 functions 目录是否存在
        $functionsDir = $pluginDir.'/functions';
        if (! File::exists($functionsDir)) {
            Log::info("Functions directory not found: {$functionsDir}");

            return $logs;
        }

        $functionService = app(CloudFunctionService::class);
        $pluginId = $plugin->getId();

        // 安装 Web 函数
        $endpointsDir = $functionsDir.'/endpoints';
        if (File::exists($endpointsDir)) {
            $endpointFiles = File::files($endpointsDir);
            foreach ($endpointFiles as $endpointFile) {
                if ($endpointFile->getExtension() !== 'json') {
                    continue;
                }
                $funcFile = 'functions/endpoints/'.$endpointFile->getFilename();
                $log = $this->installFunction($pluginDir, $funcFile, 'endpoint', $functionService, $pluginId);
                if ($log) {
                    $logs[] = $log;
                }
            }
        }

        // 安装触发函数
        $hooksDir = $functionsDir.'/hooks';
        if (File::exists($hooksDir)) {
            $hookFiles = File::files($hooksDir);
            foreach ($hookFiles as $hookFile) {
                if ($hookFile->getExtension() !== 'json') {
                    continue;
                }
                $funcFile = 'functions/hooks/'.$hookFile->getFilename();
                $log = $this->installFunction($pluginDir, $funcFile, 'hook', $functionService, $pluginId);
                if ($log) {
                    $logs[] = $log;
                }
            }
        }

        return $logs;
    }

    /**
     * 安装单个函数
     *
     * @return array|null 返回日志
     */
    private function installFunction(
        string $pluginDir,
        string $funcFile,
        string $type,
        CloudFunctionService $functionService,
        string $pluginId
    ): ?array {
        $funcPath = $pluginDir.'/'.$funcFile;

        if (! File::exists($funcPath)) {
            Log::warning("Function file not found: {$funcPath}");

            return null;
        }

        $funcData = json_decode(File::get($funcPath), true);

        if (! $funcData) {
            Log::warning("Invalid function data: {$funcPath}");

            return null;
        }

        $funcName = $funcData['name'] ?? 'Unnamed Function';
        $funcType = $type === 'endpoint' ? 'Web函数' : '触发函数';

        try {
            // 读取代码文件
            $code = $funcData['code'] ?? '';
            if (! empty($funcData['code_class'])) {
                $codeMethod = $funcData['code_method'] ?? 'main';

                // 获取方法参数名
                $reflection = new \ReflectionMethod($funcData['code_class'], $codeMethod);
                $parameters = $reflection->getParameters();
                $paramNames = [];
                foreach ($parameters as $parameter) {
                    $paramNames[] = $parameter->getName();
                }

                // 如果是1个参数就只传payload，2个参数传payload，env，3个参数传payload，env，event
                if (count($paramNames) <= 3) {
                    $code = '<?php return app('.$funcData['code_class'].'::class)->'.$codeMethod.'($payload, $env, $event);';
                } else {
                    // 按parameters的顺序传参
                    $args = [];
                    foreach ($paramNames as $paramName) {
                        $args[] = '$'.$paramName;
                    }
                    $code = '<?php return app('.$funcData['code_class'].'::class)->'.$codeMethod.'('.implode(',', $args).');';
                }
            }

            $payload = [
                'name' => $funcName,
                'slug' => $funcData['slug'] ?? 'func_'.uniqid(),
                'type' => $type,
                'runtime' => $funcData['runtime'] ?? 'php',
                'code' => $code,
                'input_schema' => ($funcData['input_schema'] ?? []),
                'output_schema' => ($funcData['output_schema'] ?? []),
                'enabled' => $funcData['enabled'] ?? true,
                'remark' => $funcData['remark'] ?? '',
            ];

            if ($type === 'endpoint') {
                $payload['http_method'] = $funcData['http_method'] ?? 'POST';
                $payload['rate_limit'] = $funcData['rate_limit'] ?? null;
                $payload['timeout_ms'] = $funcData['timeout_ms'] ?? 30000;
                $payload['max_mem_mb'] = $funcData['max_mem_mb'] ?? 128;
            }

            $result = $functionService->create($payload);
            $functionId = $result['id'] ?? null;

            // 记录安装操作
            $this->recordRepository->create([
                'plugin_id' => $pluginId,
                'resource_type' => PluginInstallRecord::TYPE_FUNCTION,
                'resource_name' => $funcName,
                'resource_table' => 'functions',
                'resource_id' => $functionId,
                'operation' => PluginInstallRecord::OP_CREATE,
                'status' => PluginInstallRecord::STATUS_SUCCESS,
                'metadata' => ['type' => $type, 'slug' => $payload['slug']],
            ]);

            Log::info("Function installed: {$funcName} ({$payload['slug']})");

            return [
                'step' => 'function',
                'message' => "创建{$funcType}: {$funcName}",
                'status' => 'success',
            ];
        } catch (\Exception $e) {
            // 记录失败
            $this->recordRepository->create([
                'plugin_id' => $pluginId,
                'resource_type' => PluginInstallRecord::TYPE_FUNCTION,
                'resource_name' => $funcName,
                'operation' => PluginInstallRecord::OP_CREATE,
                'status' => PluginInstallRecord::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 设置变量
     *
     * @return array 返回日志
     */
    private function installVariables(PluginInterface $plugin): array
    {
        $logs = [];
        $reflection = new \ReflectionClass($plugin);
        $pluginDir = dirname($reflection->getFileName());

        // 检查 functions/variables.json 是否存在
        $variablesPath = $pluginDir.'/functions/variables.json';
        if (! File::exists($variablesPath)) {
            Log::info("Variables file not found: {$variablesPath}");

            return $logs;
        }

        $variablesData = json_decode(File::get($variablesPath), true);

        if (! $variablesData) {
            Log::warning("Invalid variables data: {$variablesPath}");

            return $logs;
        }

        $pluginId = $plugin->getId();

        try {
            foreach ($variablesData as $variable) {
                $varName = $variable['name'] ?? null;
                if (! $varName) {
                    continue;
                }

                // 创建环境变量
                $env = FunctionEnv::create([
                    'name' => $varName,
                    'value' => $variable['value'] ?? '',
                    'remark' => $variable['remark'] ?? '',
                ]);

                // 记录安装操作
                $this->recordRepository->create([
                    'plugin_id' => $pluginId,
                    'resource_type' => 'variable',
                    'resource_name' => $varName,
                    'resource_table' => FunctionEnv::tableName(),
                    'resource_id' => $env->id,
                    'operation' => PluginInstallRecord::OP_CREATE,
                    'status' => PluginInstallRecord::STATUS_SUCCESS,
                ]);
            }

            $logs[] = [
                'step' => 'variable',
                'message' => '创建环境变量: '.count($variablesData).' 个',
                'status' => 'success',
            ];

            Log::info("Variables installed for plugin: {$pluginId}");
        } catch (\Exception $e) {
            throw $e;
        }

        return $logs;
    }

    /**
     * 创建菜单
     *
     * @return array 返回日志
     */
    private function installMenus(PluginInterface $plugin, array $options = []): array
    {
        $logs = [];
        $reflection = new \ReflectionClass($plugin);
        $pluginDir = dirname($reflection->getFileName());

        // 检查 menus 目录是否存在
        $menusDir = $pluginDir.'/menus';
        if (! File::exists($menusDir)) {
            Log::info("Menus directory not found: {$menusDir}");

            return $logs;
        }

        // 读取 menus 目录下的所有 JSON 文件
        $menuFiles = File::files($menusDir);
        if (empty($menuFiles)) {
            Log::info("No menu files found in: {$menusDir}");

            return $logs;
        }

        $pluginId = $plugin->getId();

        foreach ($menuFiles as $menuFile) {
            if ($menuFile->getExtension() !== 'json') {
                continue;
            }

            $menuPath = $menuFile->getPathname();
            $menuData = json_decode(File::get($menuPath), true);

            if (! $menuData) {
                Log::warning("Invalid menu data: {$menuPath}");

                continue;
            }

            try {
                $this->importMenu($menuData, $pluginId);
                $logs[] = [
                    'step' => 'menu',
                    'message' => "创建菜单: {$menuData['title']}",
                    'status' => 'success',
                ];
                Log::info("Menu installed: {$menuData['title']}");
            } catch (\Exception $e) {
                $logs[] = [
                    'step' => 'menu',
                    'message' => "创建菜单失败: {$menuData['title']} - {$e->getMessage()}",
                    'status' => 'failed',
                ];
                Log::error("Failed to install menu: {$menuData['title']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $logs;
    }

    /**
     * 导入单个菜单
     */
    private function importMenu(array $menuData, string $pluginId): void
    {

        // 创建或更新菜单
        $menu = ProjectMenu::create([
            'parent_id' => null,
            'title' => $menuData['title'],
            'key' => $this->generateUniqueMenuKey($menuData['key'] ?? 'plugin_'.$pluginId),
            'icon' => $menuData['icon'] ?? null,
            'target_type' => $menuData['target_type'] ?? 'group',
            'target_payload' => null,
            'order' => $menuData['order'] ?? 800,
            'visible' => $menuData['visible'] ?? true,
            'permission_key' => $menuData['permission_key'] ?? null,
            'area' => $menuData['area'] ?? 'admin',
            'plugin_id' => $pluginId,
            'extra' => $menuData['extra'] ?? null,
        ]);

        // 记录安装操作
        $this->recordRepository->create([
            'plugin_id' => $pluginId,
            'resource_type' => PluginInstallRecord::TYPE_MENU,
            'resource_name' => $menuData['title'],
            'resource_table' => ProjectMenu::tableName(),
            'resource_id' => $menu->id,
            'operation' => PluginInstallRecord::OP_CREATE,
            'status' => PluginInstallRecord::STATUS_SUCCESS,
        ]);

        // 导入子菜单
        if (! empty($menuData['children'])) {
            foreach ($menuData['children'] as $childData) {
                $this->importMenuChild($childData, $menu->id, $pluginId);
            }
        }
    }

    /**
     * 导入子菜单
     */
    private function importMenuChild(array $childData, int $parentId, string $pluginId): void
    {
        // 处理target_payload中的mold_slug转换为mold_id
        $targetPayload = $childData['target_payload'] ?? [];
        if (! empty($targetPayload['mold_slug'])) {
            $table_name = getMcTableName($targetPayload['mold_slug']);
            $mold = \App\Models\Mold::where('table_name', $table_name)->first();
            if ($mold) {
                $targetPayload['mold_id'] = $mold->id;
                unset($targetPayload['mold_slug']);
            }
        }

        $child = ProjectMenu::create([
            'parent_id' => $parentId,
            'title' => $childData['title'],
            'key' => $this->generateUniqueMenuKey($childData['key'] ?? 'plugin_child_'.uniqid()),
            'icon' => $childData['icon'] ?? null,
            'target_type' => $childData['target_type'] ?? 'group',
            'target_payload' => $targetPayload,
            'order' => $childData['order'] ?? 1,
            'visible' => $childData['visible'] ?? true,
            'permission_key' => $childData['permission_key'] ?? null,
            'area' => $childData['area'] ?? 'admin',
            'plugin_id' => $pluginId,
            'extra' => $childData['extra'] ?? null,
        ]);

        // 记录安装操作
        $this->recordRepository->create([
            'plugin_id' => $pluginId,
            'resource_type' => PluginInstallRecord::TYPE_MENU,
            'resource_name' => $childData['title'],
            'resource_table' => ProjectMenu::tableName(),
            'resource_id' => $child->id,
            'operation' => PluginInstallRecord::OP_CREATE,
            'status' => PluginInstallRecord::STATUS_SUCCESS,
        ]);

        // 递归导入子菜单的子菜单
        if (! empty($childData['children'])) {
            foreach ($childData['children'] as $grandchildData) {
                $this->importMenuChild($grandchildData, $child->id, $pluginId);
            }
        }
    }

    /**
     * 生成唯一的菜单 key
     */
    private function generateUniqueMenuKey(string $baseKey): string
    {
        $key = $baseKey;
        $counter = 1;

        while (ProjectMenu::where('key', $key)->exists()) {
            $key = $baseKey.'_'.$counter;
            $counter++;
        }

        return $key;
    }

    /**
     * 设置定时任务
     *
     * @return array 返回日志
     */
    private function installSchedules(PluginInterface $plugin): array
    {
        $logs = [];
        $reflection = new \ReflectionClass($plugin);
        $pluginDir = dirname($reflection->getFileName());

        // 检查 functions/schedules.json 是否存在
        $schedulesPath = $pluginDir.'/functions/schedules.json';
        if (! File::exists($schedulesPath)) {
            Log::info("Schedules file not found: {$schedulesPath}");

            return $logs;
        }

        $schedulesData = json_decode(File::get($schedulesPath), true);

        if (! $schedulesData) {
            Log::warning("Invalid schedules data: {$schedulesPath}");

            return $logs;
        }

        $pluginId = $plugin->getId();

        try {
            foreach ($schedulesData as $schedule) {
                $cronName = $schedule['name'] ?? null;
                if (! $cronName) {
                    continue;
                }

                // 查找关联的函数
                $function = null;
                if (! empty($schedule['function_slug'])) {
                    $function = ProjectFunction::where('slug', $schedule['function_slug'])->first();
                }

                // 如果找不到关联的函数，使用 function_id = 0，并强制关闭
                $functionId = $function ? $function->id : 0;

                $cron = ProjectCron::create([
                    'name' => $cronName,
                    'enabled' => false,
                    'function_id' => $functionId,
                    'schedule_type' => $schedule['schedule_type'] ?? 'cron',
                    'cron_expr' => $schedule['cron_expr'] ?? '0 0 * * *',
                    'run_at' => $schedule['run_at'] ?? null,
                    'timezone' => $schedule['timezone'] ?? 'Asia/Shanghai',
                    'payload' => json_encode($schedule['payload'] ?? []),
                    'timeout_ms' => $schedule['timeout_ms'] ?? null,
                    'max_mem_mb' => $schedule['max_mem_mb'] ?? null,
                    'remark' => $schedule['remark'] ?? '',
                    'plugin_id' => $pluginId,
                ]);
                $cronId = $cron->id;

                // 记录安装操作
                $this->recordRepository->create([
                    'plugin_id' => $pluginId,
                    'resource_type' => PluginInstallRecord::TYPE_SCHEDULE,
                    'resource_name' => $cronName,
                    'resource_table' => ProjectCron::tableName(),
                    'resource_id' => $cronId,
                    'operation' => PluginInstallRecord::OP_CREATE,
                    'status' => PluginInstallRecord::STATUS_SUCCESS,
                ]);
            }

            $logs[] = [
                'step' => 'schedule',
                'message' => '设置定时任务: '.count($schedulesData).' 个',
                'status' => 'success',
            ];

            Log::info("Schedules installed for plugin: {$pluginId}");
        } catch (\Exception $e) {
            throw $e;
        }

        return $logs;
    }

    /**
     * 导入数据
     *
     * @return array 返回日志
     */
    private function installData(PluginInterface $plugin): array
    {
        $logs = [];
        $reflection = new \ReflectionClass($plugin);
        $pluginDir = dirname($reflection->getFileName());

        // 检查 data 目录是否存在
        $dataDir = $pluginDir.'/data';
        if (! File::exists($dataDir)) {
            Log::info("Data directory not found: {$dataDir}");

            return $logs;
        }

        // 读取 data 目录下的所有子目录
        $modelDirs = File::directories($dataDir);
        if (empty($modelDirs)) {
            Log::info("No model data directories found in: {$dataDir}");

            return $logs;
        }

        $pluginId = $plugin->getId();

        foreach ($modelDirs as $modelDir) {
            $modelName = basename($modelDir);

            // 查找对应的模型
            $tableName = getMcTableName($modelName);

            // 检查表是否存在
            if (! \Illuminate\Support\Facades\Schema::hasTable($tableName)) {
                Log::warning("Table not found: {$tableName}, skipping data import");

                continue;
            }

            // 读取该模型目录下的所有 JSON 文件
            $contentFiles = File::files($modelDir);
            $count = 0;

            foreach ($contentFiles as $contentFile) {
                if ($contentFile->getExtension() !== 'json') {
                    continue;
                }

                $contentPath = $contentFile->getPathname();
                $contentData = json_decode(File::get($contentPath), true);

                if (! $contentData) {
                    Log::warning("Invalid content data: {$contentPath}");

                    continue;
                }

                try {
                    // 直接使用 DB::table 插入数据
                    // if (isset($contentData['id'])) {
                    DB::table($tableName)->insert($contentData);
                    $count++;
                    // }
                } catch (\Exception $e) {
                    Log::error("Failed to import content: {$contentPath}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($count > 0) {
                $logs[] = [
                    'step' => 'data',
                    'message' => "导入数据: {$tableName} ({$count} 条)",
                    'status' => 'success',
                ];
                Log::info("Data imported for model: {$tableName} ({$count} records)");
            }
        }

        return $logs;
    }

    /**
     * 导入触发器
     *
     * @return array 返回日志
     */
    private function installTriggers(PluginInterface $plugin): array
    {
        $logs = [];
        $reflection = new \ReflectionClass($plugin);
        $pluginDir = dirname($reflection->getFileName());

        // 检查 functions/triggers.json 是否存在
        $triggersPath = $pluginDir.'/functions/triggers.json';
        if (! File::exists($triggersPath)) {
            Log::info("Triggers file not found: {$triggersPath}");

            return $logs;
        }

        $triggersData = json_decode(File::get($triggersPath), true);

        if (! $triggersData) {
            Log::warning("Invalid triggers data: {$triggersPath}");

            return $logs;
        }

        $pluginId = $plugin->getId();

        try {
            foreach ($triggersData as $trigger) {
                $triggerName = $trigger['name'] ?? null;
                if (! $triggerName) {
                    continue;
                }

                // 查找关联的函数
                $function = null;
                if (! empty($trigger['action_function_slug'])) {
                    $function = ProjectFunction::where('slug', $trigger['action_function_slug'])->first();
                }

                // 查找关联的模型
                $mold = null;
                if (! empty($trigger['action_mold_slug'])) {
                    $table_name = getMcTableName($trigger['action_mold_slug']);
                    $mold = Mold::where('table_name', $table_name)->first();
                }

                // 如果找不到关联的模型，跳过此触发器
                if (! $mold) {
                    Log::warning('导入触发器失败: 找不到关联的模型', [
                        'trigger_name' => $triggerName,
                        'action_mold_slug' => $trigger['action_mold_slug'] ?? null,
                    ]);

                    continue;
                }

                // 如果找不到关联的函数，跳过此触发器
                if (! $function) {
                    Log::warning('导入触发器失败: 找不到关联的函数', [
                        'trigger_name' => $triggerName,
                        'action_function_slug' => $trigger['action_function_slug'] ?? null,
                    ]);

                    continue;
                }

                // 创建触发器
                $triggerModel = ProjectTrigger::create([
                    'name' => $triggerName,
                    'enabled' => $trigger['enabled'] ?? true,
                    'trigger_type' => $trigger['trigger_type'] ?? '',
                    'events' => ($trigger['events'] ?? []),
                    'mold_id' => $mold->id,
                    'action_function_id' => $function->id,
                    'content_id' => $trigger['content_id'] ?? null,
                    'watch_function_id' => $trigger['watch_function_id'] ?? null,
                    'input_schema' => ($trigger['input_schema'] ?? []),
                    'remark' => $trigger['remark'] ?? '',
                ]);

                // 记录安装操作
                $this->recordRepository->create([
                    'plugin_id' => $pluginId,
                    'resource_type' => 'trigger',
                    'resource_name' => $triggerName,
                    'resource_table' => ProjectTrigger::tableName(),
                    'resource_id' => $triggerModel->id,
                    'operation' => PluginInstallRecord::OP_CREATE,
                    'status' => PluginInstallRecord::STATUS_SUCCESS,
                ]);
            }

            $logs[] = [
                'step' => 'trigger',
                'message' => '导入触发器: '.count($triggersData).' 个',
                'status' => 'success',
            ];

            Log::info("Triggers installed for plugin: {$pluginId}");
        } catch (\Exception $e) {
            throw $e;
        }

        return $logs;
    }

    /**
     * 复制 PHP 源代码文件
     *
     * @return array 返回日志
     */
    private function installSrc(PluginInterface $plugin): array
    {
        $logs = [];
        $config = $plugin->getConfig();

        // 检查插件是否有 src 目录
        if (! ($config['has_src'] ?? false)) {
            $logs[] = [
                'step' => 'src',
                'message' => '插件未提供 PHP 源代码，跳过',
                'status' => 'success',
            ];

            return $logs;
        }

        $pluginId = $plugin->getId();

        try {
            // 获取插件目录
            $reflection = new \ReflectionClass($plugin);
            $pluginDir = dirname($reflection->getFileName());
            $srcSourceDir = $pluginDir.'/src';

            // 检查 src 目录是否存在
            if (! File::exists($srcSourceDir)) {
                $logs[] = [
                    'step' => 'src',
                    'message' => 'src 目录不存在，跳过',
                    'status' => 'success',
                ];

                return $logs;
            }

            // 目标目录保持在当前插件真实目录下，避免 plugin_id 与目录结构耦合
            $srcTargetDir = $pluginDir.'/src';

            $fileCount = 0;
            $files = File::allFiles($srcSourceDir);
            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $fileCount++;
                }
            }

            $logs[] = [
                'step' => 'src',
                'message' => "检测到 PHP 源代码目录: {$fileCount} 个文件",
                'status' => 'success',
            ];

            Log::info("PHP source files ready for plugin: {$pluginId} ({$fileCount} files)");
        } catch (\Exception $e) {
            $logs[] = [
                'step' => 'src',
                'message' => '检查 PHP 源代码目录失败: '.$e->getMessage(),
                'status' => 'failed',
            ];
            Log::error("Failed to inspect PHP source files for plugin: {$pluginId}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $logs;
    }

    /**
     * 复制前端文件
     *
     * @return array 返回日志
     */
    private function installFrontend(PluginInterface $plugin): array
    {
        $logs = [];
        $config = $plugin->getConfig();

        // 检查插件是否有前端
        if (! ($config['has_frontend'] ?? false)) {
            $logs[] = [
                'step' => 'frontend',
                'message' => '插件未提供前端页面，跳过',
                'status' => 'success',
            ];

            return $logs;
        }

        $pluginId = $plugin->getId();

        // 获取当前项目前缀
        $projectPrefix = (string) (session('current_project_prefix') ?? '');

        try {
            // 获取插件目录
            $reflection = new \ReflectionClass($plugin);
            $pluginDir = dirname($reflection->getFileName());
            $frontendSourceDir = $pluginDir.'/frontend';

            // 检查前端目录是否存在
            if (! File::exists($frontendSourceDir)) {
                $logs[] = [
                    'step' => 'frontend',
                    'message' => '前端目录不存在，跳过',
                    'status' => 'success',
                ];

                return $logs;
            }

            // 目标目录：使用 项目前缀/插件_id 格式
            $frontendTargetDir = storage_path("app/frontend/{$projectPrefix}/{$pluginId}");

            // 删除旧的前端文件（如果存在）
            if (File::exists($frontendTargetDir)) {
                File::deleteDirectory($frontendTargetDir);
            }

            // 创建目标目录
            File::makeDirectory($frontendTargetDir, 0755, true, true);

            // 复制前端文件
            File::copyDirectory($frontendSourceDir, $frontendTargetDir);

            // 检查并替换 config.js 中的占位符
            $configJsPath = $frontendTargetDir.'/dist/config.js';
            if (File::exists($configJsPath)) {
                $configContent = File::get($configJsPath);

                // 检查是否包含需要替换的占位符
                if (strpos($configContent, '{$domain}') !== false || strpos($configContent, '{$apiKey}') !== false) {
                    // 生成插件专用的 API Key
                    $apiKey = $this->generatePluginApiKey($pluginId, $this->resolvePluginApiScopes($plugin));

                    // 获取当前域名
                    $domain = rtrim(config('app.url') ?: url('/'), '/');

                    // 替换占位符
                    $configContent = str_replace('{$domain}', $domain, $configContent);
                    $configContent = str_replace('{$apiKey}', $apiKey, $configContent);

                    // 写回文件
                    File::put($configJsPath, $configContent);

                    $logs[] = [
                        'step' => 'frontend_config',
                        'message' => '已替换 config.js 中的占位符',
                        'status' => 'success',
                    ];

                    Log::info("Config.js placeholders replaced for plugin: {$pluginId}");
                }
            }

            // 统计文件数量
            $fileCount = 0;
            $totalSize = 0;
            $files = File::allFiles($frontendTargetDir);
            foreach ($files as $file) {
                $fileCount++;
                $totalSize += $file->getSize();
            }

            $logs[] = [
                'step' => 'frontend',
                'message' => "复制前端文件成功: {$fileCount} 个文件，".$this->formatBytes($totalSize),
                'status' => 'success',
            ];

            Log::info("Frontend files installed for plugin: {$pluginId} ({$fileCount} files, {$totalSize} bytes)");
        } catch (\Exception $e) {
            $logs[] = [
                'step' => 'frontend',
                'message' => '复制前端文件失败: '.$e->getMessage(),
                'status' => 'failed',
            ];
            Log::error("Failed to install frontend files for plugin: {$pluginId}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $logs;
    }

    /**
     * 格式化字节大小
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(log($bytes, 1024));
        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }

    /**
     * 根据安装记录删除插件数据
     */
    private function removePluginDataByRecords(string $pluginId): void
    {
        // 获取所有成功的安装记录
        $records = $this->recordRepository->getSuccessRecords($pluginId);

        foreach ($records as $record) {
            try {
                switch ($record->resource_type) {
                    case PluginInstallRecord::TYPE_MODEL:
                        // 删除模型（Mold）
                        if ($record->resource_id) {
                            $moldService = app(MoldService::class);
                            $moldService->delete($record->resource_id);
                            Log::info("Deleted model: {$record->resource_name}");
                        }
                        break;

                    case PluginInstallRecord::TYPE_FUNCTION:
                        // 删除云函数
                        if ($record->resource_id) {
                            $functionService = app(CloudFunctionService::class);
                            $functionService->delete($record->resource_id);
                            Log::info("Deleted function: {$record->resource_name}");
                        }
                        break;

                    case PluginInstallRecord::TYPE_MENU:
                        // 删除插件创建的菜单（根+子）
                        try {
                            // 优先用 resource_id 作为根节点删除
                            if ($record->resource_id) {
                                $this->deleteMenuTree((int) $record->resource_id);
                            }
                            // 兜底：删除所有 plugin_id 关联的菜单
                            ProjectMenu::where('plugin_id', $pluginId)->delete();
                            Log::info("Deleted plugin menus: {$pluginId}");
                        } catch (\Throwable $te) {
                            Log::warning('Delete plugin menus failed: '.$te->getMessage());
                        }
                        break;

                    case 'variable':
                        // 删除环境变量
                        if ($record->resource_id) {
                            FunctionEnv::where('id', $record->resource_id)->delete();
                            Log::info("Deleted variable: {$record->resource_name}");
                        }
                        break;

                    case PluginInstallRecord::TYPE_SCHEDULE:
                        // 删除定时任务
                        if ($record->resource_id) {
                            ProjectCron::where('id', $record->resource_id)->delete();
                            Log::info("Deleted schedule: {$record->resource_name}");
                        }
                        break;
                }
            } catch (\Exception $e) {
                Log::error("Failed to delete resource: {$record->resource_type} - {$record->resource_name}", [
                    'error' => $e->getMessage(),
                ]);
                // 继续删除其他资源
            }
        }
    }

    /**
     * 批量删除插件相关资源（通过 plugin_id）
     * 用于清理孤立数据或强制卸载
     *
     * @return array 返回删除统计
     */
    public function cleanupPluginResources(string $pluginId): array
    {
        $stats = [
            'models' => 0,
            'functions' => 0,
            'triggers' => 0,
            'crons' => 0,
            'envs' => 0,
            'menus' => 0,
        ];

        // 删除模型
        $stats['models'] = Mold::where('plugin_id', $pluginId)->delete();

        // 删除函数
        $stats['functions'] = ProjectFunction::where('plugin_id', $pluginId)->delete();

        // 删除触发器
        $stats['triggers'] = ProjectTrigger::where('plugin_id', $pluginId)->delete();

        // 删除定时任务
        $stats['crons'] = ProjectCron::where('plugin_id', $pluginId)->delete();

        // 删除环境变量
        $stats['envs'] = FunctionEnv::where('plugin_id', $pluginId)->delete();

        // 删除菜单（根+子）
        $stats['menus'] = ProjectMenu::where('plugin_id', $pluginId)->delete();

        Log::info("Cleaned up plugin resources: {$pluginId}", $stats);

        return $stats;
    }

    private function deleteMenuTree(int $rootId): void
    {
        $children = ProjectMenu::where('parent_id', $rootId)->get();
        foreach ($children as $ch) {
            $this->deleteMenuTree($ch->id);
        }
        ProjectMenu::where('id', $rootId)->delete();
    }

    /**
     * 生成插件专用的 API Key
     *
     * @param  string  $pluginId  插件ID
     * @return string 生成的 API Key
     */
    private function generatePluginApiKey(string $pluginId, array $scopes): string
    {

        // 生成新的 API Key
        $key = $this->apiKeyRepository->generateUniqueKey();

        // 保存到数据库
        ApiKey::create([
            'name' => "插件 {$pluginId} API Key",
            'key' => $key,
            'description' => "为插件 {$pluginId} 自动生成的 API Key",
            'plugin_id' => $pluginId,
            'rate_limit' => 1000,
            'expires_at' => null, // 永不过期
            'is_active' => true,
            'allowed_ips' => null,
            'scopes' => $scopes,
        ]);

        Log::info("Generated API key for plugin: {$pluginId}", [
            'scopes' => $scopes,
        ]);

        return $key;
    }

    /**
     * 解析插件声明的 API 权限；未声明时默认只读。
     */
    private function resolvePluginApiScopes(PluginInterface $plugin): array
    {
        $config = $plugin->getConfig();
        $declaredScopes = $config['api_scopes'] ?? null;

        if (! is_array($declaredScopes) || empty($declaredScopes)) {
            return $this->getDefaultPluginApiScopes();
        }

        $allowedScopes = ApiKey::availableScopes();
        $scopes = array_values(array_unique(array_filter($declaredScopes, static function ($scope) use ($allowedScopes) {
            return is_string($scope) && in_array($scope, $allowedScopes, true);
        })));

        return ! empty($scopes) ? $scopes : $this->getDefaultPluginApiScopes();
    }

    /**
     * 插件前端默认只给最小只读权限。
     */
    private function getDefaultPluginApiScopes(): array
    {
        return [
            ApiKey::SCOPE_CONTENT_READ,
            ApiKey::SCOPE_PAGE_READ,
            ApiKey::SCOPE_MEDIA_READ,
        ];
    }

    /**
     * 删除插件的 API Key
     *
     * @param  string  $pluginId  插件ID
     */
    private function removePluginApiKey(string $pluginId): void
    {
        try {
            $deleted = ApiKey::where('plugin_id', $pluginId)->delete();
            if ($deleted > 0) {
                Log::info("Deleted API key for plugin: {$pluginId} ({$deleted} records)");
            }
        } catch (\Exception $e) {
            Log::error("Failed to delete API key for plugin: {$pluginId}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 删除插件的前端页面
     *
     * @param  string  $pluginId  插件ID
     */
    private function removePluginFrontend(string $pluginId): void
    {
        try {
            $projectPrefix = (string) (session('current_project_prefix') ?? '');
            $frontendDir = storage_path("app/frontend/{$projectPrefix}/{$pluginId}");

            if (File::exists($frontendDir)) {
                File::deleteDirectory($frontendDir);
                Log::info("Deleted frontend files for plugin: {$pluginId}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to delete frontend files for plugin: {$pluginId}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function convertSourceModelSlugToId(array $fields, string $fullPrefix): array
    {
        foreach ($fields as &$field) {
            if (! empty($field['sourceModelSlug'])) {
                $tableName = $fullPrefix.$field['sourceModelSlug'];
                $relatedMold = Mold::where('table_name', $tableName)->first();
                if ($relatedMold) {
                    $field['sourceModelId'] = $relatedMold->id;
                    unset($field['sourceModelSlug']);
                }
            }
        }

        return $fields;
    }
}
