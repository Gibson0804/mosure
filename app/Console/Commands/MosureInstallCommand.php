<?php

namespace App\Console\Commands;

use App\Services\InstallationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MosureInstallCommand extends Command
{
    protected $signature = 'mosure:install
        {--name=Admin : 管理员名称}
        {--username=admin : 管理员用户名}
        {--email=admin@example.com : 管理员邮箱}
        {--password= : 管理员密码，不传则自动生成临时密码}
        {--app-url=http://127.0.0.1:9445 : 应用访问地址}
        {--db=sqlite : 数据库类型，可选 sqlite/mysql}
        {--db-host=127.0.0.1 : MySQL 主机}
        {--db-port=3306 : MySQL 端口}
        {--db-name=mosure : MySQL 数据库名}
        {--db-user=root : MySQL 用户名}
        {--db-password= : MySQL 密码}
        {--force : 已安装时强制重新执行}';

    protected $description = '以默认 SQLite 配置执行 Mosure 本地一键安装';

    public function handle(InstallationService $installationService): int
    {
        $lockPath = base_path('.locked');

        if (file_exists($lockPath) && ! $this->option('force')) {
            $this->error('检测到 .locked，系统已安装。需要重装请追加 --force。');

            return self::FAILURE;
        }

        $dbType = (string) $this->option('db');
        if (! in_array($dbType, ['sqlite', 'mysql'], true)) {
            $this->error('仅支持 sqlite 或 mysql。');

            return self::FAILURE;
        }

        $password = trim((string) $this->option('password'));
        $generatedPassword = $password === '';

        if ($generatedPassword) {
            $password = bin2hex(random_bytes(8));
        }

        $payload = [
            'dbtype' => $dbType,
            'appurl' => (string) $this->option('app-url'),
            'username' => (string) $this->option('username'),
            'name' => (string) $this->option('name'),
            'email' => (string) $this->option('email'),
            'password' => $password,
            'dbhost' => $dbType === 'mysql' ? (string) $this->option('db-host') : '',
            'dbport' => $dbType === 'mysql' ? (string) $this->option('db-port') : '',
            'dbname' => $dbType === 'mysql' ? (string) $this->option('db-name') : database_path('mosure.db'),
            'dbuser' => $dbType === 'mysql' ? (string) $this->option('db-user') : '',
            'dbpwd' => $dbType === 'mysql' ? (string) $this->option('db-password') : '',
        ];

        $this->info('开始执行 Mosure 安装...');
        $this->line('数据库: '.$dbType);
        $this->line('应用地址: '.$payload['appurl']);
        $this->line('管理员邮箱: '.$payload['email']);
        if ($dbType === 'sqlite') {
            $this->line('SQLite 文件: '.$payload['dbname']);
        }

        $result = $installationService->install($payload);

        if (($result['status'] ?? 'error') !== 'success') {
            $this->error((string) ($result['message'] ?? '安装失败'));

            return self::FAILURE;
        }

        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        file_put_contents($lockPath, 'locked'.time());

        $this->info('安装完成。');
        $this->line('访问后台: '.rtrim($payload['appurl'], '/').'/login');

        if ($generatedPassword) {
            $this->warn('未提供管理员密码，已自动生成临时密码，请首次登录后立即修改：'.$password);
        }

        return self::SUCCESS;
    }
}
