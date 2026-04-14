<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SysAiAgent;
use App\Support\StructuredLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Overtrue\Pinyin\Pinyin;

class ProjectService
{
    protected $projectTableService;

    protected $pluginService;

    protected $menuService;

    public function __construct(ProjectTableService $projectTableService, PluginService $pluginService, MenuService $menuService)
    {
        $this->projectTableService = $projectTableService;
        $this->pluginService = $pluginService;
        $this->menuService = $menuService;
    }

    private function createProjectAgent(Project $project): SysAiAgent
    {
        return SysAiAgent::create([
            'type' => 'project',
            'identifier' => $project->prefix,
            'user_id' => $project->user_id,
            'project_id' => $project->id,
            'name' => $project->name.'助手',
            'description' => '项目 '.$project->name.' 的 AI 助手',
            'avatar' => '',
            'personality' => [
                'tone' => 'professional',
                'traits' => ['专业', '高效', '严谨'],
                'greeting' => '你好！我是'.$project->name.'助手，有什么可以帮你的？',
            ],
            'dialogue_style' => [
                'length' => 'medium',
                'format' => 'markdown',
                'emoji_usage' => 'normal',
            ],
            'core_prompt' => '你是'.$project->name.'项目的专业助手，帮助用户处理与该项目相关的问题。',
            'enabled' => true,
        ]);
    }

    private function disableProjectAgent(int $projectId): void
    {
        SysAiAgent::where('project_id', $projectId)
            ->where('type', 'project')
            ->update(['enabled' => false]);
    }

    public function repairProjectAgentByPrefix(string $prefix): array
    {
        $project = Project::where('prefix', $prefix)->first();
        if (! $project) {
            return ['success' => false, 'message' => '项目不存在'];
        }

        $agent = SysAiAgent::where('type', 'project')
            ->where('identifier', $prefix)
            ->first();

        if (! $agent) {
            $this->createProjectAgent($project);
        }

        return ['success' => true, 'message' => 'AI 助手已创建'];
    }

