<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Schema\Blueprint;

/**
 * 插件安装记录模型
 * 记录插件安装过程中的每个操作详情
 */
class PluginInstallRecord extends BaseModel
{
    use HasProjectPrefix;

    protected static $baseTable = 'plugin_install_records';

    protected $fillable = [
        'plugin_id',
        'resource_type',
        'resource_name',
        'resource_table',
        'resource_id',
        'operation',
        'status',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * 资源类型常量
     */
    const TYPE_MODEL = 'model';

    const TYPE_FUNCTION = 'function';

    const TYPE_MENU = 'menu';

    const TYPE_TRIGGER = 'trigger';

    const TYPE_SCHEDULE = 'schedule';

    /**
     * 操作类型常量
     */
    const OP_CREATE = 'create';

    const OP_UPDATE = 'update';

    const OP_DELETE = 'delete';

    /**
     * 状态常量
     */
    const STATUS_PENDING = 'pending';

    const STATUS_SUCCESS = 'success';

    const STATUS_FAILED = 'failed';

    /**
     * 获取表结构定义
     */
    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->string('plugin_id', 100)->comment('插件ID');
            $table->string('resource_type', 50)->comment('资源类型：model/function/menu/trigger/schedule');
            $table->string('resource_name')->comment('资源名称');
            $table->string('resource_table')->nullable()->comment('关联的数据表名');
            $table->unsignedBigInteger('resource_id')->nullable()->comment('关联的资源ID');
            $table->enum('operation', ['create', 'update', 'delete'])->default('create')->comment('操作类型');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending')->comment('状态');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->json('metadata')->nullable()->comment('额外元数据');
            $table->timestamps();

        };
    }
}
