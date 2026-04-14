<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class MediaTag extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('color', 20)->default('#1890ff');
            $table->integer('sort')->default(0);
            $table->timestamps();
        };
    }

    // 基础表名（不带前缀）
    protected static $baseTable = 'media_tags';

    protected $fillable = [
        'name',
        'color',
        'sort',
    ];

    protected $casts = [
        'sort' => 'integer',
    ];
}
