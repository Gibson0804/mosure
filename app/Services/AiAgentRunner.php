<?php

namespace App\Services;

use App\Ai\AiToolRegistry;
use App\Models\ClientAiConversation;
use App\Models\Mold;
use App\Models\SysAiSession;
use App\Models\SysTask;
use App\Models\SysTaskStep;
use Illuminate\Support\Facades\Log;

class AiAgentRunner
{
    private GptService $gpt;

    // 上下文配置
    private const CONTEXT_MAX_ROUNDS = 5;

    private const CONTEXT_TOKEN_THRESHOLD = 3000;

    public function __construct(GptService $gpt)
    {
        $this->gpt = $gpt;
    }

    /**
     * 主入口：Plan → Execute 两阶段架构
     */
    public function run(SysTask $task): void
    {
        if ($task->canceled_at) {
            $task->update(['status' => SysTask::STATUS_CANCELED, 'finished_at' => now()]);

            return;
        }

        $payload = $task->payload ?? [];
        $question = (string) ($payload['question'] ?? '');
        $options = is_array($payload['options'] ?? null) ? ($payload['options'] ?? []) : [];

        if ($question === '') {
            $task->update([
                'status' => SysTask::STATUS_FAILED,
                'error_message' => '缺少 question',
                'finished_at' => now(),
            ]);

            return;
        }

        $userId = $task->requested_by ?? null;
        $sessionId = isset($payload['session_id']) ? (int) $payload['session_id'] : null;
        $stepMaxAttempts = (int) ($options['step_max_attempts'] ?? 3);
        $maxStepsLimit = (int) ($options['max_steps'] ?? 10);

        // 阶段零：标记为处理中（规划阶段，progress_total 仍为 0）
        $task->update([
            'status' => SysTask::STATUS_PROCESSING,
            'started_at' => $task->started_at ?: now(),
            'stage' => 'planning',
        ]);

        try {
            // 构建对话上下文
            $conversationContext = $this->buildConversationContext($sessionId);

            // ====== 阶段一：规划 ======
            $tools = AiToolRegistry::all();
            $plan = $this->plan($task, $tools, $question, $userId, $conversationContext);

            Log::info('AiAgentRunner plan result', ['task_id' => $task->id, 'plan' => $plan]);

            $mode = (string) ($plan['mode'] ?? 'direct');
            $plannedSteps = (array) ($plan['steps'] ?? []);

            // 安全限制
            if (count($plannedSteps) > $maxStepsLimit) {
                $plannedSteps = array_slice($plannedSteps, 0, $maxStepsLimit);
            }

            // 保存规划到 payload
            $currentPayload = $task->payload ?? [];
            $currentPayload['plan'] = $plan;
            $task->update(['payload' => $currentPayload]);

            // ====== 阶段二：执行 ======
            if ($mode === 'direct' || empty($plannedSteps)) {
                $this->executeDirect($task, $question, $userId, $sessionId, $conversationContext);
            } else {
                $this->executeTools($task, $plannedSteps, $question, $userId, $stepMaxAttempts, $sessionId, $conversationContext);
            }
        } catch (\Throwable $e) {
            Log::error('AiAgentRunner fatal error', ['task_id' => $task->id, 'error' => $e->getMessage()]);
            $task->update([
                'status' => SysTask::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
                'stage' => null,
            ]);
        }
    }

    /**
     * 阶段一：任务规划 —— 让 LLM 分析问题，决定使用哪些工具
     */
    private function plan(SysTask $task, array $tools, string $question, ?int $userId, string $conversationContext = ''): array
    {
        $prompt = $this->buildPlanPrompt($tools, $question, $conversationContext);
        $result = $this->gpt->chat('', [
            ['role' => 'user', 'content' => $prompt],
        ], $userId, $question, true);

        return $this->normalizePlanResult($result);
    }

