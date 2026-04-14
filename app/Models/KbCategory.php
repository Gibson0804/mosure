<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class KbCategory extends Model
{
    protected $table = 'sys_kb_categories';

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('title', 255);
            $table->string('slug', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'parent_id']);
        };
    }

    protected $fillable = [
        'user_id',
        'parent_id',
        'title',
        'slug',
        'sort_order',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'parent_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function articles()
    {
        return $this->hasMany(KbArticle::class, 'category_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
