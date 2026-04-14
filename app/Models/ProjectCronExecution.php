<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Schema\Blueprint;

class ProjectCronExecution extends BaseModel
{
    use HasProjectPrefix;

    protected static $baseTable = 'function_cron_executions';

    protected $fillable = [
        'cron_id',
        'function_id',
        'status',
        'duration_ms',
        'error',
        'payload',
        'result',
    ];

    protected $casts = [
        'cron_id' => 'integer',
        'function_id' => 'integer',
        'duration_ms' => 'integer',
        'payload' => 'array',
        'result' => 'array',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cron_id');
            $table->unsignedBigInteger('function_id');
            $table->string('status', 16)->default('success');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->index(['cron_id']);
            $table->index(['created_at']);
        };
    }
}