    /**
     * 阶段二 - 直接回答模式：不调用工具，纯 LLM 回答
     */
    private function executeDirect(SysTask $task, string $question, ?int $userId, ?int $sessionId = null, string $conversationContext = ''): void
    {
        // 总步骤 = 1（回答本身）
        $task->update([
            'progress_total' => 1,
            'progress_done' => 0,
            'stage' => 'executing',
        ]);

        $prompt = $this->buildDirectAnswerPrompt($question, $conversationContext);
        $summary = $this->gpt->chat('', [
            ['role' => 'user', 'content' => $prompt],
        ], $userId, $question, true, 'text');

        $answer = $this->extractTextAnswer($summary);

        $task->update([
            'status' => SysTask::STATUS_SUCCESS,
            'finished_at' => now(),
            'progress_done' => 1,
            'stage' => null,
            'result' => [
                'mode' => 'direct',
                'summary_md' => $answer,
                'steps' => [],
            ],
            'error_message' => null,
        ]);

        ClientAiConversation::create([
            'session_id' => $sessionId,
            'task_id' => (int) $task->id,
            'user_id' => $userId,
            'model' => '',
            'tool' => null,
            'tool_params' => null,
            'tool_result' => null,
            'question' => $question,
            'answer' => (string) $answer,
        ]);

        $this->updateSessionStats($sessionId);
    }

