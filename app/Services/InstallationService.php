<?php

namespace App\Services;

use App\Models\AiAnswerHistory;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\ProjectUserRole;
use App\Models\SysTask;
use App\Models\SysTaskStep;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\StructuredLogger;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class InstallationService
{
    protected function assertValidMysqlDatabaseName(string $database): void
    {
        if ($database === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new \InvalidArgumentException('MySQL 数据库名仅支持字母、数字和下划线。');
        }
    }

    /**
     * 执行安装过程
     *
     * @param  array  $data  安装配置数据
     * @return array 安装结果
     */
    public function install(array $data)
    {
        try {
            StructuredLogger::info('install.start', [
                'dbtype' => $data['dbtype'] ?? null,
                'appurl' => $data['appurl'] ?? null,
            ]);
            // 1. 配置环境 重置.env导致页面刷新错误
            $this->configureEnvironment($data);

            // 2. 准备 Laravel 运行时目录并检查可写性
            $this->prepareRuntimeDirectories();

            // 3. 针对 SQLite 预检查并修复常见权限问题
            $this->prepareSqliteDatabase($data);

            // 4 创建数据库（如果不存在）
            $this->createDatabaseIfNotExists($data);

            // 5. 创建系统核心层表
            $this->createSystemCoreTables();

            // 6. 创建管理员用户
            $adminUser = $this->createAdminUser($data);

            // 7. 创建存储链接
            $this->createStorageLink();

            return [
                'status' => 'success',
                'message' => '安装成功',
                // 'user' => $adminUser
            ];
        } catch (\Exception $e) {
            StructuredLogger::error('install.failed', [
                'dbtype' => $data['dbtype'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => '安装失败: '.$e->getMessage(),
            ];
        }
    }

    /**
     * 配置环境变量
     *
     * @param  array  $data  配置数据
     * @return bool
     */
    protected function configureEnvironment(array $data)
    {
        $dbType = (string) ($data['dbtype'] ?? '');
        if ($dbType === '') {
            throw new \Exception('数据库类型不能为空');
        }

        $envExamplePath = base_path('.env.example');
        $envPath = base_path('.env');
        $created = false;

        if (! file_exists($envPath)) {
            if (! file_exists($envExamplePath)) {
                throw new \Exception('.env.example 不存在，无法初始化 .env');
            }
            if (! @copy($envExamplePath, $envPath)) {
                throw new \Exception('无法从 .env.example 复制生成 .env，请检查文件权限');
            }
            $created = true;
        } else {
            $ts = date('YmdHis');
            @copy($envPath, $envPath.'.backup.'.$ts);
        }

        $contents = (string) @file_get_contents($envPath);
        if ($contents === '') {
            $contents = '';
        }

        $appUrl = (string) ($data['appurl'] ?? '');
        $contents = $this->upsertEnvValue($contents, 'APP_URL', $appUrl);

        if ($dbType === 'sqlite') {
            $dbPath = $this->resolveSqliteDatabasePath($data);
            $contents = $this->upsertEnvValue($contents, 'DB_CONNECTION', 'sqlite');
            $contents = $this->upsertEnvValue($contents, 'DB_DATABASE', $dbPath);
        } elseif ($dbType === 'mysql') {
            $databaseName = (string) ($data['dbname'] ?? '');
            $this->assertValidMysqlDatabaseName($databaseName);
            $contents = $this->upsertEnvValue($contents, 'DB_CONNECTION', 'mysql');
            $contents = $this->upsertEnvValue($contents, 'DB_HOST', (string) ($data['dbhost'] ?? ''));
            $contents = $this->upsertEnvValue($contents, 'DB_PORT', (string) ($data['dbport'] ?? ''));
            $contents = $this->upsertEnvValue($contents, 'DB_DATABASE', $databaseName);
            $contents = $this->upsertEnvValue($contents, 'DB_USERNAME', (string) ($data['dbuser'] ?? ''));
            $contents = $this->upsertEnvValue($contents, 'DB_PASSWORD', (string) ($data['dbpwd'] ?? ''));

            StructuredLogger::info('install.mysql.configured', [
                'db_host' => (string) ($data['dbhost'] ?? ''),
                'db_port' => (string) ($data['dbport'] ?? ''),
                'db_name' => $databaseName,
            ]);
        } else {
            throw new \Exception('不支持的数据库类型: '.$dbType);
        }

        $hasAppKey = (bool) preg_match('/^APP_KEY\s*=\s*(.+)$/m', $contents);
        $needsAppKey = ! $hasAppKey || (bool) preg_match('/^APP_KEY\s*=\s*$/m', $contents);
        if ($needsAppKey) {
            $contents = $this->upsertEnvValue($contents, 'APP_KEY', 'base64:'.base64_encode(random_bytes(32)));
        }

        $tmpPath = $envPath.'.tmp';
        file_put_contents($tmpPath, $contents);
        @rename($tmpPath, $envPath);

        StructuredLogger::info('install.env_written', [
            'path' => $envPath,
            'created' => $created,
            'dbtype' => $dbType,
        ]);

        return true;
    }

    private function upsertEnvValue(string $contents, string $key, string $value): string
    {
        $line = $key.'='.$this->formatEnvValue($value);
        $pattern = '/^'.preg_quote($key, '/').'=.*/m';
        if (preg_match($pattern, $contents)) {
            return (string) preg_replace($pattern, $line, $contents);
        }
        $contents = rtrim($contents, "\n");
        if ($contents !== '') {
            $contents .= "\n";
        }
        $contents .= $line."\n";

        return $contents;
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (preg_match('/[\s#"\\=]/', $value)) {
            $escaped = str_replace('"', '\\"', $value);

            return '"'.$escaped.'"';
        }

        return $value;
    }

    private function resolveSqliteDatabasePath(array $data): string
    {
        $dbName = (string) ($data['dbname'] ?? '');

        if ($dbName === '') {
            return database_path('mosure.db');
        }

        if (str_starts_with($dbName, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $dbName)) {
            return $dbName;
        }

        return base_path($dbName);
    }

    private function prepareSqliteDatabase(array $data): void
    {
        if ((string) ($data['dbtype'] ?? '') !== 'sqlite') {
            return;
        }

        $dbPath = $this->resolveSqliteDatabasePath($data);
        $databaseDir = dirname($dbPath);

        if (! file_exists($databaseDir) && ! @mkdir($databaseDir, 0775, true) && ! file_exists($databaseDir)) {
            throw new \Exception("无法创建 SQLite 目录: {$databaseDir}");
        }

        // SQLite 写入不仅要求数据库文件可写，也要求数据库所在目录可写，
        // 因为运行时可能会创建 -journal / -wal / -shm 等临时文件。
        //
        // 远程部署时常见情况是：安装命令由 SSH 用户执行，而 Web/PHP-FPM
        // 由 www-data/nginx/apache 等另一个用户执行。0775 在不同属组时
        // 仍可能导致 Web 端报 “attempt to write a readonly database”。
        // 因此 SQLite 默认安装使用 0777 目录 + 0666 数据库文件；生产环境
        // 如需收紧权限，建议改用 chown 到 Web 用户后再设置 0775/0664。
        @chmod($databaseDir, 0777);

        if (! is_dir($databaseDir)) {
            throw new \Exception("SQLite 目录无效: {$databaseDir}");
        }

        if (! is_writable($databaseDir)) {
            throw new \Exception("SQLite 目录不可写: {$databaseDir}。请为当前 PHP 运行用户授予写权限。");
        }

        if (! file_exists($dbPath) && ! @touch($dbPath)) {
            throw new \Exception("无法创建 SQLite 数据库文件: {$dbPath}");
        }

        @chmod($dbPath, 0666);

        foreach ([$dbPath.'-journal', $dbPath.'-wal', $dbPath.'-shm'] as $sqliteRuntimeFile) {
            if (file_exists($sqliteRuntimeFile)) {
                @chmod($sqliteRuntimeFile, 0666);
            }
        }

        clearstatcache(true, $dbPath);
        clearstatcache(true, $databaseDir);

        if (! is_writable($dbPath)) {
            throw new \Exception("SQLite 数据库文件不可写: {$dbPath}。请检查文件属主和权限，例如确保目录可写并将文件授权给 Web/PHP 用户。");
        }

        StructuredLogger::info('install.sqlite.ready', ['path' => $dbPath, 'dir' => $databaseDir]);
    }

    private function prepareRuntimeDirectories(): void
    {
        $paths = [
            storage_path('framework'),
            storage_path('framework/cache'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('framework/testing'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($paths as $path) {
            if (! file_exists($path) && ! @mkdir($path, 0775, true) && ! file_exists($path)) {
                throw new \Exception("无法创建运行目录: {$path}");
            }

            @chmod($path, 0775);
            clearstatcache(true, $path);

            if (! is_dir($path)) {
                throw new \Exception("运行目录无效: {$path}");
            }

            if (! is_writable($path)) {
                throw new \Exception("运行目录不可写: {$path}。请为当前 PHP/Web 用户授予写权限。");
            }
        }

        StructuredLogger::info('install.runtime_directories.ready', ['path_count' => count($paths)]);
    }

    /**
     * 创建系统核心层表
     *
     * @return void
     */
    protected function createSystemCoreTables()
    {
        try {
            StructuredLogger::info('install.core_tables.start');

            // 用户表
            if (! Schema::hasTable('sys_users')) {
                StructuredLogger::info('install.table.create', ['table' => 'sys_users']);
                Schema::create('sys_users', User::getTableSchema());
            }

            StructuredLogger::info('install.table.ready', ['table' => 'sys_users']);

            // 角色和权限相关表已移动到项目管理层级，不再在系统核心层创建

            Schema::dropIfExists('sys_projects');
            // 项目表
            if (! Schema::hasTable('sys_projects')) {
                StructuredLogger::info('install.table.create', ['table' => 'sys_projects']);
                Schema::create('sys_projects', Project::getTableSchema());
            }

            Schema::dropIfExists('sys_project_user');
            // 项目-用户关联表
            if (! Schema::hasTable('sys_project_user')) {
                StructuredLogger::info('install.table.create', ['table' => 'sys_project_user']);
                Schema::create('sys_project_user', ProjectUser::getTableSchema());
            }

            Schema::dropIfExists('sys_project_user_role');
            // 项目-用户-角色关联表
            if (! Schema::hasTable('sys_project_user_role')) {
                StructuredLogger::info('install.table.create', ['table' => 'sys_project_user_role']);
                Schema::create('sys_project_user_role', ProjectUserRole::getTableSchema());
            }

            Schema::dropIfExists('sys_settings');
            // 系统设置表
            if (! Schema::hasTable('sys_settings')) {
                StructuredLogger::info('install.table.create', ['table' => 'sys_settings']);
                Schema::create('sys_settings', SystemSetting::getTableSchema());
            }

            Schema::dropIfExists('sys_tasks');
            if (! Schema::hasTable('sys_tasks')) {
                StructuredLogger::info('install.table.create', ['table' => 'sys_tasks']);
                Schema::create('sys_tasks', SysTask::getTableSchema());
            }

            Schema::dropIfExists('sys_task_steps');
            if (! Schema::hasTable('sys_task_steps')) {
                StructuredLogger::info('install.table.create', ['table' => 'sys_task_steps']);
                Schema::create('sys_task_steps', SysTaskStep::getTableSchema());
            }

            Schema::dropIfExists('sys_ai_answer_histories');
            if (! Schema::hasTable('sys_ai_answer_histories')) {
                StructuredLogger::info('install.table.create', ['table' => 'sys_ai_answer_histories']);
                Schema::create('sys_ai_answer_histories', AiAnswerHistory::getTableSchema());
            }

            // 知识库分类表
            if (! Schema::hasTable('sys_kb_categories')) {
                StructuredLogger::info('install.table.create', ['table' => 'sys_kb_categories']);
                Schema::create('sys_kb_categories', KbCategory::getTableSchema());
            }

            // 知识库文章表
            if (! Schema::hasTable('sys_kb_articles')) {
                StructuredLogger::info('install.table.create', ['table' => 'sys_kb_articles']);
                Schema::create('sys_kb_articles', KbArticle::getTableSchema());
            }

            // AI Agent 表
            if (! Schema::hasTable('sys_ai_agents')) {
                StructuredLogger::info('install.table.create', ['table' => 'sys_ai_agents']);
                Schema::create('sys_ai_agents', \App\Models\SysAiAgent::getTableSchema());
            }

            // AI 消息表
            if (! Schema::hasTable('sys_ai_messages')) {
                StructuredLogger::info('install.table.create', ['table' => 'sys_ai_messages']);
                Schema::create('sys_ai_messages', \App\Models\SysAiMessage::getTableSchema());
            }

            // AI 会话表（系统级，不带项目前缀）
            if (! Schema::hasTable('sys_ai_sessions')) {
                StructuredLogger::info('install.table.create', ['table' => 'sys_ai_sessions']);
                Schema::create('sys_ai_sessions', \App\Models\SysAiSession::getTableSchema());
            }

            StructuredLogger::info('install.core_tables.completed');
        } catch (\Exception $e) {
            StructuredLogger::error('install.core_tables.failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 创建管理员用户
     *
     * @param  array  $data  用户数据
     * @return User 创建的用户对象
     */
    protected function createAdminUser(array $data)
    {
        // 验证必要字段
        if (empty($data['email']) || empty($data['password'])) {
            throw new \Exception('邮箱和密码不能为空');
        }

        User::truncate();
        // 创建管理员用户
        $user = User::create([
            'name' => $data['name'] ?? 'Admin',
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // 设置为系统管理员
        $user->is_admin = true;
        $user->save();

        StructuredLogger::info('install.admin_user.created', [
            'email' => $user->email,
            'user_id' => $user->id,
        ]);

        return $user;
    }

    /**
     * 创建存储符号链接
     *
     * @return void
     */
    protected function createStorageLink()
    {
        try {
            // 检查链接是否已存在
            if (file_exists(public_path('storage'))) {
                StructuredLogger::info('install.storage_link.exists');

                return;
            }

            // 创建 storage 符号链接
            $exitCode = Artisan::call('storage:link');

            if ($exitCode !== 0) {
                StructuredLogger::warning('install.storage_link.failed', ['exit_code' => $exitCode]);
            } else {
                StructuredLogger::info('install.storage_link.created');
            }
        } catch (\Exception $e) {
            StructuredLogger::error('install.storage_link.error', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 创建数据库（如果不存在）
     *
     * @return void
     */
    protected function createDatabaseIfNotExists($data)
    {
        try {
            $connection = (string) ($data['dbtype'] ?? '');
            $database = (string) ($data['dbname'] ?? '');

            // 如果使用 SQLite
            if ($connection === 'sqlite') {
                $databasePath = $this->resolveSqliteDatabasePath($data);
                $this->prepareSqliteDatabase([
                    'dbtype' => 'sqlite',
                    'dbname' => $databasePath,
                ]);

                return;
            }

            // 如果使用 MySQL
            if ($connection === 'mysql') {
                $this->assertValidMysqlDatabaseName($database);

                // 创建一个不指定数据库的连接
                $host = config("database.connections.{$connection}.host");
                $port = config("database.connections.{$connection}.port");
                $username = config("database.connections.{$connection}.username");
                $password = config("database.connections.{$connection}.password");

                $pdo = new \PDO("mysql:host={$host};port={$port}", $username, $password);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                // 检查数据库是否存在
                $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :database');
                $stmt->execute(['database' => $database]);
                $databaseExists = $stmt->fetchColumn();

                if (! $databaseExists) {
                    // 创建数据库
                    $pdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $database));
                    StructuredLogger::info('install.mysql.database_created', [
                        'database' => $database,
                    ]);
                }
            }
        } catch (\Exception $e) {
            StructuredLogger::error('install.database_create.failed', [
                'dbtype' => $data['dbtype'] ?? null,
                'dbname' => $data['dbname'] ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 检查系统是否已安装
     *
     * @param  bool  $checkDatabase  是否检查数据库表
     * @return bool
     */
    public function createSystemCoreTablesPublic()
    {
        $this->createSystemCoreTables();
    }

    public function isInstalled($checkDatabase = false)
    {
        // 检查环境文件是否存在
        if (! file_exists(base_path('.env'))) {
            return false;
        }

        // 如果不需要检查数据库，直接返回环境文件存在即认为已安装
        if (! $checkDatabase) {
            return true;
        }

        // 检查系统核心表是否存在
        try {
            return Schema::hasTable('sys_users') && Schema::hasTable('sys_projects');
        } catch (\Exception $e) {
            return false;
        }
    }
}
