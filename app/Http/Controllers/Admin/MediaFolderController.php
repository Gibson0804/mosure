<?php

namespace App\Http\Controllers\Admin;

use App\Services\MediaFolderService;
use Illuminate\Http\Request;

class MediaFolderController extends BaseAdminController
{
    protected MediaFolderService $service;

    public function __construct(MediaFolderService $service)
    {
        $this->service = $service;
    }

    public function tree(Request $request)
    {
        $tree = $this->service->getTree();

        return success($tree);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'parent_id' => 'nullable|integer',
        ]);
        $folder = $this->service->create($data['name'], $data['parent_id'] ?? null);

        return success($folder, '创建成功');
    }

    public function rename(Request $request, int $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
        ]);
        $folder = $this->service->rename($id, $data['name']);

        return success($folder, '重命名成功');
    }

    public function move(Request $request, int $id)
    {
        $data = $request->validate([
            'to_parent_id' => 'nullable|integer',
        ]);
        $folder = $this->service->move($id, $data['to_parent_id'] ?? null);

        return success($folder, '移动成功');
    }

    public function destroy(Request $request, int $id)
    {
        $data = $request->validate([
            'strategy' => 'nullable|in:keep,move',
            'target_folder_id' => 'nullable|integer',
        ]);
        try {
            $this->service->delete($id, $data['strategy'] ?? 'keep', $data['target_folder_id'] ?? null);

            return success(true, '删除成功');
        } catch (\Exception $e) {
            return error([], $e->getMessage());
        }
    }
}
