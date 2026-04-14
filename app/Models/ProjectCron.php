<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Schema\Blueprint;

class ProjectCron extends BaseModel
{
    use HasProjectPrefix;

    protected static $baseTable = 'function_crons';

    protected $fillable = [
        'name',
        'enabled',
        'function_id',
        'schedule_type',
        'run_at',
        'cron_expr',
        'timezone',
        'payload',
        'timeout_ms',
        'max_mem_mb',
        'next_run_at',
        'last_run_at',
        'remark',
        'plugin_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'function_id' => 'integer',
        'payload' => 'array',
        'timeout_ms' => 'integer',
        'max_mem_mb' => 'integer',
        'run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('function_id');
            $table->string('schedule_type', 16)->default('once')->comment('once | cron');
            $table->dateTime('run_at')->nullable();
            $table->string('cron_expr')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->json('payload')->nullable();
            $table->unsignedInteger('timeout_ms')->nullable();
            $table->unsignedInteger('max_mem_mb')->nullable();
            $table->dateTime('next_run_at')->nullable();
            $table->dateTime('last_run_at')->nullable();
            $table->string('remark')->nullable();
            $table->string('plugin_id')->nullable();
            $table->timestamps();
            $table->index(['enabled', 'next_run_at']);
            $table->index(['function_id']);
        };
    }
}
