<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Schema\Blueprint;

class ProjectFunctionExecution extends BaseModel
{
    use HasProjectPrefix;

    protected static $baseTable = 'function_executions';

    protected $fillable = [
        'function_id',
        'trigger',
        'status',
        'duration_ms',
        'error',
        'payload',
        'result',
        'request_id',
        'api_key_id',
    ];

    protected $casts = [
        'function_id' => 'integer',
        'duration_ms' => 'integer',
        'payload' => 'array',
        'result' => 'array',
        'api_key_id' => 'integer',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('function_id');
            $table->string('trigger', 16)->comment('endpoint');
            $table->string('status', 16)->default('success');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->string('request_id')->nullable();
            $table->unsignedBigInteger('api_key_id')->nullable();
            $table->timestamps();
            $table->index(['function_id']);
            $table->index(['created_at']);
        };
    }
}
