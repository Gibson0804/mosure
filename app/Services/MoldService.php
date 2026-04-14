<?php

namespace App\Services;

use App\Ai\Attributes\AiTool;
use App\Exceptions\PageNoticeException;
use App\Jobs\ProcessSysTaskJob;
use App\Models\SysTask;
use App\Repository\ContentRepository;
use App\Repository\MoldRepository;
use App\Repository\SysTaskRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MoldService extends BaseService
{
    private $moldRepository;

    private $taskRepository;

    public function __construct(MoldRepository $moldRepository, SysTaskRepository $taskRepository)
    {
        $this->moldRepository = $moldRepository;
        $this->taskRepository = $taskRepository;
    }

    /**
     * 规范化模型字段定义
     *
     * @param  array  $fields  字段定义数组
     * @return array 规范化后的字段定义数组
     */
    /**
     * 处理mold_type参数，支持数字和字符串格式
     *
     * @param  mixed  $moldType  模型类型
     * @return int|string 标准化后的模型类型值
     */
    public function normalizeMoldType($moldType)
    {

        $moldType = strtolower((string) $moldType);

        if ($moldType === 'subject' || $moldType === 'content_single' || $moldType === 'single') {
            return MoldRepository::SUBJECT_MOLD_TYPE; // 单页模型
        }

        // 默认为内容模型
        return MoldRepository::CONTENT_MOLD_TYPE; // 内容模型
    }

    public function normalizeFields(array $fields): array
    {
        $processedFields = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            // 确保 field 和 id 存在且唯一
            if (empty($field['field'])) {
                $field['field'] = 'field_'.uniqid();
            }
            if (empty($field['id'])) {
                $field['id'] = $field['field'];
            }

            // 确保 label 存在
            if (empty($field['label'])) {
                $field['label'] = $field['field'];
            }

            // 字段名规范化
            $field['field'] = preg_replace('/[^a-zA-Z0-9_]/', '_', $field['field']);
            if (preg_match('/^[0-9]/', $field['field'])) {
                $field['field'] = 'f_'.$field['field'];
            }

            // 确保 type 存在并有效
            $validFrontendTypes = [
                'input', 'textarea', 'radio', 'switch', 'checkbox', 'select',
                'numInput', 'colorPicker', 'dateTimePicker', 'datePicker', 'timePicker',
                'fileUpload', 'picUpload', 'picGallery', 'richText', 'dividingLine', 'slider',
                'rate', 'cascader', 'dateRangePicker', 'tags',
            ];

            $dbTypeMap = [
                'string' => 'input',
                'text' => 'textarea',
                'textarea' => 'textarea',
                'richText' => 'richText',
                'number' => 'numInput',
                'int' => 'numInput',
                'integer' => 'numInput',
                'float' => 'numInput',
                'decimal' => 'numInput',
                'double' => 'numInput',
                'boolean' => 'switch',
                'date' => 'datePicker',
                'datetime' => 'dateTimePicker',
                'timestamp' => 'dateTimePicker',
                'json' => 'textarea',
                'array' => 'tags',
                'select' => 'select',
                'check_box' => 'checkbox',
                'radio' => 'radio',
            ];

            if (empty($field['type'])) {
                $field['type'] = 'input';
            } elseif (isset($dbTypeMap[$field['type']])) {
                $field['type'] = $dbTypeMap[$field['type']];
            }

            if (! in_array($field['type'], $validFrontendTypes)) {
                $field['type'] = 'input';
            }

            $processedFields[] = $field;
        }

        return $processedFields;
    }

    public function suggestMold($question, $model, $requestedBy = null)
    {
        $payload = [
            'suggest' => (string) $question,
        ];

        $task = $this->taskRepository->createTask([
            'type' => SysTask::TYPE_MOLD_SUGGEST,
            'status' => SysTask::STATUS_PENDING,
            'title' => '模型生成: '.mb_substr((string) $question, 0, 50),
            'payload' => $payload,
            'requested_by' => $requestedBy,
            'related_type' => 'mold',
            'related_id' => 0,
        ]);

        ProcessSysTaskJob::dispatch($task->id);

        return [
            'task_id' => $task->id,
            'status' => $task->status,
        ];
    }

    public function getMoldInfo($id)
    {
        $res = $this->moldRepository->getMoldInfo($id);

        return $res;
    }

    public function getMoldInfoByTableName($tableName)
    {
        // 将短标识转换为带项目前缀的真实表名
        $fullTableName = $this->moldRepository->getTableName($tableName);

        return $this->moldRepository->getMoldInfoByTableName($fullTableName);
    }

    public function deleteCheck($id)
    {

        // 如果是single类型mold，返回0
        $mold = $this->moldRepository->getMoldInfo($id);
        if ($mold['mold_type'] === 'single') {
            return [
                'moldContentCount' => 0,
            ];
        }

        $moldContentCount = ContentRepository::buildContent($id)->count();

        return [
            'moldContentCount' => $moldContentCount,
        ];
    }

    public function delete($id)
    {
        $mold = $this->moldRepository->getMoldInfo($id);

        if (! $mold) {
            return false;
        }

        $tableName = $mold['table_name'] ?? null;

        if ($tableName) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable($tableName)) {
                    \Illuminate\Support\Facades\Schema::drop($tableName);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to drop table {$tableName}: ".$e->getMessage());
            }
        }

        $res = $this->moldRepository->deleteById($id);

        return $res;
    }

    public function getDefaultName()
    {
        return $this->moldRepository->getDefaultName();
    }

    public function getDefaultTableName()
    {
        return $this->moldRepository->getDefaultTableName();
    }

    #[AiTool(
        name: 'list_models',
        description: '获取系统中所有内容模型的基础信息列表，结果包含 id/name/table_name/mold_type。',
        params: []
    )]
    public function getAllMold()
    {
        return $this->moldRepository->getAllMold();
    }

    public function addForm($data)
    {

        $fields = $data['fields'];

        $baseField = MoldRepository::$baseField;
        // 过滤fields中的baseField存在的字段
        $fields = array_filter($fields, function ($field) use ($baseField) {
            return ! in_array($field['field'], $baseField);
        });

        $data['fields'] = json_encode($fields);

        // dd($this->moldRepository->checkTableName($data['table_name'], $data['name']));exit;
        // 判断table_name是否有重复，如果有重复后面加标识
        if (! $this->moldRepository->checkTableName($data['table_name'], $data['name'])) {
            throw new PageNoticeException('页面名称或标识ID已存在');
        }

        // 使用正确的表名前缀格式：{$projectPrefix}_mc_{$tableName}
        $data['table_name'] = $this->moldRepository->getTableName($data['table_name']);

        $data['subject_content'] = isset($data['subject_content']) ? $data['subject_content'] : '{}';

        if (! isset($data['list_show_fields'])) {
            $data['list_show_fields'] = [];

            foreach ($fields as $one) {

                if (in_array($one['type'], MoldRepository::$defaultListNotShowField)) {
                    continue;
                }

                $data['list_show_fields'][] = $one['field'];
            }

            $data['list_show_fields'] && $data['list_show_fields'] = json_encode($data['list_show_fields']);
        }

        // 新建模型
        $moldId = $this->moldRepository->saveMold($data);

        if ($data['mold_type'] == MoldRepository::CONTENT_MOLD_TYPE) {
            $this->getTableByField($data['table_name'], $fields);
        }

        return $moldId;

    }

    public function editFormById($data, $id)
    {

        return $this->moldRepository->updateById($data, $id);
    }

    /**
     * 新建或修改mold对应的表
     *
     * @param  string  $tabelName  表名
     * @param  array  $field  字段定义
     */
    public function getTableByField($tabelName, $field)
    {

        $allField = array_column($field, 'field');

        $baseField = MoldRepository::$baseField;

        if (Schema::hasTable($tabelName)) {
            Schema::table($tabelName, function (Blueprint $table) use ($field, $tabelName, $allField, $baseField) {
                $columns = Schema::getColumnListing($tabelName);

                // 删除不存在于新字段列表中的字段
                foreach ($columns as $oneColumn) {
                    if (! in_array($oneColumn, $baseField) && ! in_array($oneColumn, $allField)) {
                        $table->dropColumn($oneColumn);
                    }
                }

                // 添加或修改字段
                foreach ($field as $item) {
                    if (! Schema::hasColumn($tabelName, $item['field'])) {
                        $this->addFieldToTable($table, $item);
                    }
                }

                // 添加基本字段
                if (! Schema::hasColumn($tabelName, 'updated_by')) {
                    $table->string('updated_by')->nullable();
                }
                if (! Schema::hasColumn($tabelName, 'created_by')) {
                    $table->string('created_by')->nullable();
                }
                if (! Schema::hasColumn($tabelName, 'content_status')) {
                    $table->string('content_status')->nullable();
                }
                if (! Schema::hasColumn($tabelName, 'created_at')) {
                    $table->timestamps();
                }
                if (! Schema::hasColumn($tabelName, 'deleted_at')) {
                    $table->softDeletes(); // 添加 deleted_at 字段支持软删除
                }
            });
        } else {
            Schema::create($tabelName, function (Blueprint $table) use ($field) {
                $table->id();

                // 添加字段
                foreach ($field as $item) {
                    $this->addFieldToTable($table, $item);
                }

                // 添加基本字段
                $table->string('updated_by')->nullable();
                $table->string('created_by')->nullable();
                $table->string('content_status')->nullable();
                $table->timestamps();
                $table->softDeletes(); // 添加 deleted_at 字段支持软删除
            });
        }
    }

    /**
     * 根据字段类型添加对应的数据库字段
     *
     * @param  Blueprint  $table  表对象
     * @param  array  $item  字段定义
     */
    private function addFieldToTable($table, $item)
    {
        $fieldName = $item['field'];
        $fieldType = $item['type'] ?? 'string';

        switch ($fieldType) {
            case 'text':
            case 'textarea':
            case 'richText':
            case 'picGallery':
                $table->text($fieldName)->nullable();
                break;
            case 'number':
            case 'int':
            case 'integer':
                $table->integer($fieldName)->nullable();
                break;
            case 'float':
            case 'decimal':
            case 'double':
                $table->decimal($fieldName, 10, 2)->nullable();
                break;
            case 'boolean':
            case 'switch':
                $table->boolean($fieldName)->default(false);
                break;
            case 'date':
                $table->date($fieldName)->nullable();
                break;
            case 'datetime':
            case 'timestamp':
                $table->dateTime($fieldName)->nullable();
                break;
            case 'json':
            case 'array':
                $table->json($fieldName)->nullable();
                break;
            case 'select':
            case 'check_box':
            case 'radio':
                $table->string($fieldName, 1024)->nullable();
                break;
            case 'string':
            default:
                // 长度1024
                $table->string($fieldName, 1024)->nullable();
                break;
        }
    }
}
