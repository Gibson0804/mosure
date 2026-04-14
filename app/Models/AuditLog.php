<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class AuditLog extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    // 基础表名（不带前缀）
    protected static $baseTable = 'audit_logs';

    // 允许批量写入
    protected $guarded = [];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
        'diff' => 'array',
        'meta' => 'array',
        'actor_id' => 'integer',
        'resource_id' => 'integer',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            // 行为主体
            $table->string('actor_type')->nullable(); // user/api/system
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('api_key')->nullable();

            // 动作与资源
            $table->string('action'); // create/update/delete
            $table->string('module')->nullable(); // content/subject/project
            $table->string('resource_type')->nullable(); // content/subject
            $table->string('resource_table')->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();

            // 请求信息
            $table->string('request_method')->nullable();
            $table->string('request_path')->nullable();
            $table->string('request_ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id')->nullable();

            // 结果
            $table->string('status')->default('success'); // success/fail
            $table->text('error_message')->nullable();

            // 数据
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->json('diff')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['resource_table', 'resource_id']);
        };
    }
}
