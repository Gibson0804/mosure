<?php

namespace App\Services\TaskProcessors;

use App\Adapter\Prompts;
use App\Jobs\ProcessSysTaskJob;
use App\Models\SysTask;
use App\Repository\MoldRepository;
use App\Repository\SysTaskRepository;
use App\Services\GptService;
use Illuminate\Support\Facades\Log;

/**
 * 批量内容生成任务处理器
 */
class ContentBatchTaskProcessor implements TaskProcessorInterface
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

    public function process(SysTask $task): void
    {
        $payload = $task->payload ?? [];
        $moldId = (int) ($payload['mold_id'] ?? 0);
        $prompt = (string) ($payload['prompt'] ?? '');
        $count = max(1, min(50, (int) ($payload['count'] ?? 1)));

        Log::info('ContentBatch_process_start', [
            'task_id' => $task->id,
            'status' => $task->status,
            'mold_id' => $moldId,
            'count' => $count,
        ]);

        // 若已处于 processing，说明已经创建过子任务，直接转为巡检聚合
        if ($task->status === SysTask::STATUS_PROCESSING) {
            $agg = $task->result ?? [];
            $childList = isset($agg['child_tasks']) && is_array($agg['child_tasks']) ? $agg['child_tasks'] : [];
            if (empty($childList)) {
                Log::warning('ContentBatch_processing_but_no_children', [
                    'task_id' => $task->id,
                    'stage' => (string) ($agg['stage'] ?? ''),
                ]);
                // 兜底：当 processing 但 child_tasks 为空（例如被提前 markProcessing 或中途异常），继续走"首次执行拆分"逻辑
            } else {
                $this->inspectContentBatchTask($task);

                return;
            }
        }

        // 初始化聚合
        $task->update([
            'status' => SysTask::STATUS_PROCESSING,
            'started_at' => now(),
            'result' => [
                'stage' => 'analyzing',
                'total' => $count,
                'done' => 0,
                'failed' => 0,
                'percent' => 0,
                'topics' => [],
                'child_tasks' => [],
            ],
        ]);

        // 1) 生成主题列表
        $userId = $task->requested_by ?? null;
        $topics = $this->generateTopics($prompt, $count, '', $userId);
        if (empty($topics)) {
            $this->taskRepository->markFailed($task, '主题生成失败');

            return;
        }

        $agg = $task->result ?? [];
        $agg['stage'] = 'topics';
        $agg['topics'] = $topics;
        $task->update(['result' => $agg]);

        // 2) 创建子任务（生成并保存）
        $agg['stage'] = 'generating';
        $agg['child_tasks'] = [];
        foreach ($topics as $i => $topic) {
            $childPrompt = $this->buildChildContentPrompt($prompt, (string) $topic);
            $child = $this->createContentGenerationTask($moldId, $childPrompt, false, $userId, $task->id, $i, (string) $topic, true);
            $agg['child_tasks'][] = [
                'task_id' => $child->id,
                'index' => $i,
                'topic' => $topic,
                'status' => $child->status,
            ];
        }
        $task->update(['result' => $agg]);

        Log::info('ContentBatch_children_created', [
            'task_id' => $task->id,
            'children' => count($agg['child_tasks'] ?? []),
        ]);

        // 子任务异步执行，父任务增加巡检（watchdog）防止卡死
        // 由父任务自身延迟再次进入 handleSysTask -> inspectContentBatchTask
        ProcessSysTaskJob::dispatch($task->id)->delay(10);
    }

    private function inspectContentBatchTask(SysTask $task): void
    {
        $payload = $task->payload ?? [];
        $count = max(1, min(50, (int) ($payload['count'] ?? 1)));

        // 取消收敛：父任务取消后，级联取消未完成子任务，并停止巡检
        if ($task->canceled_at) {
            $agg = $task->result ?? [];
            $childList = isset($agg['child_tasks']) && is_array($agg['child_tasks']) ? $agg['child_tasks'] : [];
            $childIds = [];
            foreach ($childList as $it) {
                $id = (int) ($it['task_id'] ?? 0);
                if ($id > 0) {
                    $childIds[] = $id;
                }
            }
            if (! empty($childIds)) {
                SysTask::query()
                    ->whereIn('id', $childIds)
                    ->whereNull('canceled_at')
                    ->whereNotIn('status', [SysTask::STATUS_SUCCESS, SysTask::STATUS_FAILED, SysTask::STATUS_CANCELED])
                    ->update([
                        'status' => SysTask::STATUS_CANCELED,
                        'canceled_at' => now(),
                        'cancel_reason' => '父任务取消',
                        'finished_at' => now(),
                    ]);
            }
            $agg['stage'] = 'canceled';
            $task->update([
                'status' => SysTask::STATUS_CANCELED,
                'result' => $agg,
                'finished_at' => now(),
                'error_message' => null,
            ]);

            return;
        }

        // 超时收敛：默认 30 分钟
        $timeoutSec = (int) ($task->timeout_sec ?? 0);
        if ($timeoutSec <= 0) {
            $timeoutSec = 1800;
        }
        if ($task->started_at && $task->started_at->lt(now()->subSeconds($timeoutSec))) {
            $agg = $task->result ?? [];
            $agg['stage'] = 'failed';
            $agg['error'] = '批量任务超时';
            $task->update([
                'status' => SysTask::STATUS_FAILED,
                'result' => $agg,
                'finished_at' => now(),
                'error_message' => '批量任务超时',
            ]);

            return;
        }

        // 根据 child_tasks 或 parent_id 关联查询子任务状态
        $agg = $task->result ?? [];
        $childList = isset($agg['child_tasks']) && is_array($agg['child_tasks']) ? $agg['child_tasks'] : [];
        $childIds = [];
        foreach ($childList as $it) {
            $id = (int) ($it['task_id'] ?? 0);
            if ($id > 0) {
                $childIds[] = $id;
            }
        }

        if (! empty($childIds)) {
            $rows = SysTask::query()->whereIn('id', $childIds)->get(['id', 'status'])->keyBy('id');
            foreach ($childList as &$it) {
                $id = (int) ($it['task_id'] ?? 0);
                if ($id > 0 && isset($rows[$id])) {
                    $it['status'] = (string) $rows[$id]->status;
                }
            }
        }

        // 重新统计 done/failed
        $done = 0;
        $failed = 0;
        foreach ($childList as $it) {
            $st = (string) ($it['status'] ?? '');
            if ($st === SysTask::STATUS_SUCCESS || $st === 'success') {
                $done++;
            } elseif ($st === SysTask::STATUS_FAILED || $st === 'failed') {
                $failed++;
            }
        }
        $percent = $count > 0 ? (int) floor(($done + $failed) * 100 / $count) : 0;

        $agg['stage'] = 'generating';
        $agg['total'] = $count;
        $agg['done'] = $done;
        $agg['failed'] = $failed;
        $agg['percent'] = $percent;
        $agg['child_tasks'] = $childList;

        $update = [
            'result' => $agg,
            'progress_total' => $count,
            'progress_done' => $done,
            'progress_failed' => $failed,
        ];

        if ($count > 0 && ($done + $failed) >= $count) {
            $agg['stage'] = 'success';
            $update['status'] = SysTask::STATUS_SUCCESS;
            $update['result'] = $agg;
            $update['finished_at'] = now();
            $update['error_message'] = null;
            $task->update($update);

            return;
        }

        $task->update($update);

        // 继续巡检
        ProcessSysTaskJob::dispatch($task->id)->delay(10);
    }

    private function generateTopics(string $prompt, int $count, string $context = '', $userId = null): array
    {
        $finalPrompt = Prompts::getTopicsPrompt($prompt, $count, $context);
        $result = $this->gptService->chat('', [
            ['role' => 'user', 'content' => $finalPrompt],
        ], $userId, $prompt);

        if (! is_array($result)) {
            return [];
        }

        $topics = [];
        foreach ($result as $item) {
            $topic = is_array($item) ? ($item['topic'] ?? '') : (string) $item;
            if ($topic !== '') {
                $topics[] = $topic;
            }
        }

        return $topics;
    }

    private function buildChildContentPrompt(string $parentPrompt, string $topic): string
    {
        return Prompts::getChildContentPrompt($parentPrompt, $topic);
    }

    private function createContentGenerationTask(int $moldId, string $prompt, bool $onlyEmpty = false, $requestedBy = null, ?int $parentTaskId = null, ?int $index = null, ?string $topic = null, $isCreate = false, array $currentValues = []): SysTask
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

    private function buildContentGeneratableFieldSnapshot(int $moldId, bool $onlyEmpty = false): array
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
