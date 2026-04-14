<?php

namespace App\Traits;

use App\Constants\ProjectConstants;

trait HasProjectPrefix
{
    public static function getfullTableNameByPrefix($prefix)
    {
        return $prefix.ProjectConstants::PROJECT_FRAMEWORK_PREFIX.(self::$baseTable ?? parent::getTable());
    }

    /**
     * 获取带项目前缀的表名
     *
     * @return string
     */
    public function getTable()
    {
        // 如果已经设置了完整表名，则直接返回
        if (isset($this->table)) {
            return $this->table;
        }

        // 获取当前项目前缀
        $prefix = session('current_project_prefix');
        // 如果没有当前项目，使用默认表名
        if (! $prefix) {
            return parent::getTable();
        }

        // 获取模型的基础表名（不带前缀）
        $baseTable = self::$baseTable ?? parent::getTable();

        // 如果表名已经包含前缀，则直接返回
        if (strpos($baseTable, $prefix) === 0) {
            return $baseTable;
        }

        // 添加新前缀
        return $prefix.ProjectConstants::PROJECT_FRAMEWORK_PREFIX.$baseTable;
    }
}
