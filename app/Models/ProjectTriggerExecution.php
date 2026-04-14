<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Schema\Blueprint;

class ProjectTriggerExecution extends BaseModel
{
    use HasProjectPrefix;

    protected static $baseTable = 'trigger_executions';

    protected $fillable = [
        'trigger_id',
        'event',
        'status',
        'duration_ms',
        'error',
        'payload',
        'result',
    ];

    protected $casts = [
        'trigger_id' => 'integer',
        'duration_ms' => 'integer',
        'payload' => 'array',
        'result' => 'array',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('trigger_id');
            $table->string('event', 64);
            $table->string('status', 16)->default('success');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->index(['trigger_id']);
            $table->index(['event']);
            $table->index(['created_at']);
        };
    }
}
