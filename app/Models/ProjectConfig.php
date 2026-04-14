<?php

namespace App\Models;

use App\Traits\HasProjectPrefix;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;

class ProjectConfig extends BaseModel
{
    use HasFactory, HasProjectPrefix;

    protected static $baseTable = 'project_configs';

    protected $guarded = [];

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->id();
            $table->string('config_key')->index();
            $table->string('config_group')->nullable();
            $table->string('config_value')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['config_group', 'config_key']);
        };
    }
}
