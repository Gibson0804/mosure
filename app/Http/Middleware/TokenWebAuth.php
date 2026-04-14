<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 移动端 WebView 页面的 Token 认证中间件
 * 优先使用 Header 鉴权；若必须通过查询参数进入，则认证后立即重定向清除 token
 */
class TokenWebAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && session('token_web_auth_user_id') === Auth::id()) {
            if ($request->isMethod('GET') && $request->query->has('token')) {
                $query = $request->query();
                unset($query['token']);
                $target = $request->url();
                if (! empty($query)) {
                    $target .= '?'.http_build_query($query);
                }

                return redirect()->to($target);
            }

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

        $fromQuery = false;
        if ($token === '') {
            $token = (string) $request->query('token', '');
            $fromQuery = $token !== '';
        }

        if ($token === '') {
            abort(401, '缺少认证 token');
        }

        $user = User::where('api_token', $token)->where('is_active', true)->first();

        if (! $user) {
            abort(401, '无效的 token');
        }

        Auth::login($user);
        session([
            'token_web_auth_user_id' => $user->id,
            'token_web_auth_token' => $token,
        ]);
        $request->setUserResolver(fn () => $user);

        if ($fromQuery && $request->isMethod('GET')) {
            $query = $request->query();
            unset($query['token']);
            $target = $request->url();
            if (! empty($query)) {
                $target .= '?'.http_build_query($query);
            }

            return redirect()->to($target);
        }

        return $next($request);
    }
}
