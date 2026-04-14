<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PageNoticeException;
use App\Http\Controllers\Controller;
use App\Services\FunctionEnvService;
use Illuminate\Http\Request;

class CloudEnvController extends Controller
{
    public function index()
    {
        return viewShow('Manage/CloudEnv');
    }

    public function list(Request $request, FunctionEnvService $service)
    {
        $page = max(1, (int) $request->input('page', 1));
        $pageSize = max(1, (int) $request->input('page_size', 15));
        $keyword = trim((string) $request->input('keyword', ''));
        $ret = $service->paginate($page, $pageSize, $keyword);

        return success($ret);
    }

    public function create(Request $request, FunctionEnvService $service)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'value' => 'required|string',
            'remark' => 'nullable|string|max:255',
        ]);
        try {
            $row = $service->create($data);

            return success($row);
        } catch (PageNoticeException $e) {
            return error([], $e->getMessage());
        }
    }

    public function update(Request $request, int $id, FunctionEnvService $service)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'value' => 'required|string',
            'remark' => 'nullable|string|max:255',
        ]);
        try {
            $row = $service->update($id, $data);

            return success($row);
        } catch (PageNoticeException $e) {
            return error([], $e->getMessage());
        }
    }

    public function delete(int $id, FunctionEnvService $service)
    {
        $ok = $service->delete($id);

        return success($ok);
    }
}
