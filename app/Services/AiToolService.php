<?php

namespace App\Services;

use App\Ai\AiToolRegistry;
use App\Models\Mold;
use Illuminate\Support\Facades\Log;

class AiToolService
{
    private const PHASE_UNDERSTAND = 'understand';

    private const PHASE_TOOL_SELECT = 'tool_select';

    private const PHASE_EXECUTE = 'execute';

    private const PHASE_SUMMARIZE = 'summarize';

    private GptService $gptService;

    public function __construct(GptService $gptService)
    {
        $this->gptService = $gptService;
    }

    public function handleClientQuery(string $message, ?int $userId = null, ?string $projectPrefix = null, array $context = [], ?callable $streamCallback = null): string
    {
        Log::info('AiToolService: starting', [
            'projectPrefix' => $projectPrefix,
            'message' => mb_substr($message, 0, 100),
        ]);

        $stateContext = [
            'original_message' => $message,
            'userId' => $userId,
            'projectPrefix' => $projectPrefix,
            'conversation_context' => $context,
            'understanding' => null,
            'tool_plan' => null,
            'executed_steps' => [],
            'accumulated_results' => [],
            'last_created_content' => null,
            'attempts' => 0,
            'max_attempts' => 30,
            'current_phase' => self::PHASE_UNDERSTAND,
            'streamCallback' => $streamCallback,
        ];

        $state = self::PHASE_UNDERSTAND;

        while ($state !== self::PHASE_SUMMARIZE && $stateContext['attempts'] < $stateContext['max_attempts']) {
            $stateContext['attempts']++;

            if ($streamCallback) {
                $streamCallback('phase_start', [
                    'phase' => $state,
                    'step' => $stateContext['attempts'],
                ]);
            }

            $state = $this->processState($state, $stateContext);

            if ($streamCallback) {
                $streamCallback('phase_end', [
                    'phase' => $stateContext['current_phase'],
                    'step' => $stateContext['attempts'],
                ]);
            }

            Log::info('AiToolService: state transition', [
                'attempt' => $stateContext['attempts'],
                'from' => $stateContext['current_phase'],
                'to' => $state,
                'steps_count' => count($stateContext['executed_steps']),
            ]);
            $stateContext['current_phase'] = $state;
        }

        return $this->generateAnswer($stateContext);
    }

    private function processState(string $state, array &$ctx): string
    {
        return match ($state) {
            self::PHASE_UNDERSTAND => $this->phaseUnderstand($ctx),
            self::PHASE_TOOL_SELECT => $this->phaseToolSelect($ctx),
            self::PHASE_EXECUTE => $this->phaseExecute($ctx),
            self::PHASE_SUMMARIZE => self::PHASE_SUMMARIZE,
            default => self::PHASE_SUMMARIZE,
        };
    }

    private function phaseUnderstand(array &$ctx): string
    {
        Log::info('AiToolService: Phase UNDERSTAND');

        $streamCallback = $ctx['streamCallback'] ?? null;
        if ($streamCallback) {
            $streamCallback('llm_start', ['phase' => 'understand', 'model' => 'unknown']);
        }

        $tools = AiToolRegistry::all();
        $modelsInfo = $ctx['projectPrefix'] ? $this->getProjectModelsInfo($ctx['projectPrefix']) : '';

        $prompt = <<<PROMPT
你是一个"问题理解助手"。请分析用户的问题，提取关键信息。

【可用工具】
{json_encode(array_values($tools), JSON_UNESCAPED_UNICODE)}

【项目模型信息】
{$modelsInfo}

【对话历史】
PROMPT;

        foreach ($ctx['conversation_context'] as $msg) {
            $sender = $msg['sender'] ?? '用户';
            $prompt .= "\n[{$sender}]: ".mb_substr($msg['content'], 0, 200);
        }

        $prompt .= <<<PROMPT

【当前问题】
{$ctx['original_message']}

请输出 JSON 格式的理解结果：
{
    "intent": "用户的主要意图，如：创建文章、查询列表、发布内容等",
    "entities": {
        "content_type": "内容类型，如：博客文章、产品、新闻等",
        "target_id": "目标ID，如果有的话",
        "target_title": "目标标题或关键词",
        "attributes": "其他关键属性"
    },
    "needs_more_info": true/false,
    "clarification_questions": "如果需要更多信息，列出问题"
}
PROMPT;

        $result = $this->gptService->chat('', [
            ['role' => 'system', 'content' => '你是一个JSON输出助手，只输出JSON格式，不要包含任何解释或markdown代码块。'],
            ['role' => 'user', 'content' => $prompt],
        ], $ctx['userId'], $ctx['original_message'], true, 'text');

        $text = $this->extractJsonText($result);

        if ($streamCallback) {
            $streamCallback('llm_end', [
                'phase' => 'understand',
                'finishReason' => 'stop',
                'content' => mb_substr($text, 0, 500),
            ]);
        }

        $understanding = json_decode($text, true);

        if (! is_array($understanding)) {
            Log::warning('AiToolService: failed to parse understanding result');
            $understanding = [
                'intent' => 'unknown',
                'entities' => [],
                'needs_more_info' => false,
            ];
        }

        $ctx['understanding'] = $understanding;

        Log::info('AiToolService: understanding result', ['understanding' => $understanding]);

        if (($understanding['needs_more_info'] ?? false) && empty($ctx['clarification_attempts'])) {
            $ctx['clarification_attempts'] = ($ctx['clarification_attempts'] ?? 0) + 1;
            if ($ctx['clarification_attempts'] < 2) {
                return self::PHASE_UNDERSTAND;
            }
        }

        return self::PHASE_TOOL_SELECT;
    }

