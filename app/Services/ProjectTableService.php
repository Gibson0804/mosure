<?php

namespace App\Services;

use App\Constants\ProjectConstants;
use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\AuditLog;
use App\Models\ClientAiConversation;
use App\Models\FunctionEnv;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\MediaTag;
use App\Models\Mold;
use App\Models\PluginInstallation;
use App\Models\PluginInstallRecord;
use App\Models\Project;
use App\Models\ProjectConfig;
use App\Models\ProjectAuthRole;
use App\Models\ProjectAuthSession;
use App\Models\ProjectAuthUser;
use App\Models\ProjectAuthUserRole;
use App\Models\ProjectCron;
use App\Models\ProjectCronExecution;
use App\Models\ProjectFunction;
use App\Models\ProjectFunctionExecution;
use App\Models\ProjectHookExecution;
use App\Models\ProjectMenu;
use App\Models\ProjectPage;
use App\Models\ProjectTrigger;
use App\Models\ProjectTriggerExecution;
use App\Models\SysContentVersion;
use App\Models\SystemSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProjectTableService
{
    private $moldService;

    public function __construct(MoldService $moldService)
    {
        $this->moldService = $moldService;
    }

    /**
     * 创建项目相关的所有数据库表
     *
     * 表名前缀规范：
     * - 系统核心层（sys_）：存储系统基础功能数据和所有项目主要信息
     * - 项目管理层（项目前缀_pf_）：存储特定项目必需的数据
     * - 内容模型层（项目前缀_mc_）：存储用户在项目中自定义创建的内容模型数据
     *
     * @param  string  $prefix  项目前缀
     * @param  string  $template  项目模板
     * @return void
     */
    public function createProjectTables($prefix, $template)
    {
        // 记录开始创建项目表
        Log::info("Creating project tables for prefix: {$prefix} with template: {$template}");

        try {
            // 创建项目管理层表（项目前缀_pf_）
            $this->createProjectFrameworkTables($prefix);

            // 内容模型层表（项目前缀_mc_）由 /mold/add 接口动态创建
            // 因此不需要预先创建内容模型表

            // 记录创建完成
            Log::info("Project tables created successfully for prefix: {$prefix}");
        } catch (\Exception $e) {
            // 记录错误
            Log::error("Failed to create project tables: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * 删除项目相关的所有数据库表
     *
     * @param  string  $prefix  项目前缀
     * @return void
     */
    public function dropProjectTables($prefix)
    {
        // 记录开始删除项目表
        Log::info("Dropping project tables for prefix: {$prefix}");

        try {
            // 获取所有数据库表
            $tables = DB::select('SHOW TABLES');
            $droppedTables = [];

            // 遍历所有表，删除以项目前缀开头的表
            foreach ($tables as $table) {
                $tableName = array_values((array) $table)[0];

                // 检查表名是否以项目前缀开头
                if (strpos($tableName, $prefix.'_') === 0) {
                    Schema::dropIfExists($tableName);
                    $droppedTables[] = $tableName;
                }
            }

            // 记录删除完成
            Log::info("Project tables dropped successfully for prefix: {$prefix}. Dropped tables: ".implode(', ', $droppedTables));
        } catch (\Exception $e) {
            // 记录错误
            Log::error("Failed to drop project tables: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * 创建项目框架层表（项目前缀_pf_）
     * 这些表存储项目管理所需的基础数据
     *
     * @param  string  $prefix  项目前缀
     */
    public function createProjectFrameworkTables($prefix)
    {
        // 创建内容模型定义表
        $tableName = Mold::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, Mold::getTableSchema());
        }

        // 创建项目菜单表（项目级）
        $menuTable = ProjectMenu::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($menuTable)) {
            Schema::create($menuTable, ProjectMenu::getTableSchema());
            // 初始化核心可覆盖菜单（仅名称/显隐），其他固定菜单仍由代码提供
            $this->initializeCoreMenus($menuTable);
        }

        // 创建媒体资源表
        $mediaTable = Media::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($mediaTable)) {
            Schema::create($mediaTable, Media::getTableSchema());
        } else {
            // 已有表：补充新增的 disk 列
            if (! Schema::hasColumn($mediaTable, 'disk')) {
                Schema::table($mediaTable, function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->string('disk')->default('public')->after('path');
                });
            }
        }

        // 创建媒体文件夹表
        $folderTable = MediaFolder::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($folderTable)) {
            Schema::create($folderTable, MediaFolder::getTableSchema());
        }

        // 创建媒体标签表
        $tagTable = MediaTag::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($tagTable)) {
            Schema::create($tagTable, MediaTag::getTableSchema());
        }

        // 确保存在“未分类”系统文件夹，并回填存量媒体
        try {
            // 查询未分类文件夹
            $uncat = DB::table($folderTable)
                ->where('is_system', true)
                ->where('name', '未分类')
                ->first();

            if (! $uncat) {
                $now = now();
                $uncatId = DB::table($folderTable)->insertGetId([
                    'name' => '未分类',
                    'parent_id' => null,
                    'mpath' => '',
                    'depth' => 0,
                    'sort' => 0,
                    'cover_media_id' => null,
                    'is_system' => true,
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                // 更新自身 mpath
                DB::table($folderTable)->where('id', $uncatId)->update([
                    'mpath' => '/'.$uncatId.'/',
                    'depth' => 1,
                ]);
                $uncat = (object) ['id' => $uncatId];
            }

            // 回填媒体的 folder_id
            if (Schema::hasColumn($mediaTable, 'folder_id')) {
                DB::table($mediaTable)->whereNull('folder_id')->update([
                    'folder_id' => $uncat->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('初始化未分类文件夹或回填媒体失败: '.$e->getMessage());
        }

        // apikeys 和 apiLog
        $tableName = ApiKey::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, ApiKey::getTableSchema());
        } else {
            // 检查是否需要添加 plugin_id 字段
            if (! Schema::hasColumn($tableName, 'plugin_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('plugin_id')->nullable()->after('description')->comment('关联的插件ID');
                });
            }
            if (! Schema::hasColumn($tableName, 'scopes')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->json('scopes')->nullable()->after('allowed_ips')->comment('允许的接口权限范围');
                });
            }
        }

        $tableName = ApiLog::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, ApiLog::getTableSchema());
        }
        // 审计日志表（项目级别）
        $tableName = AuditLog::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, AuditLog::getTableSchema());
        }
        // 项目配置表
        $tableName = ProjectConfig::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, ProjectConfig::getTableSchema());
        }

        // 项目用户认证相关表（系统内置可选模块）
        foreach ([
            ProjectAuthUser::class,
            ProjectAuthRole::class,
            ProjectAuthUserRole::class,
            ProjectAuthSession::class,
        ] as $modelClass) {
            $tableName = $modelClass::getfullTableNameByPrefix($prefix);
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, $modelClass::getTableSchema());
            }
        }

        // 内容版本表（系统级，不带项目前缀）
        if (! Schema::hasTable('sys_content_versions')) {
            Schema::create('sys_content_versions', SysContentVersion::getTableSchema());
        }

        // 内容版本表（系统级，不带项目前缀）
        if (! Schema::hasTable('sys_settings')) {
            Schema::create('sys_settings', SystemSetting::getTableSchema());
        }

        // 函数平台（项目级）
        $fnTable = ProjectFunction::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($fnTable)) {
            Schema::create($fnTable, ProjectFunction::getTableSchema());
        }

        // 触发器表（项目级）
        $triggerTable = ProjectTrigger::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($triggerTable)) {
            Schema::create($triggerTable, ProjectTrigger::getTableSchema());
        }

        $triggerExecTable = ProjectTriggerExecution::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($triggerExecTable)) {
            Schema::create($triggerExecTable, ProjectTriggerExecution::getTableSchema());
        }

        $fnExecTable = ProjectFunctionExecution::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($fnExecTable)) {
            Schema::create($fnExecTable, ProjectFunctionExecution::getTableSchema());
        }

        // 插件安装表（项目级）
        $pluginTable = PluginInstallation::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($pluginTable)) {
            Schema::create($pluginTable, PluginInstallation::getTableSchema());
        }

        // 插件安装记录表（项目级）- 记录每个操作的详细信息
        $pluginRecordTable = PluginInstallRecord::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($pluginRecordTable)) {
            Schema::create($pluginRecordTable, PluginInstallRecord::getTableSchema());
        }

        $hookExecTable = ProjectHookExecution::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($hookExecTable)) {
            Schema::create($hookExecTable, ProjectHookExecution::getTableSchema());
        }

        $clientAiConversationTable = ClientAiConversation::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($clientAiConversationTable)) {
            Schema::create($clientAiConversationTable, ClientAiConversation::getTableSchema());
        } else {
            if (! Schema::hasColumn($clientAiConversationTable, 'session_id')) {
                Schema::table($clientAiConversationTable, function (Blueprint $table) {
                    $table->unsignedBigInteger('session_id')->nullable()->index()->after('id');
                });
            }
        }

        // 定时任务表（项目级）
        $cronTable = ProjectCron::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($cronTable)) {
            Schema::create($cronTable, ProjectCron::getTableSchema());
        }

        $cronExecTable = ProjectCronExecution::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($cronExecTable)) {
            Schema::create($cronExecTable, ProjectCronExecution::getTableSchema());
        }

        $envTable = FunctionEnv::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($envTable)) {
            Schema::create($envTable, FunctionEnv::getTableSchema());
        }

        // 前端托管页面表（项目级）
        $pageTable = ProjectPage::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($pageTable)) {
            Schema::create($pageTable, ProjectPage::getTableSchema());
        }

        // 知识库表（系统级，不带项目前缀）
        if (! Schema::hasTable('sys_kb_categories')) {
            Schema::create('sys_kb_categories', KbCategory::getTableSchema());
        }
        if (! Schema::hasTable('sys_kb_articles')) {
            Schema::create('sys_kb_articles', KbArticle::getTableSchema());
        }

    }

    /**
     * 根据字段定义添加字段到表中
     *
     * @param  Blueprint  $table
     * @param  array  $field
     */
    private function addFieldToTable($table, $field)
    {
        $name = $field['name'];
        $type = $field['type'];
        $nullable = $field['required'] ?? false ? false : true;

        switch ($type) {
            case 'text':
                $table->text($name)->nullable($nullable);
                break;
            case 'string':
                $table->string($name)->nullable($nullable);
                break;
            case 'integer':
                $table->integer($name)->nullable($nullable);
                break;
            case 'float':
                $table->float($name)->nullable($nullable);
                break;
            case 'boolean':
                $table->boolean($name)->default(false);
                break;
            case 'date':
                $table->date($name)->nullable($nullable);
                break;
            case 'datetime':
                $table->dateTime($name)->nullable($nullable);
                break;
            case 'json':
                $table->json($name)->nullable($nullable);
                break;
            default:
                $table->string($name)->nullable($nullable);
        }
    }

    /**
     * 临时方法：更新已有项目的 molds 表结构
     * 添加缺失的字段：table_name, mold_type, subject_content, list_show_fields, filter_show_fields
     * 该方法仅用于修复现有项目表结构，后续可删除
     *
     * @return array 更新结果
     */
    public function updateExistingMoldsTables($prefix = null)
    {
        $results = [];

        // 获取所有项目
        $projects = Project::all();

        foreach ($projects as $project) {
            $prefix = $project->prefix;
            $tableName = $prefix.ProjectConstants::PROJECT_FRAMEWORK_PREFIX.'molds';
            $result = ['project' => $project->name, 'table' => $tableName, 'updated_fields' => []];

            // 检查表是否存在
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName, &$result) {
                    // 检查并添加 table_name 字段
                    if (! Schema::hasColumn($tableName, 'table_name')) {
                        $table->string('table_name')->nullable();
                        $result['updated_fields'][] = 'table_name';
                    }

                    // 检查并添加 mold_type 字段
                    if (! Schema::hasColumn($tableName, 'mold_type')) {
                        $table->string('mold_type')->default('custom');
                        $result['updated_fields'][] = 'mold_type';
                    }

                    // 检查并添加 subject_content 字段
                    if (! Schema::hasColumn($tableName, 'subject_content')) {
                        $table->json('subject_content')->nullable();
                        $result['updated_fields'][] = 'subject_content';
                    }

                    // 检查并添加 list_show_fields 字段
                    if (! Schema::hasColumn($tableName, 'list_show_fields')) {
                        $table->json('list_show_fields')->nullable();
                        $result['updated_fields'][] = 'list_show_fields';
                    }

                    // 检查并添加 filter_show_fields 字段
                    if (! Schema::hasColumn($tableName, 'filter_show_fields')) {
                        $table->json('filter_show_fields')->nullable();
                        $result['updated_fields'][] = 'filter_show_fields';
                    }
                });

                $result['status'] = 'success';
                if (empty($result['updated_fields'])) {
                    $result['message'] = '表结构已经是最新的，无需更新';
                } else {
                    $result['message'] = '表结构更新成功';
                }
            } else {
                $result['status'] = 'error';
                $result['message'] = '表不存在';
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * 临时方法：更新已有项目的 content 表结构
     * 检查并创建缺失的表
     * 该方法仅用于修复现有项目表结构，后续可删除
     *
     * @return array 更新结果
     */
    public function updateExistingContentTables()
    {
        $results = [];

        // 获取所有项目
        $projects = Project::all();
        foreach ($projects as $project) {
            // 获取所有项目模型
            $moldTableName = $project->prefix.ProjectConstants::PROJECT_FRAMEWORK_PREFIX.'molds';

            $molds = DB::table($moldTableName)->get();

            foreach ($molds as $mold) {
                // 获取模型对应的表
                $tableName = $mold->table_name;
                $field = $mold->fields;
                if (! is_array($field)) {
                    $field = json_decode($field, true);
                }

                $this->moldService->getTableByField($tableName, $field);
            }
        }

        return $results;
    }

    /**
     * 清空项目的所有数据（保留表结构）
     *
     * 清空内容包括：
     * - 内容模型层表（项目前缀_mc_开头的表）：删除整个表
     * - 项目管理层表的数据（项目前缀_pf_开头的表）：清空数据，保留表结构
     *
     * 不影响：
     * - 系统核心层表（sys_开头的表）
     * - 项目管理层表的表结构
     *
     * @param  string  $prefix  项目前缀
     * @return array 清空结果
     */
    public function purgeProjectData($prefix)
    {
        // 记录开始清空项目数据
        Log::info("Purging project data for prefix: {$prefix}");

        try {
            // 获取所有数据库表
            $tables = DB::select('SHOW TABLES');
            $droppedTables = [];
            $truncatedTables = [];

            // 项目前缀定义
            $frameworkPrefix = ProjectConstants::PROJECT_FRAMEWORK_PREFIX; // _pf_
            $contentPrefix = ProjectConstants::MODEL_CONTENT_PREFIX; // _mc_

            // 遍历所有表，根据表名前缀采取不同操作
            foreach ($tables as $table) {
                $tableName = array_values((array) $table)[0];

                // 检查表名是否以项目前缀开头
                if (strpos($tableName, $prefix.'_') === 0) {
                    // 检查是否是内容模型层表（项目前缀_mc_）
                    if (strpos($tableName, $prefix.$contentPrefix) === 0) {
                        // 删除整个表（内容模型层表）
                        Schema::dropIfExists($tableName);
                        $droppedTables[] = $tableName;
                    }
                    // 检查是否是项目管理层表（项目前缀_pf_）
                    elseif (strpos($tableName, $prefix.$frameworkPrefix) === 0) {
                        // 清空表数据，保留表结构（项目管理层表）
                        DB::statement('TRUNCATE TABLE '.$tableName);
                        $truncatedTables[] = $tableName;
                    }
                }
            }

            // 清空后重新初始化核心菜单
            $menuTable = $prefix.$frameworkPrefix.'menus';
            if (Schema::hasTable($menuTable)) {
                $this->initializeCoreMenus($menuTable);
            }

            // 记录清空完成
            Log::info("Project data purged successfully for prefix: {$prefix}");
            Log::info('Dropped tables: '.implode(', ', $droppedTables));
            Log::info('Truncated tables: '.implode(', ', $truncatedTables));

            return [
                'success' => true,
                'dropped_tables' => $droppedTables,
                'truncated_tables' => $truncatedTables,
                'message' => '项目数据已清空：内容模型表已删除，项目管理表已清空数据，核心菜单已重新初始化',
            ];
        } catch (\Exception $e) {
            // 记录错误
            Log::error("Failed to purge project data: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * 初始化核心菜单（content_list 和 content_single）
     *
     * @param  string  $menuTable  菜单表名
     * @return void
     */
    private function initializeCoreMenus($menuTable)
    {
        try {
            $now = now();

            DB::table($menuTable)->insert([
                [
                    'parent_id' => null,
                    'title' => '内容列表',
                    'key' => 'content_list',
                    'icon' => 'UnorderedListOutlined',
                    'target_type' => 'group',
                    'target_payload' => json_encode([]),
                    'order' => 300,
                    'visible' => true,
                    'permission_key' => null,
                    'area' => 'admin',
                    'plugin_id' => null,
                    'extra' => json_encode([]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'parent_id' => null,
                    'title' => '内容单页',
                    'key' => 'content_single',
                    'icon' => 'FileOutlined',
                    'target_type' => 'group',
                    'target_payload' => json_encode([]),
                    'order' => 400,
                    'visible' => true,
                    'permission_key' => null,
                    'area' => 'admin',
                    'plugin_id' => null,
                    'extra' => json_encode([]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('初始化核心菜单失败: '.$e->getMessage());
        }
    }
}
