<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'sys_settings';

    protected $fillable = [
        'config_group',
        'config_key',
        'config_value',
        'is_encrypted',
    ];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('config_group');
            $table->string('config_key');
            $table->text('config_value');
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        };
    }
}
