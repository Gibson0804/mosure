<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class SysAiAgent extends Model
{
    use HasFactory;

    protected $table = 'sys_ai_agents';

    protected $fillable = [
        'type',
        'identifier',
        'user_id',
        'project_id',
        'name',
        'description',
        'avatar',
        'personality',
        'dialogue_style',
        'core_prompt',
        'tools',
        'capabilities',
        'enabled',
    ];

    protected $casts = [
        'tools' => 'array',
        'capabilities' => 'array',
        'personality' => 'array',
        'dialogue_style' => 'array',
        'enabled' => 'boolean',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->comment('Agent类型: secretary-秘书, project-项目, custom-自定义');
            $table->string('identifier', 100)->comment('Agent标识');
            $table->unsignedBigInteger('user_id')->nullable()->comment('所属用户ID(custom类型时使用)，目前仅用于记录创建人，不做数据隔离');
            $table->unsignedBigInteger('project_id')->nullable()->comment('项目ID(project类型时使用)');
            $table->string('name', 100)->comment('显示名称');
            $table->string('description', 500)->nullable()->comment('描述');
            $table->string('avatar', 500)->nullable()->comment('头像URL');
            $table->json('personality')->nullable()->comment('性格设定');
            $table->json('dialogue_style')->nullable()->comment('对话风格');
            $table->text('core_prompt')->comment('核心提示词');
            $table->json('tools')->nullable()->comment('工具配置');
            $table->json('capabilities')->nullable()->comment('能力配置');
            $table->boolean('enabled')->default(true)->comment('是否启用');
            $table->timestamps();

            $table->unique(['type', 'identifier']);
            $table->index('user_id');
            $table->index('project_id');
        };
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
