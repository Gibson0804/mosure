<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProjectMenu;
use App\Services\MenuService;
use App\Services\MoldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MenuController extends BaseAdminController
{
    private MenuService $menuService;

    private MoldService $moldService;

    public function __construct(MenuService $menuService, MoldService $moldService)
    {
        $this->menuService = $menuService;
        $this->moldService = $moldService;
    }

    public function index()
    {
        return viewShow('Manage/Menus');
    }

    public function tree()
    {
        // 1) 获取所有菜单（固定 + DB）
        $full = $this->menuService->getSidebarMenu();

        // 2) 从 DB 读取顶级菜单
        $items = ProjectMenu::whereNull('parent_id')->orderBy('order')->get();
        $db = [];
        foreach ($items as $it) {
            // 获取子菜单
            $children = ProjectMenu::where('parent_id', $it->id)->orderBy('order')->get();
            $childrenData = [];
            foreach ($children as $child) {
                $childrenData[] = [
                    'id' => $child->id,
                    'parent_id' => $child->parent_id,
                    'key' => $child->key,
                    'title' => $child->title,
                    'label' => $child->title,
                    'icon' => $child->icon,
                    'order' => $child->order,
                    'visible' => $child->visible,
                    'permission_key' => $child->permission_key,
                    'plugin_id' => $child->plugin_id,
                    'area' => $child->area,
                    'target_type' => $child->target_type,
                    'target_payload' => $child->target_payload,
                    'editable' => true,
                ];
            }

            $db[] = [
                'id' => $it->id,
                'parent_id' => $it->parent_id,
                'key' => $it->key,
                'title' => $it->title,
                'label' => $it->title,
                'icon' => $it->icon,
                'order' => $it->order,
                'visible' => $it->visible,
                'permission_key' => $it->permission_key,
                'plugin_id' => $it->plugin_id,
                'area' => $it->area,
                'target_type' => $it->target_type,
                'target_payload' => $it->target_payload,
                'editable' => true,
                'children' => $childrenData,
            ];
        }

        // 3) 添加固定菜单（不可编辑）
        $result = [];
        foreach ($full as $m) {
            if (($m['editable'] ?? true) === false) {
                $m['children'] = [];
                $result[] = $m;
            }
        }

        // 4) 合并 DB 菜单并排序
        $result = array_merge($result, $db);
        usort($result, function ($a, $b) {
            return (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0);
        });

        return success($result);
    }

    public function models(Request $request)
    {
        $excludeParentId = $request->input('exclude_parent_id');
        $molds = $this->moldService->getAllMold();
        $usedModelIds = $this->menuService->getUsedModelIds($excludeParentId);

        $models = [];
        foreach ($molds as $m) {
            $models[] = [
                'id' => $m['id'],
                'name' => $m['name'],
                'mold_type' => $m['mold_type'],
                'table_name' => $m['table_name'],
                'disabled' => in_array($m['id'], $usedModelIds),
            ];
        }

        return success($models);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'title' => 'required|string',
            'key' => 'nullable|string',
            'parent_id' => 'nullable|integer',
            'icon' => 'nullable|string',
            'target_type' => 'required|string',
            'target_payload' => 'nullable|array',
            'order' => 'nullable|integer',
            'visible' => 'nullable|boolean',
            'permission_key' => 'nullable|string',
            'area' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return error($validator->errors(), '参数验证失败');
        }

        $node = $this->menuService->createMenu($data);

        return success($node);
    }

    public function update($id, Request $request)
    {
        $data = $request->all();
        $node = $this->menuService->updateMenuWithChildren($id, $data);

        return success($node);
    }

    public function move($id, Request $request)
    {
        $node = ProjectMenu::findOrFail($id);
        $parentId = $request->input('parent_id');
        $order = $request->input('order');
        $node->parent_id = $parentId;
        if ($order !== null) {
            $node->order = (int) $order;
        }
        $node->save();

        return success($node);
    }

    public function destroy($id)
    {
        // 删除子树
        $this->deleteRecursive((int) $id);

        return success([]);
    }

    private function deleteRecursive(int $id): void
    {
        $children = ProjectMenu::where('parent_id', $id)->get();
        foreach ($children as $ch) {
            $this->deleteRecursive($ch->id);
        }
        ProjectMenu::where('id', $id)->delete();
    }

    public function overrideCore(Request $request)
    {
        $key = $request->input('key'); // content_list or content_single
        if (! in_array($key, ['content_list', 'content_single'], true)) {
            return error([], '非法 key');
        }
        $title = $request->input('title');
        $visible = $request->boolean('visible', true);
        $existing = ProjectMenu::where('key', $key)->first();
        if ($existing) {
            $existing->title = $title ?: $existing->title;
            $existing->visible = $visible;
            $existing->plugin_id = null; // 覆盖项不属于插件
            $existing->save();

            return success($existing);
        }
        $node = ProjectMenu::create([
            'parent_id' => null,
            'title' => $title ?: ($key === 'content_list' ? '内容列表' : '内容单页'),
            'key' => $key,
            'icon' => 'UserOutlined',
            'target_type' => 'group',
            'target_payload' => [],
            'order' => $key === 'content_list' ? 30 : 40,
            'visible' => $visible,
            'permission_key' => null,
            'area' => 'admin',
            'plugin_id' => null,
        ]);

        return success($node);
    }
}
