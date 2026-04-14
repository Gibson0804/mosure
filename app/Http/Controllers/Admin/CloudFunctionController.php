<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CloudFunctionService;
use App\Services\FunctionService;
use Illuminate\Http\Request;

class CloudFunctionController extends Controller
{
    public function webFunctions()
    {
        return viewShow('Manage/WebFunctions');
    }

    public function triggerFunctions()
    {
        return viewShow('Manage/TriggerFunctions');
    }

    public function code(Request $request, int $id)
    {
        $type = (string) $request->input('type', 'endpoint');

        return viewShow('Manage/FunctionCode', [
            'id' => $id,
            'type' => $type,
        ]);
    }

    public function detail(Request $request, int $id, CloudFunctionService $service)
    {
        $type = (string) $request->input('type', 'endpoint');
        $fn = $service->get($id, $type);

        return success($fn);
    }

    public function list(Request $request, CloudFunctionService $service)
    {
        $page = max(1, (int) $request->input('page', 1));
        $pageSize = max(1, (int) $request->input('page_size', 15));
        $filters = (array) $request->input('filter', []);
        $data = $service->list($page, $pageSize, $filters);

        return success($data);
    }

    public function create(Request $request, CloudFunctionService $service)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:endpoint,hook,cron',
            'slug' => 'required_if:type,endpoint|string|max:255',
            'input_schema' => 'nullable|array',
            'output_schema' => 'nullable|array',
        ]);
        try {
            $fn = $service->create($request->all());

            return success($fn);
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    public function update(Request $request, int $id, CloudFunctionService $service)
    {
        try {
            $fn = $service->update($id, $request->all());

            return success($fn);
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    public function toggle(int $id, CloudFunctionService $service)
    {
        try {
            $fn = $service->toggle($id);

            return success($fn);
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    public function checkBindings(int $id, CloudFunctionService $service)
    {
        $bindings = $service->checkBindings($id);

        return success($bindings);
    }

    public function delete(int $id, CloudFunctionService $service)
    {
        $ok = $service->delete($id);

        return $ok ? success([]) : error([], '删除失败');
    }

    public function executions(Request $request, int $id, CloudFunctionService $service)
    {
        $page = max(1, (int) $request->input('page', 1));
        $pageSize = max(1, (int) $request->input('page_size', 15));
        $data = $service->executions($id, $page, $pageSize);

        return success($data);
    }

    /**
     * 获取触发函数的执行记录（独立接口）
     */
    public function triggerExecutions(Request $request, int $id, CloudFunctionService $service)
    {
        $page = max(1, (int) $request->input('page', 1));
        $pageSize = max(1, (int) $request->input('page_size', 15));
        $data = $service->triggerExecutions($id, $page, $pageSize);

        return success($data);
    }

    /**
     * 管理端测试触发函数：本地执行 PHP 代码段，不走 HTTP。
     */
    public function test(Request $request, int $id, FunctionService $functionService)
    {
        $prefix = (string) (session('current_project_prefix') ?? '');
        $payload = $request->input('payload', []);
        if (! is_array($payload)) {
            if (is_string($payload) && $payload !== '') {
                $decoded = json_decode($payload, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload = $decoded;
                } else {
                    return error([], 'payload 需为 JSON 对象');
                }
            } else {
                $payload = [];
            }
        }
        $res = $functionService->runFunctionById($prefix, $id, $payload, null, false);
        if (($res['code'] ?? 500) === 200) {
            return success($res['data'] ?? []);
        }

        return error([], (string) ($res['message'] ?? 'error'));
    }

    /**
     * 管理端测试：通过 slug 调用函数（允许测试禁用的函数）
     */
    public function invoke(Request $request, string $slug, FunctionService $functionService)
    {
        $prefix = (string) (session('current_project_prefix') ?? '');
        [$code, $payload] = $functionService->invokeBySlugForAdmin($request, $prefix, $slug);

        return response()->json($payload, $code);
    }
}
