<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class Media extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->string('extension');
            $table->string('path');
            $table->string('disk')->default('public');
            $table->string('url');
            $table->integer('size');
            $table->integer('user_id');
            $table->string('type');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('folder_id')->nullable()->index();
            $table->json('tags')->nullable();
            $table->string('created_by')->nullable()->index();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        };
    }

    // 基础表名（不带前缀）
    protected static $baseTable = 'media';

    protected $fillable = [
        'filename',
        'original_filename',
        'mime_type',
        'extension',
        'path',
        'disk',
        'url',
        'size',
        'user_id',
        'type',
        'description',
        'metadata',
        'folder_id',
        'tags',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
        'user_id' => 'integer',
        'folder_id' => 'integer',
        'tags' => 'array',
    ];

    /**
     * 获取上传该媒体文件的用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 根据文件类型返回图标类型
     */
    public function getIconAttribute()
    {
        $iconMap = [
            'image' => 'file-image',
            'video' => 'file-video',
            'audio' => 'file-audio',
            'document' => 'file-text',
            'pdf' => 'file-pdf',
            'excel' => 'file-excel',
            'word' => 'file-word',
            'ppt' => 'file-ppt',
            'zip' => 'file-zip',
            'code' => 'file-code',
            'default' => 'file',
        ];

        return $iconMap[$this->type] ?? $iconMap['default'];
    }

    /**
     * 获取可读的文件大小
     */
    public function getReadableSizeAttribute()
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->size;
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2).' '.$units[$i];
    }

    /**
     * 判断是否为图片
     */
    public function getIsImageAttribute()
    {
        return $this->type === 'image' || strpos($this->mime_type, 'image/') === 0;
    }

    /**
     * 判断是否为视频
     */
    public function getIsVideoAttribute()
    {
        return $this->type === 'video' || strpos($this->mime_type, 'video/') === 0;
    }

    /**
     * 归属虚拟文件夹
     */
    public function folder()
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }
}