    private function phaseToolSelect(array &$ctx): string
    {
        Log::info('AiToolService: Phase TOOL_SELECT');

        $tools = AiToolRegistry::all();
        $modelsInfo = $ctx['projectPrefix'] ? $this->getProjectModelsInfo($ctx['projectPrefix']) : '';
        $understandingJson = json_encode($ctx['understanding'] ?? [], JSON_UNESCAPED_UNICODE);
        $toolsJson = json_encode(array_values($tools), JSON_UNESCAPED_UNICODE);

        $stepsSummary = '';
        if (! empty($ctx['executed_steps'])) {
            $stepsSummary = "【已执行的步骤】\n";
            foreach ($ctx['executed_steps'] as $idx => $step) {
                $success = isset($step['result']['__error__']) ? '失败' : '成功';
                $stepsSummary .= '- 步骤'.($idx + 1).": {$step['tool']}, 状态: {$success}\n";
                if (isset($step['result']['__error__'])) {
                    $stepsSummary .= "  错误: {$step['result']['message']}\n";
                } else {
                    $stepsSummary .= '  结果: '.json_encode($step['result'], JSON_UNESCAPED_UNICODE)."\n";
                }
            }
        }

        $extractedData = '';
        if (! empty($ctx['extracted_data'])) {
            $extractedData = "【可用的数据（来自之前的步骤）】\n";
            foreach ($ctx['extracted_data'] as $key => $value) {
                $extractedData .= "- {$key}: ".json_encode($value, JSON_UNESCAPED_UNICODE)."\n";
            }
            $extractedData .= "\n你可以直接使用这些数据作为后续工具的参数，例如 params.id 可以使用 extracted_data 中的 id 值。\n";
        }

        $prompt = <<<PROMPT
你是一个"工具规划助手"。基于问题理解结果和已执行的步骤，制定下一步工具调用计划。

【问题理解结果】
{$understandingJson}

【已执行步骤】
{$stepsSummary}

{$extractedData}
【可用工具】
{$toolsJson}

【项目模型信息】
{$modelsInfo}

请输出 JSON 格式的工具计划（只输出下一步要执行的一个工具）：
{
    "plan": [
        {
            "step": 1,
            "tool": "工具名",
            "params": {参数对象},
            "reasoning": "为什么选择这个工具，以及如何使用上一步的数据"
        }
    ],
    "reasoning": "为什么需要执行这个工具"
}
PROMPT;

        $result = $this->gptService->chat('', [
            ['role' => 'system', 'content' => '你是一个JSON输出助手，只输出JSON格式。'],
            ['role' => 'user', 'content' => $prompt],
        ], $ctx['userId'], $ctx['original_message'], true, 'text');

        $text = $this->extractJsonText($result);
        $plan = json_decode($text, true);

        if (! is_array($plan) || empty($plan['plan'])) {
            Log::info('AiToolService: no more tools needed');

            return self::PHASE_SUMMARIZE;
        }

        $ctx['tool_plan'] = $plan['plan'] ?? [];

        Log::info('AiToolService: tool plan', ['plan' => $ctx['tool_plan']]);

        return self::PHASE_EXECUTE;
    }

