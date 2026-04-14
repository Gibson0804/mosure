<?php

namespace App\Mcp\Resources;

use App\Services\ApiDocsService;
use Laravel\Mcp\Server\Resource;

class OpenApiDocResource extends Resource
{
    protected string $description = 'Mosure 开放 API 文档（当前项目的 /open 系列接口），供人类阅读和 MCP 客户端参考。';

    public function read(): string
    {
        $prefix = (string) (session('current_project_prefix') ?? '');

        try {
            $service = app(ApiDocsService::class);
            $data = $service->listApis(['limit' => 1000, 'offset' => 0]);
            $endpoints = $data['endpoints'] ?? [];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();

            return "# Mosure 开放 API 文档\n\n当前无法获取接口列表：{$msg}\n";
        }

        $lines = [];
        $lines[] = '# Mosure 开放 API 文档';
        $lines[] = '';
        if ($prefix !== '') {
            $lines[] = "当前项目前缀：`{$prefix}`";
            $lines[] = '';
        }
        $lines[] = '本文档基于系统中已注册的 `/open` 系列接口自动生成。';
        $lines[] = '';
        $lines[] = '接口总数：'.count($endpoints);
        $lines[] = '';

        $grouped = [];
        foreach ($endpoints as $ep) {
            $kind = (string) ($ep['kind'] ?? 'other');
            if (! isset($grouped[$kind])) {
                $grouped[$kind] = [];
            }
            $grouped[$kind][] = $ep;
        }

        $kindTitles = [
            'content' => '内容模型接口',
            'subject' => '单页主题接口',
            'media' => '媒体接口',
            'function' => '自定义函数接口',
        ];

        foreach ($grouped as $kind => $items) {
            $title = $kindTitles[$kind] ?? $kind;
            $lines[] = '## '.$title." ({$kind})";
            $lines[] = '';
            foreach ($items as $ep) {
                $name = (string) ($ep['name'] ?? ($ep['id'] ?? ''));
                $id = (string) ($ep['id'] ?? '');
                $method = strtoupper((string) ($ep['http_method'] ?? 'GET'));
                $path = (string) ($ep['resolved_path'] ?? ($ep['path'] ?? ''));
                $desc = trim((string) ($ep['description'] ?? ''));

                $lines[] = "### {$name}";
                $lines[] = '';
                if ($id !== '') {
                    $lines[] = "- **ID**：`{$id}`";
                }
                $lines[] = "- **HTTP 方法**：`{$method}`";
                if ($path !== '') {
                    $lines[] = "- **路径**：`{$path}`";
                }
                if ($desc !== '') {
                    $lines[] = "- **说明**：{$desc}";
                }

                $query = $ep['query_params'] ?? [];
                if (is_array($query) && ! empty($query)) {
                    $lines[] = '- **Query 参数**：';
                    foreach ($query as $key => $meta) {
                        $type = '';
                        $qdesc = '';
                        $requiredMark = '';
                        if (is_array($meta)) {
                            $type = isset($meta['type']) ? (string) $meta['type'] : '';
                            $qdesc = isset($meta['description']) ? (string) $meta['description'] : '';
                            if (! empty($meta['required'])) {
                                $requiredMark = '（必填）';
                            }
                        }
                        $line = "  - `{$key}`";
                        if ($type !== '') {
                            $line .= " `{$type}`";
                        }
                        if ($requiredMark !== '') {
                            $line .= $requiredMark;
                        }
                        if ($qdesc !== '') {
                            $line .= "：{$qdesc}";
                        }
                        $lines[] = $line;
                    }
                }

                $body = $ep['body_schema'] ?? null;
                if (is_array($body) && ! empty($body)) {
                    $bodyType = isset($body['type']) ? (string) $body['type'] : 'object';
                    $bodyDesc = isset($body['description']) ? (string) $body['description'] : '';
                    $line = "- **请求体**：`{$bodyType}`";
                    if ($bodyDesc !== '') {
                        $line .= " - {$bodyDesc}";
                    }
                    $lines[] = $line;
                }

                $lines[] = '';
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public function mimeType(): string
    {
        return 'text/markdown';
    }

    public function uri(): string
    {
        return 'file://resources/mosure-open-api-doc.md';
    }
}
