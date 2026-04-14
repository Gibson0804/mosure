<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CloudCronService;
use Illuminate\Http\Request;

class CloudCronController extends Controller
{
    public function index()
    {
        return viewShow('Manage/CloudCrons');
    }

    public function list(Request $request, CloudCronService $service)
    {
        $page = max(1, (int) $request->input('page', 1));
        $pageSize = max(1, (int) $request->input('page_size', 15));
        $filters = (array) $request->input('filter', []);

        return success($service->list($page, $pageSize, $filters));
    }

    public function get(Request $request, int $id, CloudCronService $service)
    {
        return success($service->get($id));
    }

    public function create(Request $request, CloudCronService $service)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'enabled' => 'nullable|boolean',
            'function_id' => 'required|integer',
            'schedule_type' => 'required|string|in:once,cron',
            'run_at' => 'nullable|date',
            'cron_expr' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:64',
            'payload' => 'nullable',
            'timeout_ms' => 'nullable|integer|min:100',
            'max_mem_mb' => 'nullable|integer|min:16',
            'remark' => 'nullable|string|max:500',
        ]);
        try {
            return success($service->create($request->all()));
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    public function update(Request $request, int $id, CloudCronService $service)
    {
        try {
            return success($service->update($id, $request->all()));
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    public function toggle(int $id, CloudCronService $service)
    {
        try {
            return success($service->toggle($id));
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    public function delete(int $id, CloudCronService $service)
    {
        return $service->delete($id) ? success([]) : error([], '删除失败');
    }

    public function executions(Request $request, int $id, CloudCronService $service)
    {
        $page = max(1, (int) $request->input('page', 1));
        $pageSize = max(1, (int) $request->input('page_size', 15));

        return success($service->executions($id, $page, $pageSize));
    }

    public function runNow(int $id, CloudCronService $service)
    {
        $result = $service->runNow($id);
        if (! $result['success']) {
            return error(['error' => $result['error'] ?? null], $result['message']);
        }

        return success(['result' => $result['result'], 'duration_ms' => $result['duration_ms']], $result['message']);
    }
}
