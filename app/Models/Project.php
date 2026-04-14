<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class Project extends Model
{
    use HasFactory;

    protected $table = 'sys_projects';

    protected $fillable = [
        'name',
        'prefix',
        'template',
        'description',
        'user_id',
        'created_at',
        'updated_at',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('prefix')->unique();
            $table->string('template')->default('blank');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->softDeletes();
        };
    }
}
