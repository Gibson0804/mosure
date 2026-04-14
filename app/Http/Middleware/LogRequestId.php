<?php

namespace App\Http\Middleware;

use App\Support\RequestId;
use App\Support\StructuredLogger;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LogRequestId
{
    public function handle($request, Closure $next)
    {
        // Log::info('LogRequestId_handle', [
        //     'method' => $request->getMethod(),
        //     'url' => $request->url(),
        //     'header' => $request->header(),
        // ]);
        try {
            // 优先沿用传入的请求ID，否则生成新的请求ID
            $requestId = RequestId::make($request->header('X-Request-ID'));

            // 将请求ID存储到请求中
            $request->headers->set('X-Request-ID', $requestId);
            if (function_exists('session')) {
                session(['X-Request-ID' => $requestId]);
            }
            RequestId::share($requestId);

            // 记录请求开始时间和收集请求信息
            $startTime = microtime(true);

            // 继续处理请求
            $response = $next($request);

            // 计算请求处理时间
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000); // 转换为毫秒

            $this->log($request, $response, $duration);

            // 在响应头中添加请求ID
            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } catch (\Throwable $th) {
            try {
                RequestId::share($requestId ?? RequestId::current());
                StructuredLogger::error('http.request.exception', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'error' => $th->getMessage(),
                ], 'request');
            } catch (\Throwable $e) {
                // ignore
            }
            throw $th;
        }
    }

    public function log($request, $response, $duration)
    {
        if ($response instanceof BinaryFileResponse) {
            return;
        }

        // 不记录日志的path
        $excludePaths = [
            '.well-known/appspecific/com.chrome.devtools.json',
        ];

        if (in_array($request->path(), $excludePaths)) {
            return;
        }

        $context = [
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
            'ip' => $request->ip(),
            'status' => $response->status(),
            'duration_ms' => $duration,
            'response_size' => $this->responseSize($response),
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'user_agent' => $request->userAgent(),
            'query' => $request->query(),
            'request_field_names' => array_keys($request->except([
                'password', 'password_confirmation', 'token', 'api_token', '_token',
            ])),
        ];

        // 记录日志
        if ($response->status() >= 500) {
            StructuredLogger::error('http.request', $context, 'request');
        } elseif ($response->status() >= 400) {
            StructuredLogger::warning('http.request', $context, 'request');
        } else {
            StructuredLogger::info('http.request', $context, 'request');
        }
    }

    private function responseSize($response): int
    {
        if ($response instanceof JsonResponse || $response instanceof HttpResponse) {
            $content = $response->getContent();

            return is_string($content) ? strlen($content) : 0;
        }

        return 0;

    }
}
