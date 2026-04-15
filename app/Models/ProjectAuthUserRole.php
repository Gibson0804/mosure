<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class ProjectAuthUserRole extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    protected static $baseTable = 'project_auth_user_roles';

    protected $fillable = [
        'project_auth_user_id',
        'project_auth_role_id',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_auth_user_id');
            $table->unsignedBigInteger('project_auth_role_id');
            $table->timestamps();

            // Do not force a global index name; SQLite requires index names to be unique across the whole DB.
            $table->unique(['project_auth_user_id', 'project_auth_role_id']);
            $table->index(['project_auth_user_id']);
            $table->index(['project_auth_role_id']);
        };
    }
}
