<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class ProjectUserRole extends Model
{
    protected $table = 'sys_project_user_role';

    protected $fillable = [
        'project_id',
        'user_id',
        'role_code',
    ];

    public $timestamps = true;

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role_code', 50);
            $table->timestamps();

            $table->index(['project_id', 'user_id']);
        };
    }
}
