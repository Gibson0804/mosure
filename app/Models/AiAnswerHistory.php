<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class AiAnswerHistory extends Model
{
    protected $table = 'sys_ai_answer_histories';

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('model', 191)->index();
            $table->string('fp', 64)->unique();
            $table->text('question')->nullable();
            $table->longText('prompt');
            $table->longText('response_json');
            $table->timestamps();

            $table->index(['model', 'created_at']);
        };
    }

    protected $fillable = [
        'user_id',
        'model',
        'fp',
        'question',
        'prompt',
        'response_json',
    ];
}
