<?php

namespace App\Services;

use App\Jobs\ProcessSysTaskJob;
use App\Models\SysTask;
use App\Repository\MoldRepository;
use App\Repository\SysTaskRepository;
use Illuminate\Support\Facades\Log;

class TaskService extends BaseService
{
    private $moldRepository;

    private $taskRepository;

    private $gptService;

    public function __construct(
        MoldRepository $moldRepository,
        SysTaskRepository $taskRepository,
        GptService $gptService
    ) {
        $this->moldRepository = $moldRepository;
        $this->taskRepository = $taskRepository;
        $this->gptService = $gptService;
    }

    public function createContentGenerationTask(int $moldId, string $prompt, bool $onlyEmpty = false, $requestedBy = null, ?int $parentTaskId = null, ?int $index = null, ?string $topic = null, $isCreate = false, array $currentValues = []): SysTask
    {
        $fieldSnapshot = $this->buildContentGeneratableFieldSnapshot($moldId, $onlyEmpty);

        $payload = [
            'mold_id' => $moldId,
            'only_empty' => (bool) $onlyEmpty,
            'prompt' => $prompt,
            'field_snapshot' => $fieldSnapshot,
            'current_values' => $currentValues,
        ];

        if ($parentTaskId) {
            $payload['parent_task_id'] = $parentTaskId;
        }
        if ($index !== null) {
            $payload['index'] = $index;
        }
        if ($topic !== null) {
            $payload['topic'] = $topic;
        }
        if ($isCreate) {
            $payload['isCreate'] = 1;
        }

        $title = '生成内容';
        if ($topic !== null && $topic !== '') {
            $title = '生成内容：'.(string) $topic;
        } elseif ($index !== null) {
            $title = '生成内容 #'.((int) $index + 1);
        }

        $task = $this->taskRepository->createTask([
            'type' => SysTask::TYPE_CONTENT_GENERATION,
            'status' => SysTask::STATUS_PENDING,
            'title' => $title,
            'parent_id' => $parentTaskId ?: null,
            'root_id' => $parentTaskId ?: null,
            'sort_no' => $index !== null ? ((int) $index + 1) : null,
            'payload' => $payload,
            'requested_by' => $requestedBy,
            'related_type' => 'mold',
            'related_id' => $moldId,
        ]);

        ProcessSysTaskJob::dispatch($task->id);

        return $task;
    }

    public function createContentBatchDirectTask(int $moldId, string $prompt, int $count = 1, $requestedBy = null): SysTask
    {
        $count = max(1, min(50, $count));

        $groupKey = 'B'.str_replace('-', '', (string) \Illuminate\Support\Str::uuid());

        $payload = [
            'mold_id' => $moldId,
            'prompt' => $prompt,
            'count' => $count,
            'group_key' => $groupKey,
        ];

        $task = $this->taskRepository->createTask([
            'type' => SysTask::TYPE_CONTENT_BATCH_DIRECT,
            'status' => SysTask::STATUS_PENDING,
            'title' => '批量生成内容（直接）('.$count.'条)',
            'group_key' => $groupKey,
            'progress_total' => $count,
            'progress_done' => 0,
            'progress_failed' => 0,
            'payload' => $payload,
            'requested_by' => $requestedBy,
            'related_type' => 'mold',
            'related_id' => $moldId,
        ]);

        ProcessSysTaskJob::dispatch($task->id);

        return $task;
    }

    public function createContentBatchTask(int $moldId, string $prompt, int $count = 1, $requestedBy = null): SysTask
    {
        $count = max(1, min(50, $count));

        $groupKey = 'B'.str_replace('-', '', (string) \Illuminate\Support\Str::uuid());

        $payload = [
            'mold_id' => $moldId,
            'prompt' => $prompt,
            'count' => $count,
            'group_key' => $groupKey,
        ];

        $task = $this->taskRepository->createTask([
            'type' => SysTask::TYPE_CONTENT_BATCH,
            'status' => SysTask::STATUS_PENDING,
            'title' => '批量生成内容('.$count.'条)',
            'group_key' => $groupKey,
            'progress_total' => $count,
            'progress_done' => 0,
            'progress_failed' => 0,
            'payload' => $payload,
            'requested_by' => $requestedBy,
            'related_type' => 'mold',
            'related_id' => $moldId,
        ]);

        ProcessSysTaskJob::dispatch($task->id);

        return $task;
    }

    public function createMediaCaptureTask(array $mediaUrls, ?int $folderId = null, ?string $description = null, $requestedBy = null): SysTask
    {
        if (! is_array($mediaUrls) || empty($mediaUrls)) {
            throw new \InvalidArgumentException('媒体资源URL不能为空');
        }

        $payload = [
            'media_urls' => $mediaUrls,
            'folder_id' => $folderId,
            'description' => $description,
        ];

        $task = $this->taskRepository->createTask([
            'type' => SysTask::TYPE_MEDIA_CAPTURE,
            'status' => SysTask::STATUS_PENDING,
            'title' => '采集媒体资源('.count($mediaUrls).'个)',
            'progress_total' => count($mediaUrls),
            'progress_done' => 0,
            'progress_failed' => 0,
            'payload' => $payload,
            'requested_by' => $requestedBy,
        ]);

        ProcessSysTaskJob::dispatch($task->id);

        return $task;
    }

    public function handleSysTask(SysTask $task): void
    {
        // 委托给 TaskProcessor 处理异步任务
        $processor = app(TaskProcessor::class);
        $processor->handleSysTask($task);
    }

    public function getTaskStatus(int $taskId): array
    {
        Log::info('[TaskService] 查询任务状态', [
            'task_id' => $taskId,
        ]);

        $task = $this->taskRepository->findById($taskId);

        if (! $task) {
            Log::error('[TaskService] 任务不存在', [
                'task_id' => $taskId,
            ]);
            throw new \InvalidArgumentException('任务不存在');
        }

        $status = [
            'task_id' => $task->id,
            'status' => $task->status,
            'result' => $task->result ?? null,
            'error_message' => $task->error_message,
            'finished_at' => optional($task->finished_at)?->format('Y-m-d H:i:s'),
        ];

        Log::info('[TaskService] 返回任务状态', [
            'task_id' => $taskId,
            'status' => $status,
        ]);

        return $status;
    }

    public function buildContentGeneratableFieldSnapshot(int $moldId, bool $onlyEmpty = false): array
    {
        $moldInfo = $this->moldRepository->getMoldInfo($moldId);
        $fieldsJson = $moldInfo['fields'] ?? '[]';
        $fields = json_decode($fieldsJson, true);

        if (! is_array($fields)) {
            return [];
        }

        $filtered = array_filter($fields, function ($one) {
            return in_array($one['type'], ['input', 'textarea', 'richText', 'numInput']);
        });

        if ($onlyEmpty) {
            // todo:: 可按需过滤空值字段
        }

        $snapshot = array_map(function ($one) {
            return [
                'field' => $one['field'] ?? '',
                'type' => $one['type'] ?? 'input',
                'label' => $one['label'] ?? '',
            ];
        }, $filtered);

        return array_values($snapshot);
    }
}
