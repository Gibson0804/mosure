<?php

namespace App\Services;

use App\Ai\Attributes\AiTool;
use App\Models\ProjectCron;
use App\Models\ProjectCronExecution;
use App\Models\ProjectFunction;
use Carbon\Carbon;

class CloudCronService extends BaseService
{
    #[AiTool(
        name: 'cron_list',
        description: '查询定时任务列表：支持按关键字和启用状态筛选，分页返回。',
        params: [
            'page' => ['type' => 'integer', 'required' => false, 'desc' => '页码，默认1'],
            'pageSize' => ['type' => 'integer', 'required' => false, 'desc' => '每页条数，默认15'],
            'filters' => ['type' => 'object', 'required' => false, 'desc' => '筛选条件：keyword、enabled(true/false)'],
        ]
    )]
    public function list(int $page = 1, int $pageSize = 15, array $filters = []): array
    {
        $q = ProjectCron::query();
        if (($filters['keyword'] ?? '') !== '') {
            $kw = '%'.$filters['keyword'].'%';
            $q->where('name', 'like', $kw);
        }
        if (array_key_exists('enabled', $filters)) {
            $enabled = $filters['enabled'];
            $enabledBool = filter_var($enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($enabledBool === null) {
                if ($enabled === '1' || $enabled === 1) {
                    $enabledBool = true;
                } elseif ($enabled === '0' || $enabled === 0) {
                    $enabledBool = false;
                }
            }
            if ($enabledBool !== null) {
                $q->where('enabled', $enabledBool ? 1 : 0);
            }
        }
        $total = (clone $q)->count();
        $items = $q->orderBy('id', 'desc')->skip(($page - 1) * $pageSize)->take($pageSize)->get();

        return ['data' => $items, 'meta' => ['total' => $total, 'page' => $page, 'page_size' => $pageSize, 'page_count' => (int) ceil($total / $pageSize)]];
    }

    public function getAll(array $filters = []): array
    {
        $q = ProjectCron::query();
        if (($filters['keyword'] ?? '') !== '') {
            $kw = '%'.$filters['keyword'].'%';
            $q->where('name', 'like', $kw);
        }
        if (array_key_exists('enabled', $filters)) {
            $enabled = $filters['enabled'];
            $enabledBool = filter_var($enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($enabledBool === null) {
                if ($enabled === '1' || $enabled === 1) {
                    $enabledBool = true;
                } elseif ($enabled === '0' || $enabled === 0) {
                    $enabledBool = false;
                }
            }
            if ($enabledBool !== null) {
                $q->where('enabled', $enabledBool ? 1 : 0);
            }
        }
        $items = $q->orderBy('id', 'desc')->get();

        return ['data' => $items];
    }

    #[AiTool(
        name: 'cron_detail',
        description: '查询定时任务详情：根据ID获取定时任务完整信息。',
        params: [
            'id' => ['type' => 'integer', 'required' => true, 'desc' => '定时任务ID'],
        ]
    )]
    public function get(int $id): array
    {
        $cron = ProjectCron::findOrFail($id);

        return $cron->toArray();
    }

    #[AiTool(
        name: 'cron_create',
        description: '创建定时任务：绑定一个触发函数(hook类型)，设置调度规则。',
        params: [
            'data' => ['type' => 'object', 'required' => true, 'desc' => '定时任务数据：name(名称)、function_id(绑定的hook函数ID)、schedule_type(once一次性/cron周期性)、cron_expr(cron表达式，周期性时必填)、run_at(一次性执行时间)、payload(传入参数JSON)、enabled(是否启用)'],
        ]
    )]
    public function create(array $data): array
    {
        $data = $this->normalize($data);
        $fn = ProjectFunction::find((int) $data['function_id']);
        if (! $fn) {
            throw new \InvalidArgumentException('绑定的函数不存在');
        }
        if ($fn->type !== 'hook') {
            throw new \InvalidArgumentException('仅允许绑定触发函数');
        }
        $data['next_run_at'] = $this->computeNextRunAt($data);
        $cron = ProjectCron::create($data);

        return $cron->toArray();
    }

    public function update(int $id, array $data): array
    {
        $data = $this->normalize($data);
        $cron = ProjectCron::findOrFail($id);
        if (! empty($data)) {
            $arr = array_merge($cron->toArray(), $data);
            $data['next_run_at'] = $this->computeNextRunAt($arr);
            $cron->update($data);
        }

        return $cron->toArray();
    }

    #[AiTool(
        name: 'cron_toggle',
        description: '切换定时任务启用/禁用状态。',
        params: [
            'id' => ['type' => 'integer', 'required' => true, 'desc' => '定时任务ID'],
        ]
    )]
    public function toggle(int $id)
    {
        $cron = ProjectCron::findOrFail($id);
        $cron->enabled = ! $cron->enabled;
        $cron->save();

        return $cron->toArray();
    }

    public function delete(int $id): bool
    {
        $cron = ProjectCron::find($id);

        return $cron ? $cron->delete() : false;
    }

    public function executions(int $cronId, int $page = 1, int $pageSize = 15): array
    {
        $q = ProjectCronExecution::where('cron_id', $cronId);
        $total = (clone $q)->count();
        $items = $q->orderBy('id', 'desc')->skip(($page - 1) * $pageSize)->take($pageSize)->get();

        return ['data' => $items, 'meta' => ['total' => $total, 'page' => $page, 'page_size' => $pageSize, 'page_count' => (int) ceil($total / $pageSize)]];
    }

    public function dueCrons(int $limit = 50)
    {
        return ProjectCron::where('enabled', 1)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function computeNextRunAt(array $cron)
    {
        $type = (string) ($cron['schedule_type'] ?? 'once');
        $tz = (string) ($cron['timezone'] ?? date_default_timezone_get());

        if ($type === 'once') {
            $runAt = $cron['run_at'] ?? null;
            if (! $runAt) {
                return null;
            }
            $t = Carbon::parse($runAt, $tz);

            return $t->isPast() ? null : $t->toDateTimeString();
        }

        // 使用 Cron 表达式计算下一次执行时间
        $cronExpr = (string) ($cron['cron_expr'] ?? '* * * * *');
        try {
            $expression = new \Cron\CronExpression($cronExpr);
            $nextRun = $expression->getNextRunDate('now', 0, true, $tz);

            return $nextRun ? $nextRun->format('Y-m-d H:i:s') : null;
        } catch (\Throwable $e) {
            // 如果 Cron 表达式无效，默认加一分钟
            return Carbon::now($tz)->addMinute()->toDateTimeString();
        }
    }

    /**
     * 更新定时任务的执行信息
     */
    public function updateExecutionInfo(int $id, array $data): array
    {
        $cron = ProjectCron::findOrFail($id);
        $cron->update($data);

        return $cron->toArray();
    }

    #[AiTool(
        name: 'cron_run_now',
        description: '立即执行定时任务：手动触发一次定时任务绑定的函数。',
        params: [
            'id' => ['type' => 'integer', 'required' => true, 'desc' => '定时任务ID'],
        ]
    )]
    public function runNow(int $id): array
    {
        $prefix = (string) (session('current_project_prefix') ?? '');

        $row = ProjectCron::where('id', $id)->first();
        if (! $row) {
            return ['success' => false, 'message' => '定时任务不存在'];
        }

        $cron = $row->toArray();
        $functionId = (int) ($cron['function_id'] ?? 0);
        $payload = $cron['payload'] ? json_decode($cron['payload'], true) : [];

        $start = microtime(true);
        $status = 'success';
        $error = null;
        $result = null;

        try {
            $functionService = app(FunctionService::class);
            $res = $functionService->runFunctionById($prefix, $functionId, $payload);
            if (($res['code'] ?? 500) !== 200) {
                $status = 'fail';
                $error = (string) ($res['message'] ?? 'error');
            } else {
                $result = $res['data'] ?? null;
            }
        } catch (\Throwable $e) {
            $status = 'fail';
            $error = $e->getMessage();
        } finally {
            $duration = (int) round((microtime(true) - $start) * 1000);
            ProjectCronExecution::insert([
                'cron_id' => (int) $id,
                'function_id' => $functionId,
                'status' => $status,
                'duration_ms' => $duration,
                'error' => $error,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 更新下次执行时间
        $update = ['updated_at' => now()];
        if ($cron['schedule_type'] === 'once') {
            $update['enabled'] = 0;
            $update['next_run_at'] = null;
        } else {
            $next = $this->computeNextRunAt($cron);
            $update['next_run_at'] = $next;
        }
        $row->update($update);

        if ($status === 'fail') {
            return ['success' => false, 'error' => $error, 'message' => '执行失败'];
        }

        return ['success' => true, 'result' => $result, 'duration_ms' => $duration, 'message' => '执行成功'];
    }

    private function normalize(array $d): array
    {
        if (isset($d['payload']) && is_string($d['payload'])) {
            $j = json_decode($d['payload'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $d['payload'] = $j;
            }
        }
        if (isset($d['timeout_ms'])) {
            $d['timeout_ms'] = (int) $d['timeout_ms'];
        }
        if (isset($d['max_mem_mb'])) {
            $d['max_mem_mb'] = (int) $d['max_mem_mb'];
        }

        return $d;
    }

    private function only(array $data, array $fields): array
    {
        $r = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $r[$f] = $data[$f];
            }
        }

        return $r;
    }
}