    /**
     * 阶段二 - 工具执行模式：按规划逐步执行工具，最后汇总
     */
    private function executeTools(SysTask $task, array $plannedSteps, string $question, ?int $userId, int $stepMaxAttempts, ?int $sessionId = null, string $conversationContext = ''): void
    {
        $totalSteps = count($plannedSteps) + 1; // +1 为最终汇总步
        $task->update([
            'progress_total' => $totalSteps,
            'progress_done' => 0,
            'progress_failed' => 0,
            'stage' => 'executing',
        ]);

        $stepsHistory = [];
        $completed = 0;

        foreach ($plannedSteps as $idx => $plannedStep) {
            $task->refresh();
            if ($task->canceled_at) {
                $task->update(['status' => SysTask::STATUS_CANCELED, 'finished_at' => now(), 'stage' => null]);

                return;
            }

            $toolName = (string) ($plannedStep['tool'] ?? '');
            $params = (array) ($plannedStep['params'] ?? []);
            $purpose = (string) ($plannedStep['purpose'] ?? '');

            // 解析跨步骤引用占位符（如 "$step1.data.0.id"）
            $params = $this->resolveStepReferences($params, $stepsHistory);

            // 校验工具是否存在
            $allTools = AiToolRegistry::all();
            if (! isset($allTools[$toolName])) {
                Log::warning('AiAgentRunner: planned tool not found, skipping', ['task_id' => $task->id, 'tool' => $toolName]);
                $stepsHistory[] = [
                    'tool' => $toolName,
                    'params' => $params,
                    'result' => null,
                    'skipped' => true,
                    'error' => '工具不存在: '.$toolName,
                ];

                continue;
            }

            // 创建 step 记录
            $step = new SysTaskStep;
            $step->task_id = (int) $task->id;
            $step->seq = $idx + 1;
            $step->title = $purpose ?: ('调用工具: '.$toolName);
            $step->status = 'running';
            $step->payload = ['tool' => $toolName, 'params' => $params];
            $step->attempts = 0;
            $step->max_attempts = $stepMaxAttempts;
            $step->started_at = now();
            $step->save();

            $result = null;
            $error = null;

            for ($attempt = 1; $attempt <= $stepMaxAttempts; $attempt++) {
                try {
                    $step->update(['attempts' => $attempt]);
                    $result = AiToolRegistry::invoke($toolName, $params);
                    $error = null;
                    break;
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                    Log::error('AiAgentRunner step error', [
                        'task_id' => $task->id, 'step' => $step->seq, 'attempt' => $attempt, 'err' => $error,
                    ]);
                    if ($attempt < $stepMaxAttempts) {
                        usleep((int) pow(3, $attempt - 1) * 200000);
                    }
                }
            }

            $step->finished_at = now();
            if ($error === null) {
                $step->status = 'success';
                $step->result = $result;
                $step->error_message = null;
                $step->save();
                $completed++;
                $task->update(['progress_done' => $completed]);
            } else {
                $step->status = 'failed';
                $step->error_message = $error;
                $step->save();

                // 单步失败不终止整个任务，记录后继续
                Log::warning('AiAgentRunner step failed, continuing', ['task_id' => $task->id, 'step' => $step->seq]);
                $task->update(['progress_failed' => ($task->progress_failed ?? 0) + 1]);
            }

            $stepsHistory[] = [
                'tool' => $toolName,
                'params' => $params,
                'purpose' => $purpose,
                'result' => $result,
                'error' => $error,
            ];
        }

        // 最终汇总步
        $task->refresh();
        if ($task->canceled_at) {
            $task->update(['status' => SysTask::STATUS_CANCELED, 'finished_at' => now(), 'stage' => null]);

            return;
        }

        $task->update(['stage' => 'summarizing']);

        $hasResults = false;
        foreach ($stepsHistory as $sh) {
            if (! empty($sh['result']) && empty($sh['error']) && empty($sh['skipped'])) {
                $hasResults = true;
                break;
            }
        }

        $answer = $hasResults
            ? $this->extractTextAnswer($this->gpt->chat('', [
                ['role' => 'user', 'content' => $this->buildSummaryPrompt($question, $stepsHistory, $conversationContext)],
            ], $userId, $question, true, 'text'))
            : '很抱歉，我在执行任务时遇到了一些问题，未能获取到有效数据。';

        $task->update([
            'status' => SysTask::STATUS_SUCCESS,
            'finished_at' => now(),
            'progress_done' => $completed + 1,
            'stage' => null,
            'result' => [
                'mode' => 'tools',
                'summary_md' => $answer,
                'steps' => $stepsHistory,
            ],
            'error_message' => null,
        ]);

        $lastStep = end($stepsHistory) ?: [];
        ClientAiConversation::create([
            'session_id' => $sessionId,
            'task_id' => (int) $task->id,
            'user_id' => $userId,
            'model' => '',
            'tool' => (string) ($lastStep['tool'] ?? '') ?: null,
            'tool_params' => (array) ($lastStep['params'] ?? []) ?: null,
            'tool_result' => $stepsHistory,
            'question' => $question,
            'answer' => (string) $answer,
        ]);

        $this->updateSessionStats($sessionId);
    }