    private function phaseExecute(array &$ctx): string
    {
        Log::info('AiToolService: Phase EXECUTE');

        $tools = AiToolRegistry::all();

        if (empty($ctx['tool_plan'])) {
            Log::info('AiToolService: no more tools in plan, re-evaluating...');

            return self::PHASE_TOOL_SELECT;
        }

        $currentStep = array_shift($ctx['tool_plan']);
        $toolName = $currentStep['tool'] ?? '';
        $params = $currentStep['params'] ?? [];

        if (empty($toolName) || ! isset($tools[$toolName])) {
            Log::warning('AiToolService: invalid tool', ['tool' => $toolName]);

            return self::PHASE_SUMMARIZE;
        }

        Log::info('AiToolService: executing tool', [
            'tool' => $toolName,
            'params' => $params,
            'step' => $currentStep['step'] ?? 0,
            'reasoning' => $currentStep['reasoning'] ?? '',
        ]);

        try {
            $result = AiToolRegistry::invoke($toolName, $params);

            if ($toolName === 'content_create' && isset($result['id']) && isset($result['title'])) {
                $ctx['last_created_content'] = [
                    'id' => $result['id'],
                    'title' => $result['title'],
                    'moldId' => $params['moldId'] ?? null,
                ];
            }

            $this->extractUsefulData($ctx, $toolName, $params, $result);

        } catch (\Throwable $e) {
            Log::error('AiToolService: tool invoke error', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);
            $result = ['__error__' => true, 'message' => $e->getMessage()];
        }

        $stepRecord = [
            'tool' => $toolName,
            'params' => $params,
            'result' => $result,
            'reasoning' => $currentStep['reasoning'] ?? '',
            'success' => ! isset($result['__error__']),
        ];

        $ctx['executed_steps'][] = $stepRecord;
        $ctx['accumulated_results'][] = $result;

        Log::info('AiToolService: step completed', [
            'tool' => $toolName,
            'success' => $stepRecord['success'],
            'has_more_data' => ! empty($ctx['extracted_data']),
        ]);

        if (count($ctx['executed_steps']) >= 15) {
            Log::warning('AiToolService: max steps reached');

            return self::PHASE_SUMMARIZE;
        }

        if (isset($result['__error__'])) {
            $ctx['error_count'] = ($ctx['error_count'] ?? 0) + 1;
            if ($ctx['error_count'] >= 3) {
                Log::warning('AiToolService: too many errors, stopping');

                return self::PHASE_SUMMARIZE;
            }

            return self::PHASE_TOOL_SELECT;
        }

        return self::PHASE_TOOL_SELECT;
    }

    private function generateAnswer(array &$ctx): string
    {
        Log::info('AiToolService: generating answer', [
            'steps_count' => count($ctx['executed_steps']),
        ]);

        $stepsJson = json_encode($ctx['executed_steps'], JSON_UNESCAPED_UNICODE);
        $understandingJson = json_encode($ctx['understanding'] ?? [], JSON_UNESCAPED_UNICODE);
        $lastCreated = $ctx['last_created_content'] ?? null;

        $lastCreatedInfo = '';
        if ($lastCreated) {
            $lastCreatedInfo = "【最近创建的内容】\n";
            $lastCreatedInfo .= "- ID: {$lastCreated['id']}, 标题: {$lastCreated['title']}\n\n";
        }

        $prompt = <<<PROMPT
你是一个智能助手，需要根据以下信息生成最终回复。

【用户原始问题】
{$ctx['original_message']}

【问题理解】
{$understandingJson}

{$lastCreatedInfo}
【工具执行轨迹】
{$stepsJson}

请生成一段 Markdown 格式的回复，内容要求：
1. 清晰说明执行结果
2. 如果有错误，解释原因并建议解决方案
3. 如果成功完成任务，给出确认信息
4. 内容简洁，不超过 800 字
5. 禁止使用 markdown 代码块包裹
PROMPT;

        $summary = $this->gptService->chat('', [
            ['role' => 'system', 'content' => '你是一个智能助手，直接输出 Markdown 格式的回复，不要使用代码块。'],
            ['role' => 'user', 'content' => $prompt],
        ], $ctx['userId'], $ctx['original_message'], true, 'text');

        $answer = '';
        if (is_array($summary)) {
            $answer = $summary['text'] ?? $summary['content'] ?? json_encode($summary);
        } elseif (is_string($summary)) {
            $answer = $this->stripMarkdownCodeBlocks($summary);
        }

        Log::info('AiToolService: answer generated', [
            'preview' => mb_substr($answer, 0, 100),
        ]);

        return $answer;
    }

