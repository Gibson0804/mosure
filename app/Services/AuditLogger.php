<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuditLogger
{
    private ProjectConfigService $configService;

    public function __construct(ProjectConfigService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * 记录审计日志（项目级别表，基于 session current_project_prefix 动态路由）
     */
    public function log(
        string $action,
        string $module,
        ?string $resourceTable,
        ?int $resourceId,
        ?array $before,
        ?array $after,
        Request $request,
        ?string $resourceType = null,
        string $status = 'success',
        ?string $errorMessage = null,
        array $meta = []
    ): void {
        try {
            // 检查审计是否启用
            $config = $this->getAuditConfig();
            if (! $config['enable_audit']) {
                return;
            }

            $apiKeyId = $request->attributes->get('api_key_id');
            $actorType = $apiKeyId ? 'api' : 'user';

            $maskedBefore = $this->mask($before, $config['mask_fields']);
            $maskedAfter = $this->mask($after, $config['mask_fields']);
            $diff = $this->diff($maskedBefore, $maskedAfter);

            $payload = [
                'actor_type' => $actorType,
                'actor_id' => null,
                'actor_name' => null,
                // 不存储原始 API Key，避免敏感信息泄露
                'api_key' => null,
                'action' => $action,
                'module' => $module,
                'resource_type' => $resourceType,
                'resource_table' => $resourceTable,
                'resource_id' => $resourceId,
                'request_method' => $request->getMethod(),
                'request_path' => $request->path(),
                'request_ip' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'request_id' => $request->header('X-Request-Id') ?? session('X-Request-ID', (string) Str::uuid()),
                'status' => $status,
                'error_message' => $errorMessage,
                'before_data' => $maskedBefore,
                'after_data' => $maskedAfter,
                'diff' => $diff,
                'meta' => array_merge($meta, [
                    'api_key_id' => $apiKeyId,
                ]),
            ];

            AuditLog::query()->create($payload);
        } catch (\Throwable $e) {
            // 审计失败不影响主流程
            Log::error('Audit Log Error: '.$e->getMessage());
        }
    }

    /**
     * 获取审计配置
     */
    private function getAuditConfig(): array
    {
        try {
            $config = $this->configService->getConfig();

            return [
                'enable_audit' => $config['audit']['enable_audit'] ?? true,
                'mask_fields' => $config['audit']['mask_fields'] ?? ['password', 'token', 'secret'],
            ];
        } catch (\Throwable $e) {
            // 如果获取配置失败，使用默认值
            Log::warning('Failed to get audit config, using defaults: '.$e->getMessage());

            return [
                'enable_audit' => true,
                'mask_fields' => ['password', 'token', 'secret'],
            ];
        }
    }

    private function mask(?array $data, array $maskKeys): ?array
    {
        if (! $data) {
            return $data;
        }
        $res = [];
        foreach ($data as $k => $v) {
            if (in_array(strtolower((string) $k), $maskKeys, true)) {
                $res[$k] = '***';
            } elseif (is_array($v)) {
                $res[$k] = $this->mask($v, $maskKeys);
            } else {
                $res[$k] = $v;
            }
        }

        return $res;
    }

    private function diff(?array $before, ?array $after): ?array
    {
        if ($before === null && $after === null) {
            return null;
        }
        $keys = array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? [])));
        $changes = [];
        foreach ($keys as $k) {
            $b = $before[$k] ?? null;
            $a = $after[$k] ?? null;
            if ($b !== $a) {
                $changes[$k] = [$b, $a];
            }
        }

        return $changes ?: null;
    }
}
