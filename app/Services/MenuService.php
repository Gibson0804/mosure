<?php

namespace App\Services;

use App\Models\Mold;
use App\Models\ProjectMenu;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;

class MenuService
{
    private ContentService $contentService;

    private SubjectService $subjectService;

    public function __construct(ContentService $contentService, SubjectService $subjectService)
    {
        $this->contentService = $contentService;
        $this->subjectService = $subjectService;
    }

    // 隐藏菜单
    public function hideContentMenu()
    {
        $res = ProjectMenu::whereIn('key', ['content_list', 'content_single'])->update(['visible' => false]);

        return $res;
    }

    // 根据key修改菜单
    public function updateMenu($menuKey, $data)
    {
        $menu = ProjectMenu::where('key', $menuKey)->first();
        if (! $menu) {
            return false;
        }
        $menu->fill($data);
        $menu->save();

        return $menu;
    }

    /**
     * 创建菜单（包含创建二级菜单）
     */
    public function createMenu($data)
    {
        $key = $data['key'] ?? null;
        if (! $key) {
            $key = 'menu_'.bin2hex(random_bytes(4));
        }
        $payload = $data['target_payload'] ?? [];

        // 如果 target_type 是 group 且包含 models，检查模型是否已被使用
        if ($data['target_type'] === 'group' && isset($payload['models']) && is_array($payload['models'])) {
            $checkResult = $this->checkModelsAvailable($payload['models']);
            if (! $checkResult['available']) {
                throw new \Exception($checkResult['message']);
            }
        }

        // 创建一级菜单
        $node = ProjectMenu::create([
            'parent_id' => $data['parent_id'] ?? null,
            'title' => $data['title'],
            'key' => $key,
            'icon' => $data['icon'] ?? null,
            'target_type' => $data['target_type'],
            'target_payload' => $payload,
            'order' => $data['order'] ?? 0,
            'visible' => $data['visible'] ?? true,
            'permission_key' => $data['permission_key'] ?? null,
            'area' => $data['area'] ?? 'admin',
            'plugin_id' => null,
        ]);

        // 如果 target_type 是 group 且包含 models，为每个模型创建二级菜单
        if ($data['target_type'] === 'group' && isset($payload['models']) && is_array($payload['models'])) {
            $this->createSubMenuForModels($node->id, $payload['models']);
        }

        return $node;
    }

    /**
     * 为模型创建二级菜单
     */
    private function createSubMenuForModels($parentId, $modelIds)
    {
        $molds = Mold::whereIn('id', $modelIds)->get();

        foreach ($molds as $mold) {
            // 根据 mold_type 决定二级菜单的 target_type
            if ($mold->mold_type === 'list') {
                $targetType = 'mold_list';
            } elseif ($mold->mold_type === 'single') {
                $targetType = 'mold_single';
            } else {
                continue;
            }

            ProjectMenu::create([
                'parent_id' => $parentId,
                'title' => $mold->name,
                'key' => $targetType.'_'.$mold->id,
                'icon' => null,
                'target_type' => $targetType,
                'target_payload' => ['mold_id' => $mold->id],
                'order' => 0,
                'visible' => true,
                'permission_key' => null,
                'area' => 'admin',
                'plugin_id' => null,
            ]);
        }
    }

    /**
     * 更新菜单（包含更新二级菜单）
     */
    public function updateMenuWithChildren($id, $data)
    {
        $node = ProjectMenu::findOrFail($id);
        $node->fill([
            'title' => $data['title'] ?? $node->title,
            'icon' => $data['icon'] ?? $node->icon,
            'order' => $data['order'] ?? $node->order,
            'visible' => $data['visible'] ?? $node->visible,
            'permission_key' => $data['permission_key'] ?? $node->permission_key,
            'area' => $data['area'] ?? $node->area,
        ]);
        if (isset($data['target_payload'])) {
            $node->target_payload = $data['target_payload'];
        }
        $node->save();

        // 如果 target_type 是 group 且包含 models，同步更新二级菜单
        $payload = $data['target_payload'] ?? [];
        if ($node->target_type === 'group' && isset($payload['models']) && is_array($payload['models'])) {
            // 检查新选中的模型是否已被其他菜单使用
            $checkResult = $this->checkModelsAvailable($payload['models'], $node->id);
            if (! $checkResult['available']) {
                throw new \Exception($checkResult['message']);
            }

            $this->syncSubMenuForModels($node->id, $payload['models']);
        }

        return $node;
    }