    /**
     * 创建新项目
     *
     * @param  array  $data  项目数据
     * @return Project
     *
     * @throws \Exception 当项目创建失败时抛出异常
     */
    public function createProject(array $data)
    {
        try {
            // 自动生成项目前缀
            if (! isset($data['prefix'])) {
                $data['prefix'] = $this->generatePrefix($data['name']);
            }

            // 验证前缀唯一性和格式
            $this->validatePrefix($data['prefix']);

            // 验证模板类型
            $this->validateTemplate($data['template']);

            // 开始数据库事务
            $project = DB::transaction(function () use ($data) {
                // 创建项目记录
                $project = new Project;
                $project->name = $data['name'];
                $project->prefix = $data['prefix'];
                $project->template = $data['template'];
                $project->description = $data['description'] ?? '';
                $project->user_id = Auth::id();
                $project->save();

                return $project;
            });

            // 事务外创建项目相关的数据库表
            // 首先创建项目管理层表（项目前缀_pf_）
            $this->projectTableService->createProjectTables($data['prefix'], $data['template']);

            // 如果选择了预设模板（blog/corporate），通过模板插件完成初始化
            $templatePlugins = [
                'blog' => [
                    'plugin_id' => 'blog_v1',
                    'root_title' => '博客内容管理',
                    'menu_key' => 'plugin.blog_v1',
                    'menu_icon' => 'BookOutlined',
                ],
                'corporate' => [
                    'plugin_id' => 'companysite_v1',
                    'root_title' => '企业信息管理',
                    'menu_key' => 'plugin.companysite_v1',
                    'menu_icon' => 'AppstoreOutlined',
                ],
            ];

            if (isset($templatePlugins[$data['template']])) {
                $templatePlugin = $templatePlugins[$data['template']];

                // 将项目前缀放到 session，后续 Mold 表名计算依赖它
                $previousPrefix = session('current_project_prefix');
                session(['current_project_prefix' => $data['prefix']]);

                try {
                    $pluginId = $templatePlugin['plugin_id'];
                    $plugin = $this->pluginService->get($pluginId);
                    if (! $plugin) {
                        throw new \RuntimeException("未找到项目模板插件：{$pluginId}");
                    }

                    $this->pluginService->install($pluginId, [
                        'menu_placement' => 'independent',
                        'root_title' => $templatePlugin['root_title'],
                    ]);
                    $this->menuService->hideContentMenu();
                    $this->menuService->updateMenu($templatePlugin['menu_key'], [
                        'order' => 101,
                        'icon' => $templatePlugin['menu_icon'],
                    ]);
                } finally {
                    session(['current_project_prefix' => $previousPrefix]);
                }
            }

            // 同步创建项目 AI 助手
            $this->createProjectAgent($project);

            StructuredLogger::info('project.create.success', [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'project_prefix' => $project->prefix,
                'template' => $project->template,
            ]);

            return $project;
        } catch (\Exception $e) {
            StructuredLogger::error('project.create.failed', [
                'project_name' => $data['name'] ?? null,
                'project_prefix' => $data['prefix'] ?? null,
                'template' => $data['template'] ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 生成项目前缀
     *
     * @param  string  $name  项目名称
     * @return string 生成的项目前缀
     */
    public function generatePrefix($name)
    {
        $pinyin = new Pinyin;
        // 获取拼音首字母
        $abbr = $pinyin->abbr($name, '');
        // 去除空格并转为小写
        $prefix = strtolower(str_replace(' ', '', $abbr));

        // 检查前缀是否已存在，如果存在则添加数字
        $originalPrefix = $prefix;
        $counter = 1;

        while ($this->checkPrefix($prefix)) {
            $prefix = $originalPrefix.$counter;
            $counter++;
        }

        return $prefix;
    }

    public function checkPrefix($prefix)
    {
        return Project::where('prefix', $prefix)->exists();
    }

    /**
     * 验证项目前缀
     *
     * @param  string  $prefix  项目前缀
     *
     * @throws \Exception 当项目前缀无效或已存在时抛出异常
     */
    private function validatePrefix($prefix)
    {
        // 检查前缀格式
        if (! preg_match('/^[a-z0-9_]+$/', $prefix)) {
            throw new \Exception('项目前缀只能包含小写字母、数字和下划线');
        }

        // 检查前缀是否已存在
        if ($this->checkPrefix($prefix)) {
            throw new \Exception('项目前缀已被使用，请选择其他前缀');
        }

        // 检查前缀是否与系统保留前缀冲突
        $reservedPrefixes = ['sys', 'admin', 'api', 'app', 'config', 'database', 'public', 'storage', 'vendor'];
        foreach ($reservedPrefixes as $reserved) {
            if ($prefix === $reserved || strpos($prefix, $reserved.'_') === 0) {
                throw new \Exception("项目前缀不能使用系统保留前缀: {$reserved}");
            }
        }
    }

    /**
     * 验证项目模板类型
     *
     * @param  string  $template  模板类型
     *
     * @throws \Exception 当模板类型无效时抛出异常
     */
    private function validateTemplate($template)
    {
        $validTemplates = ['blank', 'blog', 'corporate'];

        if (! in_array($template, $validTemplates)) {
            throw new \Exception('无效的项目模板类型');
        }
    }

    /**
     * 获取项目列表
     *
     * @param  int  $userId  用户ID
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProjects($userId = null)
    {
        $query = Project::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * 获取项目详情
     *
     * @param  int  $id  项目ID
     * @return Project
     */
    public function getProject($id)
    {
        return Project::findOrFail($id);
    }

    /**
     * 更新项目
     *
     * @param  int  $id  项目ID
     * @param  array  $data  项目数据
     * @return Project
     */
    public function updateProject($id, array $data)
    {
        $project = Project::findOrFail($id);

        // 前缀不允许修改，因为已经创建了相关的数据库表
        unset($data['prefix']);

        $project->fill($data);
        $project->save();

        return $project;
    }

    /**
     * 删除项目
     *
     * @param  int  $id  项目ID
     * @return bool
     *
     * @throws \Exception
     */
    public function deleteProject($id)
    {
        try {
            $project = Project::findOrFail($id);
            $prefix = $project->prefix;

            // 禁用项目 AI 助手
            $this->disableProjectAgent($project->id);

            // 删除项目相关的数据库表
            $this->projectTableService->dropProjectTables($prefix);

            // 在单独的事务中删除项目记录
            return DB::transaction(function () use ($project) {
                $deleted = $project->delete();

                StructuredLogger::info('project.delete.success', [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'project_prefix' => $project->prefix,
                ]);

                return $deleted;
            });
        } catch (\Exception $e) {
            StructuredLogger::error('project.delete.failed', [
                'project_id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 选择当前项目
     *
     * @param  int  $id  项目ID
     * @return Project
     */
    public function selectProject($id)
    {
        $project = Project::findOrFail($id);

        // 将当前选中的项目ID和名称存入会话
        session(['current_project_id' => $project->id]);
        session(['current_project_name' => $project->name]);
        session(['current_project_prefix' => $project->prefix]);

        StructuredLogger::info('project.select', [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'project_prefix' => $project->prefix,
        ]);

        return $project;
    }

    /**
     * 获取项目的所有前端入口（插件前端 + 托管页面）
     *
     * @param  string  $projectPrefix  项目前缀
     * @return array
     */
    public function getProjectFrontends($projectPrefix)
    {
        $frontends = [];

        // 1. 插件前端页面
        $pluginInstallations = \App\Models\PluginInstallation::where('status', 'enabled')->get();
        foreach ($pluginInstallations as $installation) {
            $config = $installation->config ?? [];
            if (isset($config['has_frontend']) && $config['has_frontend'] === true) {
                $plugin = $this->pluginService->get($installation->plugin_id);
                if ($plugin) {
                    $frontends[] = [
                        'name' => $plugin->getName(),
                        'url' => "/frontend/{$projectPrefix}/{$installation->plugin_id}/dist/index.html",
                        'type' => 'plugin',
                    ];
                }
            }
        }

        // 2. 托管页面（已发布的）
        try {
            $pages = \App\Models\ProjectPage::where('status', 'published')->get();
            foreach ($pages as $page) {
                $config = is_array($page->config) ? $page->config : [];
                $externalUrl = $config['external_url'] ?? null;
                $frontends[] = [
                    'name' => $page->title ?: $page->slug,
                    'url' => $externalUrl ?: "/sites/{$projectPrefix}/{$page->slug}",
                    'type' => 'hosted',
                    'external_url' => $externalUrl,
                ];
            }
        } catch (\Throwable $e) {
            // 表可能不存在，忽略
        }

        return $frontends;
    }
}
