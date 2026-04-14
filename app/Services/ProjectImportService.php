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
use ZipArchive;

class ProjectImportService
{
    /**
     * 解析导入包，检测冲突
     */
    public function parseImport(string $zipPath): array
    {
        // 检查文件是否存在
        if (! file_exists($zipPath)) {
            throw new \RuntimeException('ZIP文件不存在: '.$zipPath);
        }

        // 检查文件是否可读
        if (! is_readable($zipPath)) {
            throw new \RuntimeException('ZIP文件不可读: '.$zipPath);
        }

        $zip = new ZipArchive;
        $result = $zip->open($zipPath);

        if ($result !== true) {
            $errorMessages = [
                ZipArchive::ER_NOENT => '文件不存在',
                ZipArchive::ER_OPEN => '打开文件失败',
                ZipArchive::ER_READ => '读取文件失败',
                ZipArchive::ER_NOZIP => '不是有效的ZIP文件',
                ZipArchive::ER_INCONS => 'ZIP文件不一致',
                ZipArchive::ER_CRC => 'CRC校验失败',
                ZipArchive::ER_MEMORY => '内存不足',
            ];
            $errorMsg = $errorMessages[$result] ?? '未知错误 (错误码: '.$result.')';
            throw new \RuntimeException('无法打开ZIP文件: '.$errorMsg.', 路径: '.$zipPath);
        }

        // 读取manifest
        $manifestContent = $zip->getFromName('manifest.json');
        if (! $manifestContent) {
            $zip->close();
            throw new \RuntimeException('找不到manifest.json文件');
        }

        $manifest = json_decode($manifestContent, true);
        if (! $manifest) {
            $zip->close();
            throw new \RuntimeException('manifest.json格式错误');
        }

        $checkData = $manifest['contents'] ?? [];
        $conflicts = $this->checkConflicts($checkData);

        $zip->close();

        return [
            'manifest' => $manifest,
            'conflicts' => $conflicts,
        ];
    }

    public function checkConflicts($checkData): array
    {

        if (empty($checkData)) {
            return [];
        }

        $conflicts = [];

        // 检测模型名称冲突
        if (! empty($checkData['models'])) {
            $modelNames = array_map(function ($model) {
                $newName = getMcTableName($model);

                return $newName;
            }, $checkData['models']);
            $existingModels = Mold::whereIn('table_name', $modelNames)->get(['table_name']);
            if ($existingModels->isNotEmpty()) {
                $conflicts['models'] = $existingModels->pluck('table_name')->toArray();
            }
        }

        // 检测函数名称冲突
        if (! empty($checkData['functions']['endpoints'])) {
            $endpointNames = $checkData['functions']['endpoints'];
            $existingEndpoints = ProjectFunction::whereIn('slug', $endpointNames)
                ->where('type', 'endpoint')
                ->get(['slug']);
            if ($existingEndpoints->isNotEmpty()) {
                $conflicts['endpoints'] = $existingEndpoints->pluck('slug')->toArray();
            }
        }

        if (! empty($checkData['functions']['hooks'])) {
            $hookNames = $checkData['functions']['hooks'];
            $existingHooks = ProjectFunction::whereIn('slug', $hookNames)
                ->where('type', 'hook')
                ->get(['slug']);
            if ($existingHooks->isNotEmpty()) {
                $conflicts['hooks'] = $existingHooks->pluck('slug')->toArray();
            }
        }

        // 检测菜单标题冲突
        if (! empty($checkData['menus'])) {
            $existingMenus = ProjectMenu::whereIn('key', $checkData['menus'])->get(['key']);
            if ($existingMenus->isNotEmpty()) {
                $conflicts['menus'] = $existingMenus->pluck('key')->toArray();
            }
        }

        return $conflicts;
    }