    /**
     * 同步二级菜单
     */
    private function syncSubMenuForModels($parentId, $selectedModelIds)
    {
        // 获取当前二级菜单
        $existingChildren = ProjectMenu::where('parent_id', $parentId)->get();
        $existingModelIds = [];

        foreach ($existingChildren as $child) {
            $childMoldId = $child->target_payload['mold_id'] ?? null;
            if ($childMoldId) {
                $existingModelIds[] = $childMoldId;

                // 如果该模型不再被选中，删除此二级菜单
                if (! in_array($childMoldId, $selectedModelIds)) {
                    $child->delete();
                }
            }
        }

        // 为新选中的模型创建二级菜单
        $newModelIds = array_diff($selectedModelIds, $existingModelIds);
        if (! empty($newModelIds)) {
            $this->createSubMenuForModels($parentId, $newModelIds);
        }
    }

    /**
     * 检查模型是否已被其他一级菜单使用
     */
    public function getUsedModelIds($excludeParentId = null)
    {
        $query = ProjectMenu::whereNotNull('parent_id')
            ->where('target_type', 'like', 'mold_%');

        // 如果指定了要排除的父菜单ID，则排除该菜单下的二级菜单
        if ($excludeParentId !== null) {
            $query->where('parent_id', '!=', $excludeParentId);
        }

        $children = $query->get();
        $usedModelIds = [];

        foreach ($children as $child) {
            $moldId = $child->target_payload['mold_id'] ?? null;
            if ($moldId) {
                $usedModelIds[] = $moldId;
            }
        }

        return $usedModelIds;
    }

    /**
     * 检查模型是否可用（未被其他一级菜单使用）
     */
    public function checkModelsAvailable($modelIds, $excludeParentId = null)
    {
        $usedModelIds = $this->getUsedModelIds($excludeParentId);
        $unavailableModelIds = array_intersect($modelIds, $usedModelIds);

        if (! empty($unavailableModelIds)) {
            $molds = Mold::whereIn('id', $unavailableModelIds)->get();
            $modelNames = $molds->pluck('name')->toArray();

            return [
                'available' => false,
                'message' => '以下模型已被其他菜单使用：'.implode('、', $modelNames),
            ];
        }

        return ['available' => true];
    }

    public function getSidebarMenu(): array
    {
        // 基础固定菜单（可被覆盖：content_list, content_single）
        $core = $this->getCoreMenu();
        $db = $this->getDbMenus();

        // 将 DB 顶层（非覆盖项）注入
        foreach ($db as $node) {
            if (($node['parent_id'] ?? null) === null) {
                $extra = $node['extra'] ?? [];
                $appendTo = is_array($extra) ? ($extra['append_to_core'] ?? null) : null;
                if (in_array($appendTo, ['content_list', 'content_single'], true)) {
                    foreach ($core as &$c) {
                        if ($c['key'] === $appendTo) {
                            $c['children'] = $c['children'] ?? [];
                            $c['children'][] = $node;
                            // 为追加进核心分组的节点生成默认 link（取首个子或保持自身）
                            if (! isset($c['link']) || ! $c['link']) {
                                $c['link'] = $node['link'] ?? ($c['link'] ?? null);
                            }

                            continue 2;
                        }
                    }
                }
                // 默认作为根节点追加
                $core[] = $node;
            }
        }

        // 按 order 排序（不过滤隐藏项，由前端决定是否显示）
        usort($core, function ($a, $b) {
            return (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0);
        });

        return $core;
    }

