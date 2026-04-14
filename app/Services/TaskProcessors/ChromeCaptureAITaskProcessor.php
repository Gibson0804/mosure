<?php

namespace App\Services\TaskProcessors;

use App\Adapter\Prompts;
use App\Models\SysTask;
use App\Repository\MoldRepository;
use App\Repository\SysTaskRepository;
use App\Services\ContentService;
use App\Services\GptService;
use App\Services\MediaService;
use Illuminate\Support\Facades\Log;

/**
 * Chrome AI 采集任务处理器
 */
class ChromeCaptureAITaskProcessor implements TaskProcessorInterface
{
    private $taskRepository;

    private $contentService;

    private $gptService;

    private $mediaService;

    private $moldRepository;

    public function __construct(
        SysTaskRepository $taskRepository,
        ContentService $contentService,
        GptService $gptService,
        MediaService $mediaService,
        MoldRepository $moldRepository
    ) {
        $this->taskRepository = $taskRepository;
        $this->contentService = $contentService;
        $this->gptService = $gptService;
        $this->mediaService = $mediaService;
        $this->moldRepository = $moldRepository;
    }

    public function process(SysTask $task): void
    {
        $payload = $task->payload ?? [];
        $data = $payload['data'] ?? null;
        $modelId = $payload['model_id'] ?? null;

        if (! $data || ! $modelId) {
            $this->taskRepository->markFailed($task, '缺少必要参数：data 或 model_id');

            return;
        }

        try {
            // 获取模型字段定义
            $moldInfo = $this->moldRepository->getMoldInfo($modelId);
            $fields = $moldInfo['fields_arr'] ?? [];

            if (empty($fields)) {
                $this->taskRepository->markFailed($task, '模型字段不存在');

                return;
            }

            // 标记任务为处理中
            $task->update([
                'status' => SysTask::STATUS_PROCESSING,
                'started_at' => now(),
            ]);

            // 处理采集的数据（拼接数组、处理图片等）
            $processedData = $this->processCaptureData($data, $fields);

            // 检查是否需要AI处理
            if ($this->needAIProcessing($processedData, $fields)) {
                // 生成AI提示词
                $aiPrompt = $this->generateAIPrompt($data, $fields);

                // 调用AI处理
                $userId = $task->requested_by ?? null;
                $aiResult = $this->gptService->chat('', [
                    ['role' => 'user', 'content' => $aiPrompt],
                ], $userId, 'Chrome采集', false, 'json');

                if (! $aiResult) {
                    throw new \Exception('AI处理失败');
                }

                // 解析 AI 返回的结果并合并到已处理的数据
                $aiData = $this->parseResult($aiResult, $fields);
                $processedData = array_merge($processedData, $aiData);
            }

            // 保存到数据库
            $result = $this->contentService->addContent($processedData, $modelId);

            // 标记任务为成功
            $task->update([
                'status' => SysTask::STATUS_SUCCESS,
                'finished_at' => now(),
                'result' => [
                    'content_id' => $result['id'] ?? null,
                    'message' => '内容已成功保存',
                ],
            ]);
        } catch (\Exception $e) {
            // 标记任务为失败
            $task->update([
                'status' => SysTask::STATUS_FAILED,
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            Log::error('Chrome AI采集任务失败: '.$e->getMessage(), [
                'task_id' => $task->id,
            ]);
        }
    }

    private function processCaptureData($data, $fields)
    {
        $processedData = [];

        foreach ($fields as $field) {
            $fieldName = $field['field'];
            $fieldType = $field['type'];

            // 检查是否有该字段的数据
            if (! isset($data['fields'][$fieldName])) {
                continue;
            }

            $fieldData = $data['fields'][$fieldName];

            // 处理图片/文件上传字段（直接抓取上传，不经过AI）
            if (in_array($fieldType, ['picUpload', 'fileUpload'])) {
                // 单文件/单图：只存第一个URL字符串
                if (is_array($fieldData) && ! empty($fieldData)) {
                    foreach ($fieldData as $item) {
                        $src = is_array($item) ? ($item['src'] ?? '') : ($item->src ?? '');
                        if ($src) {
                            $media = $this->uploadImageFromUrl($src, "Chrome采集: {$fieldName}");
                            $processedData[$fieldName] = $media ? $media->url : $src;
                            break;
                        }
                    }
                }

                continue;
            }
            if ($fieldType === 'picGallery') {
                // 图片集：存 JSON URL 数组
                $urls = [];
                if (is_array($fieldData) && ! empty($fieldData)) {
                    foreach ($fieldData as $item) {
                        $src = is_array($item) ? ($item['src'] ?? '') : ($item->src ?? '');
                        if ($src) {
                            $media = $this->uploadImageFromUrl($src, "Chrome采集: {$fieldName}");
                            $urls[] = $media ? $media->url : $src;
                        }
                    }
                }
                $processedData[$fieldName] = json_encode($urls, JSON_UNESCAPED_UNICODE);

                continue;
            }

            // 处理文本字段（拼接数组）
            if (is_array($fieldData)) {
                $texts = [];
                foreach ($fieldData as $item) {
                    $text = is_array($item) ? ($item['text'] ?? '') : ($item->text ?? '');
                    if ($text) {
                        $texts[] = trim($text);
                    }
                }

                if (! empty($texts)) {
                    if ($fieldType === 'richText') {
                        $processedData[$fieldName] = implode('<br>', $texts);
                    } elseif ($fieldType === 'textarea') {
                        $processedData[$fieldName] = implode("\n", $texts);
                    } elseif ($fieldType === 'tags' || $fieldType === 'checkbox') {
                        $processedData[$fieldName] = json_encode($texts);
                    } else {
                        $processedData[$fieldName] = implode(' ', $texts);
                    }
                }
            }
        }

        return $processedData;
    }

    private function needAIProcessing($processedData, $fields)
    {
        foreach ($fields as $field) {
            $fieldName = $field['field'];
            $fieldType = $field['type'];

            // 文件字段不需要AI处理
            if (in_array($fieldType, ['picUpload', 'fileUpload', 'picGallery'])) {
                continue;
            }

            // 如果有非文件字段没有数据，需要AI处理
            if (! isset($processedData[$fieldName]) || empty($processedData[$fieldName])) {
                return true;
            }
        }

        return false;
    }

    private function generateAIPrompt($data, $fields)
    {
        $fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE);

        // 智能模式：用户选择了多个元素，需要AI自动映射到字段
        if (isset($data['elements']) && is_array($data['elements'])) {
            $elementsJson = json_encode($data['elements'], JSON_UNESCAPED_UNICODE);

            return Prompts::getSmartCapturePrompt($fieldsJson, $elementsJson);
        }

        // 简单模式：用户只选择了页面，需要AI分析并填充字段
        $pageText = $data['page_text'] ?? '';

        return Prompts::getSimpleCapturePrompt($fieldsJson, $pageText);
    }

    private function parseResult($aiResult, $fields)
    {
        $parsedData = [];

        if (! is_array($aiResult)) {
            return $parsedData;
        }

        foreach ($fields as $field) {
            $fieldName = $field['field'];
            $fieldType = $field['type'];

            // 文件字段不需要AI处理
            if (in_array($fieldType, ['picUpload', 'fileUpload', 'picGallery'])) {
                continue;
            }

            // 从AI结果中获取字段值
            if (isset($aiResult[$fieldName])) {
                $value = $aiResult[$fieldName];

                // 根据字段类型处理值
                if ($fieldType === 'tags' || $fieldType === 'checkbox') {
                    if (is_string($value)) {
                        $parsedData[$fieldName] = json_encode(explode(',', $value));
                    } else {
                        $parsedData[$fieldName] = json_encode($value);
                    }
                } else {
                    $parsedData[$fieldName] = $value;
                }
            }
        }

        return $parsedData;
    }

    private function uploadImageFromUrl($url, $description = null)
    {
        try {
            return $this->mediaService->createMediaFromUrl((string) $url, $description);
        } catch (\Exception $e) {
            Log::error('上传图片失败: '.$e->getMessage(), ['url' => $url]);

            return null;
        }
    }
}
