<?php

namespace App\Http\Controllers\Admin;

use App\Services\PluginService;
use App\Services\ProjectImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use ZipArchive;

/**
 * 插件管理控制器
 */
class PluginController extends BaseAdminController
{
    /**
     * 插件服务
     */
    private PluginService $pluginService;

    private ProjectImportService $projectImportService;

    /**
     * 构造函数
     */
    public function __construct(PluginService $pluginService, ProjectImportService $projectImportService)
    {
        $this->pluginService = $pluginService;
        $this->projectImportService = $projectImportService;
    }

    /**
     * 显示插件管理页面
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        return viewShow('Plugins/PluginsManage');
    }

    /**
     * 获取所有可用插件列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function list()
    {
        try {
            $allPlugins = $this->pluginService->all();
            $installedPlugins = $this->pluginService->getInstalledPlugins();

            $installedMap = [];
            foreach ($installedPlugins as $installed) {
                $installedMap[$installed['plugin_id']] = $installed;
            }

            $result = [];
            foreach ($allPlugins as $pluginId => $plugin) {
                $config = $plugin->getConfig();
                $installed = $installedMap[$pluginId] ?? null;

                $result[] = [
                    'id' => $pluginId,
                    'name' => $plugin->getName(),
                    'version' => $plugin->getVersion(),
                    'description' => $config['description'] ?? '',
                    'author' => $config['author'] ?? '',
                    'icon' => $config['icon'] ?? null,
                    'type' => $config['type'] ?? 'template',
                    'tags' => $config['tags'] ?? [],
                    'installed' => $installed !== null,
                    'status' => $installed['status'] ?? null,
                    'installed_version' => $installed['plugin_version'] ?? null,
                    'installed_at' => $installed['installed_at'] ?? null,
                    'has_frontend' => $config['has_frontend'] ?? false,
                    'frontend_route_prefix' => $pluginId,
                    'provides' => [
                        'models_count' => count($config['provides']['models'] ?? []),
                        'web_functions_count' => count($config['provides']['functions']['endpoints'] ?? []),
                        'trigger_functions_count' => count($config['provides']['functions']['hooks'] ?? []),
                        'menus' => ! empty($config['provides']['menus']),
                        'variables' => $config['provides']['functions']['variables'] ?? false,
                        'schedules' => $config['provides']['functions']['schedules'] ?? false,
                    ],
                ];
            }

            return success($result);
        } catch (\Exception $e) {

            return error([], '获取插件列表失败: '.$e->getMessage());
        }
    }

    /**
     * 删除插件
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'plugin_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return error($validator->errors(), '参数验证失败');
            }

            $pluginId = $request->input('plugin_id');

            $this->pluginService->deletePlugin($pluginId);

            return success([], '插件删除成功');
        } catch (\Exception $e) {
            return error([], '删除插件失败: '.$e->getMessage());
        }
    }

    /**
     * 获取插件详情
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail(Request $request)
    {
        try {
            $pluginId = $request->input('id');

            if (! $pluginId) {
                return error([], '插件ID不能为空');
            }

            $plugin = $this->pluginService->get($pluginId);

            if (! $plugin) {
                return error([], '插件不存在');
            }

            $config = $plugin->getConfig();
            $installedPlugins = $this->pluginService->getInstalledPlugins();

            $installed = null;
            foreach ($installedPlugins as $item) {
                if ($item['plugin_id'] === $pluginId) {
                    $installed = $item;
                    break;
                }
            }

            // dd($installed);

            $provides = $config['provides'] ?? [];
            $modelsCount = count($provides['models'] ?? []);
            $webFunctionsCount = count($provides['functions']['endpoints'] ?? []);
            $triggerFunctionsCount = count($provides['functions']['hooks'] ?? []);

            // 获取 snapshot 图片
            $snapshotImages = [];
            if (! empty($config['snapshot'])) {
                // 从插件 ID 中提取实际的插件目录名（移除版本号后缀）
                $pluginDir = $pluginId;
                $version = $plugin->getVersion();

                // 如果插件 ID 包含版本号后缀，移除它
                if (str_ends_with($pluginId, '_'.ltrim($version, 'v'))) {
                    $pluginDir = substr($pluginId, 0, -strlen('_'.ltrim($version, 'v')));
                } elseif (str_ends_with($pluginId, '_'.$version)) {
                    $pluginDir = substr($pluginId, 0, -strlen('_'.$version));
                }

                $pluginPath = base_path("Plugins/{$pluginDir}/{$version}/snapshot");
                if (is_dir($pluginPath)) {
                    $files = scandir($pluginPath);
                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') {
                            continue;
                        }
                        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            $snapshotImages[] = [
                                'name' => $file,
                                'path' => "snapshot/{$file}",
                                'url' => "/plugins/snapshot-image?plugin_id={$pluginDir}&version={$version}&image={$file}",
                            ];
                        }
                    }
                    // 按文件名排序
                    usort($snapshotImages, function ($a, $b) {
                        return strnatcmp($a['name'], $b['name']);
                    });
                }
            }

            return success([
                'id' => $pluginId,
                'name' => $plugin->getName(),
                'version' => $plugin->getVersion(),
                'description' => $config['description'] ?? '',
                'author' => $config['author'] ?? '',
                'homepage' => $config['homepage'] ?? '',
                'icon' => $config['icon'] ?? null,
                'type' => $config['type'] ?? 'template',
                'tags' => $config['tags'] ?? [],
                'compatible' => $config['compatible'] ?? [],
                'dependencies' => $config['dependencies'] ?? [],
                'snapshot' => $config['snapshot'] ?? false,
                'snapshot_images' => $snapshotImages,
                'provides' => array_merge($provides, [
                    'models_count' => $modelsCount,
                    'web_functions_count' => $webFunctionsCount,
                    'trigger_functions_count' => $triggerFunctionsCount,
                ]),
                'permissions' => $config['permissions'] ?? [],
                'settings' => $config['settings'] ?? [],
                'installed' => $installed !== null,
                'status' => $installed['status'] ?? null,
                'installed_version' => $installed['plugin_version'] ?? null,
                'installed_at' => $installed['installed_at'] ?? null,
                'has_frontend' => $config['has_frontend'] ?? false,
                'frontend_route_prefix' => $pluginId,
            ]);
        } catch (\Exception $e) {
            return error([], '获取插件详情失败: '.$e->getMessage());
        }
    }

    /**
     * 获取插件截图图片
     *
     * @return \Illuminate\Http\Response
     */
    public function getSnapshotImage(Request $request)
    {
        $pluginId = $request->input('plugin_id');
        $version = $request->input('version');
        $imageName = $request->input('image');

        if (empty($pluginId) || empty($version) || empty($imageName)) {
            return response('Bad Request', 400);
        }

        try {
            $imagePath = base_path("Plugins/{$pluginId}/{$version}/snapshot/{$imageName}");

            if (! file_exists($imagePath)) {
                return response('Not Found', 404);
            }

            // 检测图片类型
            $extension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
            $mimeTypes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
            ];
            $mimeType = $mimeTypes[$extension] ?? 'image/jpeg';

            $content = file_get_contents($imagePath);

            return response($content)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=86400');
        } catch (\Exception $e) {
            Log::error('Error getting snapshot image', [
                'plugin_id' => $pluginId,
                'version' => $version,
                'image' => $imageName,
                'error' => $e->getMessage(),
            ]);

            return response('Internal Server Error', 500);
        }
    }

    /**
     * 检查插件安装时的冲突
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkConflicts(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'plugin_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return error($validator->errors(), '参数验证失败');
            }

            $pluginId = $request->input('plugin_id');
            $plugin = $this->pluginService->get($pluginId);

            if (! $plugin) {
                return error([], '插件不存在');
            }

            $config = $plugin->getConfig();

            $conflicts = $this->projectImportService->checkConflicts($config['provides']);

            $hasConflicts = false;
            foreach ($conflicts as $type => $items) {
                if (! empty($items)) {
                    $hasConflicts = true;
                    break;
                }
            }

            return success([
                'has_conflicts' => $hasConflicts,
                'conflicts' => $conflicts,
            ], $hasConflicts ? '检测到以下名称冲突，请手动处理完再导入' : '未检测到冲突，可以安全安装');
        } catch (\Exception $e) {
            return error([], '检查冲突失败: '.$e->getMessage());
        }
    }

    /**
     * 安装插件
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function install(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'plugin_id' => 'required|string',
                'menu_placement' => 'nullable|string|in:independent,content',
                'root_title' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return error($validator->errors(), '参数验证失败');
            }

            $pluginId = $request->input('plugin_id');
            $placement = $request->input('menu_placement', 'independent');
            if (! in_array($placement, ['independent', 'content'])) {
                $placement = 'independent';
            }
            $rootTitle = $request->input('root_title');

            $logs = $this->pluginService->install($pluginId, [
                'menu_placement' => $placement,
                'root_title' => $rootTitle,
            ]);

            return success(['logs' => $logs], '插件安装成功');
        } catch (\Exception $e) {
            return error([], '插件安装失败: '.$e->getMessage());
        }
    }

    /**
     * 卸载插件
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function uninstall(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'plugin_id' => 'required|string',
                'remove_data' => 'boolean',
            ]);

            if ($validator->fails()) {
                return error($validator->errors(), '参数验证失败');
            }

            $pluginId = $request->input('plugin_id');
            $removeData = $request->input('remove_data', true);

            $this->pluginService->uninstall($pluginId, $removeData);

            return success([], '插件卸载成功');
        } catch (\Exception $e) {
            return error([], '插件卸载失败: '.$e->getMessage());
        }
    }

    /**
     * 上传插件
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:zip|max:102400', // 100MB
            ]);

            if ($validator->fails()) {
                return error($validator->errors(), '参数验证失败');
            }

            $file = $request->file('file');
            if (! $file || ! $file->isValid()) {
                return error([], '文件上传失败');
            }

            // 确保临时目录存在
            $tempDir = storage_path('app/temp/plugins');
            if (! file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // 存储上传的文件
            $fileName = uniqid().'.zip';
            $file->move($tempDir, $fileName);
            $fullPath = $tempDir.'/'.$fileName;

            // 检查文件是否存在
            if (! file_exists($fullPath)) {
                return error([], '文件存储失败: '.$fullPath);
            }

            // 检查文件大小
            $fileSize = filesize($fullPath);
            if ($fileSize === 0) {
                return error([], '文件大小为 0');
            }

            // 解压 ZIP 文件
            $zip = new \ZipArchive;
            $zipStatus = $zip->open($fullPath);

            if ($zipStatus !== true) {
                $errors = [
                    ZipArchive::ER_NOENT => '文件不存在',
                    ZipArchive::ER_OPEN => '无法打开文件',
                    ZipArchive::ER_READ => '读取错误',
                    ZipArchive::ER_NOZIP => '不是有效的 ZIP 文件',
                    ZipArchive::ER_INCONS => 'ZIP 文件不一致',
                    ZipArchive::ER_CRC => 'CRC 校验失败',
                    ZipArchive::ER_NOZIP => '不是 ZIP 文件',
                ];
                $errorMsg = $errors[$zipStatus] ?? '未知错误 (错误码: '.$zipStatus.')';
                // 清理临时文件
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }

                return error([], '无法打开 ZIP 文件: '.$errorMsg);
            }

            // 创建临时解压目录
            $extractDir = storage_path('app/temp/plugins/'.uniqid());
            if (! file_exists($extractDir)) {
                mkdir($extractDir, 0755, true);
            }

            $this->assertZipArchiveSafe($zip);
            $zip->extractTo($extractDir);
            $zip->close();

            // 查找 plugin.json
            $pluginJsonPath = $this->findPluginJson($extractDir);
            if (! $pluginJsonPath) {
                // 清理临时文件
                \Illuminate\Support\Facades\File::deleteDirectory($extractDir);
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }

                return error([], 'ZIP 文件中未找到 plugin.json');
            }

            // 解析 plugin.json
            $pluginJson = json_decode(file_get_contents($pluginJsonPath), true);
            if (! $pluginJson) {
                // 清理临时文件
                \Illuminate\Support\Facades\File::deleteDirectory($extractDir);
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }

                return error([], 'plugin.json 格式错误');
            }

            // 验证必需字段
            if (empty($pluginJson['name'])) {
                \Illuminate\Support\Facades\File::deleteDirectory($extractDir);
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }

                return error([], 'plugin.json 缺少 name 字段');
            }

            // 检查插件目录
            $pluginName = $pluginJson['name'];
            $pluginDir = base_path('Plugins/'.$pluginName);

            // 如果插件已存在，先备份
            if (file_exists($pluginDir)) {
                $backupDir = base_path('Plugins/_backup_'.$pluginName.'_'.time());
                rename($pluginDir, $backupDir);
            }

            // 移动插件目录
            $extractedPluginDir = dirname($pluginJsonPath);
            if (basename($extractedPluginDir) !== $pluginName) {
                // 如果解压的根目录不是插件名，需要重命名
                $newPluginDir = dirname($extractDir).'/'.$pluginName;
                rename($extractedPluginDir, $newPluginDir);
                $extractedPluginDir = $newPluginDir;
            }

            // 移动到 Plugins 目录
            \Illuminate\Support\Facades\File::moveDirectory($extractedPluginDir, $pluginDir);

            // 清理临时文件
            \Illuminate\Support\Facades\File::deleteDirectory($extractDir);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            // 重新发现插件
            $this->pluginService->discover();

            return success([
                'plugin_name' => $pluginName,
                'has_frontend' => $pluginJson['has_frontend'] ?? false,
                'has_src' => $pluginJson['has_src'] ?? false,
            ], '插件上传成功');
        } catch (\Exception $e) {
            return error([], '插件上传失败: '.$e->getMessage());
        }
    }

    /**
     * 在目录中查找 plugin.json
     */
    private function findPluginJson(string $directory): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'plugin.json') {
                return $file->getPathname();
            }
        }

        return null;
    }

    private function assertZipArchiveSafe(ZipArchive $zip): void
    {
        $maxFiles = 5000;
        $maxUncompressedBytes = 500 * 1024 * 1024;
        $totalBytes = 0;

        if ($zip->numFiles > $maxFiles) {
            throw new \RuntimeException('ZIP 文件内容过多，已拒绝解压');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) ($stat['name'] ?? '');
            $size = (int) ($stat['size'] ?? 0);

            if (
                $name === '' ||
                str_contains($name, "\0") ||
                preg_match('#(^/|^[A-Za-z]:[\\\\/]|(^|/)\.\.(/|$))#', $name)
            ) {
                throw new \RuntimeException('ZIP 文件包含非法路径，已拒绝解压');
            }

            $totalBytes += max(0, $size);
            if ($totalBytes > $maxUncompressedBytes) {
                throw new \RuntimeException('ZIP 解压后的内容过大，已拒绝处理');
            }
        }
    }
}
