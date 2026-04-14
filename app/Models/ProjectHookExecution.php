<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Schema\Blueprint;

class ProjectHookExecution extends BaseModel
{
    use HasProjectPrefix;

    protected static $baseTable = 'hook_executions';

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('hook_id');
            $table->string('trigger', 16)->comment('hook');
            $table->string('event')->nullable();
            $table->string('status', 16)->default('success');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->string('request_id')->nullable();
            $table->unsignedBigInteger('api_key_id')->nullable();
            $table->timestamps();
            $table->index(['hook_id']);
            $table->index(['event']);
            $table->index(['created_at']);
        };
    }
}
