<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetAdminPasswordCommand extends Command
{
    protected $signature = 'mosure:reset-password
        {--email= : 指定管理员邮箱，不传则自动查找管理员用户}
        {--password= : 指定新密码，不传则自动生成}';

    protected $description = '重置 Mosure 管理员密码并打印用户名和新密码';

    public function handle(): int
    {
        $this->newLine();
        $this->line('========================================');
        $this->line('  Mosure 管理员密码重置工具');
        $this->line('========================================');
        $this->newLine();

        // 查找管理员用户
        $email = $this->option('email');

        if ($email) {
            $admin = User::where('email', $email)->first();

            if (!$admin) {
                $this->error("[X] 未找到邮箱为 {$email} 的用户。");
                $this->listUsers();
                return self::FAILURE;
            }
        } else {
            $admin = User::where('is_admin', true)->first();

            if (!$admin) {
                $admin = User::first();
            }

            if (!$admin) {
                $this->error('[X] 系统中没有任何用户，请先运行安装程序：php artisan mosure:install');
                return self::FAILURE;
            }
        }

        // 生成或使用指定的新密码
        $newPassword = $this->option('password');
        $generated = false;

        if (!$newPassword || trim($newPassword) === '') {
            $newPassword = Str::random(16);
            $generated = true;
        }

        if (strlen($newPassword) < 6) {
            $this->error('[X] 密码长度不能少于 6 个字符。');
            return self::FAILURE;
        }

        // 更新密码
        $admin->password = Hash::make($newPassword);
        $admin->save();

        // 验证
        if (Hash::check($newPassword, $admin->fresh()->password)) {
            $this->info('[OK] 管理员密码重置成功！');
        } else {
            $this->error('[X] 密码更新验证失败。');
            return self::FAILURE;
        }

        $this->newLine();
        $this->line(str_repeat('=', 45));
        $this->line('  管理员信息');
        $this->line(str_repeat('=', 45));
        $this->line('  用户名  : ' . ($admin->name ?? '-'));
        $this->line('  邮  箱  : ' . $admin->email);
        $this->line('  新密码  : ' . $newPassword);
        if ($generated) {
            $this->line('  (密码为自动生成，请妥善保存)');
        }
        $this->line(str_repeat('=', 45));
        $this->newLine();
        $this->line('登录地址: ' . config('app.url', 'http://127.0.0.1:9445') . '/login');
        $this->newLine();

        return self::SUCCESS;
    }

    private function listUsers(): void
    {
        $users = User::all(['id', 'name', 'email', 'is_admin']);

        if ($users->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line('当前系统中的用户：');
        $this->table(
            ['ID', '用户名', '邮箱', '管理员'],
            $users->map(fn($u) => [
                $u->id,
                $u->name ?? '-',
                $u->email ?? '-',
                $u->is_admin ? '是' : '否',
            ])
        );
    }
}
