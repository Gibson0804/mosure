<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientCors
{
    public function handle(Request $request, Closure $next): Response
    {
        // 客户端接口的 CORS 已统一由全局 OpenCors 中间件处理，
        // 此处不再覆盖响应头，避免出现任意 Origin 的宽松策略。
        return $next($request);
    }
}
