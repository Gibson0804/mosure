<?php

namespace App\Services;

use App\Models\FunctionEnv;
use App\Models\Mold;
use App\Models\ProjectCron;
use App\Models\ProjectFunction;
use App\Models\ProjectMenu;
use App\Models\ProjectTrigger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use ZipArchive;

class ProjectExportService
{
    /**
     * 构建导出包并返回 zip 文件路径与文件名
     *
     * @param  array<int,int>  $modelIds
     * @param  array{includeData?:bool, includeMedia?:bool, includeFunctions?:array, includeMenus?:bool, mediaFolders?:array}  $options
     * @return array{path:string, filename:string}
     */
    public function buildZip(array $modelIds, array $options = []): array
    {

        $includeData = (bool) ($options['includeData'] ?? false);
        $includeMenus = (array) ($options['includeMenus'] ?? []);
        $includeFunctions = (array) ($options['includeFunctions'] ?? []);
        $mediaFolders = (array) ($options['mediaFolders'] ?? []);
        $modelDataMap = (array) ($options['modelDataMap'] ?? []);

        // 解析云函数选项
        $includeEndpointIds = (array) ($includeFunctions['endpoints'] ?? []);
        $includeHookIds = (array) ($includeFunctions['hooks'] ?? []);
        $includeVariables = (bool) ($includeFunctions['variables'] ?? false);
        $includeTriggers = (bool) ($includeFunctions['triggers'] ?? false);
        $includeSchedules = (bool) ($includeFunctions['schedules'] ?? false);

        // 读取模型定义（如果有选择模型）
        $molds = collect();
        if (! empty($modelIds)) {
            $molds = (new Mold)->newQuery()->whereIn('id', $modelIds)->get();
            if ($molds->isEmpty()) {
                throw new \InvalidArgumentException('未找到任何待导出的模型');
            }
        }

        // 临时目录
        $uniq = uniqid('export_', true);
        $baseDir = storage_path('app/export_tmp/'.$uniq);
        $modelsDir = $baseDir.'/models';
        if (! is_dir($modelsDir)) {
            mkdir($modelsDir, 0775, true);
        }

        // 写 manifest.json
        $manifest = [
            'package_version' => '1.0.0',
            'exported_at' => now()->toIso8601String(),
            'contents' => [
                'models' => [],
                'functions' => [
                    'endpoints' => [],
                    'hooks' => [],
                    'variables' => false,
                    'triggers' => false,
                    'schedules' => false,
                ],
                'menus' => [],
                'data' => [],
                'media' => [],
            ],
        ];

        // 导出模型定义
        if (! $molds->isEmpty()) {
            // 获取当前项目前缀
            $currentPrefix = session('current_project_prefix');
            $frameworkPrefix = \App\Constants\ProjectConstants::MODEL_CONTENT_PREFIX;
            $fullPrefix = $currentPrefix.$frameworkPrefix;

            foreach ($molds as $m) {

                // 获取不带项目前缀的表名
                $slug = $m->table_name ?: ('mold_'.$m->id);
                if ($currentPrefix && strpos($slug, $fullPrefix) === 0) {
                    $slug = substr($slug, strlen($fullPrefix));
                }

                $fields = $this->safeDecode($m->fields);
                $fields = $this->convertSourceModelIdToSlug($fields, $fullPrefix);

                $modelJson = [
                    'id' => $m->id,
                    'name' => $m->name,
                    'description' => $m->description,
                    'table_name' => $slug,
                    'mold_type' => $m->mold_type,
                    'fields' => $fields,
                    'settings' => $this->safeDecode($m->settings),
                    'subject_content' => $this->safeDecode($m->subject_content),
                    'list_show_fields' => $this->safeDecode($m->list_show_fields),
                ];

                file_put_contents($modelsDir.'/'.$slug.'.json', json_encode($modelJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                $manifest['contents']['models'][] = $slug;
            }
        }

        // 导出云函数
        $hasAnyFunctionOption = ! empty($includeEndpointIds) || ! empty($includeHookIds) || $includeVariables || $includeTriggers || $includeSchedules;
        if ($hasAnyFunctionOption) {
            $functionsDir = $baseDir.'/functions';
            $endpointsDir = $functionsDir.'/endpoints';
            $hooksDir = $functionsDir.'/hooks';
            if (! is_dir($endpointsDir)) {
                mkdir($endpointsDir, 0775, true);
            }
            if (! is_dir($hooksDir)) {
                mkdir($hooksDir, 0775, true);
            }

            // 导出选中的 Web 函数
            if (! empty($includeEndpointIds)) {
                $functions = ProjectFunction::whereIn('id', $includeEndpointIds)
                    ->where('type', 'endpoint')
                    ->get();
                foreach ($functions as $fn) {
                    $functionJson = [
                        'id' => $fn->id,
                        'name' => $fn->name,
                        'slug' => $fn->slug,
                        'type' => $fn->type,
                        'enabled' => $fn->enabled,
                        'code' => $fn->code,
                        'timeout_ms' => $fn->timeout_ms,
                        'max_mem_mb' => $fn->max_mem_mb,
                        'rate_limit' => $fn->rate_limit,
                        'input_schema' => $this->safeDecode($fn->input_schema),
                        'output_schema' => $this->safeDecode($fn->output_schema),
                        'http_method' => $fn->http_method,
                        'remark' => $fn->remark,
                    ];

                    $fnSlug = $fn->slug ?: ('function_'.$fn->id);
                    file_put_contents($endpointsDir.'/'.$fnSlug.'.json', json_encode($functionJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    $manifest['contents']['functions']['endpoints'][] = $fnSlug;
                }
            }

            // 导出选中的触发函数
            if (! empty($includeHookIds)) {
                $functions = ProjectFunction::whereIn('id', $includeHookIds)
                    ->where('type', 'hook')
                    ->get();
                foreach ($functions as $fn) {
                    $functionJson = [
                        'id' => $fn->id,
                        'name' => $fn->name,
                        'slug' => $fn->slug,
                        'type' => $fn->type,
                        'enabled' => $fn->enabled,
                        'code' => $fn->code,
                        'timeout_ms' => $fn->timeout_ms,
                        'max_mem_mb' => $fn->max_mem_mb,
                        'rate_limit' => $fn->rate_limit,
                        'input_schema' => $this->safeDecode($fn->input_schema),
                        'output_schema' => $this->safeDecode($fn->output_schema),
                        'http_method' => $fn->http_method,
                        'remark' => $fn->remark,
                    ];

                    $fnSlug = $fn->slug ?: ('function_'.$fn->id);
                    file_put_contents($hooksDir.'/'.$fnSlug.'.json', json_encode($functionJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    $manifest['contents']['functions']['hooks'][] = $fnSlug;
                }
            }

            // 导出配置变量（variables.json）
            if ($includeVariables) {
                $envs = FunctionEnv::get();
                if ($envs->isNotEmpty()) {
                    $variablesData = [];
                    foreach ($envs as $env) {
                        $variablesData[] = [
                            'name' => $env->name,
                            'value' => $env->value,
                            'remark' => $env->remark,
                        ];
                    }
                    file_put_contents($functionsDir.'/variables.json', json_encode($variablesData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    $manifest['contents']['functions']['variables'] = true;
                }
            }

            // 导出触发器（triggers.json）
            if ($includeTriggers) {
                $triggers = ProjectTrigger::get();
                if ($triggers->isNotEmpty()) {
                    $triggersData = [];

                    $currentPrefix = session('current_project_prefix');
                    $frameworkPrefix = \App\Constants\ProjectConstants::MODEL_CONTENT_PREFIX;
                    $fullPrefix = $currentPrefix.$frameworkPrefix;

                    foreach ($triggers as $trigger) {
                        // 获取关联的函数和模型的 slug
                        $actionFunction = ProjectFunction::find($trigger->action_function_id);
                        $mold = Mold::find($trigger->mold_id);

                        $actionMoldSlug = $mold ? str_replace($fullPrefix, '', $mold->table_name) : null;

                        $triggerData = [
                            'id' => $trigger->id,
                            'name' => $trigger->name,
                            'enabled' => $trigger->enabled,
                            'trigger_type' => $trigger->trigger_type,
                            'events' => $this->safeDecode($trigger->events),
                            'action_function_slug' => $actionFunction ? $actionFunction->slug : null,
                            'action_mold_slug' => $actionMoldSlug,
                            'content_id' => $trigger->content_id,
                            'watch_function_id' => $trigger->watch_function_id,
                            'input_schema' => $this->safeDecode($trigger->input_schema),
                            'remark' => $trigger->remark,
                        ];
                        $triggersData[] = $triggerData;
                    }
                    file_put_contents($functionsDir.'/triggers.json', json_encode($triggersData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    $manifest['contents']['functions']['triggers'] = true;
                }
            }

            // 导出定时任务（schedules.json）
            if ($includeSchedules) {
                $crons = ProjectCron::get();
                if ($crons->isNotEmpty()) {
                    $schedulesData = [];
                    foreach ($crons as $cron) {
                        // 获取关联的函数 slug
                        $function = ProjectFunction::find($cron->function_id);

                        $scheduleData = [
                            'id' => $cron->id,
                            'name' => $cron->name,
                            'enabled' => $cron->enabled,
                            'function_slug' => $function ? $function->slug : null,
                            'schedule_type' => $cron->schedule_type,
                            'run_at' => $cron->run_at,
                            'cron_expr' => $cron->cron_expr,
                            'timezone' => $cron->timezone,
                            'payload' => $this->safeDecode($cron->payload),
                            'timeout_ms' => $cron->timeout_ms,
                            'max_mem_mb' => $cron->max_mem_mb,
                            'remark' => $cron->remark,
                        ];
                        $schedulesData[] = $scheduleData;
                    }
                    file_put_contents($functionsDir.'/schedules.json', json_encode($schedulesData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    $manifest['contents']['functions']['schedules'] = true;
                }
            }
        }

        // 导出菜单（仅用户自定义的一级菜单）
        if (! empty($includeMenus)) {
            $menusDir = $baseDir.'/menus';
            if (! is_dir($menusDir)) {
                mkdir($menusDir, 0775, true);
            }

            // 只导出选中的用户自定义一级菜单
            $menus = ProjectMenu::whereIn('id', $includeMenus)
                ->whereNull('parent_id')
                ->where('plugin_id', null)
                ->whereNotIn('key', ['content_list', 'content_single'])
                ->orderBy('order')
                ->get();

            foreach ($menus as $menu) {
                // 获取子菜单
                $children = ProjectMenu::where('parent_id', $menu->id)
                    ->orderBy('order')
                    ->get();

                $childrenData = [];
                foreach ($children as $child) {
                    $targetPayload = $this->safeDecode($child->target_payload);

                    // 如果target_payload中有mold_id，转换为mold_slug
                    if (! empty($targetPayload['mold_id'])) {
                        $mold = Mold::find($targetPayload['mold_id']);
                        if ($mold) {
                            $currentPrefix = session('current_project_prefix');
                            $frameworkPrefix = \App\Constants\ProjectConstants::MODEL_CONTENT_PREFIX;
                            $fullPrefix = $currentPrefix.$frameworkPrefix;
                            $targetPayload['mold_slug'] = str_replace($fullPrefix, '', $mold->table_name);
                            unset($targetPayload['mold_id']);
                        }
                    }

                    $childrenData[] = [
                        'id' => $child->id,
                        'title' => $child->title,
                        'key' => $child->key,
                        'icon' => $child->icon,
                        'order' => $child->order,
                        'visible' => $child->visible,
                        'permission_key' => $child->permission_key,
                        'area' => $child->area,
                        'target_type' => $child->target_type,
                        'target_payload' => $targetPayload,
                    ];
                }

                $menuTargetPayload = $this->safeDecode($menu->target_payload);

                // 如果target_payload中有mold_id，转换为mold_slug
                if (! empty($menuTargetPayload['mold_id'])) {
                    $mold = Mold::find($menuTargetPayload['mold_id']);
                    if ($mold) {
                        $currentPrefix = session('current_project_prefix');
                        $frameworkPrefix = \App\Constants\ProjectConstants::MODEL_CONTENT_PREFIX;
                        $fullPrefix = $currentPrefix.$frameworkPrefix;
                        $menuTargetPayload['mold_slug'] = str_replace($fullPrefix, '', $mold->table_name);
                        unset($menuTargetPayload['mold_id']);
                    }
                }

                $menuJson = [
                    'id' => $menu->id,
                    'title' => $menu->title,
                    'key' => $menu->key,
                    'icon' => $menu->icon,
                    'order' => $menu->order,
                    'visible' => $menu->visible,
                    'permission_key' => $menu->permission_key,
                    'area' => $menu->area,
                    'target_type' => $menu->target_type,
                    'target_payload' => $menuTargetPayload,
                    'children' => $childrenData,
                ];

                $menuSlug = $menu->key ?: ('menu_'.$menu->id);
                file_put_contents($menusDir.'/'.$menuSlug.'.json', json_encode($menuJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                $manifest['contents']['menus'][] = $menuSlug;
            }
        }

        // 导出数据（可选）
        if (! empty($modelDataMap)) {
            $dataDir = $baseDir.'/data';
            if (! is_dir($dataDir)) {
                mkdir($dataDir, 0775, true);
            }

            // 获取当前项目前缀
            $currentPrefix = session('current_project_prefix');
            $frameworkPrefix = \App\Constants\ProjectConstants::MODEL_CONTENT_PREFIX;
            $fullPrefix = $currentPrefix.$frameworkPrefix;

            // 遍历每个模型，根据 modelDataMap 决定是否导出数据
            foreach ($molds as $m) {
                $shouldExportData = (bool) ($modelDataMap[$m->id] ?? false);

                if ($shouldExportData) {
                    // 获取不带项目前缀的表名
                    $baseTableName = $m->table_name;
                    if ($currentPrefix && strpos($baseTableName, $fullPrefix) === 0) {
                        $baseTableName = substr($baseTableName, strlen($fullPrefix));
                    }

                    // 使用不带前缀的表名作为目录名
                    $modelDataDir = $dataDir.'/'.$baseTableName;
                    if (! is_dir($modelDataDir)) {
                        mkdir($modelDataDir, 0775, true);
                    }

                    // 实现数据导出逻辑
                    try {
                        // 获取模型对应的内容表名（带前缀的完整表名）
                        $contentTableName = $m->table_name;
                        if ($contentTableName && Schema::hasTable($contentTableName)) {
                            // 查询该模型的所有数据
                            $records = DB::table($contentTableName)->get();
                            $count = 0;

                            foreach ($records as $record) {
                                $recordArray = (array) $record;
                                // 转换为JSON并保存
                                file_put_contents($modelDataDir.'/'.$record->id.'.json', json_encode($recordArray, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                                $count++;
                            }
                            $manifest['contents']['data'][] = $baseTableName;
                        }
                    } catch (\Exception $e) {
                        // 如果导出失败，记录错误但继续
                        Log::error('导出模型数据失败: '.$e->getMessage());
                    }
                }
            }
        }

        file_put_contents($baseDir.'/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // 打包 zip
        $zipFilename = 'project-export-'.date('Ymd-His').'.zip';
        $zipPath = storage_path('app/exports');
        if (! is_dir($zipPath)) {
            mkdir($zipPath, 0775, true);
        }
        $zipFullPath = $zipPath.'/'.$zipFilename;

        $zip = new ZipArchive;
        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('无法创建导出包');
        }

        $this->zipFolder($baseDir, $zip);
        $zip->close();

        // 清理临时目录
        $this->rrmdir($baseDir);

        // 生成签名的临时下载链接（1分钟有效）
        $downloadUrl = URL::temporarySignedRoute(
            'project.export.download',
            now()->addMinutes(1),
            [
                'file' => $zipFilename,
            ]
        );

        return [
            'download_url' => $downloadUrl,
            'filename' => $zipFilename,
            'path' => $zipFullPath,
        ];
    }

    private function safeDecode($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_null($value) || $value === '') {
            return [];
        }
        try {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function convertSourceModelIdToSlug(array $fields, string $fullPrefix): array
    {
        foreach ($fields as &$field) {
            if (! empty($field['sourceModelId'])) {
                $relatedMold = Mold::find($field['sourceModelId']);
                if ($relatedMold) {
                    $slug = $relatedMold->table_name;
                    if (strpos($slug, $fullPrefix) === 0) {
                        $slug = substr($slug, strlen($fullPrefix));
                    }
                    $field['sourceModelSlug'] = $slug;
                    unset($field['sourceModelId']);
                }
            }
        }

        return $fields;
    }

    private function zipFolder(string $folder, ZipArchive $zip, string $baseInZip = ''): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = (string) $file;
            $localPath = $baseInZip.ltrim(str_replace($folder, '', $filePath), DIRECTORY_SEPARATOR);
            if (is_dir($filePath)) {
                $zip->addEmptyDir($localPath);
            } else {
                $zip->addFile($filePath, $localPath);
            }
        }
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
