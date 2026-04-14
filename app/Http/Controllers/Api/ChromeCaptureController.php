<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSysTaskJob;
use App\Models\SysTask;
use App\Services\ContentService;
use App\Services\GptService;
use App\Services\MediaService;
use App\Services\MoldService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChromeCaptureController extends Controller
{
    protected $moldService;

    protected $contentService;

    protected $mediaService;

    protected $gptService;

    protected $taskService;

    public function __construct(MoldService $moldService, ContentService $contentService, MediaService $mediaService, GptService $gptService, TaskService $taskService)
    {
        $this->moldService = $moldService;
        $this->contentService = $contentService;
        $this->mediaService = $mediaService;
        $this->gptService = $gptService;
        $this->taskService = $taskService;
    }

    /**
     * 处理Chrome插件采集的内容
     */
    public function capture(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required',
            'model_id' => 'required',
            'data' => 'required|array',
            'mode' => 'required|in:selection,smart',
        ]);

        $projectId = $validated['project_id'];
        $modelId = $validated['model_id'];
        $data = $validated['data'];
        $mode = $validated['mode'];

        Log::info('Chrome capture request', [
            'modelId' => $modelId,
            'mode' => $mode,
            'field_count' => count($data),
        ]);

        // 获取模型信息
        $model = $this->moldService->getMoldInfo($modelId);
        if (! $model) {
            return response()->json(['error' => '模型不存在'], 404);
        }

        // 处理采集的内容
        $processedData = $this->processCapture($data, $model, $mode);

        Log::info('Chrome capture processed', [
            'modelId' => $modelId,
            'processed_field_count' => count($processedData),
        ]);

        // 保存到数据库
        $result = $this->contentService->addContent($processedData, $modelId);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => '内容已成功保存',
        ]);
    }

    /**
     * 使用AI处理采集的内容（异步任务）
     */
    public function captureWithAI(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required',
            'model_id' => 'required',
            'data' => 'required|array',
            'mode' => 'required',
        ]);

        $projectId = $validated['project_id'];
        $modelId = $validated['modelId'] ?? $validated['model_id'];
        $data = $validated['data'];
        $mode = $validated['mode'];

        // 获取模型信息
        $model = $this->moldService->getMoldInfo($modelId);
        if (! $model) {
            return response()->json(['error' => '模型不存在'], 404);
        }

        // 获取模型字段
        $fields = $model->fields ?? [];
        if (! is_array($fields)) {
            $fields = json_decode($fields, true) ?? [];
        }

        // 创建异步任务
        $prefix = (string) session('current_project_prefix');
        if (! $prefix) {
            return response()->json(['error' => 'Missing project_prefix'], 400);
        }

        $task = new SysTask;
        $task->project_prefix = $prefix;
        $task->domain = 'chrome_capture';
        $task->type = 'chrome_capture_ai';
        $task->title = 'Chrome AI采集';
        $task->status = SysTask::STATUS_PENDING;
        $task->payload = [
            'model_id' => $modelId,
            'data' => $data,
            'mode' => $mode,
            'fields' => $fields,
        ];
        $task->save();

        // 分发任务到队列
        ProcessSysTaskJob::dispatch($task->id);

        return response()->json([
            'success' => true,
            'task_id' => $task->id,
            'message' => 'AI采集任务已创建，正在后台处理',
        ]);
    }

    /**
     * 快速采集（右键菜单）
     */
    public function quickCapture(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required',
            'model_id' => 'required',
            'data' => 'required|array',
        ]);

        $projectId = $validated['project_id'];
        $modelId = $validated['model_id'];
        $data = $validated['data'];

        // 获取模型信息
        $model = $this->moldService->getMoldInfo($modelId);
        if (! $model) {
            // 如果没有指定模型，使用默认的快速保存模型
            $quickData = [
                'title' => $data['title'] ?? '快速保存',
                'url' => $data['url'] ?? '',
                'content' => $data['content'] ?? '',
                'type' => $data['type'] ?? 'text',
                'timestamp' => $data['timestamp'] ?? now(),
            ];

            // 保存到快速保存表
            $result = $this->saveQuickCapture($quickData, $projectId);
        } else {
            // 根据模型字段智能映射
            $processedData = $this->mapToModel($data, $model);
            $result = $this->contentService->addContent($processedData, $modelId);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => '快速保存成功',
        ]);
    }

    /**
     * 处理采集的内容
     */
    private function processCapture($data, $model, $mode)
    {
        $processedData = [];
        $fields = $model->fields ?? [];

        if (! is_array($fields)) {
            $fields = json_decode($fields, true) ?? [];
        }

        if ($mode === 'selection') {
            // 新格式：数据按字段分组
            if (isset($data['fields']) && is_array($data['fields'])) {
                foreach ($fields as $field) {
                    $fieldName = $field['field'];
                    $fieldType = $field['type'];

                    // 如果前端已经提供了字段数据，直接使用
                    if (isset($data['fields'][$fieldName])) {
                        $fieldData = $data['fields'][$fieldName];

                        // 根据字段类型处理数据
                        // 判断是否是索引数组（多个元素）
                        $isIndexedArray = is_array($fieldData) && array_key_exists(0, $fieldData);

                        if ($isIndexedArray) {
                            // 多个元素
                            if ($fieldType === 'richText') {
                                $html = '';
                                foreach ($fieldData as $item) {
                                    $html .= '<div>'.($item['html'] ?? $item['text'] ?? '').'</div>';
                                }
                                $processedData[$fieldName] = $html;
                            } elseif ($fieldType === 'textarea') {
                                $text = '';
                                foreach ($fieldData as $item) {
                                    $text .= ($item['text'] ?? '')."\n";
                                }
                                $processedData[$fieldName] = trim($text);
                            } elseif ($fieldType === 'tags' || $fieldType === 'checkbox') {
                                $tags = [];
                                foreach ($fieldData as $item) {
                                    $tags[] = $item['text'] ?? '';
                                }
                                $processedData[$fieldName] = json_encode($tags);
                            } else {
                                // 默认取第一个元素的文本
                                $processedData[$fieldName] = $fieldData[0]['text'] ?? '';
                            }
                        } else {
                            // 单个元素（关联数组或对象）
                            $text = is_array($fieldData) ? ($fieldData['text'] ?? '') : ($fieldData->text ?? '');
                            $html = is_array($fieldData) ? ($fieldData['html'] ?? '') : ($fieldData->html ?? '');
                            $src = is_array($fieldData) ? ($fieldData['src'] ?? '') : ($fieldData->src ?? '');
                            $attributes = is_array($fieldData) ? ($fieldData['attributes'] ?? []) : ($fieldData->attributes ?? []);

                            if ($fieldType === 'richText') {
                                $processedData[$fieldName] = $html ?: $text;
                            } elseif ($fieldType === 'input' || $fieldType === 'textarea') {
                                $processedData[$fieldName] = $text;
                            } elseif (in_array($fieldType, ['picUpload', 'fileUpload'])) {
                                $imageUrl = $src ?: (is_array($attributes) ? ($attributes['src'] ?? '') : ($attributes->src ?? ''));
                                // 单文件/单图：只存 URL 字符串
                                if ($imageUrl) {
                                    $media = $this->uploadImageFromUrl($imageUrl, "Chrome采集: {$fieldName}");
                                    $processedData[$fieldName] = $media ? $media->url : $imageUrl;
                                } else {
                                    $processedData[$fieldName] = '';
                                }
                            } elseif ($fieldType === 'picGallery') {
                                $imageUrl = $src ?: (is_array($attributes) ? ($attributes['src'] ?? '') : ($attributes->src ?? ''));
                                // 图片集：存 JSON URL 数组
                                $existing = $processedData[$fieldName] ?? [];
                                if (is_string($existing)) {
                                    $existing = json_decode($existing, true) ?: [];
                                }
                                if ($imageUrl) {
                                    $media = $this->uploadImageFromUrl($imageUrl, "Chrome采集: {$fieldName}");
                                    $existing[] = $media ? $media->url : $imageUrl;
                                }
                                $processedData[$fieldName] = json_encode($existing, JSON_UNESCAPED_UNICODE);
                            } else {
                                $processedData[$fieldName] = $text;
                            }
                        }
                    }
                }
            } else {
                // 兼容旧格式：contents 数组
                $contents = $data['contents'] ?? [];

                foreach ($fields as $field) {
                    $fieldName = $field['field'];
                    $fieldType = $field['type'];

                    // 智能匹配字段
                    if ($fieldName === 'title') {
                        $processedData[$fieldName] = $data['title'] ?? '';
                    } elseif ($fieldName === 'url' || $fieldName === 'source_url') {
                        $processedData[$fieldName] = $data['url'] ?? '';
                    } elseif ($fieldType === 'richText' && count($contents) > 0) {
                        // 合并所有选中的内容
                        $html = '';
                        foreach ($contents as $content) {
                            $html .= '<div>'.$content['html'].'</div>';
                        }
                        $processedData[$fieldName] = $html;
                    } elseif ($fieldType === 'textarea' && count($contents) > 0) {
                        // 合并文本内容
                        $text = '';
                        foreach ($contents as $content) {
                            $text .= $content['text']."\n";
                        }
                        $processedData[$fieldName] = trim($text);
                    }
                }
            }
        } elseif ($mode === 'smart') {
            // 智能模式的数据处理保持不变
            $aiResult = $data['ai_result'] ?? [];
            foreach ($fields as $field) {
                $fieldName = $field['field'];
                if (isset($aiResult[$fieldName])) {
                    $processedData[$fieldName] = $aiResult[$fieldName];
                }
            }
        }

        return $processedData;
    }

    /**
     * 处理采集的数据（拼接数组、处理图片等）
     */
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
                        // 富文本字段用换行符拼接
                        $processedData[$fieldName] = implode('<br>', $texts);
                    } elseif ($fieldType === 'textarea') {
                        // 文本域用换行符拼接
                        $processedData[$fieldName] = implode("\n", $texts);
                    } elseif ($fieldType === 'tags' || $fieldType === 'checkbox') {
                        // 标签字段转JSON
                        $processedData[$fieldName] = json_encode($texts);
                    } else {
                        // 其他字段用空格拼接
                        $processedData[$fieldName] = implode(' ', $texts);
                    }
                }
            }
        }

        return $processedData;
    }

    /**
     * 检查是否需要AI处理
     */
    private function needAIProcessing($processedData, $fields)
    {
        foreach ($fields as $field) {
            $fieldName = $field['field'];
            $fieldType = $field['type'];

            // 文件字段不需要AI处理
            if (in_array($fieldType, ['picUpload', 'fileUpload'])) {
                continue;
            }

            // 如果有非文件字段没有数据，需要AI处理
            if (! isset($processedData[$fieldName]) || empty($processedData[$fieldName])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 生成AI提示词
     */
    private function generateAIPrompt($data, $fields)
    {
        $fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE);

        // 智能模式：用户选择了多个元素，需要AI自动映射到字段
        if (isset($data['elements']) && is_array($data['elements'])) {
            $elementsJson = json_encode($data['elements'], JSON_UNESCAPED_UNICODE);

            $prompt = <<<PROMPT
你是一个内容提取专家。请根据用户选择的网页元素，智能提取并映射到目标模型字段。

网页信息：
标题：{$data['title']}
URL：{$data['url']}

用户选择的元素：
$elementsJson

目标模型字段：
$fieldsJson

请分析每个元素的内容和特征，智能判断它应该对应哪个字段，并提取相应的值。

返回格式：
{
  "field_name": "提取的值",
  ...
}

规则：
1. 根据元素的内容类型（文本、图片、链接等）和字段类型进行匹配
2. 如果元素包含图片URL，映射到图片上传字段
3. 如果元素是标题，映射到标题字段
4. 如果元素是正文内容，映射到内容字段
5. 如果无法确定映射关系，可以跳过该元素
6. 只返回JSON，不要其他文字
PROMPT;
        } else {
            // 传统模式：用户已经按字段分组
            $pageJson = json_encode($data, JSON_UNESCAPED_UNICODE);

            $prompt = <<<PROMPT
你是一个内容提取专家。请根据网页内容和目标字段，智能提取并生成对应的内容。

网页数据：
$pageJson

目标字段：

请以JSON格式返回提取的数据，格式如下：
{
  "field_name": "提取的值",
  ...
}

只返回JSON，不要其他文字。
PROMPT;
        }

        return $prompt;
    }

    /**
     * 解析返回的结果
     */
    private function parseResult($result, $fields)
    {
        try {
            // 处理返回的结果
            if (is_string($result)) {
                $data = json_decode($result, true);
            } elseif (is_array($result)) {
                $data = $result;
            } else {
                return [];
            }

            if (! $data) {
                return [];
            }

            // 验证并过滤字段
            $processedData = [];
            foreach ($fields as $field) {
                $fieldName = $field['field'];
                if (isset($data[$fieldName])) {
                    $processedData[$fieldName] = $data[$fieldName];
                }
            }

            return $processedData;
        } catch (\Exception $e) {
            Log::error('解析响应失败: '.$e->getMessage());

            return [];
        }
    }

    /**
     * 智能映射到模型
     */
    private function mapToModel($data, $model)
    {
        $processedData = [];
        $fields = $model->schemas;

        foreach ($fields as $field) {
            $fieldName = $field['field'];
            $fieldType = $field['type'];

            // 智能映射规则
            if ($fieldName === 'title') {
                $processedData[$fieldName] = $data['title'] ?? $data['content'] ?? '';
            } elseif ($fieldName === 'url' || $fieldName === 'source_url' || $fieldName === 'link') {
                $processedData[$fieldName] = $data['url'] ?? $data['content'] ?? '';
            } elseif ($fieldName === 'content' || $fieldName === 'description') {
                $processedData[$fieldName] = $data['content'] ?? '';
            } elseif ($fieldName === 'type' || $fieldName === 'category') {
                $processedData[$fieldName] = $data['type'] ?? 'web';
            } elseif ($fieldName === 'created_at' || $fieldName === 'timestamp') {
                $processedData[$fieldName] = $data['timestamp'] ?? now();
            }
        }

        return $processedData;
    }

    /**
     * 保存快速采集
     */
    private function saveQuickCapture($data, $projectId)
    {
        // 这里可以保存到一个专门的快速保存表
        // 或者创建一个默认的快速保存模型
        return [
            'id' => uniqid(),
            'project_id' => $projectId,
            'data' => $data,
            'created_at' => now(),
        ];
    }

    /**
     * 获取任务状态
     */
    public function getTaskStatus($taskId)
    {
        $prefix = (string) session('current_project_prefix');
        $task = SysTask::where('id', $taskId)
            ->where('project_prefix', $prefix)
            ->first();

        if (! $task) {
            return response()->json(['error' => '任务不存在'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $task->id,
                'status' => $task->status,
                'title' => $task->title,
                'created_at' => $task->created_at,
                'started_at' => $task->started_at,
                'finished_at' => $task->finished_at,
                'error_message' => $task->error_message,
                'result' => $task->result,
            ],
        ]);
    }

    /**
     * 媒体资源采集（异步任务）
     */
    public function captureMedia(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required',
            'media_urls' => 'required|array',
            'folder_id' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        $projectId = $validated['project_id'];
        $mediaUrls = $validated['media_urls'];
        $folderId = $validated['folder_id'] ?? null;
        $description = $validated['description'] ?? 'Chrome插件采集的媒体资源';

        // 验证媒体资源URL格式
        $formattedUrls = [];
        foreach ($mediaUrls as $mediaUrl) {
            if (is_array($mediaUrl)) {
                if (isset($mediaUrl['url']) && ! empty($mediaUrl['url'])) {
                    $formattedUrls[] = [
                        'url' => $mediaUrl['url'],
                        'filename' => $mediaUrl['filename'] ?? null,
                    ];
                }
            } elseif (is_string($mediaUrl) && ! empty($mediaUrl)) {
                $formattedUrls[] = [
                    'url' => $mediaUrl,
                    'filename' => null,
                ];
            }
        }

        if (empty($formattedUrls)) {
            return response()->json(['error' => '媒体资源URL不能为空'], 400);
        }

        // 创建媒体资源采集任务
        $requestedBy = session('user_id');
        $task = $this->taskService->createMediaCaptureTask(
            $formattedUrls,
            $folderId,
            $description,
            $requestedBy
        );

        return response()->json([
            'success' => true,
            'task_id' => $task->id,
            'message' => '媒体资源采集任务已创建，正在后台处理',
        ]);
    }

    /**
     * 从 URL 抓取并上传图片
     */
    private function uploadImageFromUrl($imageUrl, $description = null)
    {
        try {
            return $this->mediaService->createMediaFromUrl((string) $imageUrl, $description, [
                'custom_filename' => uniqid('chrome_', true),
            ]);
        } catch (\Exception $e) {
            Log::error('图片上传失败: '.$e->getMessage(), ['url' => $imageUrl]);

            return null;
        }
    }
}
