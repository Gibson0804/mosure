<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class SysTaskStep extends Model
{
    protected $table = 'sys_task_steps';

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedInteger('seq')->default(0)->index();
            $table->string('title', 255)->nullable();
            $table->string('status', 32)->default('pending')->index();

            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->json('error_detail')->nullable();

            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(0);

            $table->string('request_id', 64)->nullable()->index();
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->unsignedInteger('cost_ms')->nullable();
            $table->unsignedInteger('token_prompt')->nullable();
            $table->unsignedInteger('token_completion')->nullable();
            $table->unsignedInteger('token_total')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'seq']);
            $table->index(['task_id', 'status']);
        };
    }

    protected $fillable = [
        'task_id',
        'seq',
        'title',
        'status',
        'payload',
        'result',
        'error_message',
        'error_code',
        'error_detail',
        'attempts',
        'max_attempts',
        'request_id',
        'provider',
        'model',
        'cost_ms',
        'token_prompt',
        'token_completion',
        'token_total',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'error_detail' => 'array',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'seq' => 'integer',
        'cost_ms' => 'integer',
        'token_prompt' => 'integer',
        'token_completion' => 'integer',
        'token_total' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
