<?php

namespace App\Services\TaskProcessors;

use App\Adapter\Prompts;
use App\Models\SysTask;
use App\Repository\ContentRepository;
use App\Repository\MoldRepository;
use App\Repository\SysTaskRepository;
use App\Services\GptService;
use Illuminate\Support\Facades\Log;

/**
 * 内容生成任务处理器
 */
class ContentGenerationTaskProcessor implements TaskProcessorInterface
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
        Log::info('[ContentGenerationTaskProcessor] 开始处理任务', [
            'task_id' => $task->id,
            'status' => $task->status,
            'payload_keys' => array_keys($task->payload ?? []),
        ]);

        $payload = $task->payload ?? [];

        Log::info('contentGenerationTaskProcessor_payload', ['field_snapshot_count' => is_array($payload['field_snapshot'] ?? null) ? count($payload['field_snapshot']) : 0]);

        $fields = $payload['field_snapshot'] ?? $this->buildContentGeneratableFieldSnapshot(
            (int) ($payload['mold_id'] ?? 0),
            (bool) ($payload['only_empty'] ?? false)
        );

        Log::info('contentGenerationTaskProcessor_payload_fields', ['field_count' => is_array($fields) ? count($fields) : 0]);

        if (empty($fields)) {
            Log::error('[ContentGenerationTaskProcessor] 字段为空', [
                'task_id' => $task->id,
                'mold_id' => $payload['mold_id'] ?? null,
                'payload' => $payload,
            ]);
            $this->taskRepository->markFailed($task, '生成失败，请稍后重试');

            return;
        }

        $finalPrompt = Prompts::getContentPrompt((string) ($payload['prompt'] ?? ''), $fields);

        // 历史缓存：相同模型 + 相同问题（及参数）直接返回
        $userId = $task->requested_by ?? null;
        $question = (string) ($payload['prompt'] ?? '');

        $result = $this->gptService->chat('', [
            ['role' => 'user', 'content' => $finalPrompt],
        ], $userId, $question);

        Log::info('[ContentGenerationTaskProcessor] AI返回结果', [
            'task_id' => $task->id,
            'result' => $result,
        ]);

        Log::info('contentGenerationTaskProcessor_fields', ['fields' => $fields]);
        Log::info('contentGenerationTaskProcessor_result', ['result' => $result]);

        $normalized = $this->normalizeAiResult($result, $fields);

        $moldId = (int) ($payload['mold_id'] ?? 0);
        $onlyEmpty = (bool) ($payload['only_empty'] ?? false);
        $currentValues = is_array($payload['current_values'] ?? null) ? ($payload['current_values'] ?? []) : [];

        Log::info('normalized', ['normalized' => $normalized]);
        // 1) 将AI标准化结果映射为 { field => value }
        $aiData = $this->mapNormalizedToContent($normalized, $moldId);
        // 2) 与用户已输入值合并（用户优先，受 only_empty 约束）
        Log::info('aiData', ['aiData' => $aiData]);
        $merged = $this->mergeWithUserValues($moldId, $aiData, $currentValues, $onlyEmpty);
        // 3) 为非AI类型字段补默认值
        Log::info('merged', ['merged' => $merged]);
        $finalData = $this->fillMissingWithDefaults($moldId, $merged, $currentValues, $onlyEmpty);

        Log::info('finalData', ['finalData' => $finalData]);
        // 如果属于批量父任务的子任务，尝试保存内容并回写父任务聚合信息
        $parentTaskId = isset($payload['parent_task_id']) ? (int) $payload['parent_task_id'] : null;
        $contentId = null;
        if ($parentTaskId) {
            try {
                if (! empty($finalData)) {
                    // checkbox 等数组需要转存为逗号分隔
                    $saveData = $this->prepareForPersistence($moldId, $finalData);
                    $created = ContentRepository::buildContent($moldId)->create($saveData);
                    $contentId = $created->id ?? null;
                }
            } catch (\Throwable $e) {
                $this->taskRepository->markFailed($task, '保存内容失败: '.$e->getMessage());
                if ($parentTaskId) {
                    $this->updateParentAggregate($parentTaskId, $task->id, 'failed', [
                        'error' => '保存内容失败: '.$e->getMessage(),
                    ]);
                }

                return;
            }
        }

        // 回传给前端：合并后的字段列表（包含默认值补齐）
        $normalizedOut = $this->toNormalizedList($moldId, $finalData);

        Log::info('[ContentGenerationTaskProcessor] 准备标记任务成功', [
            'task_id' => $task->id,
            'normalizedOut' => $normalizedOut,
            'finalData' => $finalData,
        ]);

        $this->taskRepository->markSuccess($task, $normalizedOut);

        Log::info('[ContentGenerationTaskProcessor] 任务已标记成功', [
            'task_id' => $task->id,
            'task_status_after' => $task->fresh()->status,
            'task_result_after' => $task->fresh()->result,
        ]);

        if ($parentTaskId) {
            $extra = [];
            if ($contentId) {
                $extra['content_id'] = $contentId;
            }
            $this->updateParentAggregate($parentTaskId, $task->id, 'success', $extra);
        }
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

    private function normalizeAiResult($result, array $fields): array
    {
        if (! is_array($result)) {
            return [];
        }

        // 将 result 转换为以 id 为键的关联数组
        $resultMap = [];
        foreach ($result as $item) {
            if (isset($item['id'])) {
                $resultMap[$item['id']] = $item;
            }
        }

        $normalized = [];
        foreach ($fields as $fieldDef) {
            $field = $fieldDef['field'] ?? '';
            $type = $fieldDef['type'] ?? 'input';
            $label = $fieldDef['label'] ?? $field;

            $value = $resultMap[$field]['value'] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $normalized[] = [
                'field' => $field,
                'type' => $type,
                'label' => $label,
                'value' => $value,
            ];
        }

        return $normalized;
    }

    private function mapNormalizedToContent(array $normalized, int $moldId): array
    {
        $data = [];
        foreach ($normalized as $item) {
            $field = $item['field'] ?? '';
            if ($field === '') {
                continue;
            }
            $data[$field] = $item['value'] ?? null;
        }

        return $data;
    }

    private function mergeWithUserValues(int $moldId, array $aiData, array $userValues, bool $onlyEmpty): array
    {
        $merged = $aiData;
        foreach ($userValues as $field => $value) {
            if ($onlyEmpty && isset($merged[$field]) && $merged[$field] !== null && $merged[$field] !== '') {
                continue;
            }
            $merged[$field] = $value;
        }

        return $merged;
    }

    private function fillMissingWithDefaults(int $moldId, array $data, array $userValues, bool $onlyEmpty): array
    {
        $moldInfo = $this->moldRepository->getMoldInfo($moldId);
        $fields = is_array($moldInfo['fields_arr'] ?? null) ? $moldInfo['fields_arr'] : [];

        foreach ($fields as $fieldDef) {
            $field = $fieldDef['field'] ?? '';
            $type = $fieldDef['type'] ?? 'input';

            if ($field === '' || isset($data[$field])) {
                continue;
            }

            // 如果用户有值，使用用户值
            if (isset($userValues[$field])) {
                $data[$field] = $userValues[$field];

                continue;
            }

            // 否则使用默认值
            $default = $this->getFieldDefault($fieldDef, $type);
            if ($default !== null) {
                $data[$field] = $default;
            }
        }

        return $data;
    }

    private function getFieldDefault(array $fieldDef, string $type)
    {
        $default = $fieldDef['default'] ?? null;

        if ($default !== null && $default !== '') {
            return $default;
        }

        if ($type === 'checkbox') {
            return [];
        }

        if ($type === 'relation') {
            return $this->getRelationDefaults($fieldDef);
        }

        return null;
    }

    private function getRelationDefaults(array $fieldDef): array
    {
        $relationType = $fieldDef['relationType'] ?? '';
        $options = $fieldDef['options'] ?? [];

        if ($relationType === 'hasOne' && ! empty($options)) {
            $first = $options[0] ?? [];
            if (isset($first['value'])) {
                return [$first['value']];
            }
        }

        return [];
    }

    private function toNormalizedList(int $moldId, array $data): array
    {
        $moldInfo = $this->moldRepository->getMoldInfo($moldId);
        $fields = is_array($moldInfo['fields_arr'] ?? null) ? $moldInfo['fields_arr'] : [];

        $list = [];
        foreach ($fields as $fieldDef) {
            $field = $fieldDef['field'] ?? '';
            $type = $fieldDef['type'] ?? 'input';
            $label = $fieldDef['label'] ?? $field;

            $value = $data[$field] ?? null;

            $list[] = [
                'field' => $field,
                'type' => $type,
                'label' => $label,
                'value' => $value,
            ];
        }

        return $list;
    }

    private function prepareForPersistence(int $moldId, array $data): array
    {
        $m = $this->moldRepository->getMoldInfo($moldId);
        $defs = is_array($m['fields_arr'] ?? null) ? $m['fields_arr'] : [];
        $typeByField = [];
        foreach ($defs as $d) {
            $f = (string) ($d['field'] ?? '');
            if ($f !== '') {
                $typeByField[$f] = (string) ($d['type'] ?? 'input');
            }
        }
        $save = [];
        foreach ($data as $field => $val) {
            $t = $typeByField[$field] ?? 'input';
            if ($t === 'checkbox') {
                if (is_array($val)) {
                    $save[$field] = implode(',', array_map('strval', $val));
                } else {
                    $save[$field] = (string) $val;
                }
            } else {
                $save[$field] = is_bool($val) ? ($val ? '1' : '0') : $val;
            }
        }

        return $save;
    }

    private function updateParentAggregate(int $parentTaskId, int $childTaskId, string $childStatus, array $extra = []): void
    {
        $parent = $this->taskRepository->findById($parentTaskId);
        if (! $parent) {
            return;
        }
        $agg = $parent->result ?? [];
        $total = isset($agg['total']) ? (int) $agg['total'] : (int) ($parent->progress_total ?? 0);
        $done = isset($agg['done']) ? (int) $agg['done'] : (int) ($parent->progress_done ?? 0);
        $failed = isset($agg['failed']) ? (int) $agg['failed'] : (int) ($parent->progress_failed ?? 0);

        // 更新 child_tasks 中对应项状态
        $list = isset($agg['child_tasks']) && is_array($agg['child_tasks']) ? $agg['child_tasks'] : [];
        foreach ($list as &$it) {
            if ((int) ($it['task_id'] ?? 0) === $childTaskId) {
                $it['status'] = $childStatus;
                foreach ($extra as $k => $v) {
                    $it[$k] = $v;
                }
                break;
            }
        }
        unset($it);
        $agg['child_tasks'] = $list;

        // 统计完成/失败数
        $done = 0;
        $failed = 0;
        foreach ($list as $it) {
            $st = (string) ($it['status'] ?? '');
            if ($st === SysTask::STATUS_SUCCESS) {
                $done++;
            } elseif ($st === SysTask::STATUS_FAILED) {
                $failed++;
            }
        }
        $agg['done'] = $done;
        $agg['failed'] = $failed;
        $percent = $total > 0 ? round(($done + $failed) * 100 / $total, 2) : 0;
        $agg['percent'] = $percent;

        // 更新父任务
        $parent->update([
            'result' => $agg,
            'progress_total' => $total,
            'progress_done' => $done,
            'progress_failed' => $failed,
        ]);

        // 如果全部完成，标记父任务成功
        if ($done + $failed >= $total) {
            $agg['stage'] = 'success';
            $parent->update([
                'status' => SysTask::STATUS_SUCCESS,
                'result' => $agg,
                'finished_at' => now(),
                'error_message' => null,
            ]);
        }
    }
}
