<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class SysContentVersion extends Model
{
    protected $table = 'sys_content_versions';

    protected $fillable = [
        'project_prefix',
        'mold_id',
        'content_id',
        'version',
        'event',
        'content_status',
        'data_json',
        'changed_fields',
        'is_published',
        'created_by',
    ];

    protected $casts = [
        'mold_id' => 'integer',
        'content_id' => 'integer',
        'version' => 'integer',
        'is_published' => 'integer',
        'data_json' => 'array',
        'changed_fields' => 'array',
        'created_by' => 'integer',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('project_prefix', 64)->nullable();
            $table->unsignedInteger('mold_id');
            $table->unsignedBigInteger('content_id');
            $table->unsignedInteger('version');
            $table->string('event', 32); // create/update/publish/unpublish/rollback/delete
            $table->string('content_status', 32)->nullable();
            $table->json('data_json')->nullable();
            $table->json('changed_fields')->nullable();
            $table->unsignedTinyInteger('is_published')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['mold_id', 'content_id', 'version']);
            $table->index(['mold_id', 'content_id', 'created_at']);
            $table->index(['content_id', 'version']);
        };
    }
}
