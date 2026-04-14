<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class SysAiMessage extends Model
{
    protected $table = 'sys_ai_messages';

    protected $fillable = [
        'session_id',
        'sender_id',
        'sender_type',
        'sender_name',
        'content',
        'mentions',
        'is_system',
        'is_meaningless',
        'status',
        'processed_at',
    ];

    protected $casts = [
        'mentions' => 'array',
        'is_system' => 'integer',
        'status' => 'integer',
        'processed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 0;

    public const STATUS_PROCESSING = 1;

    public const STATUS_COMPLETED = 2;

    public const STATUS_FAILED = 9;

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->integer('session_id')->comment('会话ID');
            $table->integer('sender_id')->comment('发送者ID');
            $table->string('sender_type', 20)->comment('发送者类型: user-用户, agent-助手成员');
            $table->string('sender_name', 100)->nullable()->comment('发送者名称');
            $table->mediumText('content')->comment('消息内容');
            $table->string('mentions')->nullable()->comment('提及的Agent');
            $table->integer('is_system')->comment('是否系统消息');
            // 是否是无意义的消息
            $table->integer('is_meaningless')->default(0)->comment('是否无意义的消息');
            $table->integer('status')->comment('状态');
            $table->datetime('processed_at')->nullable()->comment('处理时间');
            $table->timestamps();
        };
    }

    public function session()
    {
        return $this->belongsTo(SysAiSession::class, 'session_id');
    }

    public function sender()
    {
        if ($this->sender_type === 'agent') {
            return $this->belongsTo(SysAiAgent::class, 'sender_id');
        }

        return null;
    }

    public function isUserMessage(): bool
    {
        return $this->sender_type === 'user';
    }

    public function isAgentMessage(): bool
    {
        return $this->sender_type === 'agent';
    }

    public function isSystemMessage(): bool
    {
        return $this->is_system === 1;
    }

    public function hasMentions(): bool
    {
        return ! empty($this->mentions);
    }

    public function getMentionedAgents(): array
    {
        if (! $this->mentions) {
            return [];
        }

        return array_filter($this->mentions, function ($mention) {
            return ($mention['type'] ?? '') === 'agent';
        });
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'processed_at' => now(),
        ]);
    }
}
