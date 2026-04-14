<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class FunctionEnv extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    protected static $baseTable = 'function_envs';

    protected $fillable = [
        'name', 'value', 'remark', 'plugin_id',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->text('value');
            $table->string('remark')->nullable();
            $table->string('plugin_id')->nullable();
            $table->timestamps();
            $table->unique(['name']);
            $table->index(['created_at']);
        };
    }
}