    /**
     * 执行导入
     */
    public function import(string $zipPath, array $options = []): array
    {
        // 检查文件是否存在
        if (! file_exists($zipPath)) {
            throw new \RuntimeException('ZIP文件不存在: '.$zipPath);
        }

        // 检查文件是否可读
        if (! is_readable($zipPath)) {
            throw new \RuntimeException('ZIP文件不可读: '.$zipPath);
        }

        $zip = new ZipArchive;
        $result = $zip->open($zipPath);

        if ($result !== true) {
            $errorMessages = [
                ZipArchive::ER_NOENT => '文件不存在',
                ZipArchive::ER_OPEN => '打开文件失败',
                ZipArchive::ER_READ => '读取文件失败',
                ZipArchive::ER_NOZIP => '不是有效的ZIP文件',
                ZipArchive::ER_INCONS => 'ZIP文件不一致',
                ZipArchive::ER_CRC => 'CRC校验失败',
                ZipArchive::ER_MEMORY => '内存不足',
            ];
            $errorMsg = $errorMessages[$result] ?? '未知错误 (错误码: '.$result.')';
            throw new \RuntimeException('无法打开ZIP文件: '.$errorMsg.', 路径: '.$zipPath);
        }

        // 读取manifest
        $manifestContent = $zip->getFromName('manifest.json');
        if (! $manifestContent) {
            $zip->close();
            throw new \RuntimeException('找不到manifest.json文件');
        }

        $manifest = json_decode($manifestContent, true);
        if (! $manifest) {
            $zip->close();
            throw new \RuntimeException('manifest.json格式错误');
        }

        $results = [
            'models' => 0,
            'functions' => 0,
            'menus' => 0,
            'data' => 0,
            'errors' => [],
        ];

        try {
            Log::info('项目导入: 开始导入', [
                'manifest' => $manifest,
            ]);

            // 导入模型
            if (! empty($manifest['contents']['models'])) {
                Log::info('项目导入: 开始导入模型', [
                    'count' => count($manifest['contents']['models']),
                ]);
                $results['models'] = $this->importModels($zip, $manifest['contents']['models'], $options);
                Log::info('项目导入: 模型导入完成', [
                    'imported' => $results['models'],
                ]);
            }

            // 导入云函数
            Log::info('项目导入: 开始导入云函数');
            $results['functions'] = $this->importFunctions($zip, $manifest['contents']['functions'], $options);
            Log::info('项目导入: 云函数导入完成', [
                'imported' => $results['functions'],
            ]);

            // 导入变量
            if (! empty($manifest['contents']['functions']['variables'])) {
                Log::info('项目导入: 开始导入变量');
                $results['variables'] = $this->importVariables($zip);
                Log::info('项目导入: 变量导入完成', [
                    'imported' => $results['variables'],
                ]);
            }

            // 导入触发器
            if (! empty($manifest['contents']['functions']['triggers'])) {
                Log::info('项目导入: 开始导入触发器');
                $results['triggers'] = $this->importTriggers($zip);
                Log::info('项目导入: 触发器导入完成', [
                    'imported' => $results['triggers'],
                ]);
            }

            // 导入定时任务
            if (! empty($manifest['contents']['functions']['schedules'])) {
                Log::info('项目导入: 开始导入定时任务');
                $results['schedules'] = $this->importSchedules($zip);
                Log::info('项目导入: 定时任务导入完成', [
                    'imported' => $results['schedules'],
                ]);
            }

            // 导入菜单
            if (! empty($manifest['contents']['menus'])) {
                Log::info('项目导入: 开始导入菜单', [
                    'count' => count($manifest['contents']['menus']),
                ]);
                $results['menus'] = $this->importMenus($zip, $manifest['contents']['menus'], $options);
                Log::info('项目导入: 菜单导入完成', [
                    'imported' => $results['menus'],
                ]);
            }

            // 导入数据
            if (! empty($manifest['contents']['data'])) {
                Log::info('项目导入: 开始导入数据', [
                    'count' => count($manifest['contents']['data']),
                ]);
                $results['data'] = $this->importData($zip, $manifest['contents']['data'], $options);
                Log::info('项目导入: 数据导入完成', [
                    'imported' => $results['data'],
                ]);
            }

            Log::info('项目导入: 导入完成', [
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('项目导入: 导入失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $zip->close();
            throw $e;
        }

        $zip->close();

        return $results;
    }

    /**
     * 导入模型
     */
    private function importModels(ZipArchive $zip, array $models, array $options): int
    {
        $count = 0;
        $modelOption = $options['models_all'] ?? 'skip';

        Log::info('项目导入: 导入模型', [
            'count' => count($models),
            'option' => $modelOption,
        ]);

        // 获取当前项目前缀
        $currentPrefix = session('current_project_prefix');
        $frameworkPrefix = \App\Constants\ProjectConstants::MODEL_CONTENT_PREFIX;
        $fullPrefix = $currentPrefix.$frameworkPrefix;

        foreach ($models as $modelSlug) {
            // 从ZIP文件中读取完整的模型数据
            $modelFilePath = 'models/'.$modelSlug.'.json';
            $modelContent = $zip->getFromName($modelFilePath);

            if (! $modelContent) {
                Log::warning('项目导入: 模型文件不存在', [
                    'model_slug' => $modelSlug,
                    'file_path' => $modelFilePath,
                ]);

                continue;
            }

            $modelData = json_decode($modelContent, true);
            if (! $modelData) {
                Log::warning('项目导入: 模型数据解析失败', [
                    'model_slug' => $modelSlug,
                ]);

                continue;
            }

            $modelName = $modelData['name'] ?? 'Unnamed Model';
            Log::info('项目导入: 开始导入模型', [
                'model_slug' => $modelSlug,
                'model_name' => $modelName,
            ]);

            $fields = $modelData['fields'] ?? [];
            $fields = $this->convertSourceModelSlugToId($fields, $fullPrefix);

            $payload = [
                'name' => $modelName,
                'table_name' => $modelData['table_name'] ?? 'model_'.uniqid(),
                'mold_type' => $modelData['mold_type'] ?? 1,
                'fields' => $fields,
                'settings' => $modelData['settings'] ?? [],
                'subject_content' => json_encode($modelData['subject_content'] ?? []),
                'list_show_fields' => json_encode($modelData['list_show_fields'] ?? []),
                'plugin_id' => 0,
            ];

            try {
                $moldId = app(\App\Services\MoldService::class)->addForm($payload);
                $count++;
                Log::info('项目导入: 模型导入成功', [
                    'model_slug' => $modelSlug,
                    'model_name' => $modelName,
                    'mold_id' => $moldId,
                ]);
            } catch (\Exception $e) {
                Log::error('项目导入: 模型导入失败', [
                    'model_slug' => $modelSlug,
                    'model_name' => $modelName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('项目导入: 模型导入结束', [
            'total_count' => count($models),
            'imported_count' => $count,
        ]);

        return $count;
    }

    /**
     * 导入云函数
     */
    private function importFunctions(ZipArchive $zip, array $functionsData, array $options): int
    {
        $count = 0;

        Log::info('项目导入: 导入云函数', [
            'endpoints_count' => count($functionsData['endpoints'] ?? []),
            'hooks_count' => count($functionsData['hooks'] ?? []),
        ]);

        // 导入Web函数
        if (! empty($functionsData['endpoints'])) {
            foreach ($functionsData['endpoints'] as $fnSlug) {
                // 从ZIP文件中读取完整的函数数据
                $fnFilePath = 'functions/endpoints/'.$fnSlug.'.json';
                $fnContent = $zip->getFromName($fnFilePath);

                if (! $fnContent) {
                    Log::warning('项目导入: 函数文件不存在', [
                        'function_slug' => $fnSlug,
                        'type' => 'endpoint',
                    ]);

                    continue;
                }

                $fullFnData = json_decode($fnContent, true);
                if (! $fullFnData) {
                    Log::warning('项目导入: 函数数据解析失败', [
                        'function_slug' => $fnSlug,
                        'type' => 'endpoint',
                    ]);

                    continue;
                }

                try {
                    ProjectFunction::create([
                        'name' => $fullFnData['name'],
                        'slug' => $fullFnData['slug'] ?? '',
                        'type' => 'endpoint',
                        'enabled' => $fullFnData['enabled'] ?? true,
                        'code' => $fullFnData['code'] ?? '',
                        'timeout_ms' => $fullFnData['timeout_ms'] ?? 30000,
                        'max_mem_mb' => $fullFnData['max_mem_mb'] ?? 128,
                        'rate_limit' => $fullFnData['rate_limit'] ?? null,
                        'input_schema' => ($fullFnData['input_schema'] ?? []),
                        'output_schema' => ($fullFnData['output_schema'] ?? []),
                        'http_method' => $fullFnData['http_method'] ?? 'POST',
                        'remark' => $fullFnData['remark'] ?? '',
                    ]);
                    $count++;
                    Log::info('项目导入: Web函数导入成功', [
                        'function_slug' => $fnSlug,
                        'function_name' => $fullFnData['name'],
                    ]);
                } catch (\Exception $e) {
                    Log::error('项目导入: Web函数导入失败', [
                        'function_slug' => $fnSlug,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // 导入触发函数
        if (! empty($functionsData['hooks'])) {
            $hookOption = $options['hooks_all'] ?? 'skip';
            foreach ($functionsData['hooks'] as $fnSlug) {
                // 从ZIP文件中读取完整的函数数据
                $fnFilePath = 'functions/hooks/'.$fnSlug.'.json';
                $fnContent = $zip->getFromName($fnFilePath);

                if (! $fnContent) {
                    Log::warning('项目导入: 函数文件不存在', [
                        'function_slug' => $fnSlug,
                        'type' => 'hook',
                    ]);

                    continue;
                }

                $fullFnData = json_decode($fnContent, true);
                if (! $fullFnData) {
                    Log::warning('项目导入: 函数数据解析失败', [
                        'function_slug' => $fnSlug,
                        'type' => 'hook',
                    ]);

                    continue;
                }

                try {
                    ProjectFunction::create([
                        'name' => $fullFnData['name'],
                        'slug' => $fullFnData['slug'] ?? '',
                        'type' => 'hook',
                        'enabled' => $fullFnData['enabled'] ?? true,
                        'runtime' => $fullFnData['runtime'] ?? 'nodejs18',
                        'code' => $fullFnData['code'] ?? '',
                        'webhook_url' => $fullFnData['webhook_url'] ?? '',
                        'timeout_ms' => $fullFnData['timeout_ms'] ?? 30000,
                        'max_mem_mb' => $fullFnData['max_mem_mb'] ?? 128,
                        'rate_limit' => $fullFnData['rate_limit'] ?? null,
                        'input_schema' => ($fullFnData['input_schema'] ?? []),
                        'output_schema' => ($fullFnData['output_schema'] ?? []),
                        'http_method' => $fullFnData['http_method'] ?? 'POST',
                        'remark' => $fullFnData['remark'] ?? '',
                    ]);
                    $count++;
                    Log::info('项目导入: Hook函数导入成功', [
                        'function_slug' => $fnSlug,
                        'function_name' => $fullFnData['name'],
                    ]);
                } catch (\Exception $e) {
                    Log::error('项目导入: Hook函数导入失败', [
                        'function_slug' => $fnSlug,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('项目导入: 云函数导入结束', [
            'imported_count' => $count,
        ]);

        return $count;
    }

    /**
     * 导入菜单
     */
    private function importMenus(ZipArchive $zip, array $menus, array $options): int
    {
        $count = 0;
        $menuOrder = 201; // 一级菜单order从201开始递增

        Log::info('项目导入: 导入菜单', [
            'count' => count($menus),
        ]);

        foreach ($menus as $menuSlug) {
            $menuContent = $zip->getFromName('menus/'.$menuSlug.'.json');
            if (! $menuContent) {
                Log::warning('项目导入: 菜单文件不存在', [
                    'menu_slug' => $menuSlug,
                ]);

                continue;
            }

            $menuData = json_decode($menuContent, true);
            if (! $menuData) {
                Log::warning('项目导入: 菜单数据解析失败', [
                    'menu_slug' => $menuSlug,
                ]);

                continue;
            }

            Log::info('项目导入: 开始导入一级菜单', [
                'menu_slug' => $menuSlug,
                'menu_title' => $menuData['title'],
            ]);

            // 重新生成key以避免重复
            $newMenuKey = $this->generateUniqueKey($menuData['key'], 'menus');

            try {
                $newMenu = ProjectMenu::create([
                    'title' => $menuData['title'],
                    'key' => $newMenuKey,
                    'icon' => $menuData['icon'] ?? '',
                    'order' => $menuOrder,
                    'visible' => $menuData['visible'] ?? true,
                    'permission_key' => $menuData['permission_key'] ?? '',
                    'area' => $menuData['area'] ?? 'admin',
                    'target_type' => $menuData['target_type'] ?? 'group',
                    'target_payload' => null,
                ]);
                $parentId = $newMenu->id;
                $count++;
                $menuOrder++;

                Log::info('项目导入: 一级菜单导入成功', [
                    'menu_slug' => $menuSlug,
                    'menu_title' => $menuData['title'],
                    'menu_id' => $parentId,
                ]);
            } catch (\Exception $e) {
                Log::error('项目导入: 一级菜单导入失败', [
                    'menu_slug' => $menuSlug,
                    'menu_title' => $menuData['title'],
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            // 导入二级菜单（children）
            if (! empty($menuData['children']) && isset($parentId)) {
                Log::info('项目导入: 开始导入二级菜单', [
                    'parent_menu_id' => $parentId,
                    'children_count' => count($menuData['children']),
                ]);

                foreach ($menuData['children'] as $childData) {
                    // 重新生成key以避免重复
                    $newChildKey = $this->generateUniqueKey($childData['key'], 'menus');

                    // 处理target_payload中的mold_slug转换为mold_id
                    $childTargetPayload = $childData['target_payload'] ?? [];
                    if (! empty($childTargetPayload['mold_slug'])) {
                        $table_name = getMcTableName($childTargetPayload['mold_slug']);
                        $mold = Mold::where('table_name', $table_name)->first();
                        if ($mold) {
                            $childTargetPayload['mold_id'] = $mold->id;
                            unset($childTargetPayload['mold_slug']);
                        }
                    }

                    try {
                        ProjectMenu::create([
                            'parent_id' => $parentId,
                            'title' => $childData['title'],
                            'key' => $newChildKey,
                            'icon' => $childData['icon'] ?? '',
                            'order' => $childData['order'] ?? 0,
                            'visible' => $childData['visible'] ?? true,
                            'permission_key' => $childData['permission_key'] ?? '',
                            'area' => $childData['area'] ?? 'admin',
                            'target_type' => $childData['target_type'] ?? 'group',
                            'target_payload' => $childTargetPayload,
                        ]);
                        $count++;
                        Log::info('项目导入: 二级菜单导入成功', [
                            'parent_menu_id' => $parentId,
                            'child_title' => $childData['title'],
                        ]);
                    } catch (\Exception $e) {
                        Log::error('项目导入: 二级菜单导入失败', [
                            'parent_menu_id' => $parentId,
                            'child_title' => $childData['title'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        Log::info('项目导入: 菜单导入结束', [
            'imported_count' => $count,
        ]);

        return $count;
    }

    /**
     * 导入数据
     */
    private function importData(ZipArchive $zip, array $data, array $options): int
    {
        $count = 0;

        Log::info('项目导入: 导入数据', [
            'table_count' => count($data),
        ]);

        foreach ($data as $tableName) {
            $fullTableName = getMcTableName($tableName);

            Log::info('项目导入: 开始导入表数据', [
                'table_name' => $tableName,
                'full_table_name' => $fullTableName,
            ]);

            if (! Schema::hasTable($fullTableName)) {
                Log::warning('项目导入: 表不存在，跳过', [
                    'table_name' => $tableName,
                    'full_table_name' => $fullTableName,
                ]);

                continue;
            }

            // 读取数据文件
            $dataDir = 'data/'.$tableName;
            $recordCount = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'] ?? '';

                if (strpos($name, $dataDir.'/') === 0 && strpos($name, '.json') !== false) {
                    $content = $zip->getFromIndex($i);
                    if ($content) {
                        $record = json_decode($content, true);
                        if ($record && isset($record['id'])) {
                            try {
                                DB::table($fullTableName)->insert($record);
                                $count++;
                                $recordCount++;
                            } catch (\Exception $e) {
                                Log::error('项目导入: 数据插入失败', [
                                    'table_name' => $tableName,
                                    'record_id' => $record['id'],
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                }
            }

            Log::info('项目导入: 表数据导入完成', [
                'table_name' => $tableName,
                'record_count' => $recordCount,
            ]);
        }

        Log::info('项目导入: 数据导入结束', [
            'total_imported' => $count,
        ]);

        return $count;
    }

    /**
     * 生成唯一名称
     */
    private function generateUniqueName(string $baseName, string $table): string
    {
        $suffix = 1;
        $newName = $baseName.' ('.$suffix.')';

        while ($this->nameExists($newName, $table)) {
            $suffix++;
            $newName = $baseName.' ('.$suffix.')';
        }

        return $newName;
    }

    /**
     * 生成唯一key
     */
    private function generateUniqueKey(string $baseKey, string $table): string
    {
        $suffix = 1;
        $newKey = $baseKey.'_'.$suffix;

        while ($this->keyExists($newKey, $table)) {
            $suffix++;
            $newKey = $baseKey.'_'.$suffix;
        }

        return $newKey;
    }

    /**
     * 检查名称是否存在
     */
    private function nameExists(string $name, string $table): bool
    {
        try {
            switch ($table) {
                case 'molds':
                    return Mold::where('name', $name)->exists();
                case 'project_functions':
                    return ProjectFunction::where('name', $name)->exists();
                case 'menus':
                    return ProjectMenu::where('title', $name)->exists();
                default:
                    return false;
            }
        } catch (\Exception $e) {
            // 如果查询失败，直接返回false
            return false;
        }
    }

    /**
     * 检查key是否存在
     */
    private function keyExists(string $key, string $table): bool
    {
        try {
            switch ($table) {
                case 'menus':
                    return ProjectMenu::where('key', $key)->exists();
                default:
                    return false;
            }
        } catch (\Exception $e) {
            // 如果查询失败，直接返回false
            return false;
        }
    }

    /**
     * 导入变量
     */
    private function importVariables(ZipArchive $zip): int
    {
        $variablesContent = $zip->getFromName('functions/variables.json');
        if (! $variablesContent) {
            Log::warning('项目导入: 变量文件不存在');

            return 0;
        }

        $variables = json_decode($variablesContent, true);
        if (! $variables) {
            Log::warning('项目导入: 变量数据解析失败');

            return 0;
        }

        Log::info('项目导入: 导入变量', [
            'count' => count($variables),
        ]);

        $count = 0;
        foreach ($variables as $varData) {
            try {
                FunctionEnv::create([
                    'name' => $varData['name'],
                    'value' => $varData['value'] ?? '',
                    'remark' => $varData['remark'] ?? '',
                ]);
                $count++;
                Log::info('项目导入: 变量导入成功', [
                    'variable_name' => $varData['name'],
                ]);
            } catch (\Exception $e) {
                Log::error('项目导入: 变量导入失败', [
                    'variable_name' => $varData['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('项目导入: 变量导入结束', [
            'imported_count' => $count,
        ]);

        return $count;
    }

    /**
     * 导入触发器
     */
    private function importTriggers(ZipArchive $zip): int
    {
        $triggersContent = $zip->getFromName('functions/triggers.json');
        if (! $triggersContent) {
            Log::warning('项目导入: 触发器文件不存在');

            return 0;
        }

        $triggers = json_decode($triggersContent, true);
        if (! $triggers) {
            Log::warning('项目导入: 触发器数据解析失败');

            return 0;
        }

        Log::info('项目导入: 导入触发器', [
            'count' => count($triggers),
        ]);

        $count = 0;
        foreach ($triggers as $triggerData) {
            Log::info('项目导入: 开始导入触发器', [
                'trigger_name' => $triggerData['name'],
            ]);

            // 查找关联的函数
            $function = null;
            if (! empty($triggerData['action_function_slug'])) {
                $function = ProjectFunction::where('slug', $triggerData['action_function_slug'])->first();
            }

            // 查找关联的模型
            $mold = null;
            if (! empty($triggerData['action_mold_slug'])) {
                $table_name = getMcTableName($triggerData['action_mold_slug']);
                $mold = Mold::where('table_name', $table_name)->first();
            }

            // 如果找不到关联的模型，跳过此触发器
            if (! $mold) {
                Log::warning('项目导入: 触发器找不到关联的模型，跳过', [
                    'trigger_name' => $triggerData['name'],
                    'action_mold_slug' => $triggerData['action_mold_slug'] ?? null,
                ]);

                continue;
            }

            // 如果找不到关联的函数，跳过此触发器
            if (! $function) {
                Log::warning('项目导入: 触发器找不到关联的函数，跳过', [
                    'trigger_name' => $triggerData['name'],
                    'action_function_slug' => $triggerData['action_function_slug'] ?? null,
                ]);

                continue;
            }

            try {
                ProjectTrigger::create([
                    'name' => $triggerData['name'],
                    'enabled' => $triggerData['enabled'] ?? true,
                    'trigger_type' => $triggerData['trigger_type'] ?? '',
                    'events' => ($triggerData['events'] ?? []),
                    'mold_id' => $mold->id,
                    'action_function_id' => $function->id,
                    'content_id' => $triggerData['content_id'] ?? null,
                    'watch_function_id' => $triggerData['watch_function_id'] ?? null,
                    'input_schema' => ($triggerData['input_schema'] ?? []),
                    'remark' => $triggerData['remark'] ?? '',
                ]);
                $count++;
                Log::info('项目导入: 触发器导入成功', [
                    'trigger_name' => $triggerData['name'],
                ]);
            } catch (\Exception $e) {
                Log::error('项目导入: 触发器导入失败', [
                    'trigger_name' => $triggerData['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('项目导入: 触发器导入结束', [
            'imported_count' => $count,
        ]);

        return $count;
    }

    /**
     * 导入定时任务
     */
    private function importSchedules(ZipArchive $zip): int
    {
        $schedulesContent = $zip->getFromName('functions/schedules.json');
        if (! $schedulesContent) {
            Log::warning('项目导入: 定时任务文件不存在');

            return 0;
        }

        $schedules = json_decode($schedulesContent, true);
        if (! $schedules) {
            Log::warning('项目导入: 定时任务数据解析失败');

            return 0;
        }

        Log::info('项目导入: 导入定时任务', [
            'count' => count($schedules),
        ]);

        $count = 0;
        foreach ($schedules as $scheduleData) {
            Log::info('项目导入: 开始导入定时任务', [
                'schedule_name' => $scheduleData['name'],
            ]);

            // 查找关联的函数
            $function = null;
            if (! empty($scheduleData['function_slug'])) {
                $function = ProjectFunction::where('slug', $scheduleData['function_slug'])->first();
            }

            // 如果找不到关联的函数，使用 function_id = 0，并强制关闭
            $functionId = $function ? $function->id : 0;

            if (! $function) {
                Log::warning('项目导入: 定时任务找不到关联的函数，将禁用', [
                    'schedule_name' => $scheduleData['name'],
                    'function_slug' => $scheduleData['function_slug'] ?? null,
                ]);
            }

            try {
                ProjectCron::create([
                    'name' => $scheduleData['name'],
                    'enabled' => false,
                    'function_id' => $functionId,
                    'schedule_type' => $scheduleData['schedule_type'] ?? 'cron',
                    'run_at' => $scheduleData['run_at'] ?? null,
                    'cron_expr' => $scheduleData['cron_expr'] ?? '',
                    'timezone' => $scheduleData['timezone'] ?? 'Asia/Shanghai',
                    'payload' => json_encode($scheduleData['payload'] ?? []),
                    'timeout_ms' => $scheduleData['timeout_ms'] ?? null,
                    'max_mem_mb' => $scheduleData['max_mem_mb'] ?? null,
                    'remark' => $scheduleData['remark'] ?? '',
                ]);
                $count++;
                Log::info('项目导入: 定时任务导入成功', [
                    'schedule_name' => $scheduleData['name'],
                    'function_found' => $function ? true : false,
                ]);
            } catch (\Exception $e) {
                Log::error('项目导入: 定时任务导入失败', [
                    'schedule_name' => $scheduleData['name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('项目导入: 定时任务导入结束', [
            'imported_count' => $count,
        ]);

        return $count;
    }

    private function convertSourceModelSlugToId(array $fields, string $fullPrefix): array
    {
        foreach ($fields as &$field) {
            if (! empty($field['sourceModelSlug'])) {
                $tableName = $fullPrefix.$field['sourceModelSlug'];
                $relatedMold = \App\Models\Mold::where('table_name', $tableName)->first();
                if ($relatedMold) {
                    $field['sourceModelId'] = $relatedMold->id;
                    unset($field['sourceModelSlug']);
                }
            }
        }

        return $fields;
    }
}
