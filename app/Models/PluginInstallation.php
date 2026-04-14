<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Schema\Blueprint;

class PluginInstallation extends BaseModel
{
    use HasProjectPrefix;

    protected static $baseTable = 'plugin_installations';

    protected $fillable = [
        'plugin_id',
        'plugin_version',
        'status',
        'config',
        'installed_at',
    ];

    protected $casts = [
        'config' => 'array',
        'installed_at' => 'datetime',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->string('plugin_id', 100)->comment('插件ID');
            $table->string('plugin_version', 20)->comment('安装的版本');
            $table->enum('status', ['installed', 'enabled', 'disabled'])->default('enabled')->comment('状态');
            $table->json('config')->nullable()->comment('插件配置');
            $table->timestamp('installed_at')->nullable()->comment('安装时间');
            $table->timestamps();

            // 索引
            $table->unique('plugin_id');
            $table->index('status');
        };
    }
}
