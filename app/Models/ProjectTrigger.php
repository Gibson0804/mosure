<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Schema\Blueprint;

class ProjectTrigger extends BaseModel
{
    use HasProjectPrefix;

    protected static $baseTable = 'triggers';

    protected $fillable = [
        'name',
        'enabled',
        'trigger_type',
        'events',
        'mold_id',
        'content_id',
        'watch_function_id',
        'action_function_id',
        'input_schema',
        'remark',
        'plugin_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'events' => 'array',
        'input_schema' => 'array',
        'mold_id' => 'integer',
        'content_id' => 'integer',
        'watch_function_id' => 'integer',
        'action_function_id' => 'integer',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->string('trigger_type', 32)->comment('content_model | content_single | function_exec');
            $table->json('events')->nullable()->comment('[before_create, after_create, ...]');
            $table->unsignedBigInteger('mold_id')->default(0)->comment('0 = 全部');
            $table->unsignedBigInteger('content_id')->nullable();
            $table->unsignedBigInteger('watch_function_id')->nullable();
            $table->unsignedBigInteger('action_function_id')->comment('触发后执行的函数');
            $table->json('input_schema')->nullable();
            $table->string('remark')->nullable();
            $table->string('plugin_id')->nullable();
            $table->timestamps();
            $table->index(['trigger_type']);
            $table->index(['mold_id']);
            $table->index(['watch_function_id']);
            $table->index(['enabled']);
        };
    }
}
