<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class ApiLog extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('key_id');
            $table->string('method');
            $table->string('endpoint');
            $table->string('ip_address');
            $table->text('user_agent')->nullable();
            $table->integer('status_code');
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->integer('response_time_ms');
            $table->timestamps();

        };
    }

    // 基础表名（不带前缀）
    protected static $baseTable = 'api_logs';

    protected $fillable = [
        'key_id',
        'method',
        'endpoint',
        'ip_address',
        'user_agent',
        'status_code',
        'request_data',
        'response_data',
        'response_time_ms',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
    ];

    /**
     * 关联到API密钥
     */
    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class, 'key_id');
    }
}
