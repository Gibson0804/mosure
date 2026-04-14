<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Models\ProjectConfig;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyMcpAccess
{
    public function handle(Request $request, Closure $next)
    {
        $token = $this->extractToken($request);
        $baseContext = [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 180),
            'accept' => substr((string) $request->header('Accept', ''), 0, 180),
            'content_type' => substr((string) $request->header('Content-Type', ''), 0, 180),
            'request_id' => (string) $request->header('X-Request-Id', ''),
        ];

        Log::info('mcp.access.start', $baseContext + [
            'has_token' => $token !== null && $token !== '',
            'token_preview' => $this->maskToken($token),
        ]);

        if (! $token) {
            Log::warning('mcp.access.denied.missing_token', $baseContext);

            return response()->json([
                'message' => '缺少 MCP 访问令牌。请在请求头中提供 Authorization: Bearer <token> 或 X-MCP-Token。',
            ], 401);
        }

        if (strlen($token) < 17) {
            Log::warning('mcp.access.denied.bad_token_length', $baseContext + [
                'token_preview' => $this->maskToken($token),
                'token_length' => strlen($token),
            ]);

            return response()->json([
                'message' => 'MCP 访问令牌格式不正确。',
            ], 401);
        }

        $prefix = substr($token, 0, -16);
        $rand = substr($token, -16);
        if (! preg_match('/^[a-z0-9_]+$/', $prefix) || ! preg_match('/^[a-f0-9]{16}$/i', $rand)) {
            Log::warning('mcp.access.denied.bad_token_format', $baseContext + [
                'prefix' => $prefix,
                'token_preview' => $this->maskToken($token),
            ]);

            return response()->json([
                'message' => 'MCP 访问令牌无效：前缀或随机段格式错误。',
            ], 401);
        }

        $project = Project::where('prefix', $prefix)->first();
        session(['current_project_prefix' => $prefix]);
        if (! $project) {
            Log::warning('mcp.access.denied.project_not_found', $baseContext + [
                'prefix' => $prefix,
                'token_preview' => $this->maskToken($token),
            ]);

            return response()->json([
                'message' => 'MCP 访问令牌无效：未找到对应项目。',
            ], 401);
        }

        $enabledRaw = ProjectConfig::getQuery()
            ->where('config_group', 'mcp')
            ->where('config_key', 'enabled')
            ->value('config_value');
        $enabled = $this->jsonToScalar($enabledRaw) ?? false;

        if (! $enabled) {
            Log::warning('mcp.access.denied.disabled', $baseContext + [
                'prefix' => $prefix,
                'project_id' => $project->id,
                'enabled_raw' => $enabledRaw,
                'enabled_cast' => $enabled,
            ]);

            return response()->json([
                'message' => 'MCP 服务已关闭，请在“项目配置 - MCP”中启用后再尝试。',
            ], 403);
        }

        $storedToken = ProjectConfig::getQuery()
            ->where('config_group', 'mcp')
            ->where('config_key', 'token')
            ->value('config_value');

        if (! $storedToken || ! hash_equals($storedToken, $token)) {
            Log::warning('mcp.access.denied.token_mismatch', $baseContext + [
                'prefix' => $prefix,
                'project_id' => $project->id,
                'request_token_preview' => $this->maskToken($token),
                'stored_token_preview' => $this->maskToken($storedToken),
                'has_stored_token' => ! empty($storedToken),
            ]);

            return response()->json([
                'message' => 'MCP 访问令牌不匹配或尚未生成，请在“项目配置 - MCP”中生成令牌。',
            ], 401);
        }

        // 将项目前缀放入请求，便于下游使用（如工具层按项目工作）
        $request->attributes->set('mcp_project_prefix', $prefix);

        try {
            $response = $next($request);
            Log::info('mcp.access.allowed', $baseContext + [
                'prefix' => $prefix,
                'project_id' => $project->id,
                'status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                'session_id' => (string) $request->header('Mcp-Session-Id', ''),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::error('mcp.access.exception', $baseContext + [
                'prefix' => $prefix,
                'project_id' => $project->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function extractToken(Request $request): ?string
    {
        $auth = $request->header('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }
        $header = $request->header('X-MCP-Token');
        if (! empty($header)) {
            return trim($header);
        }

        return null;
    }

    private function maskToken(?string $token): ?string
    {
        if ($token === null || $token === '') {
            return $token;
        }

        if (strlen($token) <= 10) {
            return substr($token, 0, 2).'***';
        }

        return substr($token, 0, 6).'***'.substr($token, -4);
    }

    private function jsonToScalar($raw)
    {
        if ($raw === null) {
            return null;
        }
        if (is_bool($raw) || is_numeric($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $value = trim($raw);
            if ($value === '1' || strtolower($value) === 'true') {
                return true;
            }
            if ($value === '0' || strtolower($value) === 'false' || $value === '') {
                return false;
            }

            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
        }

        $decoded = null;
        try {
            $decoded = json_decode((string) $raw, true);
        } catch (\Throwable $e) {
            // ignore
        }
        if (is_array($decoded)) {
            return $decoded;
        }

        return $decoded;
    }
}
