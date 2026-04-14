<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Services\ApiKeyService;
use App\Services\ProjectAuthService;
use App\Services\OpenApiPermissionResolver;
use App\Services\ProjectConfigService;
use App\Services\ProjectService;
use App\Support\StructuredLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    protected $apiKeyService;

    protected $projectService;

    protected $configService;

    protected ProjectAuthService $projectAuthService;

    protected OpenApiPermissionResolver $permissionResolver;

    public function __construct(ApiKeyService $apiKeyService, ProjectService $projectService, ProjectConfigService $configService, ProjectAuthService $projectAuthService, OpenApiPermissionResolver $permissionResolver)
    {
        $this->apiKeyService = $apiKeyService;
        $this->projectService = $projectService;
        $this->configService = $configService;
        $this->projectAuthService = $projectAuthService;
        $this->permissionResolver = $permissionResolver;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Log::info('ValidateApiKey middleware', [
        //     'method' => $request->getMethod(),
        //     'url' => $request->url(),
        //     'header' => $request->header(),
        // ]);
        // OPTIONS 预检请求直接放行，由 OpenCors 中间件处理
        if ($request->getMethod() === 'OPTIONS') {
            return $next($request);
        }

        // 托管页面 Sites Token：同源免密，仅提取项目前缀
        $sitesToken = (string) ($request->header('X-Sites-Token') ?? '');
        if ($sitesToken !== '' && str_starts_with($sitesToken, 'st_')) {
            $prefix = substr($sitesToken, 3); // st_{prefix}
            if ($prefix !== '' && $this->projectService->checkPrefix($prefix)) {
                // Referer 同源校验
                $referer = (string) $request->header('Referer', '');
                $appUrl = rtrim(config('app.url', ''), '/');
                $sitesBase = $appUrl.'/sites/'.$prefix;
                if ($referer !== '' && (str_starts_with($referer, $sitesBase.'/') || $referer === $sitesBase)) {
                    session(['current_project_prefix' => $prefix]);

                    return $next($request);
                }
            }

            return response()->json([
                'code' => 403,
                'message' => 'Invalid sites token or request origin',
                'data' => null,
            ], 403);
        }

        // 项目用户认证公开入口：登录/注册不需要 API Key，控制器内部按项目配置校验
        if ($this->isProjectAuthPublicEndpoint($request)) {
            $segments = explode('/', trim($request->path(), '/'));
            $prefix = $segments[2] ?? null;
            if (! $prefix || ! $this->projectService->checkPrefix($prefix)) {
                return response()->json([
                    'code' => 403,
                    'message' => 'Invalid project prefix',
                    'data' => null,
                ], 403);
            }
            session(['current_project_prefix' => $prefix]);

            return $next($request);
        }

        // 项目用户登录态：前台用户登录后可直接按角色权限访问 OpenAPI
        $projectUserToken = (string) ($request->header('X-Project-User-Token') ?? '');
        if ($projectUserToken === '') {
            $authHeader = (string) $request->header('Authorization', '');
            if (str_starts_with($authHeader, 'Bearer pu_')) {
                $projectUserToken = trim(substr($authHeader, strlen('Bearer ')));
            }
        }
        if ($projectUserToken !== '') {
            $prefix = $this->projectAuthService->extractPrefixFromToken($projectUserToken);
            if (! $prefix || ! $this->projectService->checkPrefix($prefix)) {
                return response()->json([
                    'code' => 403,
                    'message' => 'Invalid project prefix in project user token',
                    'data' => null,
                ], 403);
            }
            session(['current_project_prefix' => $prefix]);
            $projectUser = $this->projectAuthService->authenticateToken($projectUserToken);
            if (! $projectUser || ! $this->projectAuthService->tokenAllowsRequest($projectUser, $request, $this->permissionResolver)) {
                StructuredLogger::securityWarning('auth.project_user.denied', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'project_user_id' => $projectUser?->id,
                ]);

                return response()->json([
                    'code' => 403,
                    'message' => 'Project user does not have permission to access this endpoint',
                    'data' => null,
                ], 403);
            }

            $request->attributes->set('auth_subject_type', 'project_user');
            $request->attributes->set('project_user_id', $projectUser->id);

            return $next($request);
        }

        // 外部调用走常规 API Key 校验
        $apiKey = $request->header('X-API-KEY');
        // 兼容 Authorization Bearer中
        if (! $apiKey) {
            $apiKey = $request->header('Authorization');
            if (str_starts_with($apiKey, 'Bearer ')) {
                $apiKey = substr($apiKey, strlen('Bearer '));
            }
        }
        if (! $apiKey) {
            StructuredLogger::securityWarning('auth.api_key.missing', [
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'code' => 401,
                'message' => 'API Key is required',
                'data' => null,
            ], 401);
        }

        // 从密钥中提取项目前缀
        $prefix = $this->extractPrefixFromApiKey($apiKey);
        if (! $prefix || ! $this->projectService->checkPrefix($prefix)) {
            StructuredLogger::securityWarning('auth.api_key.invalid_prefix', [
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'code' => 403,
                'message' => 'Invalid project prefix in API Key',
                'data' => null,
            ], 403);
        }

        session(['current_project_prefix' => $prefix]);

        $isValid = $this->apiKeyService->validateApiKey($apiKey, $request->ip(), $request->method(), $request->path());
        if (! $isValid['valid']) {
            StructuredLogger::securityWarning('auth.api_key.denied', [
                'path' => $request->path(),
                'method' => $request->method(),
                'reason' => $isValid['message'] ?? 'invalid',
            ]);

            return response()->json([
                'code' => 403,
                'message' => $isValid['message'],
                'data' => null,
            ], 403);
        }

        // 将验证通过的 api_key_id 透传到请求上下文，便于审计日志记录
        if (! empty($isValid['api_key']) && isset($isValid['api_key']->id)) {
            $request->attributes->set('api_key_id', $isValid['api_key']->id);
        }

        $apiKeyModel = $isValid['api_key'] ?? null;
        if ($apiKeyModel && ! $this->hasRequiredScope($request, $apiKeyModel)) {
            StructuredLogger::securityWarning('auth.api_key.scope_denied', [
                'path' => $request->path(),
                'method' => $request->method(),
                'required_scope' => $this->resolveRequiredScope($request),
            ]);

            return response()->json([
                'code' => 403,
                'message' => 'API Key does not have permission to access this endpoint',
                'data' => null,
            ], 403);
        }

        // 检查 IP 白名单
        if (! $this->checkIpWhitelist($request)) {
            StructuredLogger::securityWarning('auth.api_key.ip_denied', [
                'path' => $request->path(),
                'method' => $request->method(),
                'client_ip' => $request->ip(),
            ]);

            return response()->json([
                'code' => 403,
                'message' => 'IP address not in whitelist',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }


    private function isProjectAuthPublicEndpoint(Request $request): bool
    {
        $path = trim($request->path(), '/');
        $segments = explode('/', $path);

        return ($segments[0] ?? '') === 'open'
            && ($segments[1] ?? '') === 'auth'
            && in_array(($segments[3] ?? ''), ['login', 'register'], true)
            && $request->isMethod('POST');
    }

    /**
     * 从 API 密钥中提取项目前缀
     */
    private function extractPrefixFromApiKey($apiKey)
    {
        // 密钥格式：ak_{prefix}_{13位uniqid}_{10位time}
        if (! str_starts_with($apiKey, 'ak_')) {
            return null;
        }

        // 去掉 "ak_" 前缀（3个字符）
        $keyWithoutPrefix = substr($apiKey, 3);

        // 从后往前截取 10 位时间戳
        if (strlen($keyWithoutPrefix) < 25) { // 1 + 13 + 10 + 1 = 25（至少需要1个前缀字符）
            return null;
        }

        // 剩下的就是项目前缀（去掉最后的下划线）
        $prefix = substr($keyWithoutPrefix, 0, -25);

        return $prefix;
    }

    private function hasRequiredScope(Request $request, ApiKey $apiKey): bool
    {
        $requiredScope = $this->resolveRequiredScope($request);
        if ($requiredScope === null) {
            return true;
        }

        $scopes = $apiKey->scopes;
        if (! is_array($scopes) || $scopes === []) {
            return true;
        }

        return in_array($requiredScope, $scopes, true);
    }

    private function resolveRequiredScope(Request $request): ?string
    {
        return $this->permissionResolver->resolve($request)['scope'] ?? null;
    }

    /**
     * 检查 IP 白名单
     */
    private function checkIpWhitelist(Request $request): bool
    {
        try {
            $config = $this->configService->getConfig();
            $enableIpWhitelist = $config['api']['enable_ip_whitelist'] ?? false;

            // 如果未启用 IP 白名单，直接放行
            if (! $enableIpWhitelist) {
                return true;
            }

            $ipWhitelist = $config['api']['ip_whitelist'] ?? [];
            if (empty($ipWhitelist)) {
                return true;
            }

            $clientIp = $request->ip();
            if (! $clientIp) {
                return false;
            }

            // 检查 IP 是否在白名单中
            foreach ($ipWhitelist as $allowedIp) {
                if ($this->ipMatch($clientIp, $allowedIp)) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            // 如果获取配置失败，默认放行
            StructuredLogger::warning('auth.api_key.ip_whitelist_check_failed', [
                'error' => $e->getMessage(),
            ], 'security');

            return true;
        }
    }

    /**
     * 检查 IP 是否匹配（支持单个 IP 和 CIDR 格式）
     */
    private function ipMatch(string $clientIp, string $allowedIp): bool
    {
        // 精确匹配
        if ($clientIp === $allowedIp) {
            return true;
        }

        // CIDR 格式匹配
        if (str_contains($allowedIp, '/')) {
            return $this->ipMatchCidr($clientIp, $allowedIp);
        }

        return false;
    }

    /**
     * CIDR 格式 IP 匹配
     */
    private function ipMatchCidr(string $ip, string $cidr): bool
    {
        [$range, $netmask] = explode('/', $cidr, 2);
        $netmask = (int) $netmask;

        // 将 IP 和范围转换为整数
        $ipDecimal = ip2long($ip);
        $rangeDecimal = ip2long($range);

        if ($ipDecimal === false || $rangeDecimal === false) {
            return false;
        }

        // 计算网络掩码
        $mask = -1 << (32 - $netmask);
        $rangeDecimal &= $mask;

        // 检查 IP 是否在范围内
        return ($ipDecimal & $mask) === $rangeDecimal;
    }
}
