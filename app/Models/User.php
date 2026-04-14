<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'sys_users';

    public static function getTableSchema()
    {

        return function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->string('api_token', 80)->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        };
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'is_admin',
        'remember_token',
        'is_active',
        'api_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * 检查用户是否为系统管理员
     *
     * @return bool
     */
    public function isAdmin()
    {
        return (bool) $this->is_admin;
    }

    /**
     * 检查用户是否有权限执行某个操作
     * 注意：系统管理员拥有所有权限
     *
     * @param  string  $action  操作名称
     * @param  int|null  $projectId  项目 ID（如果适用）
     * @return bool
     */
    public function can($action, $projectId = null)
    {
        // 系统管理员拥有所有权限
        if ($this->isAdmin()) {
            return true;
        }

        // 项目级别的权限检查将在项目管理层实现
        // 这里只是一个占位符，具体实现将在项目管理层中定义

        return false;
    }
}
