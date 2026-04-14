<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class MediaFolder extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    // 基础表名（不带前缀）
    protected static $baseTable = 'media_folders';

    protected $fillable = [
        'name',
        'parent_id',
        'mpath',
        'depth',
        'sort',
        'cover_media_id',
        'is_system',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'depth' => 'integer',
        'sort' => 'integer',
        'cover_media_id' => 'integer',
        'is_system' => 'boolean',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('mpath', 255)->nullable()->index();
            $table->unsignedInteger('depth')->default(0);
            $table->integer('sort')->default(0);
            $table->unsignedBigInteger('cover_media_id')->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index(['parent_id', 'name']);
        };
    }

    /**
     * 获取完整路径（包含所有父文件夹）
     */
    public function getFullPathAttribute()
    {
        if (! $this->mpath) {
            return $this->name;
        }

        // mpath 格式为 /id1/id2/id3/
        $ids = array_filter(explode('/', trim($this->mpath, '/')));

        if (empty($ids)) {
            return $this->name;
        }

        // 获取所有父文件夹
        $folders = self::whereIn('id', $ids)->orderByRaw('FIELD(id, '.implode(',', $ids).')')->get();

        // 构建路径
        $path = $folders->pluck('name')->implode('/');

        return $path;
    }

    /**
     * 父文件夹关联
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * 子文件夹关联
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
