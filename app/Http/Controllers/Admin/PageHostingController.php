<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSysTaskJob;
use App\Models\Mold;
use App\Models\SysTask;
use App\Repository\SysTaskRepository;
use App\Services\PageHostingService;
use App\Services\PluginService;
use Illuminate\Http\Request;

class PageHostingController extends Controller
{
    /**
     * 管理页面视图
     */
    public function index()
    {
        return viewShow('Manage/PageHosting');
    }

    /**
     * 列表：托管页面 + 插件前端页面
     */
    public function list(Request $request, PageHostingService $service, PluginService $pluginService)
    {
        $page = max(1, (int) $request->input('page', 1));
        $pageSize = max(1, (int) $request->input('page_size', 15));
        $filters = (array) $request->input('filter', []);

        $result = $service->list($page, $pageSize, $filters);

        // 获取插件前端页面
        $pluginPages = $this->getPluginFrontendPages($pluginService);

        $result['plugin_pages'] = $pluginPages;

        return success($result);
    }

    /**
     * 获取单个托管页面详情
     */
    public function get(Request $request, string $slug, PageHostingService $service)
    {
        return success($service->get($slug));
    }

    /**
     * 创建托管页面
     */
    public function create(Request $request, PageHostingService $service)
    {
        $request->validate([
            'slug' => 'required|string|max:100|regex:/^[a-z0-9][a-z0-9-]*$/',
            'title' => 'required|string|max:200',
            'html_content' => 'nullable|string',
            'description' => 'nullable|string',
            'external_url' => 'nullable|url|max:500',
            'page_type' => 'nullable|string|in:single,spa',
            'status' => 'nullable|string|in:draft,published',
        ]);

        try {
            $data = $request->all();
            $data['created_by'] = auth()->id();

            return success($service->create($data));
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    /**
     * 更新托管页面
     */
    public function update(Request $request, string $slug, PageHostingService $service)
    {
        $request->validate([
            'title' => 'nullable|string|max:200',
            'html_content' => 'nullable|string',
            'description' => 'nullable|string',
            'external_url' => 'nullable|url|max:500',
            'page_type' => 'nullable|string|in:single,spa',
            'status' => 'nullable|string|in:draft,published',
        ]);

        try {
            return success($service->update($slug, $request->all()));
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    /**
     * 切换发布状态
     */
    public function toggle(string $slug, PageHostingService $service)
    {
        try {
            return success($service->toggle($slug));
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    /**
     * 删除托管页面
     */
    public function delete(string $slug, PageHostingService $service)
    {
        try {
            return success($service->deletePage($slug));
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    /**
     * ZIP 上传部署
     */
    public function deployZip(Request $request, PageHostingService $service)
    {
        $request->validate([
            'slug' => 'required|string|max:100|regex:/^[a-z0-9][a-z0-9-]*$/',
            'title' => 'required|string|max:200',
            'file' => 'required|file|mimes:zip|max:51200',
            'description' => 'nullable|string',
            'external_url' => 'nullable|url|max:500',
        ]);

        try {
            $zipPath = $request->file('file')->getRealPath();
            $meta = [
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'external_url' => $request->input('external_url'),
                'created_by' => auth()->id(),
            ];

            return success($service->deployZip($request->input('slug'), $zipPath, $meta));
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    /**
     * AI 生成页面：创建异步任务，由 PageGenerationTaskProcessor 独立处理
     */
    public function aiGenerate(Request $request, SysTaskRepository $taskRepo)
    {
        $request->validate([
            'prompt' => 'required|string|max:2000',
        ]);

        $prompt = trim($request->input('prompt'));

        $task = $taskRepo->createTask([
            'domain' => 'page_hosting',
            'type' => SysTask::TYPE_PAGE_GENERATION,
            'status' => SysTask::STATUS_PENDING,
            'title' => 'AI生成前端页面',
            'payload' => [
                'prompt' => $prompt,
            ],
            'requested_by' => auth()->id(),
        ]);

        ProcessSysTaskJob::dispatch((int) $task->id);

        return success([
            'task_id' => $task->id,
            'message' => 'AI 正在生成页面，请稍候...',
        ]);
    }

    /**
     * 获取当前项目的内容模型摘要（供前端生成 AI 提示词）
     */
    public function modelsSummary()
    {
        try {
            $molds = Mold::all();
            $models = $molds->map(function ($mold) {
                $fields = is_array($mold->fields) ? $mold->fields : json_decode($mold->fields ?? '[]', true);
                $fieldsSummary = [];
                if (is_array($fields)) {
                    foreach ($fields as $f) {
                        if (! is_array($f) || ($f['type'] ?? '') === 'dividingLine') {
                            continue;
                        }
                        $fieldsSummary[] = [
                            'field' => $f['field'] ?? '',
                            'label' => $f['label'] ?? '',
                            'type' => $f['type'] ?? '',
                        ];
                    }
                }

                return [
                    'name' => $mold->name,
                    'table_name' => $mold->table_name,
                    'mold_type' => $mold->mold_type,
                    'fields' => $fieldsSummary,
                ];
            })->toArray();

            return success(['models' => $models]);
        } catch (\Throwable $e) {
            return success(['models' => []]);
        }
    }

    /**
     * 获取插件前端页面列表（只读展示）
     */
    private function getPluginFrontendPages(PluginService $pluginService): array
    {
        $pages = [];
        try {
            $installedPlugins = $pluginService->getInstalledPlugins();
            foreach ($installedPlugins as $installed) {
                $pluginId = $installed['plugin_id'] ?? '';
                $pluginInstance = $pluginService->get($pluginId);
                if (! $pluginInstance) {
                    continue;
                }

                $config = $pluginInstance->getConfig();
                if (! ($config['has_frontend'] ?? false)) {
                    continue;
                }

                $prefix = session('current_project_prefix', '');
                $appUrl = rtrim(config('app.url', ''), '/');

                $pages[] = [
                    'plugin_id' => $pluginId,
                    'name' => $config['name'] ?? $pluginId,
                    'version' => $config['version'] ?? '',
                    'description' => $config['description'] ?? '',
                    'access_url' => "{$appUrl}/frontend/{$prefix}/{$pluginId}/dist/index.html",
                    'source' => 'plugin',
                ];
            }
        } catch (\Throwable $e) {
            // 插件加载失败不影响主功能
        }

        return $pages;
    }
}