    private function getCoreMenu(): array
    {
        // 固定菜单（不可编辑）
        // content_list 和 content_single 已移至 DB，由 ProjectTableService 初始化
        $menu = [
            ['key' => 'dashboard', 'label' => __('dashboard'), 'icon' => 'DashboardOutlined', 'link' => '/dashboard', 'order' => 100, 'editable' => false],
            ['key' => 'mold', 'label' => __('content_type_management'), 'icon' => 'DatabaseOutlined', 'link' => '/mold/add', 'order' => 200, 'editable' => false, 'children' => [
                ['parent_key' => 'mold', 'key' => 'mold.add', 'label' => __('content_type_add'), 'link' => '/mold/add'],
                ['parent_key' => 'mold', 'key' => 'mold.list', 'label' => __('content_type_list'), 'link' => '/mold/list'],
            ]],
            ['key' => 'media', 'label' => __('media'), 'icon' => 'FileImageOutlined', 'link' => '/media', 'order' => 500, 'editable' => false],
            ['key' => 'cloud_fn', 'label' => '云函数管理', 'icon' => 'ApiOutlined', 'order' => 600, 'editable' => false, 'children' => [
                ['parent_key' => 'cloud-fn', 'key' => 'cloud-fn.web', 'label' => 'Web函数', 'link' => '/manage/web-functions'],
                ['parent_key' => 'cloud-fn', 'key' => 'cloud-fn.trigger', 'label' => '触发函数', 'link' => '/manage/trigger-functions'],
                ['parent_key' => 'cloud-fn', 'key' => 'cloud-fn.env', 'label' => '环境变量', 'link' => '/manage/cloud-env'],
                ['parent_key' => 'cloud-fn', 'key' => 'cloud-fn.triggers', 'label' => '触发器', 'link' => '/manage/triggers'],
                ['parent_key' => 'cloud-fn', 'key' => 'cloud-fn.crons', 'label' => '定时任务', 'link' => '/manage/cloud-crons'],
            ], 'link' => '/manage/web-functions'],
            ['key' => 'plugins', 'label' => '插件', 'icon' => 'AppstoreOutlined', 'order' => 700, 'editable' => false, 'children' => [
                ['parent_key' => 'plugins', 'key' => 'plugins.manage', 'label' => '插件管理', 'link' => '/plugins'],
                ['parent_key' => 'plugins', 'key' => 'plugins.marketplace', 'label' => '插件市场', 'link' => '/plugins/marketplace'],
            ], 'link' => '/plugins'],
            ['key' => 'page_hosting', 'label' => '前端托管', 'icon' => 'CodeOutlined', 'link' => '/manage/page-hosting', 'order' => 750, 'editable' => false],
            ...($this->isProjectAuthEnabled() ? [[
                'key' => 'project_auth',
                'label' => '项目用户',
                'icon' => 'TeamOutlined',
                'link' => '/manage/project-auth',
                'order' => 800,
                'editable' => false,
            ]] : []),
            ['key' => 'manage', 'label' => __('project_manage'), 'icon' => 'ProjectOutlined', 'order' => 900, 'editable' => false, 'children' => [
                ['parent_key' => 'manage', 'key' => 'manage.export', 'label' => '项目导出/导入', 'link' => '/manage/export'],
                ['parent_key' => 'manage', 'key' => 'manage.api-keys', 'label' => __('api_key_manage'), 'link' => '/api-key'],
                ['parent_key' => 'manage', 'key' => 'manage.api-docs', 'label' => __('api_doc'), 'link' => '/api-docs', 'icon' => 'ApiOutlined'],
                ['parent_key' => 'manage', 'key' => 'manage.audit-logs', 'label' => __('audit_logs'), 'link' => '/manage/audit-logs'],
                ['parent_key' => 'manage', 'key' => 'manage.sys-tasks', 'label' => '任务中心', 'link' => '/manage/sys-tasks', 'icon' => 'UnorderedListOutlined'],
                ['parent_key' => 'manage', 'key' => 'manage.project-config', 'label' => __('project_config'), 'link' => '/manage/project-config'],
                ['parent_key' => 'manage', 'key' => 'manage.menus', 'label' => '菜单管理', 'link' => '/manage/menus'],
            ], 'link' => '/manage/export'],
        ];

        return $menu;
    }


