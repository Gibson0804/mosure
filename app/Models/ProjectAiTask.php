<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class ProjectAiTask extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    protected static $baseTable = 'ai_tasks';

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type', 32)->default('multi_step');
            $table->string('model', 128)->nullable();
            $table->text('question');
            $table->string('status', 16)->default('pending');
            $table->integer('steps_total')->default(0);
            $table->integer('steps_completed')->default(0);
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(0);
            $table->json('result')->nullable();
            $table->text('summary_md')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        };
    }

    protected $fillable = [
        'user_id',
        'type',
        'model',
        'question',
        'status',
        'steps_total',
        'steps_completed',
        'attempts',
        'max_attempts',
        'result',
        'summary_md',
        'error',
    ];

    protected $casts = [
        'result' => 'array',
    ];
}
