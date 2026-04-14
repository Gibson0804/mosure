<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Schema\Blueprint;

class ProjectFunction extends BaseModel
{
    use HasProjectPrefix;

    protected static $baseTable = 'functions';

    protected $fillable = [
        'name',
        'slug',
        'enabled',
        'code',
        'timeout_ms',
        'max_mem_mb',
        'rate_limit',
        'input_schema',
        'output_schema',
        'http_method',
        'type',
        'remark',
        'plugin_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'input_schema' => 'array',
        'output_schema' => 'array',
        'timeout_ms' => 'integer',
        'max_mem_mb' => 'integer',
        'rate_limit' => 'integer',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('enabled')->default(true);
            $table->text('code')->nullable();
            $table->unsignedInteger('timeout_ms')->default(60000);
            $table->unsignedInteger('max_mem_mb')->default(128);
            $table->unsignedInteger('rate_limit')->nullable();
            $table->json('input_schema')->nullable();
            $table->json('output_schema')->nullable();
            $table->string('http_method', 8)->default('POST');
            $table->string('type', 16)->default('endpoint');
            $table->string('remark')->nullable();
            $table->string('plugin_id')->nullable()->comment('插件名称，非插件留空');
            $table->timestamps();
            $table->index(['slug', 'enabled']);
        };
    }
}
