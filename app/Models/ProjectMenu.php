<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Schema\Blueprint;

class ProjectMenu extends BaseModel
{
    use HasProjectPrefix;

    protected static $baseTable = 'menus';

    protected $fillable = [
        'parent_id',
        'title',
        'key',
        'icon',
        'target_type',
        'target_payload',
        'order',
        'visible',
        'permission_key',
        'area',
        'plugin_id',
        'extra',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'order' => 'integer',
        'visible' => 'boolean',
        'target_payload' => 'array',
        'extra' => 'array',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('title');
            $table->string('key')->unique();
            $table->string('icon')->nullable();
            $table->string('target_type', 32)->default('group')->comment('group|mold_list|mold_single|function|route|url|shortcut');
            $table->json('target_payload')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('visible')->default(true);
            $table->string('permission_key')->nullable();
            $table->string('area', 16)->default('admin');
            $table->string('plugin_id')->nullable()->index();
            $table->json('extra')->nullable();
            $table->timestamps();
        };
    }
}
