<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Models\User;
use App\Services\SystemConfigService;
use App\Services\SystemMailService;
use App\Support\StructuredLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController
{
    private SystemMailService $mailService;

    private SystemConfigService $sysConfig;

    public function __construct(SystemMailService $mailService, SystemConfigService $sysConfig)
    {
        $this->mailService = $mailService;
        $this->sysConfig = $sysConfig;
    }

    /**
     * 显示登录页面
     *
     * @return \Inertia\Response
     */
    public function showLogin(): \Inertia\Response|\Illuminate\Http\RedirectResponse
    {
        if (! file_exists(base_path('.locked'))) {
            return redirect()->route('install.step1');
        }

        return viewShow('Auth/Login');
    }

    /**
     * 处理登录请求
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function doLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();

            StructuredLogger::securityInfo('auth.login.success', [
                'email' => (string) $request->input('email'),
                'ip' => $request->ip(),
            ]);

            // 登录成功后重定向到项目列表页面
            return redirect()->route('project.index');
        }

        StructuredLogger::securityWarning('auth.login.failed', [
            'email' => (string) $request->input('email'),
            'ip' => $request->ip(),
        ]);

        throw ValidationException::withMessages([
            'password' => ['账号或密码错误'],
        ]);
    }

    /**
     * 处理用户退出登录
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * 显示忘记密码页面
     *
     * @return \Inertia\Response
     */
    public function showForgotPassword()
    {
        return viewShow('Auth/ForgotPassword');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // 检查用户是否存在
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['该邮箱地址不存在。'],
            ]);
        }

        // 生成短期密码重置令牌并写入缓存（30 分钟有效）
        $token = Str::random(64);
        $normalizedEmail = Str::lower(trim((string) $request->email));
        $indexKey = $this->passwordResetEmailIndexKey($normalizedEmail);
        $oldCacheKey = Cache::get($indexKey);
        if (is_string($oldCacheKey) && $oldCacheKey !== '') {
            Cache::forget($oldCacheKey);
        }

        $cacheKey = $this->passwordResetCacheKey($token);
        Cache::put($cacheKey, ['email' => $normalizedEmail], now()->addMinutes(30));
        Cache::put($indexKey, $cacheKey, now()->addMinutes(30));

        // 构建重置链接
        $resetLink = route('password.reset', ['token' => $token, 'email' => $request->email]);

        // 发送密码重置邮件
        try {
            $this->mailService->send($user->email, new \App\Mail\PasswordReset($user, $token, $resetLink));

            return redirect()->back()->with('status', '密码重置链接已发送到您的邮箱，请查收。')->with('status_type', 'success');
        } catch (\Exception $e) {
            Cache::forget($cacheKey);
            Cache::forget($indexKey);
            StructuredLogger::securityWarning('auth.password_reset_mail.failed', [
                'email' => $normalizedEmail,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['邮件发送失败，请检查系统邮件配置后重试。'],
            ]);
        }
    }

    /**
     * 显示重置密码表单
     *
     * @param  string  $token
     * @return \Inertia\Response
     */
    public function showResetForm(Request $request, $token)
    {
        return viewShow('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * 重置用户密码
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resetPassword(Request $request)
    {
        $sec = Arr::get($this->sysConfig->getConfigRaw(), 'security', []);
        $min = (int) ($sec['password_min_length'] ?? 8);
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::min(max(1, $min))],
        ]);

        $normalizedEmail = Str::lower(trim((string) $request->email));
        $cacheKey = $this->passwordResetCacheKey((string) $request->token);
        $cached = Cache::pull($cacheKey);

        if (! is_array($cached) || ($cached['email'] ?? null) !== $normalizedEmail) {
            throw ValidationException::withMessages([
                'email' => ['此密码重置令牌无效或已过期。'],
            ]);
        }

        $user = User::where('email', $normalizedEmail)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['此密码重置令牌无效或已过期。'],
            ]);
        }

        Cache::forget($this->passwordResetEmailIndexKey($normalizedEmail));

        // 更新密码
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // 触发密码重置事件
        event(new PasswordReset($user));

        // 自动登录用户
        Auth::login($user);

        return redirect()->route('project.index')->with('status', '密码已成功重置！')->with('status_type', 'success');
    }

    private function passwordResetCacheKey(string $token): string
    {
        return 'password_reset:'.hash('sha256', $token);
    }

    private function passwordResetEmailIndexKey(string $email): string
    {
        return 'password_reset_email:'.hash('sha256', $email);
    }

    /**
     * 显示修改密码页面
     *
     * @return \Inertia\Response
     */
    public function showChangePassword()
    {
        return viewShow('System/ChangePassword');
    }

    /**
     * 修改当前登录用户的密码
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function changePassword(Request $request)
    {
        $sec = Arr::get($this->sysConfig->getConfigRaw(), 'security', []);
        $min = (int) ($sec['password_min_length'] ?? 8);
        $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Rules\Password::min(max(1, $min))],
        ]);

        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')->with('error', '请先登录');
        }

        // 验证当前密码
        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['当前密码不正确。'],
            ]);
        }

        // 更新密码
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()->with('status', '密码已成功更新！');
    }

    /**
     * 显示个人资料页面
     */
    public function showProfile(Request $request)
    {
        $u = $request->user();

        // 生成二维码登录令牌，使用 cache 存储，5分钟过期
        $token = Str::random(60);
        $cacheKey = "qr_login:{$token}";

        Cache::put($cacheKey, [
            'user_id' => $u->id,
            'expires_at' => now()->addMinutes(5)->toDateTimeString(),
            'used' => false,
        ], now()->addMinutes(5));

        return viewShow('System/Profile', [
            'user' => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar' => $u->avatar,
            ],
            'qr_login_token' => $token,
        ]);
    }

    /**
     * 更新个人资料
     */
    public function updateProfile(Request $request)
    {
        $u = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('sys_users', 'email')->ignore($u->id)],
            'avatar' => ['nullable', 'string'],
        ], [
            'name.required' => '昵称不能为空',
            'email.required' => '邮箱不能为空',
            'email.email' => '请输入有效的邮箱',
            'email.unique' => '该邮箱已被占用',
        ]);

        $u->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'avatar' => $data['avatar'] ?? $u->avatar,
        ]);

        return redirect()->back()->with('status', '资料已更新');
    }
}
