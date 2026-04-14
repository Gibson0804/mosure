<?php

namespace App\Services\TaskProcessors;

use App\Models\SysTask;
use App\Repository\SysTaskRepository;
use App\Services\MediaService;

/**
 * 媒体资源采集任务处理器
 */
class MediaCaptureTaskProcessor implements TaskProcessorInterface
{
    private $taskRepository;

    private $mediaService;

    public function __construct(
        SysTaskRepository $taskRepository,
        MediaService $mediaService
    ) {
        $this->taskRepository = $taskRepository;
        $this->mediaService = $mediaService;
    }

    public function process(SysTask $task): void
    {
        $payload = $task->payload ?? [];
        $mediaUrls = $payload['media_urls'] ?? [];
        $folderId = $payload['folder_id'] ?? null;
        $description = $payload['description'] ?? null;

        if (! is_array($mediaUrls) || empty($mediaUrls)) {
            $this->taskRepository->markFailed($task, '缺少媒体资源URL');

            return;
        }

        // 标记任务为处理中
        $task->update([
            'status' => SysTask::STATUS_PROCESSING,
            'started_at' => now(),
            'progress_total' => count($mediaUrls),
            'progress_done' => 0,
            'progress_failed' => 0,
        ]);

        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($mediaUrls as $index => $mediaUrl) {
            $url = $mediaUrl['url'] ?? '';
            $filename = $mediaUrl['filename'] ?? null;

            if (empty($url)) {
                $results[] = [
                    'index' => $index,
                    'url' => $url,
                    'status' => 'failed',
                    'error' => 'URL为空',
                ];
                $failedCount++;

                continue;
            }

            try {
                // 下载媒体文件
                $mediaId = $this->downloadAndSaveMedia($url, $filename, $description, $folderId);

                $results[] = [
                    'index' => $index,
                    'url' => $url,
                    'status' => 'success',
                    'media_id' => $mediaId,
                ];
                $successCount++;
            } catch (\Throwable $e) {
                $results[] = [
                    'index' => $index,
                    'url' => $url,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                $failedCount++;
            }

            // 更新进度
            $task->update([
                'progress_done' => $successCount,
                'progress_failed' => $failedCount,
            ]);
        }

        // 标记任务完成
        $this->taskRepository->markSuccess($task, [
            'results' => $results,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'total_count' => count($mediaUrls),
        ]);
    }

    private function downloadAndSaveMedia(string $url, ?string $filename = null, ?string $description = null, ?int $folderId = null): int
    {
        $options = [];
        if ($folderId) {
            $options['folder_id'] = $folderId;
        }
        if ($filename) {
            $options['custom_filename'] = $filename;
        }

        $media = $this->mediaService->createMediaFromUrl($url, $description, $options);
        if (! $media) {
            throw new \Exception('下载或保存媒体失败');
        }

        return $media->id;
    }
}
