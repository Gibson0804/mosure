<?php

namespace App\Services;

use App\Exceptions\PageNoticeException;
use App\Models\ApiKey;
use App\Repository\ApiKeyRepository;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class ApiKeyService extends BaseService
{
    private $apiKeyRepository;

    public function __construct(ApiKeyRepository $apiKeyRepository)
    {
        $this->apiKeyRepository = $apiKeyRepository;
    }

    /**
     * 根据ID获取API密钥
     */
    public function getById($id)
    {
        try {
            $this->ensureSchema();

            return $this->apiKeyRepository->getById($id);
        } catch (\Exception $e) {
            Log::error('获取API密钥失败: '.$e->getMessage(), ['id' => $id]);
            throw new PageNoticeException('获取API密钥失败');
        }
    }

    /**
     * 获取API密钥列表
     */
    public function getList($perPage = 15, $page = 1)
    {
        try {
            $this->ensureSchema();

            return $this->apiKeyRepository->getAll($perPage, $page);
        } catch (\Exception $e) {
            Log::error('获取API密钥列表失败: '.$e->getMessage());
            throw new PageNoticeException('获取API密钥列表失败');
        }
    }

    /**
     * 创建API密钥
     */
    public function create($data)
    {
        try {
            $this->ensureSchema();
            // 验证数据
            $this->validateCreateData($data);

            $apiKeyData = [
                'name' => $data['name'],
                'key' => $data['api_key'],
                'description' => $data['description'] ?? null,
                'rate_limit' => $data['rate_limit'] ?? 1000,
                'expires_at' => isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
                'is_active' => $data['is_active'] ?? true,
                'allowed_ips' => $data['allowed_ips'] ?? null,
                'scopes' => $this->normalizeScopes($data['scopes'] ?? ApiKey::availableScopes()),
            ];

            $apiKey = $this->apiKeyRepository->create($apiKeyData);

            Log::info('API密钥创建成功', ['api_key_id' => $apiKey->id, 'name' => $apiKey->name]);

            return $apiKey;
        } catch (\Exception $e) {
            Log::error('创建API密钥失败: '.$e->getMessage(), ['data' => $data]);
            throw new PageNoticeException('创建API密钥失败: '.$e->getMessage());
        }
    }

    /**
     * 更新API密钥
     */
    public function update($id, $data)
    {
        try {
            $this->ensureSchema();
            $this->validateUpdateData($data);

            // 如果要更新密钥，需要验证唯一性
            if (isset($data['api_key'])) {
                if (! $this->apiKeyRepository->isKeyAvailable($data['api_key'], $id)) {
                    throw new PageNoticeException('API密钥已存在');
                }
            }

            $updateData = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'rate_limit' => $data['rate_limit'] ?? 1000,
                'is_active' => $data['is_active'] ?? true,
                'allowed_ips' => $data['allowed_ips'] ?? null,
                'scopes' => $this->normalizeScopes($data['scopes'] ?? ApiKey::availableScopes()),
            ];

            // 处理过期时间
            if (isset($data['expires_at'])) {
                $updateData['expires_at'] = $data['expires_at'] ? Carbon::parse($data['expires_at']) : null;
            }

            // 如果更新了密钥
            if (isset($data['api_key'])) {
                $updateData['key'] = $data['api_key'];
            }

            $apiKey = $this->apiKeyRepository->update($id, $updateData);

            Log::info('API密钥更新成功', ['api_key_id' => $apiKey->id, 'name' => $apiKey->name]);

            return $apiKey;
        } catch (\Exception $e) {
            Log::error('更新API密钥失败: '.$e->getMessage(), ['id' => $id, 'data' => $data]);
            throw new PageNoticeException('更新API密钥失败: '.$e->getMessage());
        }
    }

    /**
     * 删除API密钥
     */
    public function delete($id)
    {
        try {
            $this->ensureSchema();
            $apiKey = $this->apiKeyRepository->getById($id);

            $this->apiKeyRepository->delete($id);

            Log::info('API密钥删除成功', ['api_key_id' => $id, 'name' => $apiKey->name]);

            return true;
        } catch (\Exception $e) {
            Log::error('删除API密钥失败: '.$e->getMessage(), ['id' => $id]);
            throw new PageNoticeException('删除API密钥失败: '.$e->getMessage());
        }
    }

    /**
     * 切换API密钥状态
     */
    public function toggle($id)
    {
        try {
            $this->ensureSchema();
            $apiKey = $this->apiKeyRepository->toggleStatus($id);

            Log::info('API密钥状态切换成功', [
                'api_key_id' => $id,
                'name' => $apiKey->name,
                'is_active' => $apiKey->is_active,
            ]);

            return $apiKey;
        } catch (\Exception $e) {
            Log::error('切换API密钥状态失败: '.$e->getMessage(), ['id' => $id]);
            throw new PageNoticeException('切换API密钥状态失败: '.$e->getMessage());
        }
    }

    /**
     * 生成新的API密钥（仅生成密钥，不保存到数据库）
     */
    public function generateKey($data)
    {
        try {
            $this->ensureSchema();

            // 生成新密钥
            $newKey = $this->apiKeyRepository->generateUniqueKey();

            return [
                'api_key' => $newKey,
            ];
        } catch (\Exception $e) {
            Log::error('生成API密钥失败: '.$e->getMessage(), ['data' => $data]);
            throw new PageNoticeException('生成API密钥失败: '.$e->getMessage());
        }
    }

    /**
     * 验证API密钥
     */
    public function validateApiKey($key, $ip, $method, $endpoint)
    {
        try {
            $this->ensureSchema();
            $apiKey = $this->apiKeyRepository->getByKey($key);

            if (! $apiKey) {
                return ['valid' => false, 'message' => 'API密钥不存在'];
            }

            if (! $apiKey->is_active) {
                return ['valid' => false, 'message' => 'API密钥已禁用'];
            }

            if ($apiKey->isExpired()) {
                return ['valid' => false, 'message' => 'API密钥已过期'];
            }

            if (! $apiKey->isIpAllowed($ip)) {
                return ['valid' => false, 'message' => 'IP地址不允许访问'];
            }

            // 检查请求频次限制
            $rateLimit = $apiKey->rate_limit ?? 1000; // 默认每分钟1000次
            $limiterKey = 'api_key:'.$apiKey->id.':rate_limit';

            if (RateLimiter::tooManyAttempts($limiterKey, $rateLimit)) {
                $seconds = RateLimiter::availableIn($limiterKey);

                return [
                    'valid' => false,
                    'message' => "请求过于频繁，请稍后再试。请在 {$seconds} 秒后重试。",
                    'retry_after' => $seconds,
                ];
            }

            // 记录请求次数
            RateLimiter::hit($limiterKey, 60); // 60秒时间窗口

            // 记录使用情况
            $apiKey->incrementUsage();

            // 记录请求日志
            $this->apiKeyRepository->logRequest(
                $apiKey->id,
                $method,
                $endpoint,
                $ip,
                request()->userAgent(),
                200,
                request()->all(),
                null,
                0
            );

            return ['valid' => true, 'api_key' => $apiKey];
        } catch (\Exception $e) {
            Log::error('验证API密钥失败: '.$e->getMessage(), ['key' => $key]);

            return ['valid' => false, 'message' => '验证失败'];
        }
    }

    /**
     * 获取API密钥使用统计
     */
    public function getUsageStats($id)
    {
        try {
            return $this->apiKeyRepository->getUsageStats($id);
        } catch (\Exception $e) {
            Log::error('获取API密钥使用统计失败: '.$e->getMessage(), ['id' => $id]);
            throw new PageNoticeException('获取使用统计失败');
        }
    }

    /**
     * 获取API请求日志
     */
    public function getLogs($apiKeyId, $perPage = 20, $page = 1)
    {
        try {
            return $this->apiKeyRepository->getLogs($apiKeyId, $perPage, $page);
        } catch (\Exception $e) {
            Log::error('获取API请求日志失败: '.$e->getMessage(), ['api_key_id' => $apiKeyId]);
            throw new PageNoticeException('获取请求日志失败');
        }
    }

    /**
     * 清理过期的API密钥
     */
    public function cleanupExpired()
    {
        try {
            $count = $this->apiKeyRepository->cleanupExpiredKeys();
            Log::info('清理过期API密钥完成', ['count' => $count]);

            return $count;
        } catch (\Exception $e) {
            Log::error('清理过期API密钥失败: '.$e->getMessage());
            throw new PageNoticeException('清理过期密钥失败');
        }
    }

    /**
     * 验证创建数据
     */
    private function validateCreateData($data)
    {
        if (empty($data['name'])) {
            throw new PageNoticeException('API密钥名称不能为空');
        }

        if (isset($data['rate_limit']) && ($data['rate_limit'] < 1 || $data['rate_limit'] > 10000)) {
            throw new PageNoticeException('请求限制必须在1-10000之间');
        }

        if (isset($data['expires_at'])) {
            try {
                Carbon::parse($data['expires_at']);
            } catch (\Exception $e) {
                throw new PageNoticeException('过期时间格式无效');
            }
        }

        $this->assertValidScopes($data['scopes'] ?? ApiKey::availableScopes());
    }

    /**
     * 验证更新数据
     */
    private function validateUpdateData($data)
    {
        if (empty($data['name'])) {
            throw new PageNoticeException('API密钥名称不能为空');
        }

        if (isset($data['rate_limit']) && ($data['rate_limit'] < 1 || $data['rate_limit'] > 10000)) {
            throw new PageNoticeException('请求限制必须在1-10000之间');
        }

        if (isset($data['expires_at'])) {
            try {
                Carbon::parse($data['expires_at']);
            } catch (\Exception $e) {
                throw new PageNoticeException('过期时间格式无效');
            }
        }

        $this->assertValidScopes($data['scopes'] ?? ApiKey::availableScopes());
    }

    private function assertValidScopes($scopes): void
    {
        if (! is_array($scopes)) {
            throw new PageNoticeException('API 权限配置格式无效');
        }

        if ($scopes === []) {
            throw new PageNoticeException('请至少选择一个 API 权限');
        }

        $allowed = ApiKey::availableScopes();
        foreach ($scopes as $scope) {
            if (! in_array((string) $scope, $allowed, true)) {
                throw new PageNoticeException('存在不支持的 API 权限项');
            }
        }
    }

    private function normalizeScopes($scopes): array
    {
        $this->assertValidScopes($scopes);

        return array_values(array_unique(array_map('strval', $scopes)));
    }

    private function ensureSchema(): void
    {
        $tableName = (new ApiKey)->getTable();
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, ApiKey::getTableSchema());

            return;
        }

        if (! Schema::hasColumn($tableName, 'scopes')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->json('scopes')->nullable()->after('allowed_ips')->comment('允许的接口权限范围');
            });
        }
    }
}