    /**
     * 更新 session 统计信息，并在消息过多时触发上下文压缩
     */
    private function updateSessionStats(?int $sessionId): void
    {
        if (! $sessionId) {
            return;
        }
        try {
            $session = SysAiSession::find($sessionId);
            if (! $session) {
                return;
            }

            $msgCount = ClientAiConversation::where('session_id', $sessionId)->count();
            $session->update([
                'message_count' => $msgCount,
                'last_message_at' => now(),
            ]);

            // 当消息数超过上下文保留轮数时，触发压缩
            if ($msgCount > self::CONTEXT_MAX_ROUNDS) {
                $this->compressSessionContext($session);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to update session stats', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 压缩 session 上下文：将较早的对话压缩为摘要
     */
    private function compressSessionContext(SysAiSession $session): void
    {
        try {
            $allMessages = ClientAiConversation::where('session_id', $session->id)
                ->orderBy('id')
                ->get(['question', 'answer']);

            if ($allMessages->count() <= self::CONTEXT_MAX_ROUNDS) {
                return;
            }

            // 保留最近 CONTEXT_MAX_ROUNDS 轮不压缩，压缩更早的
            $toCompress = $allMessages->slice(0, $allMessages->count() - self::CONTEXT_MAX_ROUNDS)->values();
            if ($toCompress->isEmpty()) {
                return;
            }

            // 构建待压缩的对话文本
            $dialogText = '';
            foreach ($toCompress as $msg) {
                $dialogText .= '用户: '.mb_substr((string) $msg->question, 0, 300)."\n";
                $dialogText .= '助手: '.mb_substr((string) $msg->answer, 0, 500)."\n---\n";
            }

            // 如果已有摘要，合并进去
            $existingSummary = $session->context_summary ?? '';
            $toSummarize = '';
            if (! empty($existingSummary)) {
                $toSummarize = "之前的摘要:\n{$existingSummary}\n\n新增对话:\n{$dialogText}";
            } else {
                $toSummarize = $dialogText;
            }

            $prompt = <<<PROMPT
请将以下对话历史压缩为简洁的上下文摘要。保留关键事实、用户偏好、重要结论和操作结果。

## 对话历史
{$toSummarize}

## 输出要求
1. 输出一段不超过 500 字的中文摘要
2. 保留关键信息：用户问了什么、系统做了什么、结果如何
3. 去除冗余细节，只保留对后续对话有帮助的信息
4. 直接输出摘要文本，不要包含任何标题或格式标记
PROMPT;

            $result = $this->gpt->chat('', [
                ['role' => 'user', 'content' => $prompt],
            ], null, '', true, 'text');

            $summary = $this->extractTextAnswer($result);

            if (! empty(trim($summary))) {
                $tokenEstimate = (int) (mb_strlen($summary) * 1.5);
                $session->update([
                    'context_summary' => $summary,
                    'context_token_count' => $tokenEstimate,
                ]);
                Log::info('Session context compressed', ['session_id' => $session->id, 'summary_length' => mb_strlen($summary)]);
            }
        } catch (\Throwable $e) {
            Log::warning('compressSessionContext failed', ['session_id' => $session->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 构建对话上下文：从 session 中获取最近 N 轮对话，拼装为 Prompt 片段
     */
    private function buildConversationContext(?int $sessionId): string
    {
        if (! $sessionId) {
            return '';
        }

        try {
            $session = SysAiSession::find($sessionId);
            if (! $session) {
                return '';
            }

            // 优先使用压缩摘要
            $summaryPart = '';
            if (! empty($session->context_summary)) {
                $summaryPart = "\n## 之前的对话摘要\n{$session->context_summary}\n";
            }

            // 获取最近 N 轮原始对话
            $recentMessages = ClientAiConversation::where('session_id', $sessionId)
                ->orderByDesc('id')
                ->limit(self::CONTEXT_MAX_ROUNDS)
                ->get(['question', 'answer'])
                ->reverse()
                ->values();

            if ($recentMessages->isEmpty() && empty($summaryPart)) {
                return '';
            }

            $historyPart = '';
            if ($recentMessages->isNotEmpty()) {
                $rounds = [];
                foreach ($recentMessages as $msg) {
                    $q = mb_substr((string) $msg->question, 0, 200);
                    $a = mb_substr((string) $msg->answer, 0, 500);
                    $rounds[] = "用户: {$q}\n助手: {$a}";
                }
                $historyText = implode("\n---\n", $rounds);
                $historyPart = "\n## 最近的对话历史\n{$historyText}\n";
            }

            $context = $summaryPart.$historyPart;
            if (empty(trim($context))) {
                return '';
            }

            return "\n".trim($context)."\n\n## 重要提示\n请结合上述对话历史来理解用户当前问题中的指代关系（如\"它\"、\"上面的\"、\"刚才的\"等）。\n";
        } catch (\Throwable $e) {
            Log::warning('buildConversationContext failed', ['session_id' => $sessionId, 'error' => $e->getMessage()]);

            return '';
        }
    }

    // ==================== Prompt 构建 ====================

    /**
     * 规划阶段 Prompt：让 LLM 分析问题并制定完整执行计划
     */
    private function buildPlanPrompt(array $tools, string $question, string $conversationContext = ''): string
    {
        $toolPayload = array_map(function ($tool) {
            return [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'params' => $tool['params'],
            ];
        }, array_values($tools));

        $toolsJson = json_encode($toolPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $modelsJson = json_encode($this->getProjectModelsInfo(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $ds = '$'; // 避免 heredoc 中 $step 被 PHP 当变量解析

        return <<<PROMPT
你是一名任务规划器。请分析用户的问题，判断是否需要调用系统工具来回答，并制定完整的执行计划。

## 可用工具列表
{$toolsJson}

## 当前项目的内容模型
以下是当前项目中已定义的所有内容模型，包含模型名称、表标识（tableName）和字段信息。
在规划 content_list / content_detail / content_count 等工具时，请直接使用下方的 table_name 作为 tableName 参数，无需再调用 list_models 获取。
{$modelsJson}

## 用户问题
{$question}
{$conversationContext}
## 输出规则
请输出一个 JSON 对象，包含以下字段：

1. **mode**（必填）：执行模式
   - `"direct"`：问题无需调用任何工具，可直接用大模型知识回答（如常识问答、闲聊、概念解释等）
   - `"tools"`：问题需要调用一个或多个工具获取数据后才能回答

2. **reason**（必填）：简要说明为什么选择该模式（中文，一句话）

3. **steps**（mode 为 tools 时必填）：执行步骤数组，每个元素包含：
   - `tool`：工具名称（必须是可用工具列表中的名称）
   - `params`：工具参数（必须是该工具定义的参数，不要臆造）
   - `purpose`：该步骤的目的（中文，简短描述）

## 跨步骤引用
当后续步骤的参数依赖前面步骤的执行结果时，使用占位符 `"{$ds}stepN.字段路径"` 引用，N 从 1 开始。
系统会在执行时自动将占位符替换为实际值。支持的路径格式：
- `"{$ds}step1.id"` — 引用第 1 步结果中的 id 字段
- `"{$ds}step1.items.0.id"` — 引用第 1 步结果中 items 数组第一个元素的 id
- `"{$ds}step1.items.0.title"` — 引用第 1 步结果中 items 数组第一个元素的 title

**注意：占位符必须是合法的 JSON 字符串（用双引号包裹），不要写中文描述或裸字符。**

## 重要约束
- 只规划"确定能用已有工具完成"的步骤，不要规划不存在的工具
- 查询内容时，tableName 必须使用「当前项目的内容模型」中提供的 table_name，不要猜测或缩写
- 创建内容时，moldId 必须使用「当前项目的内容模型」中提供的 id，不要猜测或留空
- 步骤数量应尽量精简，通常不超过 5 步
- 若用户问题模糊或属于闲聊，直接用 `"direct"` 模式
- 仅输出 JSON，不要包含任何额外文字或 markdown 代码块

## 示例输出
直接回答：{"mode":"direct","reason":"这是一个常识性问题，无需查询系统数据"}
需要工具：{"mode":"tools","reason":"需要查询待办事项数据","steps":[{"tool":"content_list","params":{"tableName":"实际的table_name","params":{},"fields":["title","status"],"page":1,"pageSize":20},"purpose":"查询待办事项列表"}]}
创建内容：{"mode":"tools","reason":"需要创建一条新内容","steps":[{"tool":"content_create","params":{"moldId":实际的模型id,"contentInfo":{"title":"示例标题"}},"purpose":"创建新内容"}]}
跨步骤引用：{"mode":"tools","reason":"需要先查询再修改","steps":[{"tool":"content_list","params":{"tableName":"实际的table_name","params":{},"fields":["id","title"],"page":1,"pageSize":1},"purpose":"查询最新一条内容"},{"tool":"content_update","params":{"moldId":2,"contentId":"{$ds}step1.items.0.id","contentInfo":{"title":"新标题"}},"purpose":"修改该内容的标题"}]}
PROMPT;
    }

    /**
     * 获取当前项目所有内容模型的摘要信息
     */
    private function getProjectModelsInfo(): array
    {
        try {
            $molds = Mold::all();

            return $molds->map(function ($mold) {
                $fields = json_decode($mold->fields ?? '[]', true) ?: [];
                $fieldsSummary = array_values(array_filter(array_map(function ($f) {
                    if (! is_array($f) || ($f['type'] ?? '') === 'dividingLine') {
                        return null;
                    }

                    return [
                        'field' => $f['field'] ?? '',
                        'label' => $f['label'] ?? '',
                        'type' => $f['type'] ?? '',
                    ];
                }, $fields)));

                return [
                    'id' => $mold->id,
                    'name' => $mold->name,
                    'table_name' => $mold->table_name,
                    'mold_type' => $mold->mold_type,
                    'fields' => $fieldsSummary,
                ];
            })->toArray();
        } catch (\Throwable $e) {
            Log::warning('AiAgentRunner: failed to load project models', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * 直接回答 Prompt
     */
    private function buildDirectAnswerPrompt(string $question, string $conversationContext = ''): string
    {
        return <<<PROMPT
你是一个智能助手，请直接回答用户的问题。
{$conversationContext}
## 用户问题
{$question}

## 输出要求
1. 使用中文回答，输出 Markdown 格式
2. **回答要简洁直接，直击要点**，不要冗余铺垫或重复信息
3. 简单问题用一两句话回答即可，复杂问题再适当展开
4. 可使用标题、列表、表格等排版元素，但不要过度使用
5. 禁止使用 ```markdown 代码块包裹输出
6. 如果问题不清晰或信息不足，简短说明并引导用户补充
PROMPT;
    }

    /**
     * 工具执行后的汇总 Prompt
     */
    private function buildSummaryPrompt(string $question, array $stepsHistory, string $conversationContext = ''): string
    {
        // 精简 stepsHistory 避免 token 过长
        $cleanSteps = array_map(function ($step) {
            $item = [
                'tool' => $step['tool'] ?? '',
                'purpose' => $step['purpose'] ?? '',
            ];
            if (! empty($step['error'])) {
                $item['error'] = $step['error'];
            } elseif (! empty($step['skipped'])) {
                $item['skipped'] = true;
            } else {
                $item['result'] = $step['result'] ?? null;
            }

            return $item;
        }, $stepsHistory);

        $stepsJson = json_encode($cleanSteps, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
你是一个智能助手，请根据用户问题和工具执行结果，生成一份清晰易读的回答。
{$conversationContext}
## 用户问题
{$question}

## 工具执行结果
{$stepsJson}

## 输出要求
1. 使用中文回答，输出 Markdown 格式
2. **回答要简洁直接，只包含用户需要的核心信息**，不要废话和冗余描述
3. 例如用户问"最久的代办任务是什么"，只需回答"任务XXX，创建于XXXX"即可
4. 数据量大时可用表格或列表，但只展示关键字段，不要罗列所有数据
5. 禁止使用 ```markdown 代码块包裹输出
6. 如果部分步骤失败或数据不完整，简短说明
7. 如果无法完全回答，末尾一句话提示用户可继续追问
PROMPT;
    }

    // ==================== 工具方法 ====================

    /**
     * 解析规划结果
     */
    private function normalizePlanResult($result): array
    {
        $decoded = $this->decodeJsonResult($result);

        $mode = (string) ($decoded['mode'] ?? 'direct');
        if (! in_array($mode, ['direct', 'tools'], true)) {
            $mode = 'direct';
        }

        $steps = [];
        if ($mode === 'tools' && isset($decoded['steps']) && is_array($decoded['steps'])) {
            foreach ($decoded['steps'] as $step) {
                if (! is_array($step) || empty($step['tool'])) {
                    continue;
                }
                $steps[] = [
                    'tool' => (string) $step['tool'],
                    'params' => (array) ($step['params'] ?? []),
                    'purpose' => (string) ($step['purpose'] ?? ''),
                ];
            }
        }

        // 如果 mode=tools 但没有有效步骤，回退为 direct
        if ($mode === 'tools' && empty($steps)) {
            $mode = 'direct';
        }

        return [
            'mode' => $mode,
            'reason' => (string) ($decoded['reason'] ?? ''),
            'steps' => $steps,
        ];
    }

    /**
     * 解析跨步骤引用占位符：将 "$stepN.field.path" 替换为前步实际结果值
     */
    private function resolveStepReferences(array $params, array $stepsHistory): array
    {
        array_walk_recursive($params, function (&$value) use ($stepsHistory) {
            if (! is_string($value)) {
                return;
            }
            // 匹配 $step1.xxx.yyy 格式
            if (! preg_match('/^\$step(\d+)\.(.+)$/', $value, $matches)) {
                return;
            }

            $stepIndex = (int) $matches[1] - 1; // 转为 0-indexed
            $fieldPath = $matches[2];

            if (! isset($stepsHistory[$stepIndex]) || ! isset($stepsHistory[$stepIndex]['result'])) {
                Log::warning('resolveStepReferences: step result not found', ['ref' => $value, 'step_index' => $stepIndex]);

                return;
            }

            $resolved = $this->getNestedValue($stepsHistory[$stepIndex]['result'], $fieldPath);
            if ($resolved !== null) {
                $value = $resolved;
            } else {
                Log::warning('resolveStepReferences: field path not found', ['ref' => $value, 'path' => $fieldPath]);
            }
        });

        return $params;
    }

    /**
     * 按点分路径从嵌套数组中取值，如 "data.0.id"
     */
    private function getNestedValue($data, string $path)
    {
        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $seg) {
            if ($current instanceof \stdClass) {
                $current = (array) $current;
            }
            if (is_array($current) && array_key_exists($seg, $current)) {
                $current = $current[$seg];
            } elseif (is_array($current) && is_numeric($seg) && array_key_exists((int) $seg, $current)) {
                $current = $current[(int) $seg];
            } else {
                return null;
            }
        }

        if ($current instanceof \stdClass) {
            $current = (array) $current;
        }

        return $current;
    }

    /**
     * 从 LLM 返回中提取纯文本答案
     */
    private function extractTextAnswer($summary): string
    {
        if (is_array($summary) && isset($summary['text']) && is_string($summary['text'])) {
            return $summary['text'];
        }
        if (is_string($summary)) {
            return $summary;
        }

        return json_encode($summary, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 从 LLM 返回中解析 JSON，含容错处理
     */
    private function decodeJsonResult($result): array
    {
        if (is_array($result)) {
            if (isset($result['mode']) || isset($result['tool'])) {
                return $result;
            }
            if (isset($result['text']) && is_string($result['text'])) {
                $decoded = $this->tryDecodeJson($result['text']);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }
        if (is_string($result)) {
            $decoded = $this->tryDecodeJson($result);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * 尝试解析 JSON，失败时做容错修复（将中文裸字符替换为占位符字符串）
     */
    private function tryDecodeJson(string $text): ?array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // 容错：将无引号的中文值替换为带引号的字符串（如 contentId:步骤1结果中的id → contentId:"$unknown"）
        $fixed = preg_replace('/:(\s*)([\x{4e00}-\x{9fff}\x{3000}-\x{303f}][\x{4e00}-\x{9fff}\x{3000}-\x{303f}\w\d]*)/u', ':$1"$unknown"', $text);
        if ($fixed !== $text) {
            Log::warning('decodeJsonResult: applied Chinese bare-word fix', ['original' => mb_substr($text, 0, 500)]);
            $decoded = json_decode($fixed, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
