<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class ClientAiConversation extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id')->nullable()->index();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('model');
            $table->string('tool')->nullable();
            $table->json('tool_params')->nullable();
            $table->json('tool_result')->nullable();
            $table->text('question');
            $table->text('answer');
            $table->timestamps();
        };
    }

    protected static $baseTable = 'client_ai_conversations';

    protected $fillable = [
        'session_id',
        'task_id',
        'user_id',
        'model',
        'tool',
        'tool_params',
        'tool_result',
        'question',
        'answer',
    ];

    protected $casts = [
        'tool_params' => 'array',
        'tool_result' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(SysAiSession::class, 'session_id');
    }
}
