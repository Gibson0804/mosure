<?php

namespace App\Services\ChatProcessors;

use App\Ai\AiToolRegistry;
use App\Models\SysAiAgent;
use App\Models\SysAiMessage;
use App\Services\GptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectAgentService
{
    private GptService $gptService;

    public function __construct()
    {
        $this->gptService = app(GptService::class);
    }

    public function process(SysAiMessage $message, SysAiAgent $agent): void
    {
        Log::info('ProjectAgentService: processing', [
            'message_id' => $message->id,
            'agent_name' => $agent->name,
            'identifier' => $agent->identifier,
        ]);

        $sessionId = $message->session_id;
        $session = DB::table('sys_ai_sessions')->where('id', $sessionId)->first();
        if (! $session) {
            Log::error('ProjectAgentService: session not found', ['session_id' => $sessionId]);

            return;
        }

        $userId = $session->user_id;
        $question = $message->content;
        $projectPrefix = $agent->identifier;

        $project = DB::table('sys_projects')
            ->where('user_id', $userId)
            ->where('prefix', $projectPrefix)
            ->first();

        if (! $project) {
            Log::error('ProjectAgentService: project not found', [
                'user_id' => $userId,
                'prefix' => $projectPrefix,
            ]);
            $this->replyToUser($message, $agent, '抱歉，未找到相关项目信息。');

            return;
        }

        $previousPrefix = session('current_project_prefix');
        session(['current_project_prefix' => $projectPrefix]);

        try {
            $context = AgentService::getConversationContext($sessionId, 20);
            $systemPrompt = $this->buildSystemPrompt($project, $projectPrefix);
            $messages = $this->buildMessages($systemPrompt, $context, $question);
            $tools = AiToolRegistry::all();

            Log::info('ProjectAgentService: messages built', [
                'messages_count' => count($messages),
                'tools_count' => count($tools),
            ]);

            $maxIterations = 8;
            $result = $this->gptService->chatWithTools($messages, $tools, null, $question, $maxIterations);

            $answer = $result['content'] ?? $result['text'] ?? '';

            Log::info('ProjectAgentService: response', [
                'message_id' => $message->id,
                'answer_preview' => mb_substr($answer, 0, 100),
                'iterations' => $result['iterations'] ?? 1,
            ]);

            $this->replyToUser($message, $agent, $answer);
        } catch (\Throwable $e) {
            Log::error('ProjectAgentService: error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->replyToUser($message, $agent, '抱歉，处理您的请求时出现了一些问题。');
        } finally {
            session(['current_project_prefix' => $previousPrefix]);
        }
    }

    private function buildSystemPrompt(object $project, string $projectPrefix): string
    {
        $projectInfo = $this->buildProjectInfo($project, $projectPrefix);
        $projectName = $project->name;

        $prompt = <<<PROMPT
你是 {$projectName} 的项目专属 AI 助手。你的目标是基于当前项目的真实模型和字段，帮助用户完成查询、创建、修改、发布、下架、删除、统计等操作。

## 你的工作原则
1. 优先准确理解用户意图，再决定是否调用工具。
2. 如果用户问的是通用知识、常识解释、写作润色、简单建议，且不依赖项目数据或项目操作，可以直接回答，不必强行调用工具。
3. 只基于当前项目可用的模型、字段和工具回答项目相关内容，不要臆造模型、字段、ID、统计结果或执行结果。
4. 历史消息只用于理解上下文，不代表当前还要继续执行历史里的任务；除非用户在最后一条消息里明确继续，否则不要补做旧任务、不要重复回答旧问题。
5. 你的唯一回答目标是“最后一条用户消息”。不要把历史中的其他问题混在本次回答里。
6. 当最后一条消息与项目无关时，直接正常回答，不要硬转成项目操作。
7. 当信息不足以安全执行写操作时，只追问最关键的 1-2 个字段，不要一次性索要大量信息。
8. 当用户的问题有歧义时，先结合项目模型做合理推断；如果仍有明显歧义，再发起澄清。
9. 能直接完成就直接完成，不要先输出“我准备这样做”“我将调用某某工具”之类的过程性废话。

## 任务处理策略
1. 查询类：
- 优先使用最贴近用户意图的模型和字段查询。
- 如果用户没有明确说“只看已发布”，查询列表时应尽量覆盖全部状态，避免漏数据。
- 返回结果时先给结论，再给必要的关键字段，不要把整批原始数据机械堆给用户。

2. 创建类：
- 先根据用户描述匹配最合适的模型。
- 尽量从用户原话中提取标题、名称、摘要、正文、分类、状态、时间等信息。
- 默认采用最小必要字段创建，不要为了“补全信息”主动查询分类、标签、关联模型。
- 只有在以下情况才查询分类/标签/关联对象：用户明确指定了它们；字段是必填且无法省略；或不查询就无法正确执行。
- `single` 类型模型禁止使用 `content_create` 创建记录；这类模型只有唯一内容，新增或修改都必须走 `subject_update`。
- 创建内容时，只能传入当前模型真实存在的字段；字段清单里没有的字段一律不要传。
- 如果缺少创建所必需的核心字段，只追问核心字段。
- 不要编造用户没提供的重要业务信息；能留空或走默认值的字段就不要强填。

3. 修改类：
- 先尽量定位唯一记录；如果无法唯一定位，先向用户确认目标记录。
- 只修改用户明确要求变更的字段，不要顺带改动无关字段。
- 修改内容时，只能修改当前模型真实存在且用户明确要求变更的字段。
- 如果用户说“把它发布/下架/删除”，先确认“它”指向的记录。
- 如果最近对话中刚创建/提到某条内容，且用户说“发布这篇文章/发布它/删除它”，优先将“它”解析为最近那条内容，而不是重新发散查询无关数据。
- 对 `single` 类型模型，读取统一使用 `subject_detail`，写入统一使用 `subject_update`；不要使用 `content_update`。

4. 统计类：
- 明确统计对象、筛选条件、时间范围。
- 若用户未给时间范围，不要擅自添加虚构范围；可按全部数据统计，或在必要时追问。

5. 通用问答类：
- 如果问题是百科常识、概念解释、写作改写、简短建议等，不需要工具时直接回答。
- 这类问题不要强行映射到项目模型、内容创建或数据查询。

## 模型选择要求
1. 选择模型时，优先依据“模型名称 + 表标识 + 关键字段”综合判断。
2. `single` 类型模型通常表示单例配置或页面信息；`list` 类型模型通常表示可新增多条内容。
3. `single` 类型模型只允许：
- 查询详情时用 `subject_detail`
- 更新内容时用 `subject_update`
- 不允许 `content_create` / `content_update`
4. `list` 类型模型只允许：
- 新增时用 `content_create`
- 修改时用 `content_update`
- 不要对 `list` 类型模型使用 `subject_update`
5. 如果多个模型名字相近，优先选字段更匹配用户需求的模型。
6. 分类、标签、关联对象不是默认必查项。只有在用户明确指定、字段必填或执行确实依赖它们时才查询。

## 回答要求
1. 回答简洁、明确、面向结果。
2. 已执行成功：直接说明做了什么、对象是什么、关键结果是什么。
3. 无法执行：明确说明缺什么信息，或哪个条件不明确。
4. 不确定时明确说“不确定”，并通过查询或追问消除不确定。

## 当前项目信息
{$projectInfo}

## 可用工具
你可以使用工具查询模型、查询内容、查看详情、创建内容、修改内容、发布、下架、删除和统计。应优先通过工具获取事实，再给出答案。
PROMPT;

        return $prompt;
    }

    private function buildProjectInfo(object $project, string $projectPrefix): string
    {
        $molds = DB::table($projectPrefix.'_pf_molds')
            ->orderBy('id')
            ->get(['id', 'name', 'table_name', 'mold_type', 'fields', 'list_show_fields']);

        $projectName = $project->name;

        if ($molds->isEmpty()) {
            return "- 项目名称: {$projectName}
- 模型列表: 暂无";
        }

        $lines = [
            "- 项目名称: {$projectName}",
            "- 项目前缀: {$projectPrefix}",
            '- 模型说明:',
        ];

        foreach ($molds as $mold) {
            $fieldSummary = $this->summarizeMoldFields($mold->fields, $mold->list_show_fields);
            $writableFields = $this->extractWritableFields($mold->fields);
            $tableName = (string) ($mold->table_name ?? '');
            $moldType = (string) ($mold->mold_type ?? 'unknown');
            $toolHint = $this->buildToolHint($moldType);
            $lines[] = sprintf(
                '  - #%d %s | table=%s | type=%s | fields=%s | writable=%s | tools=%s',
                (int) $mold->id,
                (string) $mold->name,
                $tableName !== '' ? $tableName : '-',
                $moldType,
                $fieldSummary !== '' ? $fieldSummary : '-',
                $writableFields !== '' ? $writableFields : '-',
                $toolHint
            );
        }

        return implode("\n", $lines);
    }

    private function buildMessages(string $systemPrompt, array $context, string $question): array
    {
        $messages = [];

        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        $recentEntityHints = $this->buildRecentEntityHints($context);
        if ($recentEntityHints !== '') {
            $messages[] = ['role' => 'system', 'content' => $recentEntityHints];
        }

        if (! empty($context)) {
            $messages[] = ['role' => 'system', 'content' => '以下是之前的对话历史，仅用于理解上下文。不要重复回答历史问题，不要继续执行历史中未明确续接的任务，只处理最后一条用户消息。'];
            foreach ($context as $ctx) {
                $role = $ctx['role'] ?? 'user';
                $sender = $ctx['sender'] ?? ($role === 'assistant' ? '助手' : '用户');
                $messages[] = [
                    'role' => in_array($role, ['user', 'assistant'], true) ? $role : 'user',
                    'content' => "[{$sender}]: {$ctx['content']}",
                ];
            }
        }

        $messages[] = ['role' => 'system', 'content' => '下面是当前需要你回答的最新用户问题。只回答这一条，不要附带回答历史中的其他问题。'];
        $messages[] = ['role' => 'user', 'content' => $question];

        return $messages;
    }

    private function replyToUser(SysAiMessage $message, SysAiAgent $agent, string $content): void
    {
        $agentService = app(AgentService::class);
        $agentService->replyToUser($message, $content, $agent);
    }

    private function summarizeMoldFields($fieldsJson, $listShowFieldsJson): string
    {
        $fields = is_string($fieldsJson) ? json_decode($fieldsJson, true) : $fieldsJson;
        $listShowFields = is_string($listShowFieldsJson) ? json_decode($listShowFieldsJson, true) : $listShowFieldsJson;

        if (! is_array($fields)) {
            return '';
        }

        $prioritized = [];

        if (is_array($listShowFields)) {
            foreach ($listShowFields as $field) {
                if (is_string($field) && $field !== '') {
                    $prioritized[] = $field;
                }
            }
        }

        $preferredFields = ['title', 'name', 'summary', 'content', 'category', 'category_id', 'category_name', 'tags', 'status', 'publish_status', 'date', 'publish_date', 'author', 'sort'];

        foreach ($preferredFields as $preferredField) {
            foreach ($fields as $fieldDef) {
                if (! is_array($fieldDef)) {
                    continue;
                }

                $field = (string) ($fieldDef['field'] ?? '');
                if ($field === $preferredField && ! in_array($field, $prioritized, true)) {
                    $prioritized[] = $field;
                }
            }
        }

        foreach ($fields as $fieldDef) {
            if (! is_array($fieldDef)) {
                continue;
            }

            $field = (string) ($fieldDef['field'] ?? '');
            if ($field === '' || in_array($field, $prioritized, true)) {
                continue;
            }

            $type = (string) ($fieldDef['type'] ?? '');
            if (in_array($field, $preferredFields, true)
                || in_array($type, ['input', 'textarea', 'richText', 'radio', 'select', 'switch', 'numInput', 'picUpload', 'tags'], true)) {
                $prioritized[] = $field;
            }
        }

        $prioritized = array_values(array_unique(array_filter($prioritized, fn ($field) => is_string($field) && $field !== '')));

        if ($prioritized === []) {
            return '';
        }

        return implode(', ', array_slice($prioritized, 0, 8));
    }

    private function extractWritableFields($fieldsJson): string
    {
        $fields = is_string($fieldsJson) ? json_decode($fieldsJson, true) : $fieldsJson;
        if (! is_array($fields)) {
            return '';
        }

        $result = [];
        foreach ($fields as $fieldDef) {
            if (! is_array($fieldDef)) {
                continue;
            }

            $field = (string) ($fieldDef['field'] ?? '');
            $type = (string) ($fieldDef['type'] ?? '');
            if ($field === '' || $type === 'dividingLine') {
                continue;
            }

            $result[] = $field;
        }

        $result = array_values(array_unique($result));

        return implode(', ', array_slice($result, 0, 12));
    }

    private function buildToolHint(string $moldType): string
    {
        if ($moldType === 'single') {
            return 'read=subject_detail, write=subject_update, forbid=content_create|content_update';
        }

        if ($moldType === 'list') {
            return 'list=content_list, detail=content_detail, create=content_create, update=content_update';
        }

        return '-';
    }

    private function buildRecentEntityHints(array $context): string
    {
        if ($context === []) {
            return '';
        }

        $hints = [];
        $recentAssistantMessages = array_slice(array_values(array_filter($context, function ($ctx) {
            return ($ctx['role'] ?? '') === 'assistant' && ! empty($ctx['content']);
        })), -6);

        foreach ($recentAssistantMessages as $ctx) {
            $content = (string) ($ctx['content'] ?? '');

            if (preg_match('/文章ID[：:]\s*(\d+)/u', $content, $matches)) {
                $hints[] = '最近一次明确提到的文章ID是 '.$matches[1].'。当用户说“发布这篇文章/发布它/删除它”且没有新目标时，优先将“它”理解为这篇文章。';
            }

            if (preg_match('/文章标题[：:]\s*([^\n]+)/u', $content, $matches)) {
                $title = trim($matches[1]);
                if ($title !== '') {
                    $hints[] = '最近一次明确提到的文章标题是《'.$title.'》。';
                }
            }

            if (preg_match('/文章ID为(\d+)/u', $content, $matches)) {
                $hints[] = '最近一次明确提到的文章ID是 '.$matches[1].'。当用户说“发布这篇文章/发布它/删除它”且没有新目标时，优先将“它”理解为这篇文章。';
            }
        }

        $hints = array_values(array_unique($hints));
        if ($hints === []) {
            return '';
        }

        return "以下是从最近对话中提取的可直接用于当前操作的实体线索：\n- ".implode("\n- ", $hints);
    }
}