    private function isProjectAuthEnabled(): bool
    {
        try {
            return (bool) Arr::get(app(ProjectConfigService::class)->getConfig(), 'auth.enabled', false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getDbMenus(): array
    {
        try {
            $model = new ProjectMenu;
            $table = $model->getTable();
            if (! Schema::hasTable($table)) {
                return [];
            }
            $items = ProjectMenu::orderBy('order')->get();
        } catch (\Throwable $e) {
            // 表不存在或其他异常，返回空以使用硬编码菜单
            return [];
        }
        // 转换为 array
        $nodes = [];
        foreach ($items as $it) {
            $node = [
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
                'link' => $this->resolveLink($it->toArray()),
                'editable' => true,
            ];
            $nodes[] = $node;
        }
        // 构建树（仅返回根 + 一级/二级树；在 getSidebarMenu 决定如何拼装）
        $byId = [];
        foreach ($nodes as $n) {
            $byId[$n['id']] = $n + ['children' => []];
        }
        foreach ($byId as $id => $n) {
            $pid = $n['parent_id'];
            if ($pid && isset($byId[$pid])) {
                $byId[$pid]['children'][] = $n;
            }
        }
        // 只返回顶层
        $root = [];
        foreach ($byId as $id => $n) {
            if (! $n['parent_id']) {
                // 为 content_list 和 content_single 动态添加子菜单
                if ($n['key'] === 'content_list') {
                    $n['children'] = $this->getContentChildren('list'); // mold_type=list 列表类型
                    $n['link'] = $n['children'][0]['link'] ?? '';
                } elseif ($n['key'] === 'content_single') {
                    $n['children'] = $this->getContentChildren('single'); // mold_type=single 单页类型
                    $n['link'] = $n['children'][0]['link'] ?? '';
                } else {

                }
                $root[] = $n;
            }
        }

        return $root;
    }

    private function getContentChildren(string $moldType): array
    {
        try {
            // 查询指定类型的模型
            $molds = Mold::select('id', 'name', 'mold_type')
                ->where('mold_type', $moldType)
                ->get()
                ->toArray();

            $children = [];
            foreach ($molds as $m) {
                // 根据 mold_type 决定链接和 parent_key
                if ($m['mold_type'] === 'list') {
                    // 列表类型
                    $children[] = [
                        'parent_key' => 'content_list',
                        'key' => 'content_list_'.$m['id'],
                        'label' => $m['name'],
                        'link' => '/content/list/'.$m['id'],
                    ];
                } elseif ($m['mold_type'] === 'single') {
                    // 单页类型
                    $children[] = [
                        'parent_key' => 'content_single',
                        'key' => 'content_single_'.$m['id'],
                        'label' => $m['name'],
                        'link' => '/subject/edit/'.$m['id'],
                    ];
                }
            }

            return $children;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function resolveLink(array $node): ?string
    {

        switch ($node['target_type']) {
            case 'mold_list':
                $moldId = $node['target_payload']['mold_id'] ?? null;

                return $moldId ? '/content/list/'.$moldId : null;
            case 'mold_single':
                $moldId = $node['target_payload']['mold_id'] ?? null;

                return $moldId ? '/subject/edit/'.$moldId : null;
            case 'route':
                return $node['target_payload']['route'] ?? null;
            case 'url':
                return $node['target_payload']['url'] ?? null;
            default:
                return $node['target_payload']['link'] ?? ($node['link'] ?? null);
        }
    }

    /**
     * 获取用户自定义的一级菜单（用于导出）
     */
    public function getUserDefinedMenus(): array
    {
        try {
            $model = new ProjectMenu;
            $table = $model->getTable();
            if (! Schema::hasTable($table)) {
                return [];
            }

            // 获取用户自定义的一级菜单（parent_id 为 null，plugin_id 为 null）
            // 排除系统固定菜单（content_list, content_single）
            $menus = ProjectMenu::whereNull('parent_id')
                ->whereNull('plugin_id')
                ->whereNotIn('key', ['content_list', 'content_single'])
                ->orderBy('order')
                ->get();

            return $menus->map(function ($menu) {
                return [
                    'id' => $menu->id,
                    'title' => $menu->title,
                    'key' => $menu->key,
                ];
            })->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
