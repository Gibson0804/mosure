<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class ProjectAiTaskStep extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    protected static $baseTable = 'ai_task_steps';

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->integer('idx');
            $table->string('name', 128)->nullable();
            $table->string('tool', 128)->nullable();
            $table->json('params')->nullable();
            $table->string('status', 16)->default('pending');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->integer('duration_ms')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        };
    }

    protected $fillable = [
        'task_id',
        'idx',
        'name',
        'tool',
        'params',
        'status',
        'attempts',
        'max_attempts',
        'result',
        'error',
        'duration_ms',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'params' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
