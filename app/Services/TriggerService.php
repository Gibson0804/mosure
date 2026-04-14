<?php

namespace App\Services;

use App\Models\ProjectFunction;
use App\Models\ProjectTrigger;
use App\Models\ProjectTriggerExecution;
use Illuminate\Support\Facades\Log;

class TriggerService extends BaseService
{
    public function list(int $page = 1, int $pageSize = 15, array $filters = []): array
    {
        $q = ProjectTrigger::query();
        if (! empty($filters['keyword'])) {
            $kw = '%'.$filters['keyword'].'%';
            $q->where('name', 'like', $kw);
        }
        if (isset($filters['enabled'])) {
            $enabled = $filters['enabled'];
            if ($enabled === '1' || $enabled === 1 || $enabled === true) {
                $q->where('enabled', 1);
            }
            if ($enabled === '0' || $enabled === 0 || $enabled === false) {
                $q->where('enabled', 0);
            }
        }
        if (! empty($filters['trigger_type'])) {
            $q->where('trigger_type', $filters['trigger_type']);
        }
        if (isset($filters['mold_id'])) {
            $q->where('mold_id', (int) $filters['mold_id']);
        }
        if (isset($filters['watch_function_id'])) {
            $q->where('watch_function_id', (int) $filters['watch_function_id']);
        }
        $total = (clone $q)->count();
        $items = $q->orderBy('id', 'desc')->skip(($page - 1) * $pageSize)->take($pageSize)->get();

        // 附加模型名称和函数名称
        foreach ($items as $item) {
            // 获取模型名称
            if (! empty($item->mold_id)) {
                $mold = \App\Models\Mold::find($item->mold_id);
                $item->mold_name = $mold ? $mold->name : null;
            }

            // 获取函数名称
            if (! empty($item->watch_function_id)) {
                $fn = ProjectFunction::find($item->watch_function_id);
                $item->function_name = $fn ? $fn->name : null;
            }
        }

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

    public function get(int $id)
    {
        $trigger = ProjectTrigger::find($id);
        if (! $trigger) {
            abort(404);
        }

        return $trigger->toArray();
    }

    public function create(array $data)
    {
        $data = $this->normalize($data);
        $this->assertActionFunction((int) $data['action_function_id']);
        $trigger = ProjectTrigger::create($data);

        return $trigger->toArray();
    }

    public function update(int $id, array $data)
    {
        $data = $this->normalize($data);
        if (isset($data['action_function_id'])) {
            $this->assertActionFunction((int) $data['action_function_id']);
        }
        $trigger = ProjectTrigger::findOrFail($id);
        $trigger->update($data);

        return $trigger->toArray();
    }

    public function toggle(int $id)
    {
        $trigger = ProjectTrigger::findOrFail($id);
        $trigger->enabled = ! $trigger->enabled;
        $trigger->save();

        return $trigger->toArray();
    }

    public function delete(int $id): bool
    {
        $trigger = ProjectTrigger::find($id);

        return $trigger ? $trigger->delete() : false;
    }

    public function executions(int $triggerId, int $page = 1, int $pageSize = 15): array
    {
        $q = ProjectTriggerExecution::where('trigger_id', $triggerId);
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

    public function dispatch(string $event, array $payload): array
    {
        Log::info('触发器服务: 开始分发', [
            'event' => $event,
            'payload' => $payload,
        ]);

        $all = ProjectTrigger::where('enabled', 1)->get()->toArray();
        $stage = $this->stageOf($event);
        if ($stage === null) {
            Log::warning('触发器服务: 无效的事件阶段', ['event' => $event]);

            return [];
        }

        Log::info('触发器服务: 找到启用的触发器', [
            'event' => $event,
            'stage' => $stage,
            'total_enabled_triggers' => count($all),
        ]);

        foreach ($all as $row) {
            $t = (array) $row;
            $tt = (string) ($t['trigger_type'] ?? '');
            $events = $this->asArray($t['events'] ?? null) ?: [];

            Log::info('触发器服务: 检查触发器', [
                'trigger_id' => $t['id'],
                'trigger_name' => $t['name'],
                'trigger_type' => $tt,
                'events' => $events,
                'stage' => $stage,
                'stage_in_events' => in_array($stage, $events, true),
            ]);

            if (! in_array($stage, $events, true)) {
                Log::info('触发器服务: 阶段不在事件中，跳过');

                continue;
            }

            if (str_starts_with($event, 'content.')) {
                $moldId = (int) ($payload['mold_id'] ?? 0);
                if ($tt === 'content_model') {
                    $bindMold = (int) ($t['mold_id'] ?? 0);
                    $match = ($bindMold === 0 || $bindMold === $moldId);
                    Log::info('触发器服务: 模型触发器检查', [
                        'bind_mold_id' => $bindMold,
                        'payload_mold_id' => $moldId,
                        'match' => $match,
                    ]);
                    if (! $match) {
                        continue;
                    }
                } elseif ($tt === 'content_single') {
                    $bindMold = (int) ($t['mold_id'] ?? 0);
                    $bindId = (int) ($t['content_id'] ?? 0);
                    $pid = (int) ($payload['id'] ?? 0);
                    $match = ($bindMold === $moldId && $bindId === $pid);
                    Log::info('触发器服务: 单条内容触发器检查', [
                        'bind_mold_id' => $bindMold,
                        'bind_content_id' => $bindId,
                        'payload_mold_id' => $moldId,
                        'payload_content_id' => $pid,
                        'match' => $match,
                    ]);
                    if (! $match) {
                        continue;
                    }
                } else {
                    Log::info('触发器服务: 未知的触发器类型，跳过', ['trigger_type' => $tt]);

                    continue;
                }
            } elseif (str_starts_with($event, 'function.')) {
                if ($tt !== 'function_exec') {
                    Log::info('触发器服务: 不是函数执行类型，跳过', ['trigger_type' => $tt]);

                    continue;
                }
                $watchId = (int) ($t['watch_function_id'] ?? 0);
                $match = ($watchId > 0 && $watchId === (int) ($payload['function_id'] ?? 0));
                Log::info('触发器服务: 函数执行触发器检查', [
                    'watch_function_id' => $watchId,
                    'payload_function_id' => (int) ($payload['function_id'] ?? 0),
                    'match' => $match,
                ]);
                if (! $match) {
                    continue;
                }
            } else {
                Log::info('触发器服务: 未知的事件前缀，跳过', ['event' => $event]);

                continue;
            }

            Log::info('触发器服务: 执行触发器', [
                'trigger_id' => $t['id'],
                'trigger_name' => $t['name'],
                'action_function_id' => $t['action_function_id'],
            ]);

            $res = $this->executeTrigger($t, $event, $payload);
            Log::info('触发器服务: 触发器执行完成', [
                'trigger_id' => $t['id'],
                'result' => $res,
            ]);

            return $res;
        }

        Log::info('触发器服务: 没有找到匹配的触发器', ['event' => $event]);

        return [];
    }

    private function executeTrigger(array $trigger, string $event, array $payload): array
    {
        $actionId = (int) ($trigger['action_function_id'] ?? 0);
        if ($actionId <= 0) {
            Log::warning('触发器服务: 无效的动作函数ID', [
                'trigger_id' => $trigger['id'],
                'action_function_id' => $actionId,
            ]);

            return [];
        }

        // 校验触发函数：type=hook
        $fn = ProjectFunction::find($actionId);
        if (! $fn) {
            Log::warning('触发器服务: 动作函数不存在', [
                'trigger_id' => $trigger['id'],
                'action_function_id' => $actionId,
            ]);

            return [];
        }

        if ($fn->type !== 'hook') {
            Log::warning('触发器服务: 无效的函数类型', [
                'trigger_id' => $trigger['id'],
                'action_function_id' => $actionId,
                'function_type' => $fn->type,
            ]);

            return [];
        }

        Log::info('触发器服务: 开始执行函数', [
            'trigger_id' => $trigger['id'],
            'trigger_name' => $trigger['name'],
            'action_function_id' => $actionId,
            'function_name' => $fn->name,
            'function_slug' => $fn->slug,
            'event' => $event,
        ]);

        $start = microtime(true);
        $status = 'success';
        $error = null;
        $result = null;

        try {
            $functionService = app(FunctionService::class);
            $prefix = (string) (session('current_project_prefix') ?? '');
            $res = $functionService->runFunctionById($prefix, $actionId, $payload, $event);
            Log::info('触发器服务: 函数执行完成', [
                'trigger_id' => $trigger['id'],
                'action_function_id' => $actionId,
                'result_code' => $res['code'] ?? null,
                'result_message' => $res['message'] ?? null,
                'result_data' => $res['data'] ?? null,
            ]);

            if (($res['code'] ?? 500) !== 200) {
                $status = 'fail';
                $error = (string) ($res['message'] ?? 'error');
                Log::error('触发器服务: 函数执行失败', [
                    'trigger_id' => $trigger['id'],
                    'action_function_id' => $actionId,
                    'error' => $error,
                ]);
            } else {
                $result = $res['data'] ?? null;
                Log::info('触发器服务: 函数执行成功', [
                    'trigger_id' => $trigger['id'],
                    'action_function_id' => $actionId,
                    'result' => $result,
                ]);
            }
            if ($result) {
                return $result;
            }

            return [];
        } catch (\Throwable $e) {
            $status = 'fail';
            $error = $e->getMessage();
            Log::error('触发器服务: 异常', [
                'trigger_id' => $trigger['id'],
                'action_function_id' => $actionId,
                'error' => $error,
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $duration = (int) round((microtime(true) - $start) * 1000);
            try {
                ProjectTriggerExecution::create([
                    'trigger_id' => (int) $trigger['id'],
                    'event' => $event,
                    'status' => $status,
                    'duration_ms' => $duration,
                    'error' => $error,
                    'payload' => $payload,
                    'result' => $result,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to create trigger execution record', [
                    'trigger_id' => (int) $trigger['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [];
    }

    private function stageOf(string $event): ?string
    {
        if (str_starts_with($event, 'content.')) {
            return substr($event, strlen('content.'));
        }
        if (str_starts_with($event, 'function.')) {
            return substr($event, strlen('function.'));
        }

        return null;
    }

    private function normalize(array $data): array
    {
        // 目前仅支持content_model
        $data['trigger_type'] = 'content_model';
        if (isset($data['enabled'])) {
            $data['enabled'] = $data['enabled'] ? 1 : 0;
        }
        if (isset($data['mold_id'])) {
            $data['mold_id'] = (int) $data['mold_id'];
        }
        if (isset($data['content_id']) && $data['content_id'] !== null && $data['content_id'] !== '') {
            $data['content_id'] = (int) $data['content_id'];
        }
        if (isset($data['watch_function_id']) && $data['watch_function_id'] !== null && $data['watch_function_id'] !== '') {
            $data['watch_function_id'] = (int) $data['watch_function_id'];
        }
        if (isset($data['action_function_id'])) {
            $data['action_function_id'] = (int) $data['action_function_id'];
        }
        foreach (['events', 'input_schema'] as $j) {
            if (isset($data[$j]) && is_string($data[$j])) {
                try {
                    $data[$j] = json_decode($data[$j], true);
                } catch (\Throwable $e) {
                }
            }
        }
        // 确保 events 是数组
        if (isset($data['events']) && is_string($data['events'])) {
            $data['events'] = [$data['events']];
        }
        if (! empty($data['events']) && is_array($data['events'])) {
            $data['events'] = array_values(array_unique(array_map('strval', $data['events'])));
        }

        $data['input_schema'] = $this->generateInputSchema($data['events'] ?? [], $data['mold_id'] ?? 0);

        return $data;
    }

    /**
     * 根据 events 和 mold_id 生成 input_schema
     */
    private function generateInputSchema(array $events, int $moldId): array
    {
        if (empty($events)) {
            return [];
        }

        // 获取第一个触发时机
        $firstEvent = is_array($events) ? $events[0] : $events;

        // 获取模型字段
        $fields = [];
        if ($moldId > 0) {
            $mold = \App\Models\Mold::find($moldId);
            $mold->fields = json_decode($mold->fields, true);
            if ($mold && ! empty($mold->fields)) {
                $fields = array_map(function ($field) {
                    return $field['field'] ?? '';
                }, $mold->fields);
                $fields = array_filter($fields);
            }
        }

        // 生成字段示例数据
        $dataFields = [];
        foreach ($fields as $field) {
            $dataFields[$field] = 'xxx';
        }

        // 根据触发时机生成不同的示例
        $example = [
            'mold_id' => $moldId > 0 ? $moldId : 1,
            'id' => rand(1, 1000),
        ];

        if ($firstEvent === 'before_create') {
            $example['data'] = $dataFields;
            $example['before'] = [];
            $example['after'] = [];
        } elseif ($firstEvent === 'after_create') {
            $example['data'] = $dataFields;
            $example['before'] = [];
            $example['after'] = $dataFields;
        } elseif ($firstEvent === 'before_update') {
            $example['data'] = $dataFields;
            $example['before'] = $dataFields;
            $example['after'] = [];
        } elseif ($firstEvent === 'after_update') {
            $example['data'] = [];
            $example['before'] = $dataFields;
            $example['after'] = $dataFields;
        } elseif ($firstEvent === 'before_delete' || $firstEvent === 'after_delete') {
            $example['data'] = [];
            $example['before'] = $dataFields;
            $example['after'] = [];
        } else {
            // 默认情况
            $example['data'] = $dataFields;
            $example['before'] = [];
            $example['after'] = [];
        }

        return $example;
    }

    private function only(array $data, array $fields): array
    {
        $res = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $res[$f] = $data[$f];
            }
        }

        return $res;
    }

    private function asArray($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $d = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $d;
            }
        }

        return null;
    }

    private function assertActionFunction(int $functionId): void
    {
        $fn = ProjectFunction::find($functionId);
        if (! $fn) {
            throw new \InvalidArgumentException('触发函数不存在');
        }
        if ($fn->type !== 'hook') {
            throw new \InvalidArgumentException('触发函数必须为 type=hook');
        }
    }
}
