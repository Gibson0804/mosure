<?php

namespace App\Repository;

use App\Models\ApiKey;
use App\Models\ApiLog;

class ApiKeyRepository
{
    /**
     * 获取所有API密钥
     */
    public function getAll($perPage = 15, $page = 1)
    {
        $query = ApiKey::query()
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $data = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
        ];
    }

    /**
     * 根据ID获取API密钥
     */
    public function getById($id)
    {
        return ApiKey::findOrFail($id);
    }

    /**
     * 创建API密钥
     */
    public function create($data)
    {
        return ApiKey::create($data);
    }

    /**
     * 更新API密钥
     */
    public function update($id, $data)
    {
        $apiKey = $this->getById($id);
        $apiKey->update($data);

        return $apiKey->fresh();
    }

    /**
     * 删除API密钥
     */
    public function delete($id)
    {
        $apiKey = $this->getById($id);

        return $apiKey->delete();
    }

    /**
     * 根据密钥值获取API密钥
     */
    public function getByKey($key)
    {
        $res = ApiKey::where('key', $key)->first();

        return $res;
    }

    /**
     * 生成唯一的API密钥
     */
    public function generateUniqueKey()
    {
        // 获取当前项目前缀
        $prefix = session('current_project_prefix');
        if (! $prefix) {
            throw new \Exception('项目前缀不能为空');
        }

        // uniqid 固定 13 位，time 固定 10 位
        // 密钥格式：ak_{prefix}_{13位uniqid}_{10位time}
        do {
            $uniqid = strtoupper(uniqid());
            // 确保 uniqid 为 13 位（uniqid 默认生成 13 位）
            if (strlen($uniqid) < 13) {
                $uniqid = str_pad($uniqid, 13, '0');
            } else {
                $uniqid = substr($uniqid, 0, 13);
            }

            $time = time();
            // 确保 time 为 10 位
            $timeStr = str_pad($time, 10, '0', STR_PAD_LEFT);

            $key = 'ak_'.$prefix.'_'.$uniqid.'_'.$timeStr;
        } while (ApiKey::where('key', $key)->exists());

        return $key;
    }

    /**
     * 检查API密钥是否可用
     */
    public function isKeyAvailable($key, $excludeId = null)
    {
        $query = ApiKey::where('key', $key)->where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return ! $query->exists();
    }

    /**
     * 切换API密钥状态
     */
    public function toggleStatus($id)
    {
        $apiKey = $this->getById($id);
        $apiKey->update(['is_active' => ! $apiKey->is_active]);

        return $apiKey->fresh();
    }

    /**
     * 获取API密钥使用统计
     */
    public function getUsageStats($id)
    {
        $apiKey = $this->getById($id);

        return [
            'total_requests' => $apiKey->usage_count,
            'last_used' => $apiKey->last_used_at,
            'is_expired' => $apiKey->isExpired(),
            'is_active' => $apiKey->is_active,
        ];
    }

    /**
     * 记录API请求日志
     */
    public function logRequest($apiKeyId, $method, $endpoint, $ip, $userAgent, $statusCode, $requestData = null, $responseData = null, $responseTime = 0)
    {
        return ApiLog::create([
            'key_id' => $apiKeyId,
            'method' => $method,
            'endpoint' => $endpoint,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'status_code' => $statusCode,
            'request_data' => $this->maskSensitiveData($requestData),
            'response_data' => $this->maskSensitiveData($responseData),
            'response_time_ms' => $responseTime,
        ]);
    }

    private function maskSensitiveData($data)
    {
        if (! is_array($data)) {
            return $data;
        }

        $maskedKeys = [
            'password', 'password_confirmation', 'token', 'api_token', 'api_key',
            'authorization', 'secret', 'secret_key', 'dbpwd', 'access_token', 'refresh_token',
            'x-api-key', 'x-client-token', 'x-mcp-token',
        ];

        $result = [];
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, $maskedKeys, true)) {
                $result[$key] = '***';

                continue;
            }

            $result[$key] = is_array($value) ? $this->maskSensitiveData($value) : $value;
        }

        return $result;
    }

    /**
     * 获取API密钥的请求日志
     */
    public function getLogs($apiKeyId, $perPage = 20, $page = 1)
    {
        $query = ApiLog::where('api_key_id', $apiKeyId)
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $data = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
        ];
    }

    /**
     * 清理过期的API密钥
     */
    public function cleanupExpiredKeys()
    {
        return ApiKey::where('expires_at', '<', now())
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * 获取活跃的API密钥数量
     */
    public function getActiveCount()
    {
        return ApiKey::where('is_active', true)->count();
    }

    /**
     * 获取最近使用过的API密钥
     */
    public function getRecentlyUsed($limit = 10)
    {
        return ApiKey::whereNotNull('last_used_at')
            ->orderBy('last_used_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
