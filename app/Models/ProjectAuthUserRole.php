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

            // Use a short deterministic unique index name:
            // - avoids MySQL 64-char identifier limit on long prefixed tables
            // - remains unique across project tables for SQLite
            $tableNameHash = substr(md5((string) $table->getTable()), 0, 8);
            $uniqueName = 'pau_role_uq_'.$tableNameHash;
            $table->unique(['project_auth_user_id', 'project_auth_role_id'], $uniqueName);
            $table->index(['project_auth_user_id']);
            $table->index(['project_auth_role_id']);
        };
    }
}
