<?php

namespace App\Services;

use App\Ai\Attributes\AiTool;
use App\Models\ProjectCron;
use App\Models\ProjectFunction;
use App\Models\ProjectTrigger;

class CloudFunctionService extends BaseService
{
    #[AiTool(
        name: 'cloud_function_list',
        description: '查询云函数列表：支持按类型(endpoint/hook)、关键字、启用状态筛选，分页返回。',
        params: [
            'page' => ['type' => 'integer', 'required' => false, 'desc' => '页码，默认1'],
            'pageSize' => ['type' => 'integer', 'required' => false, 'desc' => '每页条数，默认15'],
            'filters' => ['type' => 'object', 'required' => false, 'desc' => '筛选条件：type(endpoint/hook)、keyword、enabled(true/false)'],
        ]
    )]
    public function list(int $page = 1, int $pageSize = 15, array $filters = []): array
    {
        $typeFilter = $filters['type'] ?? null;
        $types = [];
        if (is_array($typeFilter)) {
            $types = array_values(array_filter($typeFilter, fn ($item) => $item !== null && $item !== ''));
        } elseif ($typeFilter !== null && $typeFilter !== '') {
            $types = [(string) $typeFilter];
        }
        $keyword = (string) ($filters['keyword'] ?? '');
        $enabled = $filters['enabled'] ?? null;
        $q = ProjectFunction::query();
        if (! empty($types)) {
            if (count($types) === 1) {
                $q->where('type', $types[0]);
            } else {
                $q->whereIn('type', $types);
            }
        }
        if ($enabled !== null) {
            $enabledBool = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
            $q->where('enabled', $enabledBool ? 1 : 0);
        }
        if ($keyword !== '') {
            $q->where('name', 'like', '%'.$keyword.'%');
        }
        $total = (clone $q)->count();
        $items = $q->orderBy('id', 'desc')->skip(($page - 1) * $pageSize)->take($pageSize)->get();

        foreach ($items as $item) {
            $item->type_display = $item->type === 'hook' ? '触发函数' : 'web函数（HTTP）';

            // 解析 JSON 字段
            foreach (['input_schema', 'output_schema'] as $field) {
                if (isset($item->$field) && is_string($item->$field)) {
                    $decoded = json_decode($item->$field, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $item->$field = $decoded;
                    }
                }
            }
        }

        return [
            'data' => $items->toArray(),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'page_count' => (int) ceil($total / $pageSize),
        ];
    }

    /**
     * 获取所有web云函数的简化信息
     *
     * @return array 包含name、slug、remark的函数列表
     */
    public function getAllWebFunctions(): array
    {
        return ProjectFunction::where('type', 'endpoint')
            ->orderBy('id', 'desc')
            ->get(['name', 'slug', 'remark'])
            ->toArray();
    }

    /**
     * 获取所有触发函数的简化信息
     *
     * @return array 包含name、slug、remark的函数列表
     */
    public function getAllHookFunctions(): array
    {
        return ProjectFunction::where('type', 'hook')
            ->orderBy('id', 'desc')
            ->get(['name', 'slug', 'remark'])
            ->toArray();
    }

    #[AiTool(
        name: 'cloud_function_detail',
        description: '查询云函数详情：根据ID获取云函数完整信息，包括代码、配置等。',
        params: [
            'id' => ['type' => 'integer', 'required' => true, 'desc' => '云函数ID'],
            'type' => ['type' => 'string', 'required' => false, 'desc' => '类型：endpoint或hook，默认endpoint'],
        ]
    )]
    public function get(int $id, string $type = 'endpoint')
    {
        $fn = ProjectFunction::findOrFail($id);
        $arr = $fn->toArray();
        $arr['type'] = $type;

        // 解析 JSON 字段
        foreach (['input_schema', 'output_schema'] as $field) {
            if (isset($arr[$field]) && is_string($arr[$field])) {
                $decoded = json_decode($arr[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $arr[$field] = $decoded;
                }
            }
        }

        return $arr;
    }

    public function create(array $data)
    {
        $prefix = (string) (session('current_project_prefix') ?? '');
        $type = (string) ($data['type'] ?? 'endpoint');
        $data = $this->normalize($data);
        // 保存前进行代码静态扫描（仅非插件函数存在 code 时）
        if (! empty($data['code']) && ($data['plugin_id'] ?? '') == '') {
            $this->assertCodeSafe((string) $data['code']);
        }
        $data['type'] = $type === 'hook' ? 'hook' : 'endpoint';
        if ($data['type'] === 'hook' && empty($data['slug'])) {
            // 表结构中 slug 非空唯一，为 hook 自动生成一个占位 slug，避免冲突
            $data['slug'] = 'hook_'.str_replace('.', '', uniqid('', true));
        }
        $fn = ProjectFunction::create($data);

        return $this->get($fn->id, $type);
    }

    public function update(int $id, array $data)
    {
        $prefix = (string) (session('current_project_prefix') ?? '');
        $type = (string) ($data['type'] ?? 'endpoint');
        $data = $this->normalize($data);
        // 保存前进行代码静态扫描（仅非插件函数存在 code 时）
        if (array_key_exists('code', $data) && $data['code'] && ($data['plugin_id'] ?? '') == '') {
            $this->assertCodeSafe((string) $data['code']);
        }
        if (array_key_exists('type', $data)) {
            $data['type'] = $type === 'hook' ? 'hook' : 'endpoint';
        }
        $fn = ProjectFunction::findOrFail($id);
        $fn->update($data);

        return $this->get($id, $type);
    }

    public function checkBindings(int $id): array
    {
        $crons = ProjectCron::where('function_id', $id)
            ->select('id', 'name')
            ->get()
            ->toArray();

        $triggers = ProjectTrigger::where('action_function_id', $id)
            ->select('id', 'name')
            ->get()
            ->toArray();

        return [
            'crons' => $crons,
            'triggers' => $triggers,
            'has_usage' => ! empty($crons) || ! empty($triggers),
        ];
    }

    public function delete(int $id): bool
    {
        // 删除绑定的定时任务
        \App\Models\ProjectCron::where('function_id', $id)->delete();

        // 删除绑定的触发器
        \App\Models\ProjectTrigger::where('action_function_id', $id)->delete();

        // 删除函数本身
        $fn = ProjectFunction::find($id);

        return $fn ? $fn->delete() : false;
    }

    #[AiTool(
        name: 'cloud_function_toggle',
        description: '切换云函数启用/禁用状态。',
        params: [
            'id' => ['type' => 'integer', 'required' => true, 'desc' => '云函数ID'],
        ]
    )]
    public function toggle(int $id)
    {
        $type = (string) request()->input('type', 'endpoint');
        $fn = ProjectFunction::findOrFail($id);
        $fn->enabled = ! $fn->enabled;
        $fn->save();

        return $this->get($id, $type);
    }

    public function executions(int $functionId, int $page = 1, int $pageSize = 15): array
    {
        // Web云函数：查询 ProjectFunctionExecution
        $q = \App\Models\ProjectFunctionExecution::where('function_id', $functionId);
        $total = (clone $q)->count();
        $items = $q->orderBy('id', 'desc')->skip(($page - 1) * $pageSize)->take($pageSize)->get();

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'page_count' => (int) ceil($total / $pageSize),
            ],
        ];
    }

    /**
     * 获取触发函数的执行记录
     * 同时显示触发器触发和定时任务触发的执行记录
     *
     * @param  int  $functionId  函数ID
     * @param  int  $page  页码
     * @param  int  $pageSize  每页条数
     */
    public function triggerExecutions(int $functionId, int $page = 1, int $pageSize = 15): array
    {
        // 查询触发器触发的执行记录
        $triggerIds = \App\Models\ProjectTrigger::where('action_function_id', $functionId)
            ->pluck('id')
            ->toArray();

        $triggerExecutions = [];
        if (! empty($triggerIds)) {
            $triggerExecutions = \App\Models\ProjectTriggerExecution::whereIn('trigger_id', $triggerIds)
                ->get()
                ->map(function ($item) {
                    $item->trigger_type = 'trigger';

                    return $item;
                })
                ->toArray();
        }

        // 查询定时任务触发的执行记录
        $cronIds = \App\Models\ProjectCron::where('function_id', $functionId)
            ->pluck('id')
            ->toArray();

        $cronExecutions = [];
        if (! empty($cronIds)) {
            $cronExecutions = \App\Models\ProjectCronExecution::whereIn('cron_id', $cronIds)
                ->get()
                ->map(function ($item) {
                    $item->trigger_type = 'cron';

                    return $item;
                })
                ->toArray();
        }

        // 合并两种执行记录
        $allExecutions = array_merge($triggerExecutions, $cronExecutions);

        // 按创建时间倒序排序
        usort($allExecutions, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        // 分页
        $total = count($allExecutions);
        $offset = ($page - 1) * $pageSize;
        $items = array_slice($allExecutions, $offset, $pageSize);

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'page_count' => (int) ceil($total / $pageSize),
            ],
        ];
    }

    private function normalize(array $data): array
    {
        // 将字符串的 JSON 字段转回 array
        foreach (['input_schema', 'output_schema'] as $j) {
            if (isset($data[$j]) && is_string($data[$j])) {
                try {
                    $data[$j] = json_decode($data[$j], true);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
        if (isset($data['rate_limit'])) {
            $data['rate_limit'] = (int) $data['rate_limit'];
        }
        if (isset($data['timeout_ms'])) {
            $data['timeout_ms'] = (int) $data['timeout_ms'];
        }
        if (isset($data['max_mem_mb'])) {
            $data['max_mem_mb'] = (int) $data['max_mem_mb'];
        }

        return $data;
    }

    /**
     * 基础代码静态扫描：阻断高危关键字/函数/Token
     */
    private function assertCodeSafe(string $code): void
    {
        $deny = [
            // 'app(',
            'resolve(', 'container(',
            'DB::', 'Storage::', 'File::', 'Redis::', 'Cache::', 'Auth::', 'Gate::',
            'Http::', // 强制使用注入的 $Http 包装
            'exec', 'shell_exec', 'system(', 'passthru', 'proc_open', 'popen', 'proc_close', 'proc_get_status',
            'curl_exec', 'curl_multi_exec', 'pcntl_', 'posix_', 'dl', 'putenv', 'ini_set',
            'fopen', 'file_put_contents', 'unlink', 'rename', 'mkdir', 'rmdir', 'symlink', 'chmod', 'chown', 'copy',
            'stream_socket_server', 'fsockopen',
        ];
        foreach ($deny as $kw) {
            if (stripos($code, $kw) !== false) {
                throw new \RuntimeException('检测到不允许的代码片段：'.$kw);
            }
        }

        // Token 级别禁止
        $tokens = token_get_all($code);
        $forbidden = [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_NAMESPACE, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG];
        foreach ($tokens as $t) {
            if (is_array($t) && in_array($t[0], $forbidden, true)) {
                throw new \RuntimeException('检测到不允许的语法：'.token_name($t[0]));
            }
        }
    }
}
