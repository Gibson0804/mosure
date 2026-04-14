<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class ApiKey extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    public const SCOPE_CONTENT_READ = 'content.read';

    public const SCOPE_CONTENT_WRITE = 'content.write';

    public const SCOPE_PAGE_READ = 'page.read';

    public const SCOPE_PAGE_WRITE = 'page.write';

    public const SCOPE_MEDIA_READ = 'media.read';

    public const SCOPE_MEDIA_WRITE = 'media.write';

    public const SCOPE_FUNCTION_INVOKE = 'function.invoke';

    public static function availableScopes(): array
    {
        return [
            self::SCOPE_CONTENT_READ,
            self::SCOPE_CONTENT_WRITE,
            self::SCOPE_PAGE_READ,
            self::SCOPE_PAGE_WRITE,
            self::SCOPE_MEDIA_READ,
            self::SCOPE_MEDIA_WRITE,
            self::SCOPE_FUNCTION_INVOKE,
        ];
    }

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->text('description')->nullable();
            $table->string('plugin_id')->nullable()->comment('关联的插件ID');
            $table->integer('rate_limit')->default(1000); // 每分钟请求限制
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('allowed_ips')->nullable(); // 允许的IP地址，逗号分隔
            $table->json('scopes')->nullable()->comment('允许的接口权限范围');
            $table->integer('usage_count')->default(0); // 使用次数
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        };
    }

    // 基础表名（不带前缀）
    protected static $baseTable = 'api_keys';

    protected $fillable = [
        'name',
        'key',
        'description',
        'plugin_id',
        'rate_limit',
        'expires_at',
        'is_active',
        'allowed_ips',
        'scopes',
        'usage_count',
        'last_used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
        'rate_limit' => 'integer',
        'usage_count' => 'integer',
        'scopes' => 'array',
    ];

    /**
     * 检查API密钥是否已过期
     */
    public function isExpired()
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * 检查IP地址是否允许访问
     */
    public function isIpAllowed($ip)
    {
        if (! $this->allowed_ips) {
            return true; // 如果没有设置IP限制，则允许所有IP
        }

        $allowedIps = explode(',', $this->allowed_ips);

        return in_array($ip, array_map('trim', $allowedIps));
    }

    /**
     * 增加使用次数
     */
    public function incrementUsage()
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * 记录API请求日志
     */
    public function logRequest($method, $endpoint, $ip, $userAgent, $statusCode, $requestData = null, $responseData = null, $responseTime = 0)
    {
        return $this->hasMany(ApiLog::class)->create([
            'api_key_id' => $this->id,
            'method' => $method,
            'endpoint' => $endpoint,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'status_code' => $statusCode,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'response_time_ms' => $responseTime,
        ]);
    }

    /**
     * 获取API密钥的日志
     */
    public function logs()
    {
        return $this->hasMany(ApiLog::class, 'api_key_id');
    }
}
