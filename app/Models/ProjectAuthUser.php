<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class ProjectAuthUser extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    protected static $baseTable = 'project_auth_users';

    protected $fillable = [
        'email',
        'phone',
        'username',
        'name',
        'password',
        'avatar',
        'status',
        'meta',
        'last_login_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'meta' => 'array',
        'last_login_at' => 'datetime',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('username')->nullable();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->string('avatar')->nullable();
            $table->string('status', 24)->default('active');
            $table->json('meta')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('email');
            $table->unique('phone');
            $table->unique('username');
            $table->index(['status']);
        };
    }
}
