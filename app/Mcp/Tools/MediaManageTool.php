<?php

namespace App\Mcp\Tools;

use App\Models\Media;
use App\Services\MediaService;
use Generator;
use Illuminate\Http\UploadedFile;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Media Manage Tool')]
class MediaManageTool extends Tool
{
    public MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function description(): string
    {
        return '媒体管理（列表、上传、删除）';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('action')->description('操作类型：list|upload|delete')->required();
        $schema->integer('id')->description('媒体 ID（用于 delete）');
        $schema->integer('page')->description('页码（list）');
        $schema->integer('page_size')->description('每页条数（list）');
        $schema->raw('filters', ['type' => 'object', 'description' => '筛选条件（list）']);
        $schema->string('file')->description('文件内容（base64）用于 upload');
        $schema->string('filename')->description('文件名用于 upload');
        $schema->integer('folder_id')->description('文件夹 ID（upload）');

        return $schema;
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        try {
            $action = $arguments['action'];

            switch ($action) {
                case 'list':
                    $page = $arguments['page'] ?? 1;
                    $pageSize = $arguments['page_size'] ?? 15;
                    $filters = $arguments['filters'] ?? [];
                    $result = $this->mediaService->getMediaList(array_merge($filters, [
                        'page' => $page,
                        'page_size' => $pageSize,
                    ]));

                    return ToolResult::text(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                case 'upload':
                    $filename = $arguments['filename'] ?? 'upload.bin';
                    $folderId = $arguments['folder_id'] ?? null;
                    $fileBase64 = $arguments['file'] ?? '';
                    if (! $fileBase64) {
                        return ToolResult::text(json_encode(['success' => false, 'error' => 'No file content'], JSON_UNESCAPED_UNICODE));
                    }
                    $fileData = base64_decode($fileBase64);
                    if ($fileData === false) {
                        return ToolResult::text(json_encode(['success' => false, 'error' => 'Invalid base64'], JSON_UNESCAPED_UNICODE));
                    }
                    // 临时文件
                    $tmpPath = sys_get_temp_dir().'/'.$filename;
                    file_put_contents($tmpPath, $fileData);
                    $uploadedFile = new UploadedFile($tmpPath, $filename, mime_content_type($tmpPath), null, true);
                    $media = $this->mediaService->createMedia($uploadedFile, null, null, ['folder_id' => $folderId]);
                    // 清理临时文件
                    unlink($tmpPath);

                    return ToolResult::text(json_encode(['success' => true, 'media' => $media], JSON_UNESCAPED_UNICODE));

                case 'delete':
                    $id = $arguments['id'];
                    $media = Media::findOrFail($id);
                    $this->mediaService->deleteMedia($media);

                    return ToolResult::text(json_encode(['success' => true, 'id' => $id], JSON_UNESCAPED_UNICODE));

                default:
                    return ToolResult::text(json_encode(['error' => 'Invalid action'], JSON_UNESCAPED_UNICODE));
            }
        } catch (\Throwable $e) {
            return ToolResult::text(json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
        }
    }
}