    private function getProjectModelsInfo(string $projectPrefix): string
    {
        $previousPrefix = session('current_project_prefix');
        session(['current_project_prefix' => $projectPrefix]);

        try {
            $molds = Mold::get(['id', 'name', 'table_name', 'fields']);

            if ($molds->isEmpty()) {
                return '';
            }

            $models = [];
            foreach ($molds as $mold) {
                $fields = json_decode($mold->fields ?? '[]', true);
                $fieldList = [];
                if (is_array($fields)) {
                    foreach ($fields as $f) {
                        if (($f['type'] ?? '') === 'dividingLine') {
                            continue;
                        }
                        $fieldList[] = $f['field'] ?? '';
                    }
                }
                $models[] = [
                    'moldId' => $mold->id,
                    'name' => $mold->name,
                    'tableName' => $mold->table_name,
                    'fields' => array_filter($fieldList),
                ];
            }

            $modelsJson = json_encode($models, JSON_UNESCAPED_UNICODE);

            return <<<INFO

【项目内容模型信息】
以下是当前项目（{$projectPrefix}）的所有内容模型：

{$modelsJson}

重要提醒：
- 创建内容使用 content_create，moldId 用 id 字段
- 查询列表使用 content_list，tableName 用 table_name 字段
- 上架使用 content_publish，需要 moldId 和内容ID
- 下架使用 content_unpublish
INFO;
        } catch (\Throwable $e) {
            Log::error('AiToolService: getProjectModelsInfo error', ['error' => $e->getMessage()]);

            return '';
        } finally {
            session(['current_project_prefix' => $previousPrefix]);
        }
    }

    private function extractUsefulData(array &$ctx, string $toolName, array $params, $result): void
    {
        if (! isset($ctx['extracted_data'])) {
            $ctx['extracted_data'] = [];
        }

        switch ($toolName) {
            case 'content_list':
                if (isset($result['items']) && is_array($result['items']) && ! empty($result['items'])) {
                    $firstItem = $result['items'][0];
                    if (isset($firstItem['id'])) {
                        $ctx['extracted_data']['last_list_item'] = $firstItem;
                        $ctx['extracted_data']['last_list_item_id'] = $firstItem['id'];
                        if (isset($firstItem['title'])) {
                            $ctx['extracted_data']['last_list_item_title'] = $firstItem['title'];
                        }
                    }
                    $ctx['extracted_data']['last_list_count'] = $result['total'] ?? count($result['items']);
                }
                break;

            case 'content_detail':
                if (isset($result['id'])) {
                    $ctx['extracted_data']['current_content'] = $result;
                    $ctx['extracted_data']['current_content_id'] = $result['id'];
                    if (isset($result['title'])) {
                        $ctx['extracted_data']['current_content_title'] = $result['title'];
                    }
                    if (isset($result['mold_id'])) {
                        $ctx['extracted_data']['current_content_moldId'] = $result['mold_id'];
                    }
                }
                break;

            case 'content_create':
                if (isset($result['id'])) {
                    $ctx['extracted_data']['last_created_content'] = $result;
                    $ctx['extracted_data']['last_created_id'] = $result['id'];
                    if (isset($result['title'])) {
                        $ctx['extracted_data']['last_created_title'] = $result['title'];
                    }
                    if (isset($result['mold_id'])) {
                        $ctx['extracted_data']['last_created_moldId'] = $result['mold_id'];
                    }
                }
                break;

            case 'content_count':
                if (is_numeric($result)) {
                    $ctx['extracted_data']['last_count'] = (int) $result;
                }
                break;
        }

        Log::info('AiToolService: extracted data updated', ['keys' => array_keys($ctx['extracted_data'])]);
    }

    private function extractJsonText($result): string
    {
        $text = '';
        if (is_array($result)) {
            $text = $result['text'] ?? $result['content'] ?? '';
        } elseif (is_string($result)) {
            $text = $result;
        }

        $text = trim($text);
        if (str_starts_with($text, '```json')) {
            $text = substr($text, 7);
        }
        if (str_starts_with($text, '```')) {
            $text = substr($text, 3);
        }
        if (str_ends_with(trim($text), '```')) {
            $text = trim(substr($text, 0, -3));
        }

        return trim($text);
    }

    private function stripMarkdownCodeBlocks(string $text): string
    {
        $text = preg_replace('/^```markdown\s*/im', '', $text);
        $text = preg_replace('/^```\s*$/im', '', $text);
        $text = preg_replace('/\s*```$/im', '', $text);

        return trim($text);
    }
}
