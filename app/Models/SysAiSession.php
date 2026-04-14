<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class SysAiSession extends BaseModel
{
    use HasFactory;

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->string('title', 255)->default('新对话');
            $table->string('session_type', 20)->default('private')->comment('会话类型: private-私聊, group-群聊');
            $table->boolean('is_default')->default(false);
            $table->string('member_ids')->nullable()->comment('成员ID列表');
            // todo::下面俩字是不是没用
            $table->string('agent_type', 20)->nullable()->comment('Agent类型: secretary-秘书, project-项目, custom-自定义');
            $table->string('agent_identifier', 100)->nullable()->comment('Agent标识');
            $table->dateTime('last_message_at')->nullable()->index();
            $table->unsignedInteger('message_count')->default(0);
            $table->text('context_summary')->nullable()->comment('上下文摘要');
            $table->unsignedInteger('context_token_count')->default(0);
            $table->timestamps();
        };
    }

    protected static $baseTable = 'sys_ai_sessions';

    protected $fillable = [
        'user_id',
        'project_id',
        'last_read_message_id',
        'title',
        'is_default',
        'member_ids',
        'session_type',
        'agent_type',
        'agent_identifier',
        'last_message_at',
        'message_count',
        'context_summary',
        'context_token_count',
    ];
}
