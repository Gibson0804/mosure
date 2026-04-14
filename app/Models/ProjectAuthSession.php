<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class ProjectAuthSession extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    protected static $baseTable = 'project_auth_sessions';

    protected $fillable = [
        'project_auth_user_id',
        'token_hash',
        'expires_at',
        'revoked_at',
        'last_used_at',
        'ip_address',
        'user_agent_hash',
    ];

    protected $casts = [
        'project_auth_user_id' => 'integer',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_auth_user_id');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['project_auth_user_id']);
            $table->index(['expires_at']);
        };
    }
}
