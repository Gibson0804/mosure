<?php

namespace App\Repository;

use App\Constants\ProjectConstants;
use App\Exceptions\PageNoticeException;
use App\Models\Mold;

class MoldRepository
{
    const CONTENT_MOLD_TYPE = 'list';

    const SUBJECT_MOLD_TYPE = 'single';

    const CORE_TABLE_PRFIX = 'hc_';

    const CONTENT_TABLE_PRFIX = 'cc_';

    public static $defaultListNotShowField = [
        'textarea',
        'richText',
    ];

    public static $baseField = ['id', 'created_at', 'updated_at', 'deleted_at', 'updated_by', 'created_by', 'content_status'];

    public function getAllSubectBase()
    {

        $res = Mold::select('id', 'name', 'table_name')->where('mold_type', self::SUBJECT_MOLD_TYPE)->get()->toArray();

        return $res;
    }

    /**
     * 获取所有模型
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllMold()
    {
        // 创建新的 Mold 实例，确保使用正确的表名
        $mold = new Mold;

        return $mold->newQuery()->get();
    }

    public function getAllContentBase()
    {

        $res = Mold::select('id', 'name', 'table_name')->where('mold_type', self::CONTENT_MOLD_TYPE)->get()->toArray();

        return $res;
    }

    public function getTableNameByID($id)
    {
        $res = $this->getMoldInfo($id);

        return $res['table_name'];
    }

    public function getTableByName($name)
    {
        $tableInfo = Mold::where('table_name', $name)->first();

        return $tableInfo;
    }

    public function getMoldInfo($id)
    {
        $tableInfo = Mold::find($id);

        if (! $tableInfo) {
            // throw new PageNoticeException('模型不存在');
        }

        $tableInfo['fields_arr'] = json_decode($tableInfo['fields'], true);
        $tableInfo['subject_content_arr'] = json_decode($tableInfo['subject_content'], true);
        $tableInfo['list_show_fields_arr'] = json_decode($tableInfo['list_show_fields'], true);
        $tableInfo['filter_show_fields_arr'] = json_decode($tableInfo['filter_show_fields'] ?? '[]', true);

        return $tableInfo;
    }

    public function getMoldInfoByTableName($tableName)
    {
        $tableInfo = Mold::where('table_name', $tableName)->first();

        if (! $tableInfo) {
            return null;
        }

        $tableInfo['fields_arr'] = json_decode($tableInfo['fields'], true);
        $tableInfo['subject_content_arr'] = json_decode($tableInfo['subject_content'], true);
        $tableInfo['list_show_fields_arr'] = json_decode($tableInfo['list_show_fields'], true);
        $tableInfo['filter_show_fields_arr'] = json_decode($tableInfo['filter_show_fields'] ?? '[]', true);

        return $tableInfo;
    }

    public function saveMold($data)
    {

        // created_at等时间字段框架会自动填充，不要重复添加
        unset($data['created_at']);
        unset($data['updated_at']);
        unset($data['deleted_at']);

        $res = Mold::create($data);

        return $res->id;
    }

    public function updateById($data, $id)
    {

        return Mold::where('id', $id)->update($data);
    }

    public function deleteById($id)
    {
        return Mold::where('id', $id)->delete();
    }

    public function getDefaultName()
    {

        $defaultTableName = '文章列表';

        $count = Mold::where('name', $defaultTableName)->count();

        while ($count > 0) {
            $defaultTableName .= '_'.$count;
            $count = Mold::where('name', $defaultTableName)->count();
        }

        return $defaultTableName;

    }

    public function getDefaultTableName()
    {
        $defaultId = 'article_list';

        $count = Mold::where('table_name', $this->getTableName($defaultId))->count();

        while ($count > 0) {
            $defaultId .= '_'.$count;
            $count = Mold::where('table_name', $this->getTableName($defaultId))->count();
        }

        return $defaultId;
    }

    public function checkTableName($tableName, $name)
    {
        $tableNameCount = Mold::where('table_name', $this->getTableName($tableName))->count();
        $nameCount = Mold::where('name', $name)->count();

        if ($tableNameCount > 0 || $nameCount > 0) {
            return false;
        }

        return true;
    }

    // 组合tableName
    public function getTableName($tableName)
    {
        $projectPrefix = session('current_project_prefix', 'default');

        $tableName = $projectPrefix.ProjectConstants::MODEL_CONTENT_PREFIX.$tableName;

        return $tableName;
    }
}
