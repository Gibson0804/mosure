<?php

namespace App\Http\Middleware;

use App\Services\ContentService;
use App\Services\MenuService;
use App\Services\SubjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    private $cService;

    private $sService;

    private $menuService;

    public function __construct(
        ContentService $cService,
        SubjectService $sService,
        MenuService $menuService)
    {
        $this->cService = $cService;
        $this->sService = $sService;
        $this->menuService = $menuService;
    }

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     */
    public function share(Request $request): array
    {
        // 基本用户信息
        $authUser = $request->user();
        $user = [
            'appName' => $authUser?->name ?: 'Admin',
            'name' => $authUser?->name,
            'email' => $authUser?->email,
            'avatar' => $authUser?->avatar,
            'id' => $authUser?->id,
        ];

        $translate = $this->getTranslate();
        $flash = [
            'status' => $request->session()->get('status'),
            'type' => $request->session()->get('status_type', 'success'),
        ];

        // 检查当前路径是否为登录、安装或其他不需要菜单的页面
        $path = $request->path();
        $authPaths = ['login', 'forgot-password', 'reset-password', 'install'];

        // 如果是认证相关页面，不加载菜单
        foreach ($authPaths as $authPath) {
            if (strpos($path, $authPath) === 0) {
                return array_merge(parent::share($request), [
                    'user' => ['appName' => 'Admin'],
                    'translate' => $translate,
                    'flash' => $flash,
                ]);
            }
        }

        if (! $authUser) {
            return array_merge(parent::share($request), [
                'user' => ['appName' => 'Admin'],
                'translate' => $translate,
                'flash' => $flash,
            ]);
        }

        // 对于需要菜单的页面，正常加载
        $menu = $this->getMenu();
        [$mainSelectedKeys, $subSelectedKeys] = $this->getSelectedKeys($request, $menu);

        return array_merge(parent::share($request), [
            'user' => $user,
            'menu' => $menu,
            'mainSelectedKeys' => $mainSelectedKeys,
            'subSelectedKeys' => $subSelectedKeys,
            'project_info' => [
                'prefix' => session('current_project_prefix'),
                'name' => session('current_project_name'),
            ],
            'translate' => $translate,
            'flash' => $flash,
        ]);
    }

    public function getTranslate()
    {
        $translate = [];
        $langFile = file_get_contents(base_path('lang/cn.json'));
        $langData = json_decode($langFile, true);
        foreach ($langData as $key => $value) {
            $translate[$key] = __($key);
        }

        return $translate;
    }

    public function checkSelectedKeys($curPath, $link)
    {
        // 去掉查询参数，只保留路径部分
        $curPath = parse_url($curPath, PHP_URL_PATH) ?: $curPath;

        if ($link === $curPath) {
            return true;
        }

        if (strpos($curPath, 'mold/edit') !== false && $link === '/mold/list') {
            return true;
        }

        if (strpos($curPath, 'content/add') !== false && str_replace('/add/', '/list/', $curPath) === $link) {
            return true;
        }

        // content/edit/123/2 -> content/list/123
        if (strpos($curPath, 'content/edit/') !== false) {
            $moldId = Str::before(Str::after($curPath, '/content/edit/'), '/');
            if ($link === '/content/list/'.$moldId) {
                return true;
            }
        }

        return false;
    }

    public function getSelectedKeys(Request $request, $menu)
    {
        // 给个默认值
        $mainSelectedKeys = 'dashboard';
        $subSelectedKeys = 'dashboard';

        $curPath = $request->getRequestUri();

        foreach ($menu as $menuItem) {
            if (isset($menuItem['children'])) {
                foreach ($menuItem['children'] as $subMenuItem) {
                    if ($this->checkSelectedKeys($curPath, $subMenuItem['link'])) {
                        return [$menuItem['key'], $subMenuItem['key']];
                    }
                }
            } else {
                if ($this->checkSelectedKeys($curPath, $menuItem['link'])) {
                    return [$menuItem['key'], $menuItem['key']];
                }
            }
        }

        return [$mainSelectedKeys, $subSelectedKeys];
    }

    private function getSubjectChildren($key)
    {
        try {
            $subjectMenu = $this->sService->getAllSubjectMenu();
            $subjectChildren = [];
            foreach ($subjectMenu as $cMenu) {
                $subjectChildren[] = [
                    'parent_key' => $key,
                    'key' => $key.'_'.$cMenu['id'],
                    'label' => $cMenu['name'],
                    'link' => '/subject/edit/'.$cMenu['id'],
                ];
            }

            return $subjectChildren;
        } catch (\Exception $e) {
            // 捕获可能的数据库错误并返回空数组
            return [];
        }
    }

    private function getContentChildren($key)
    {
        try {
            $contentMenu = $this->cService->getAllContentMenu();
            $contetnChildren = [];
            foreach ($contentMenu as $cMenu) {
                $contetnChildren[] = [
                    'parent_key' => $key,
                    'key' => $key.'_'.$cMenu['id'],
                    'label' => $cMenu['name'],
                    'link' => '/content/list/'.$cMenu['id'],
                ];
            }

            return $contetnChildren;
        } catch (\Exception $e) {
            // 捕获可能的数据库错误并返回空数组
            return [];
        }
    }

    private function getMenu()
    {
        $menu = $this->menuService->getSidebarMenu();

        // 过滤隐藏菜单（仅侧边栏不显示，菜单管理页需要显示所有）
        return array_values(array_filter($menu, function ($item) {
            return ! isset($item['visible']) || $item['visible'] !== false;
        }));
    }
}
