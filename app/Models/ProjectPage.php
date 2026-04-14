<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Schema\Blueprint;

class ProjectPage extends BaseModel
{
    use HasProjectPrefix;

    protected static $baseTable = 'pages';

    protected $fillable = [
        'slug',
        'title',
        'description',
        'page_type',
        'status',
        'html_content',
        'config',
        'created_by',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 100);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('page_type', ['single', 'spa'])->default('single');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->longText('html_content')->nullable();
            $table->json('config')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique('slug');
        };
    }
}
