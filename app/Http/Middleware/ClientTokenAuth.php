<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ClientTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // 预检请求直接放行（由全局 OPTIONS 路由处理）
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $authHeader = (string) $request->header('Authorization', '');
        $token = '';

        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = trim(substr($authHeader, 7));
        }

        if ($token === '') {
            $token = (string) $request->header('X-Client-Token', '');
        }

        if ($token === '') {
            return response()->json([
                'code' => 401,
                'message' => 'Missing client token',
                'data' => null,
            ], 401);
        }

        /** @var User|null $user */
        $user = User::where('api_token', $token)->where('is_active', true)->first();

        if (! $user) {
            return response()->json([
                'code' => 401,
                'message' => 'Invalid or expired client token',
                'data' => null,
            ], 401);
        }

        $requestedClientType = (string) $request->header('X-Client-Type', '');
        $clientType = in_array($requestedClientType, ['app', 'h5', 'chrome'], true)
            ? $requestedClientType
            : 'app';
        $sessionKey = (string) $request->header('X-Client-Session', '');
        $currentSessionKey = (string) Cache::get("client_session:{$user->id}:{$clientType}", '');

        if ($sessionKey === '' || $currentSessionKey === '' || ! hash_equals($currentSessionKey, $sessionKey)) {
            return response()->json([
                'code' => 401,
                'message' => 'Client session expired',
                'data' => null,
            ], 401);
        }

        // 将当前用户注入到认证上下文
        Auth::setUser($user);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // 统一校验与注入项目前缀（除特定接口外）
        $bypassPrefixCheck =
            $request->is('client/auth/logout') ||
            $request->is('api/chrome/auth/login') ||
            $request->is('client/me') ||
            $request->is('api/chrome/me') ||
            $request->is('client/projects') ||
            $request->is('api/chrome/projects') ||
            $request->is('client/kb/*');

        if (! $bypassPrefixCheck) {
            $prefix = (string) ($request->input('project_prefix')
                ?? $request->header('X-Project-Prefix')
                ?? '');

            if ($prefix === '') {
                return response()->json([
                    'code' => 400,
                    'message' => 'Missing project_prefix',
                    'data' => null,
                ], 400);
            }

            // 注入会话，供 HasProjectPrefix 等使用
            session(['current_project_prefix' => $prefix]);
        }

        return $next($request);
    }
}
