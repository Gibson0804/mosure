<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class Mold extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('table_name')->nullable(); // 添加 table_name 字段
            $table->string('mold_type')->default('custom'); // 添加 mold_type 字段
            $table->json('fields');
            $table->json('settings')->nullable();
            $table->json('subject_content')->nullable(); // 添加 subject_content 字段
            $table->json('list_show_fields')->nullable(); // 添加 list_show_fields 字段
            $table->json('filter_show_fields')->nullable(); // 内容列表筛选项展示字段
            $table->string('plugin_id')->nullable();
            $table->timestamps();
        };
    }

    // 基础表名（不带前缀）
    protected static $baseTable = 'molds';

    public $fillable = [
        'name',
        'table_name',
        'mold_type',
        'fields',
        'subject_content',
        'list_show_fields',
        'filter_show_fields',
        'plugin_id',
    ];
}
