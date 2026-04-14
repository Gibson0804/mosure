<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TriggerService;
use Illuminate\Http\Request;

class TriggerController extends Controller
{
    public function index()
    {
        return viewShow('Manage/Triggers');
    }

    public function list(Request $request, TriggerService $service)
    {
        $page = max(1, (int) $request->input('page', 1));
        $pageSize = max(1, (int) $request->input('page_size', 15));
        $filters = (array) $request->input('filter', []);
        $data = $service->list($page, $pageSize, $filters);

        return success($data);
    }

    public function detail(Request $request, int $id, TriggerService $service)
    {
        $fn = $service->get($id);

        return success($fn);
    }

    public function create(Request $request, TriggerService $service)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_type' => 'required|string|in:content_model,content_single,function_exec',
            'events' => 'required|array|min:1',
            'mold_id' => 'nullable|integer',
            'content_id' => 'nullable|integer',
            'watch_function_id' => 'nullable|integer',
            'action_function_id' => 'required|integer',
        ]);
        try {
            $fn = $service->create($request->all());

            return success($fn);
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    public function update(Request $request, int $id, TriggerService $service)
    {
        try {
            $fn = $service->update($id, $request->all());

            return success($fn);
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    public function toggle(int $id, TriggerService $service)
    {
        try {
            $fn = $service->toggle($id);

            return success($fn);
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    public function delete(int $id, TriggerService $service)
    {
        $ok = $service->delete($id);

        return $ok ? success([]) : error([], '删除失败');
    }

    public function executions(Request $request, int $id, TriggerService $service)
    {
        $page = max(1, (int) $request->input('page', 1));
        $pageSize = max(1, (int) $request->input('page_size', 15));
        $data = $service->executions($id, $page, $pageSize);

        return success($data);
    }
}
