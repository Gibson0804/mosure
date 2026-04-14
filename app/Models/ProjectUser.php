<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class ProjectUser extends Model
{
    protected $table = 'sys_project_user';

    protected $fillable = [
        'project_id',
        'user_id',
    ];

    public $timestamps = true;

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->primary(['project_id', 'user_id']);
            $table->timestamps();
        };
    }
}
