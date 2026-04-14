<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private function resolveClientType(?string $clientType): string
    {
        if (in_array($clientType, ['h5', 'web'], true)) {
            return 'h5';
        }

        return $clientType === 'chrome' ? 'chrome' : 'app';
    }

    private function sessionCacheKey(int $userId, string $clientType): string
    {
        return "client_session:{$userId}:{$clientType}";
    }

    private function issueClientSession(User $user, string $clientType): array
    {
        if (! $user->api_token) {
            $user->api_token = Str::random(60);
            $user->save();
        }

        $sessionKey = Str::random(64);
        Cache::forever($this->sessionCacheKey((int) $user->id, $clientType), $sessionKey);

        return [
            'token' => $user->api_token,
            'client_type' => $clientType,
            'session_key' => $sessionKey,
        ];
    }

    /**
     * 客户端登录接口（基于邮箱 + 密码），返回 JSON。
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required'],
            'password' => ['required'],
            'client_type' => ['nullable', 'string'],
        ], [
            'email.required' => '请输入邮箱',
            'password.required' => '请输入密码',
        ]);

        // 尝试从数据库获取用户
        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'code' => 401,
                'message' => '账号或密码错误',
                'data' => null,
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'code' => 403,
                'message' => '账户未激活或已被禁用',
                'data' => null,
            ], 403);
        }

        $clientType = $this->resolveClientType($credentials['client_type'] ?? null);
        $session = $this->issueClientSession($user, $clientType);

        return response()->json([
            'code' => 200,
            'message' => 'login_success',
            'data' => [
                'token' => $session['token'],
                'client_type' => $session['client_type'],
                'session_key' => $session['session_key'],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'is_admin' => (bool) $user->is_admin,
                ],
            ],
        ]);
    }

    /**
     * 客户端退出登录接口。
     */
    public function logout(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user) {
            // 获取当前请求的 token
            $authHeader = (string) $request->header('Authorization', '');
            $token = '';

            if (str_starts_with($authHeader, 'Bearer ')) {
                $token = trim(substr($authHeader, 7));
            }

            if ($token === '') {
                $token = (string) $request->header('X-Client-Token', '');
            }

            $clientType = $this->resolveClientType((string) $request->header('X-Client-Type', ''));
            $sessionKey = (string) $request->header('X-Client-Session', '');

            if ($user->api_token === $token && $sessionKey !== '') {
                $cacheKey = $this->sessionCacheKey((int) $user->id, $clientType);
                $currentSessionKey = (string) Cache::get($cacheKey, '');

                if ($currentSessionKey !== '' && hash_equals($currentSessionKey, $sessionKey)) {
                    Cache::forget($cacheKey);
                }
            }

            $hasAppSession = (string) Cache::get($this->sessionCacheKey((int) $user->id, 'app'), '') !== '';
            $hasH5Session = (string) Cache::get($this->sessionCacheKey((int) $user->id, 'h5'), '') !== '';
            $hasChromeSession = (string) Cache::get($this->sessionCacheKey((int) $user->id, 'chrome'), '') !== '';

            if (! $hasAppSession && ! $hasH5Session && ! $hasChromeSession) {
                $user->api_token = null;
                $user->save();
            }
        }

        return response()->json([
            'code' => 200,
            'message' => 'logout_success',
            'data' => null,
        ]);
    }

    /**
     * 获取当前登录用户信息。
     */
    public function me(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'code' => 401,
                'message' => 'Unauthenticated',
                'data' => null,
            ], 401);
        }

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'is_admin' => (bool) $user->is_admin,
                ],
            ],
        ]);
    }

    /**
     * 二维码登录接口
     */
    public function qrLogin(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'client_type' => ['nullable', 'string'],
        ]);

        // 从 cache 中查找有效的二维码令牌
        $cacheKey = "qr_login:{$data['token']}";
        $qrData = Cache::get($cacheKey);
        if (! $qrData || $qrData['used'] || now()->gt($qrData['expires_at'])) {

            return response()->json([
                'code' => 400,
                'message' => '二维码已过期或已使用',
                'data' => null,
            ], 400);
        }

        $user = User::find($qrData['user_id']);

        if (! $user) {
            return response()->json([
                'code' => 400,
                'message' => '用户不存在',
                'data' => null,
            ], 400);
        }

        if (! $user->is_active) {
            return response()->json([
                'code' => 403,
                'message' => '账户未激活或已被禁用',
                'data' => null,
            ], 403);
        }

        // 标记令牌为已使用
        $qrData['used'] = true;
        Cache::put($cacheKey, $qrData, now()->addMinutes(5));

        $clientType = $this->resolveClientType($data['client_type'] ?? null);
        $session = $this->issueClientSession($user, $clientType);

        return response()->json([
            'code' => 200,
            'message' => 'login_success',
            'data' => [
                'token' => $session['token'],
                'client_type' => $session['client_type'],
                'session_key' => $session['session_key'],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'is_admin' => (bool) $user->is_admin,
                ],
            ],
        ]);
    }
}
