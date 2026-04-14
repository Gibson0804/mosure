<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    /**
     * 获取模型的表名（静态方法）
     *
     * @return string
     */
    public static function tableName()
    {
        // 创建模型实例来获取表名
        $instance = new static;

        return $instance->getTable();
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
