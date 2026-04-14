<?php

namespace App\Services;

use App\Ai\Attributes\AiTool;
use App\Models\ProjectPage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class PageHostingService extends BaseService
{
    private function getPageAccessUrl(string $prefix, string $slug): string
    {
        $appUrl = rtrim(config('app.url', ''), '/');

        return "{$appUrl}/sites/{$prefix}/{$slug}";
    }

    private function normalizeExternalUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function mergePageConfig(?array $existingConfig, array $data): ?array
    {
        $config = is_array($existingConfig) ? $existingConfig : [];

        if (array_key_exists('config', $data) && is_array($data['config'])) {
            $config = array_merge($config, $data['config']);
        }

        if (array_key_exists('external_url', $data)) {
            $externalUrl = $this->normalizeExternalUrl($data['external_url']);
            if ($externalUrl) {
                $config['external_url'] = $externalUrl;
            } else {
                unset($config['external_url']);
            }
        }

        return ! empty($config) ? $config : null;
    }

    private function mapPageItem(ProjectPage $item, string $prefix): array
    {
        $config = is_array($item->config) ? $item->config : [];
        $accessUrl = $this->getPageAccessUrl($prefix, $item->slug);

        return [
            'id' => $item->id,
            'slug' => $item->slug,
            'title' => $item->title,
            'description' => $item->description,
            'page_type' => $item->page_type,
            'status' => $item->status,
            'external_url' => $config['external_url'] ?? null,
            'access_url' => $accessUrl,
            'created_at' => $item->created_at?->toDateTimeString(),
            'updated_at' => $item->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * 确保 pages 表存在（兼容已有项目）
     */
    private function ensureTableExists(): void
    {
        $prefix = session('current_project_prefix', '');
        if ($prefix === '') {
            return;
        }

        $tableName = ProjectPage::getfullTableNameByPrefix($prefix);
        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, ProjectPage::getTableSchema());
        }
    }

    /**
     * 获取 Mosure SDK 脚本（自动注入到托管页面）
     */
    private function getMosureSdk(string $projectPrefix): string
    {
        $appUrl = rtrim(config('app.url', ''), '/');

        return <<<JS
<script>
const mosureSdk = {
    apiBase: '{$appUrl}/open',
    sitesToken: 'st_{$projectPrefix}',
    async request(method, url, data) {
        const opts = { method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Sites-Token': this.sitesToken } };
        if (data && method !== 'GET') opts.body = JSON.stringify(data);
        const resp = await fetch(this.apiBase + url, opts);
        return resp.json();
    },
    async getList(tableName, params = {}) {
        const qs = new URLSearchParams(params).toString();
        return this.request('GET', '/content/list/' + tableName + (qs ? '?' + qs : ''));
    },
    async getItem(tableName, id) {
        return this.request('GET', '/content/detail/' + tableName + '/' + id);
    },
    async createItem(tableName, data) {
        return this.request('POST', '/content/create/' + tableName, data);
    },
    async updateItem(tableName, id, data) {
        return this.request('PUT', '/content/update/' + tableName + '/' + id, data);
    },
    async deleteItem(tableName, id) {
        return this.request('DELETE', '/content/delete/' + tableName + '/' + id);
    },
    async getPage(tableName) {
        return this.request('GET', '/page/detail/' + tableName);
    },
    async updatePage(tableName, data) {
        return this.request('PUT', '/page/update/' + tableName, { subject_content: data });
    }
};
window.Mosure = mosureSdk;
</script>
JS;
    }

    // #[AiTool(
    //     name: 'hosted_page_sdk_info',
    //     description: '获取前端托管页面可用的 Mosure SDK 完整文档，包括所有方法签名、参数说明、返回值格式和使用示例。在使用 hosted_page_create 或 hosted_page_update 生成包含数据交互的页面前，应先调用此工具了解 SDK 用法。',
    //     params: []
    // )]
    public function getSdkInfo(): array
    {
        return [
            'description' => '系统会自动在托管页面的 <head> 中注入 window.Mosure 全局对象，页面 JS 可直接使用，无需手动引入。',
            'methods' => [
                [
                    'name' => 'Mosure.getList(table, params)',
                    'description' => '获取内容列表',
                    'params' => [
                        'table' => '(string, 必填) 内容模型表名，如 "todo"、"article"',
                        'params' => '(object, 可选) 查询参数，如 { page: 1, page_size: 10 }',
                    ],
                    'returns' => '{ code: 0, data: { items: [...], meta: { total, page, page_size, page_count } } }',
                    'example' => 'const res = await Mosure.getList("todo", { page: 1, page_size: 20 }); const items = res.data.items;',
                ],
                [
                    'name' => 'Mosure.getItem(table, id)',
                    'description' => '获取单条内容详情',
                    'params' => [
                        'table' => '(string, 必填) 内容模型表名',
                        'id' => '(number, 必填) 记录 ID',
                    ],
                    'returns' => '{ code: 0, data: { id, field1, field2, ... , created_at, updated_at } }',
                    'example' => 'const res = await Mosure.getItem("todo", 1); const item = res.data;',
                ],
                [
                    'name' => 'Mosure.createItem(table, data)',
                    'description' => '创建一条内容记录',
                    'params' => [
                        'table' => '(string, 必填) 内容模型表名',
                        'data' => '(object, 必填) 要创建的数据，键为字段名，值为字段值，如 { title: "买菜", completed: false }',
                    ],
                    'returns' => '{ code: 0, data: { id, ...创建的完整记录 } }',
                    'example' => 'const res = await Mosure.createItem("todo", { title: "买菜", completed: false });',
                ],
                [
                    'name' => 'Mosure.updateItem(table, id, data)',
                    'description' => '更新一条内容记录',
                    'params' => [
                        'table' => '(string, 必填) 内容模型表名',
                        'id' => '(number, 必填) 记录 ID',
                        'data' => '(object, 必填) 要更新的字段，如 { completed: true }',
                    ],
                    'returns' => '{ code: 0, data: { id, ...更新后的完整记录 } }',
                    'example' => 'await Mosure.updateItem("todo", 1, { completed: true });',
                ],
                [
                    'name' => 'Mosure.deleteItem(table, id)',
                    'description' => '删除一条内容记录',
                    'params' => [
                        'table' => '(string, 必填) 内容模型表名',
                        'id' => '(number, 必填) 记录 ID',
                    ],
                    'returns' => '{ code: 0, data: [] }',
                    'example' => 'await Mosure.deleteItem("todo", 1);',
                ],
            ],
            'notes' => [
                '所有方法都是 async 的，返回 Promise，需要用 await 调用。',
                '返回的 JSON 中 code=0 表示成功，code!=0 表示失败，失败时 message 字段包含错误信息。',
                'table 参数是内容模型的表名（slug），由 mold 定义，如用户创建了 "todo" 模型则 table="todo"。',
                'SDK 由系统自动注入，html_content 中不需要也不应该包含 SDK 的 <script> 标签。',
                '页面应使用内联 CSS 和 JS，生成完整可运行的单文件 HTML。',
            ],
            'full_example' => <<<'EXAMPLE'
<!DOCTYPE html>
<html lang="zh">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>待办列表</title>
  <style>
    body { font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 0 20px; }
    .item { display: flex; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee; }
    .item.done span { text-decoration: line-through; color: #999; }
    input[type="text"] { flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
    button { padding: 8px 16px; background: #1890ff; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
  </style>
</head>
<body>
  <h1>待办列表</h1>
  <div style="display:flex;gap:8px;margin-bottom:16px;">
    <input type="text" id="newTitle" placeholder="输入待办事项...">
    <button onclick="addTodo()">添加</button>
  </div>
  <div id="list"></div>
  <script>
    async function loadList() {
      const res = await Mosure.getList("todo");
      const list = document.getElementById("list");
      list.innerHTML = res.data.items.map(item =>
        '<div class="item ' + (item.completed ? 'done' : '') + '">' +
          '<input type="checkbox" ' + (item.completed ? 'checked' : '') +
            ' onchange="toggleTodo(' + item.id + ', this.checked)">' +
          '<span style="flex:1;margin-left:8px">' + item.title + '</span>' +
          '<button onclick="deleteTodo(' + item.id + ')" style="background:#ff4d4f;font-size:12px;padding:4px 8px;">删除</button>' +
        '</div>'
      ).join("");
    }
    async function addTodo() {
      const input = document.getElementById("newTitle");
      if (!input.value.trim()) return;
      await Mosure.createItem("todo", { title: input.value.trim(), completed: false });
      input.value = "";
      loadList();
    }
    async function toggleTodo(id, completed) {
      await Mosure.updateItem("todo", id, { completed });
      loadList();
    }
    async function deleteTodo(id) {
      await Mosure.deleteItem("todo", id);
      loadList();
    }
    loadList();
  </script>
</body>
</html>
EXAMPLE,
        ];
    }

    // #[AiTool(
    //     name: 'hosted_page_list',
    //     description: '列出当前项目的所有托管页面，返回 slug、标题、状态、类型等信息。',
    //     params: []
    // )]
    public function list(int $page = 1, int $pageSize = 15, array $filters = []): array
    {
        $this->ensureTableExists();
        $prefix = session('current_project_prefix', '');
        $q = ProjectPage::query();

        $keyword = (string) ($filters['keyword'] ?? '');
        if ($keyword !== '') {
            $q->where(function ($query) use ($keyword) {
                $query->where('title', 'like', "%{$keyword}%")
                    ->orWhere('slug', 'like', "%{$keyword}%");
            });
        }

        $status = $filters['status'] ?? null;
        if ($status !== null && $status !== '') {
            $q->where('status', $status);
        }

        $total = (clone $q)->count();
        $items = $q->orderByDesc('id')
            ->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get()
            ->map(fn ($item) => $this->mapPageItem($item, $prefix))
            ->toArray();

        return [
            'items' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'page_count' => $total > 0 ? (int) ceil($total / $pageSize) : 0,
            ],
        ];
    }

    // #[AiTool(
    //     name: 'hosted_page_get',
    //     description: '获取指定托管页面的详情，包括 HTML 内容。',
    //     params: [
    //         'slug' => ['type' => 'string', 'required' => true, 'desc' => '页面 URL 标识'],
    //     ]
    // )]
    public function get(string $slug): array
    {
        $page = ProjectPage::where('slug', $slug)->firstOrFail();
        $prefix = session('current_project_prefix', '');
        $data = $page->toArray();
        $config = is_array($page->config) ? $page->config : [];
        $data['external_url'] = $config['external_url'] ?? null;
        $data['access_url'] = $this->getPageAccessUrl($prefix, $page->slug);

        return $data;
    }

    // #[AiTool(
    //     name: 'hosted_page_create',
    //     description: '创建一个前端托管页面。页面创建后自动发布，可通过 /sites/{项目前缀}/{slug} 公开访问。AI 应生成完整的单文件 HTML 页面（含内联 CSS 和 JS）。如果页面需要与内容模型交互（增删改查数据），请先调用 hosted_page_sdk_info 获取 Mosure SDK 的方法签名和使用示例，SDK 会自动注入页面无需手动引入。',
    //     params: [
    //         'slug' => ['type' => 'string', 'required' => true, 'desc' => '页面URL标识，仅限小写字母、数字和短横线，如 todolist'],
    //         'title' => ['type' => 'string', 'required' => true, 'desc' => '页面标题'],
    //         'html_content' => ['type' => 'string', 'required' => true, 'desc' => '完整的 HTML 页面内容（含内联CSS和JS），不需要包含 Mosure SDK 脚本（系统会自动注入）'],
    //         'description' => ['type' => 'string', 'required' => false, 'desc' => '页面描述'],
    //     ]
    // )]
    public function create(array $data): array
    {
        $this->ensureTableExists();
        $slug = $data['slug'] ?? '';
        if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
            throw new \InvalidArgumentException('slug 只能包含小写字母、数字和短横线，且不能以短横线开头');
        }

        if (ProjectPage::where('slug', $slug)->exists()) {
            throw new \InvalidArgumentException("slug '{$slug}' 已存在");
        }

        $config = $this->mergePageConfig(null, $data);

        $page = ProjectPage::create([
            'slug' => $slug,
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? null,
            'page_type' => $data['page_type'] ?? 'single',
            'status' => $data['status'] ?? 'published',
            'html_content' => $data['html_content'] ?? '',
            'config' => $config,
            'created_by' => $data['created_by'] ?? null,
        ]);

        $prefix = session('current_project_prefix', '');
        $accessUrl = $this->getPageAccessUrl($prefix, $slug);

        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'status' => $page->status,
            'access_url' => $accessUrl,
            'message' => "页面已创建并发布，访问地址: {$accessUrl}",
        ];
    }

    // #[AiTool(
    //     name: 'hosted_page_update',
    //     description: '更新已有托管页面的内容。可更新 HTML 内容、标题、描述等。如果更新 html_content 且涉及数据交互，请先调用 hosted_page_sdk_info 了解 SDK 用法。',
    //     params: [
    //         'slug' => ['type' => 'string', 'required' => true, 'desc' => '要更新的页面 slug'],
    //         'html_content' => ['type' => 'string', 'required' => false, 'desc' => '新的 HTML 页面内容'],
    //         'title' => ['type' => 'string', 'required' => false, 'desc' => '新标题'],
    //         'description' => ['type' => 'string', 'required' => false, 'desc' => '新描述'],
    //     ]
    // )]
    public function update(string $slug, array $data): array
    {
        $page = ProjectPage::where('slug', $slug)->firstOrFail();

        $fillable = ['title', 'description', 'html_content', 'status', 'config'];
        $updates = array_intersect_key($data, array_flip($fillable));
        if (array_key_exists('external_url', $data)) {
            $updates['config'] = $this->mergePageConfig(is_array($page->config) ? $page->config : null, $data);
        } elseif (array_key_exists('config', $data)) {
            $updates['config'] = $this->mergePageConfig(is_array($page->config) ? $page->config : null, $data);
        }

        if (! empty($updates)) {
            $page->update($updates);
        }

        $prefix = session('current_project_prefix', '');
        $accessUrl = $this->getPageAccessUrl($prefix, $slug);

        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'status' => $page->status,
            'access_url' => $accessUrl,
            'message' => "页面已更新，访问地址: {$accessUrl}",
        ];
    }

    // #[AiTool(
    //     name: 'hosted_page_delete',
    //     description: '删除一个托管页面。',
    //     params: [
    //         'slug' => ['type' => 'string', 'required' => true, 'desc' => '要删除的页面 slug'],
    //     ]
    // )]
    public function deletePage(string $slug): array
    {
        $page = ProjectPage::where('slug', $slug)->firstOrFail();

        // 如果是 SPA 类型，删除文件目录
        if ($page->page_type === 'spa') {
            $prefix = session('current_project_prefix', '');
            $dir = storage_path("app/sites/{$prefix}/{$slug}");
            if (File::isDirectory($dir)) {
                File::deleteDirectory($dir);
            }
        }

        $page->delete();

        return ['message' => "页面 '{$slug}' 已删除"];
    }

    /**
     * 切换页面状态 (publish/unpublish)
     */
    public function toggle(string $slug): array
    {
        $page = ProjectPage::where('slug', $slug)->firstOrFail();
        $page->status = $page->status === 'published' ? 'draft' : 'published';
        $page->save();

        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'status' => $page->status,
        ];
    }

    /**
     * 通过 ZIP 上传部署 SPA 页面
     */
    public function deployZip(string $slug, string $zipPath, array $meta = []): array
    {
        $prefix = session('current_project_prefix', '');
        $targetDir = storage_path("app/sites/{$prefix}/{$slug}/dist");

        // 清理旧文件
        if (File::isDirectory($targetDir)) {
            File::deleteDirectory($targetDir);
        }
        File::makeDirectory($targetDir, 0755, true);

        // 解压
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('无法打开 ZIP 文件');
        }
        $this->assertZipArchiveSafe($zip);
        $zip->extractTo($targetDir);
        $zip->close();

        // 创建或更新 DB 记录
        $config = $this->mergePageConfig(null, $meta);

        $page = ProjectPage::updateOrCreate(
            ['slug' => $slug],
            [
                'title' => $meta['title'] ?? $slug,
                'description' => $meta['description'] ?? null,
                'page_type' => 'spa',
                'status' => 'published',
                'html_content' => null,
                'config' => $config,
                'created_by' => $meta['created_by'] ?? null,
            ]
        );

        $accessUrl = $this->getPageAccessUrl($prefix, $slug);

        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'status' => $page->status,
            'access_url' => $accessUrl,
            'message' => "SPA 页面已部署，访问地址: {$accessUrl}",
        ];
    }

    /**
     * 渲染单页面 HTML（注入 Mosure SDK）
     */
    public function renderSinglePage(ProjectPage $page, string $projectPrefix): string
    {
        $html = $page->html_content ?? '';
        $sdk = $this->getMosureSdk($projectPrefix);

        // 在 </head> 前注入 SDK，如果没有 <head> 则在最前面注入
        if (stripos($html, '</head>') !== false) {
            $html = str_ireplace('</head>', $sdk."\n</head>", $html);
        } else {
            $html = $sdk."\n".$html;
        }

        return $html;
    }

    private function assertZipArchiveSafe(\ZipArchive $zip): void
    {
        $maxFiles = 5000;
        $maxUncompressedBytes = 500 * 1024 * 1024;
        $totalBytes = 0;

        if ($zip->numFiles > $maxFiles) {
            throw new \RuntimeException('ZIP 文件内容过多，已拒绝解压');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) ($stat['name'] ?? '');
            $size = (int) ($stat['size'] ?? 0);

            if (
                $name === '' ||
                str_contains($name, "\0") ||
                preg_match('#(^/|^[A-Za-z]:[\\\\/]|(^|/)\.\.(/|$))#', $name)
            ) {
                throw new \RuntimeException('ZIP 文件包含非法路径，已拒绝解压');
            }

            $totalBytes += max(0, $size);
            if ($totalBytes > $maxUncompressedBytes) {
                throw new \RuntimeException('ZIP 解压后的内容过大，已拒绝处理');
            }
        }
    }
}
