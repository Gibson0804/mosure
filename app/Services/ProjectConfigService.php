<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProjectConfigService
{
    private const DEFAULT_CONFIG = [
        'basic' => [
            'display_name' => '',
            'logo' => '',
            'primary_color' => '#1890ff',
            'description' => '',
        ],
        'api' => [
            'enable_ip_whitelist' => false,
            'ip_whitelist' => [],
            'enable_cors' => true,
            'allowed_origins' => [],
            'enable_audit' => true,
            'mask_fields' => ['password', 'token', 'secret'],
        ],
        'mcp' => [
            'enabled' => false,
            'token' => '',
        ],
        'auth' => [
            'enabled' => false,
            'provider' => 'local',
            'allow_register' => false,
            'session_ttl_minutes' => 10080,
            'allowed_origins' => [],
            'require_email_verify' => false,
        ],
        'advanced' => [
            'custom_meta' => [],
        ],
    ];

    public function getConfig(): array
    {
        $prefix = session('current_project_prefix');
        if (! $prefix) {
            throw new \RuntimeException('未选择项目，无法读取配置');
        }
        $this->ensureConfigSchema();

        $result = $this->structuredDefaults();

        $stored = ProjectConfig::query()->get();
        foreach ($stored as $item) {
            $group = $item->config_group;
            $key = $item->config_key;

            if ($group) {
                Arr::set($result, $group.'.'.$key, $this->castValue($item->config_value, $group, $key));
            } else {
                Arr::set($result, $key, $this->castValue($item->config_value, null, $key));
            }
        }

        // 基础信息默认值使用项目表数据填充
        $project = Project::where('prefix', $prefix)->first();
        if ($project) {
            $result['basic']['display_name'] = $result['basic']['display_name'] ?: $project->name;
            $result['basic']['description'] = $result['basic']['description'] ?: ($project->description ?? '');
        }

        return $result;
    }

    /**
     * 类型转换：将字符串值转换为适当的类型
     */
    private function castValue($value, ?string $group, string $key)
    {
        // 布尔类型字段
        $booleanFields = [
            'api.enable_ip_whitelist',
            'api.enable_cors',
            'api.enable_audit',
            'mcp.enabled',
            'auth.enabled',
            'auth.allow_register',
            'auth.require_email_verify',
        ];

        $fieldPath = $group ? $group.'.'.$key : $key;

        if (in_array($fieldPath, $booleanFields, true)) {
            return $value === '1' || $value === 'true' || $value === true;
        }

        // 数组类型字段（JSON 字符串）
        $arrayFields = [
            'api.ip_whitelist',
            'api.allowed_origins',
            'api.mask_fields',
            'auth.allowed_origins',
            'advanced.custom_meta',
        ];

        if (in_array($fieldPath, $arrayFields, true)) {
            if (is_string($value)) {
                $decoded = json_decode($value, true);

                return is_array($decoded) ? $decoded : [];
            }

            return is_array($value) ? $value : [];
        }

        // 数字类型字段
        $numberFields = [
            'auth.session_ttl_minutes',
        ];

        if (in_array($fieldPath, $numberFields, true)) {
            return is_numeric($value) ? (int) $value : $value;
        }

        return $value;
    }

    public function saveConfig(array $configs): array
    {
        $prefix = session('current_project_prefix');
        if (! $prefix) {
            throw new \RuntimeException('未选择项目，无法保存配置');
        }

        if (empty($configs)) {
            return $this->getConfig();
        }
        $this->ensureConfigSchema();

        $current = $this->getConfig();
        $entries = [];
        $targetGroups = [];

        foreach ($configs as $group => $values) {
            if (! is_array($values)) {
                continue;
            }

            if (! array_key_exists($group, $current)) {
                continue;
            }

            if ($group === 'advanced' && isset($values['custom_meta']) && is_string($values['custom_meta'])) {
                $decoded = json_decode($values['custom_meta'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('高级配置 JSON 格式错误');
                }
                $values['custom_meta'] = $decoded ?? [];
            }

            if (! in_array($group, $targetGroups, true)) {
                $targetGroups[] = $group;
            }

            $existingGroup = isset($current[$group]) && is_array($current[$group]) ? $current[$group] : [];
            $mergedGroup = $this->mergeGroupValues($existingGroup, $values);
            $entries = array_merge($entries, $this->flattenConfigEntries([$group => $mergedGroup]));
        }

        if (empty($entries)) {
            return $this->getConfig();
        }

        $project = Project::where('prefix', $prefix)->first();

        DB::transaction(function () use ($entries, $project, $targetGroups) {
            $processedKeys = [];
            $projectUpdates = [];

            foreach ($entries as $entry) {
                $key = $entry['key'];
                $group = $entry['group'];
                $value = $entry['value'];
                $identifier = ($group ?? '_root').'|'.$key;

                ProjectConfig::updateOrCreate(
                    ['config_key' => $key, 'config_group' => $group],
                    ['config_value' => $value]
                );

                $processedKeys[] = $identifier;

                if ($project && $group === 'basic') {
                    if ($key === 'display_name') {
                        $projectUpdates['name'] = $value ?: $project->name;
                    }
                    if ($key === 'description') {
                        $projectUpdates['description'] = $value ?? $project->description;
                    }
                }
            }

            if ($project && ! empty($projectUpdates)) {
                $project->fill($projectUpdates);
                $project->save();
            }

            if (empty($targetGroups)) {
                return;
            }

            $existing = ProjectConfig::query()->get();
            foreach ($existing as $item) {
                $identifier = (($item->config_group ?? '_root').'|'.$item->config_key);
                $groupRoot = $item->config_group ? explode('.', $item->config_group)[0] : null;

                if ($groupRoot === null || ! in_array($groupRoot, $targetGroups, true)) {
                    continue;
                }

                if (! in_array($identifier, $processedKeys, true)) {
                    $item->delete();
                }
            }
        });

        return $this->getConfig();
    }


    private function ensureConfigSchema(): void
    {
        $tableName = (new ProjectConfig)->getTable();
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, ProjectConfig::getTableSchema());

            return;
        }

        try {
            $indexes = Schema::getIndexes($tableName);
            $hasComposite = false;
            $singleConfigKeyUnique = null;
            foreach ($indexes as $index) {
                $columns = array_values((array) ($index['columns'] ?? []));
                $isUnique = (bool) ($index['unique'] ?? false);
                if ($isUnique && $columns === ['config_group', 'config_key']) {
                    $hasComposite = true;
                }
                if ($isUnique && $columns === ['config_key']) {
                    $singleConfigKeyUnique = $index['name'] ?? null;
                }
            }

            if ($singleConfigKeyUnique) {
                Schema::table($tableName, function (Blueprint $table) use ($singleConfigKeyUnique) {
                    $table->dropUnique($singleConfigKeyUnique);
                });
            }

            if (! $hasComposite) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unique(['config_group', 'config_key']);
                });
            }
        } catch (\Throwable $e) {
            Log::warning('项目配置表索引检查/修复失败: '.$e->getMessage(), ['table' => $tableName]);
        }
    }

    private function structuredDefaults(): array
    {
        return json_decode(json_encode(self::DEFAULT_CONFIG), true);
    }

    private function flattenConfigEntries(array $config): array
    {
        $entries = [];

        // 数组类型字段列表（需要转换为 JSON）
        $arrayFields = [
            'api.ip_whitelist',
            'api.allowed_origins',
            'api.mask_fields',
            'auth.allowed_origins',
            'advanced.custom_meta',
        ];

        $flatten = function ($array, $prefix = '', &$output = []) use (&$flatten, $arrayFields) {
            foreach ($array as $key => $value) {
                $fullKey = $prefix === '' ? (string) $key : $prefix.'.'.$key;

                // 如果是数组类型字段，不展开，保留整个数组
                if (in_array($fullKey, $arrayFields, true) && is_array($value)) {
                    $output[$fullKey] = $value;

                    continue;
                }

                if (is_array($value) && $value !== []) {
                    $flatten($value, $fullKey, $output);
                } else {
                    $output[$fullKey] = $value;
                }
            }

            return $output;
        };

        $flat = $flatten($config);

        foreach ($flat as $dotKey => $value) {
            $parts = explode('.', $dotKey);
            $configKey = array_pop($parts);
            $group = count($parts) > 0 ? implode('.', $parts) : null;

            // 如果是数组类型字段，转换为 JSON 字符串
            $fullPath = $group ? $group.'.'.$configKey : $configKey;
            if (in_array($fullPath, $arrayFields, true) && is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $entries[] = [
                'group' => $group,
                'key' => (string) $configKey,
                'value' => $value,
            ];
        }

        return $entries;
    }

    private function mergeGroupValues(array $existing, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (is_array($value)) {
                if ($this->isAssocArray($value)) {
                    $existing[$key] = $this->mergeGroupValues(
                        isset($existing[$key]) && is_array($existing[$key]) ? $existing[$key] : [],
                        $value
                    );
                } else {
                    $existing[$key] = $value;
                }
            } else {
                $existing[$key] = $value;
            }
        }

        return $existing;
    }

    private function isAssocArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
