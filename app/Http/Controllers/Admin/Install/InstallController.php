<?php

namespace App\Http\Controllers\Admin\Install;

use App\Services\InstallationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class InstallController
{
    protected $installationService;

    public function __construct(InstallationService $installationService)
    {
        $this->installationService = $installationService;
    }

    private function sanitizeInstallLogData(array $data): array
    {
        foreach (['password', 'dbpwd'] as $secretField) {
            if (array_key_exists($secretField, $data) && $data[$secretField] !== null && $data[$secretField] !== '') {
                $data[$secretField] = '******';
            }
        }

        return $data;
    }

    private function mysqlDbNameRules(): array
    {
        return ['required_if:dbtype,mysql', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'];
    }

    private function assertValidMysqlDatabaseName(string $database): void
    {
        if ($database === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new \InvalidArgumentException('MySQL 数据库名仅支持字母、数字和下划线。');
        }
    }

    private function getDbDefaults(Request $request): array
    {
        $defaultConnection = config('database.default', 'mysql');

        $defaults = [
            'dbtype' => $defaultConnection === 'sqlite' ? 'sqlite' : 'mysql',
            'appurl' => $request->getSchemeAndHttpHost(),
            'dbhost' => env('DB_HOST', '127.0.0.1'),
            'dbport' => env('DB_PORT', '3306'),
            'dbname' => env('DB_DATABASE', 'mosure'),
            'dbuser' => env('DB_USERNAME', 'root'),
            'dbpwd' => env('DB_PASSWORD', ''),
        ];

        if ($defaults['dbtype'] === 'sqlite') {
            $defaults['dbhost'] = '';
            $defaults['dbport'] = '';
            $defaults['dbname'] = database_path('mosure.db');
            $defaults['dbuser'] = '';
            $defaults['dbpwd'] = '';
        }

        return $defaults;
    }

    private function hasPresetDbConfig(array $defaults): bool
    {
        if ($defaults['dbtype'] === 'sqlite') {
            return true;
        }

        return ! empty($defaults['dbhost']) &&
            ! empty($defaults['dbport']) &&
            ! empty($defaults['dbname']) &&
            ! empty($defaults['dbuser']);
    }

    private function isDockerEnvironment(): bool
    {
        return file_exists('/.dockerenv') || (bool) env('RUNNING_IN_DOCKER', false);
    }

    /**
     * 测试数据库连接 - 直接使用PDO，避免使用Laravel DB类
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testDbConnection(Request $request)
    {
        try {
            // 验证请求数据
            $validated = $request->validate([
                'dbtype' => 'required|string|in:sqlite,mysql',
                'dbhost' => 'required_if:dbtype,mysql|string',
                'dbport' => 'required_if:dbtype,mysql|string',
                'dbname' => $this->mysqlDbNameRules(),
                'dbuser' => 'required_if:dbtype,mysql|string',
                'dbpwd' => 'nullable|string',
            ]);

            $dbType = $validated['dbtype'];

            // 直接使用PDO测试连接，避免使用Laravel DB类
            if ($dbType === 'sqlite') {
                // SQLite 连接测试
                $dbPath = database_path('mosure.db');

                // 确保目录存在
                if (! file_exists(dirname($dbPath))) {
                    mkdir(dirname($dbPath), 0755, true);
                }

                // 尝试创建或打开文件
                if (! file_exists($dbPath)) {
                    $file = fopen($dbPath, 'w');
                    if (! $file) {
                        throw new \Exception('无法创建 SQLite 数据库文件，请检查目录权限');
                    }
                    fclose($file);
                }

                try {
                    // 尝试连接 - 仅测试连接能力，不查询表
                    $pdo = new \PDO('sqlite:'.$dbPath);
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                    // 测试执行一个简单的SQL语句，不查询特定表
                    $pdo->query('SELECT 1');

                    return success([], 'SQLite 数据库连接成功');
                } catch (\PDOException $e) {
                    throw new \Exception('SQLite 数据库连接失败: '.$e->getMessage());
                }
            } else {
                // MySQL 连接测试
                try {
                    $host = $validated['dbhost'];
                    $port = $validated['dbport'];
                    $database = $validated['dbname'];
                    $username = $validated['dbuser'];
                    $password = $validated['dbpwd'] ?? '';

                    $this->assertValidMysqlDatabaseName($database);

                    // 先不指定数据库名称，只测试连接到MySQL服务器
                    $dsn = "mysql:host={$host};port={$port}";
                    $options = [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_TIMEOUT => 3, // 设置连接超时为3秒
                    ];

                    $pdo = new \PDO($dsn, $username, $password, $options);

                    // 测试执行一个简单的SQL语句，不查询特定表
                    $pdo->query('SELECT 1');

                    // 检查数据库是否存在
                    $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :database');
                    $stmt->execute(['database' => $database]);
                    $dbExists = $stmt->fetchColumn();

                    if (! $dbExists) {
                        // 尝试创建数据库
                        $pdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;', $database));

                        return success("MySQL 连接成功，已创建数据库 {$database}");
                    }

                    return success("MySQL 连接成功，数据库 {$database} 已存在");
                } catch (\PDOException $e) {
                    // 如果MySQL连接失败，提供更详细的错误信息
                    $errorCode = $e->getCode();
                    $errorMessage = $e->getMessage();

                    if (strpos($errorMessage, 'No such file or directory') !== false) {
                        throw new \Exception('MySQL 服务器未运行或无法连接。\n\n建议使用 SQLite 数据库，或确保MySQL服务器已启动。');
                    } elseif (strpos($errorMessage, 'Access denied') !== false) {
                        throw new \Exception('MySQL 访问被拒绝：用户名或密码错误。');
                    } elseif (strpos($errorMessage, 'Unknown database') !== false) {
                        throw new \Exception('数据库不存在，请先创建数据库或使用正确的数据库名称。');
                    } else {
                        throw new \Exception('MySQL 连接失败: '.$errorMessage);
                    }
                }
            }
        } catch (\PDOException $e) {
            // 如果是MySQL连接错误，提供使用SQLite的建议
            $errorMessage = $e->getMessage();
            $details = $errorMessage;

            if (strpos($errorMessage, 'No such file or directory') !== false) {
                $details = 'MySQL服务器可能未运行。\n\n建议：\n1. 尝试使用 SQLite 数据库（选择数据库类型为 SQLite）\n2. 或者启动本地 MySQL 服务器\n3. 确保主机名和端口配置正确';
            }

            return error('数据库连接失败: '.$errorMessage, ['details' => $details]);
        } catch (\Exception $e) {
            return error('操作失败: '.$e->getMessage(), ['details' => $e->getMessage()]);
        }
    }

    private function checkInstalled()
    {
        if (! file_exists(base_path('.locked'))) {
            return false;
        }

        // .locked 仅作为安装标记；若数据库核心表不存在，视为未完成安装，
        // 允许继续访问安装向导（例如重置数据库后的重新安装场景）。
        return $this->installationService->isInstalled(true);
    }

    /**
     * 安装第一步 - 系统要求检查
     *
     * @return \Inertia\Response
     */
    public function installStep1(Request $request)
    {
        if ($this->checkInstalled()) {
            return redirect()->route('login');
        }

        // 准备安装页面数据
        $resInfo = [
            'requirements' => $this->checkSystemRequirements(),
        ];

        return viewShow('Install/Step1Requirements', [
            'info' => $resInfo,
        ]);
    }

    /**
     * 安装第二步 - 管理员和数据库设置
     *
     * @return \Inertia\Response
     */
    public function installStep2(Request $request)
    {
        if ($this->checkInstalled()) {
            return redirect()->route('login');
        }

        $dbDefaults = $this->getDbDefaults($request);
        $hasPresetDb = $this->hasPresetDbConfig($dbDefaults);

        // 准备安装页面数据
        $resInfo = [
            'database_types' => [
                ['value' => 'sqlite', 'label' => 'SQLite (推荐开发环境使用)'],
                ['value' => 'mysql', 'label' => 'MySQL (推荐生产环境使用)'],
            ],
            'app_url' => $request->getSchemeAndHttpHost(),
            'db_defaults' => $dbDefaults,
            'docker_auto_db' => $hasPresetDb && $this->isDockerEnvironment(),
        ];

        return viewShow('Install/Step2Setup', [
            'info' => $resInfo,
        ]);
    }

    /**
     * 安装第三步 - 安装确认
     *
     * @return \Inertia\Response
     */
    public function installStep3(Request $request)
    {
        if ($this->checkInstalled()) {
            return redirect()->route('login');
        }

        return viewShow('Install/Step3Confirmation');
    }

    /**
     * 检查系统安装要求
     *
     * @return array
     */
    private function checkSystemRequirements()
    {
        $requirements = [];

        // PHP版本检查
        $phpVersion = phpversion();
        $requirements['php_version'] = [
            'name' => 'PHP版本',
            'value' => $phpVersion,
            'required' => '8.0.0',
            'status' => version_compare($phpVersion, '8.0.0', '>='),
        ];

        // 必要的PHP扩展todo::
        $requiredExtensions = ['PDO', 'JSON', 'Fileinfo', 'OpenSSL', 'Tokenizer', 'Mbstring', 'Ctype', 'XML', 'Zip'];
        $extensions = [];

        foreach ($requiredExtensions as $extension) {
            $loaded = extension_loaded(strtolower($extension));
            $extensions[] = [
                'name' => $extension,
                'status' => $loaded,
            ];
        }

        $requirements['extensions'] = $extensions;

        // 目录权限检查
        $directories = [
            'storage' => storage_path(),
            'bootstrap/cache' => base_path('bootstrap/cache'),
            'database' => database_path(),
            'Plugins' => base_path('Plugins'),
        ];

        $directoryPermissions = [];

        foreach ($directories as $name => $path) {
            $isWritable = is_writable($path);
            $directoryPermissions[] = [
                'name' => $name,
                'path' => $path,
                'status' => $isWritable,
            ];
        }

        $requirements['directories'] = $directoryPermissions;

        return $requirements;
    }

    /**
     * 处理安装请求
     */
    public function install(Request $request): JsonResponse
    {
        if (file_exists(base_path('.locked'))) {
            return error('系统已安装，无法重新安装');
        }

        try {
            // 增加 PHP 执行时间限制
            set_time_limit(300); // 设置为 5 分钟
            ini_set('memory_limit', '512M'); // 增加内存限制
            // 记录请求数据，便于调试

            // 验证安装数据
            $validated = $request->validate([
                'dbtype' => 'required|string|in:sqlite,mysql',
                'appurl' => 'nullable|string',
                'dbhost' => 'required_if:dbtype,mysql|string',
                'dbport' => 'required_if:dbtype,mysql|string',
                'dbname' => $this->mysqlDbNameRules(),
                'dbuser' => 'required_if:dbtype,mysql|string',
                'dbpwd' => 'nullable|string',
                'username' => 'required|string|min:3|max:50',
                'password' => 'required|string|min:6',
                'email' => 'required|email',
                'name' => 'nullable|string|max:50',
            ]);

            Log::info('API安装请求数据', $this->sanitizeInstallLogData($request->all()));
            Log::info('API安装数据验证成功', $this->sanitizeInstallLogData($validated));

            // 执行安装
            $result = $this->installationService->install($validated);

            // Log::info('API安装结果:', $result);
            if ($result['status'] === 'success') {
                Log::info('API安装成功', $result);
                file_put_contents(base_path('.locked'), 'locked'.time());

                // 安装后清缓存不应影响安装结果，且此时可能尚未存在 DB cache 表。
                try {
                    config(['cache.default' => 'file']);
                    Artisan::call('cache:clear');
                } catch (\Throwable $cacheClearError) {
                    Log::warning('安装后 cache:clear 失败，已忽略', [
                        'message' => $cacheClearError->getMessage(),
                    ]);
                }

                try {
                    Artisan::call('config:clear');
                } catch (\Throwable $configClearError) {
                    Log::warning('安装后 config:clear 失败，已忽略', [
                        'message' => $configClearError->getMessage(),
                    ]);
                }
                // Artisan::call('view:clear');

                return success($result, '安装成功');
            } else {
                Log::error('API安装失败:', ['message' => $result['message']]);

                return error($result, $result['message']);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            Log::error('API安装数据验证失败:', $errors);

            return error($errors, '安装失败: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::error('API安装异常:', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            // 检查是否是环境文件相关错误
            if (strpos($e->getMessage(), '.env') !== false || strpos($e->getMessage(), '.env.example') !== false) {
                return error(['details' => '请确保项目根目录下存在 .env.example 文件，并且应用对该文件有读写权限'], '安装失败: 环境配置文件错误 - '.$e->getMessage());
            }

            // 检查是否是数据库相关错误
            if (strpos($e->getMessage(), 'database') !== false || strpos($e->getMessage(), 'DB_') !== false ||
                strpos($e->getMessage(), 'PDO') !== false || strpos($e->getMessage(), 'SQL') !== false) {
                return error(['details' => '请确保数据库配置正确，并且数据库服务器可访问。如果使用MySQL，请确保服务器已启动。'], '安装失败: 数据库错误 - '.$e->getMessage());
            }

            return error(['details' => $e->getMessage()], '安装失败: '.$e->getMessage());
        }
    }
}
