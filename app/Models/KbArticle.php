<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class KbArticle extends Model
{
    protected $table = 'sys_kb_articles';

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('title', 255);
            $table->string('slug', 255)->nullable();
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->longText('content_html')->nullable();
            $table->json('tags')->nullable();
            $table->string('status', 32)->default('private')->index();
            $table->integer('sort_order')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'status']);
        };
    }

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'slug',
        'summary',
        'content',
        'content_html',
        'tags',
        'status',
        'sort_order',
        'view_count',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'category_id' => 'integer',
        'tags' => 'array',
        'sort_order' => 'integer',
        'view_count' => 'integer',
    ];

    public const STATUS_PRIVATE = 'private';

    public const STATUS_PUBLIC = 'public';

    public function category()
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
