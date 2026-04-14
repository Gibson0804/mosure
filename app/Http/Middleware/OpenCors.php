<?php

namespace App\Http\Middleware;

use App\Services\ProjectConfigService;
use App\Support\StructuredLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OpenCors
{
    protected $configService;

    public function __construct(ProjectConfigService $configService)
    {
        $this->configService = $configService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // 只对 /open 和 /client 路径启用 CORS
        if (! $request->is('open/*') && ! $request->is('client/*')) {
            return $next($request);
        }

        // 获取请求的Origin
        $requestOrigin = (string) $request->header('Origin', '');

        // OPTIONS 预检请求拿不到实际认证凭证；实际请求统一按项目 API CORS 配置校验。
        if ($request->getMethod() === 'OPTIONS') {
            if ($requestOrigin === '') {
                return response('', 204);
            }

            if ($request->is('client/*')) {
                if (! $this->isTrustedClientOrigin($requestOrigin)) {
                    return response()->json([
                        'code' => 403,
                        'message' => 'Client origin not allowed',
                        'data' => null,
                    ], 403);
                }

                $response = response('', 204);
                $this->applyCorsHeaders($response, $requestOrigin);

                return $response;
            }

            $response = response('', 204);
            $this->applyWildcardCorsHeaders($response);

            return $response;
        }

        $response = $next($request);

        if ($request->is('client/*')) {
            if ($requestOrigin !== '' && ! $this->isTrustedClientOrigin($requestOrigin)) {
                return response()->json([
                    'code' => 403,
                    'message' => 'Client origin not allowed',
                    'data' => null,
                ], 403);
            }

            if ($requestOrigin !== '') {
                $this->applyCorsHeaders($response, $requestOrigin);
            }

            return $response;
        }

        return $this->applyProjectCorsPolicy($request, $response, $requestOrigin);
    }

    private function applyProjectCorsPolicy(Request $request, Response $response, string $requestOrigin): Response
    {
        // 实际请求：验证项目的 CORS 配置
        try {
            $config = $this->configService->getConfig();

            $enableCors = $config['api']['enable_cors'] ?? true;
            $allowedOrigins = $config['api']['allowed_origins'] ?? [];
            // 如果禁用了CORS，直接返回403
            if (! $enableCors) {
                return response()->json([
                    'code' => 403,
                    'message' => 'CORS is disabled for this project',
                    'data' => null,
                ], 403);
            }

            // 非浏览器请求（没有 Origin）允许继续
            if ($requestOrigin === '') {
                return $response;
            }

            // 验证 Origin 是否在允许列表中
            if (! empty($allowedOrigins)) {
                $allowed = false;
                foreach ($allowedOrigins as $allowedOrigin) {
                    if ($this->matchOrigin($requestOrigin, $allowedOrigin)) {
                        $allowed = true;
                        break;
                    }
                }

                if (! $allowed) {
                    return response()->json([
                        'code' => 403,
                        'message' => 'Origin not allowed',
                        'data' => null,
                    ], 403);
                }

                $origin = $requestOrigin;
            } else {
                $this->applyWildcardCorsHeaders($response);

                return $response;
            }

            $this->applyCorsHeaders($response, $origin);

            return $response;
        } catch (\Throwable $e) {
            // 如果获取配置失败（例如 prefix 未设置），允许请求继续
            // 这种情况下可能是系统错误，让后续中间件处理
            StructuredLogger::warning('cors.config.error', [
                'error' => $e->getMessage(),
                'path' => $request->path(),
                'origin' => $requestOrigin,
            ], 'security');

            if ($requestOrigin !== '' && $this->isTrustedClientOrigin($requestOrigin)) {
                $this->applyCorsHeaders($response, $requestOrigin);
            }

            return $response;
        }
    }

    private function applyCorsHeaders(Response $response, string $origin): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Client-Token, X-Project-Prefix, X-Api-Key, X-Client-Type, X-Client-Session, X-Project-User-Token');
        $response->headers->set('Access-Control-Max-Age', '86400');
        $response->headers->set('Access-Control-Allow-Credentials', 'false');
    }

    private function applyWildcardCorsHeaders(Response $response): void
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Client-Token, X-Project-Prefix, X-Api-Key, X-Client-Type, X-Client-Session, X-Project-User-Token');
        $response->headers->set('Access-Control-Max-Age', '86400');
        $response->headers->set('Access-Control-Allow-Credentials', 'false');
    }

    private function isTrustedClientOrigin(string $origin): bool
    {
        $allowed = [];

        $appUrl = config('app.url');
        if (is_string($appUrl) && $appUrl !== '') {
            $appOrigin = $this->extractOrigin($appUrl);
            if ($appOrigin !== null) {
                $allowed[] = $appOrigin;
            }
        }

        $extra = array_filter(array_map('trim', explode(',', (string) env('CLIENT_CORS_ALLOWED_ORIGINS', ''))));
        foreach ($extra as $item) {
            $normalized = $this->extractOrigin($item);
            if ($normalized !== null) {
                $allowed[] = $normalized;
            }
        }

        return in_array($origin, array_values(array_unique($allowed)), true);
    }

    private function extractOrigin(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = strtolower($parts['scheme']).'://'.$parts['host'];
        if (! empty($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    /**
     * 匹配Origin是否符合允许规则
     * 支持通配符，如 *.example.com
     */
    protected function matchOrigin(string $requestOrigin, string $allowedOrigin): bool
    {
        // 完全匹配
        if ($requestOrigin === $allowedOrigin) {
            return true;
        }

        // 通配符匹配
        if (strpos($allowedOrigin, '*') !== false) {
            $pattern = str_replace('\*', '.*', preg_quote($allowedOrigin, '/'));

            return (bool) preg_match('/^'.$pattern.'$/', $requestOrigin);
        }

        return false;
    }
}
