<?php

namespace App\Services\ChatProcessors;

use App\Ai\AiToolRegistry;
use App\Models\SysAiAgent;
use App\Models\SysAiMessage;
use App\Services\AgentKnowledgeBaseToolService;
use App\Services\GptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SecretaryService
{
    private GptService $gptService;

    private AgentKnowledgeBaseToolService $knowledgeBaseToolService;

    public function __construct()
    {
        $this->gptService = app(GptService::class);
        $this->knowledgeBaseToolService = app(AgentKnowledgeBaseToolService::class);
    }

    public function process(SysAiMessage $message, SysAiAgent $agent): void
    {
        Log::info('SecretaryService: processing', [
            'message_id' => $message->id,
            'content' => mb_substr($message->content, 0, 50),
        ]);

        $sessionId = $message->session_id;
        $session = DB::table('sys_ai_sessions')->where('id', $sessionId)->first();
        if (! $session) {
            Log::error('SecretaryService: session not found', ['session_id' => $sessionId]);

            return;
        }

        $userId = $session->user_id;
        $question = $message->content;

        $userProjects = DB::table('sys_projects')->get();
        $dispatchableAgents = $this->getDispatchableAgents($sessionId, $userId);
        $context = AgentService::getConversationContext($sessionId, 20);
        $conversationFocus = $this->resolveConversationFocus($context, $userProjects, $dispatchableAgents);

        if ($this->shouldHandleKnowledgeBase($question, $context)) {
            $answer = $this->handleKnowledgeBase($question, $context, $userId);
            app(AgentService::class)->replyToUser($message, $answer, $agent);

            return;
        }

        if ($this->shouldForceProjectDispatch($question, $conversationFocus, $userProjects, $dispatchableAgents)) {
            app(AgentService::class)->replyToUser($message, '好的，我来帮您处理。', $agent);
            $this->dispatchToAgents($message, $agent, [[
                'agent_name' => $conversationFocus['agent']['name'],
                'task' => $question,
            ]], $dispatchableAgents);

            return;
        }

        if ($this->shouldDirectAnswer($question, $userProjects, $dispatchableAgents, $conversationFocus)) {
            $directAnswer = $this->generateDirectAnswer($question, $userId);
            if ($directAnswer !== '') {
                app(AgentService::class)->replyToUser($message, $directAnswer, $agent);

                return;
            }
        }

        $systemPrompt = $this->buildSystemPrompt($userProjects, $dispatchableAgents);

        Log::info('SecretaryService: systemPrompt '.json_encode($systemPrompt, JSON_UNESCAPED_UNICODE));
        $messages = [];

        // 1. system：核心提示词（角色、规则、知识库、JSON输出要求）
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        // 2. system：历史对话说明
        if (! empty($context)) {
            $messages[] = ['role' => 'system', 'content' => '以下是之前的对话历史，仅用于理解上下文。只处理最后一条用户消息，不要继续分发或补答历史里未完成的问题。'];
            foreach ($context as $ctx) {
                $sender = $ctx['sender'] ?? '用户';
                $role = 'user';
                if ($ctx['role'] == 'assistant' && $ctx['sender_id'] === $agent->id) {
                    $role = 'assistant';
                }
                $messages[] = [
                    'role' => $role,
                    'content' => "[{$sender}]: {$ctx['content']}",
                ];
            }
        }

        // 3. user：当前问题
        $messages[] = ['role' => 'system', 'content' => '下面是当前最新用户问题。请只针对这一条做出分发决策或直接回答。'];
        $messages[] = ['role' => 'user', 'content' => $question];

        Log::info('SecretaryService: messages '.json_encode($messages, JSON_UNESCAPED_UNICODE));

        $response = $this->gptService->chat('default', $messages, $userId, $question, false, 'text');

        $text = $response['text'] ?? $response['content'] ?? '{}';
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

        Log::info('SecretaryService: LLM response', [
            'message_id' => $message->id,
            'response_preview' => mb_substr($text, 0, 100),
        ]);

        $result = json_decode($text, true);
        if (! is_array($result)) {
            $fallbackDispatch = $this->parseNonJsonDispatchReply($text, $dispatchableAgents);
            if ($fallbackDispatch !== null) {
                $result = [
                    'answer' => '好的，我来帮您处理。',
                    'dispatch' => [$fallbackDispatch],
                ];
            } else {
                $fallbackDirectAnswer = $this->sanitizeNonJsonSecretaryReply($text);
                $result = ['direct_answer' => $fallbackDirectAnswer !== '' ? $fallbackDirectAnswer : '抱歉，我无法理解您的问题，请重试。'];
            }
        }

        $dispatchList = $result['dispatch'] ?? [];
        $reply = $result['answer'] ?? '';
        $directAnswer = $result['direct_answer'] ?? '';

        if (! empty($reply)) {
            $agentService = app(AgentService::class);
            $agentService->replyToUser($message, $reply, $agent);
        }

        if (! empty($directAnswer)) {
            $agentService = app(AgentService::class);
            $agentService->replyToUser($message, $directAnswer, $agent);

            return;
        }

        if (! empty($dispatchList)) {
            $this->dispatchToAgents($message, $agent, $dispatchList, $dispatchableAgents);
        }
    }

    private function getDispatchableAgents(int $sessionId, int $userId): array
    {
        $session = DB::table('sys_ai_sessions')->where('id', $sessionId)->first();
        if (! $session) {
            return [];
        }

        $agents = [];

        $memberIds = $session->member_ids ? json_decode($session->member_ids, true) : [];
        if (! empty($memberIds)) {
            $memberAgents = DB::table('sys_ai_agents')
                ->whereIn('id', $memberIds)
                ->where('type', '!=', 'secretary')
                ->where('enabled', true)
                ->get();
            foreach ($memberAgents as $a) {
                $agents[] = [
                    'id' => $a->id,
                    'type' => $a->type,
                    'name' => $a->name,
                    'description' => $a->description,
                    'identifier' => $a->identifier ?? '',
                ];
            }
        }

        return $agents;
    }

    private function buildProjectInfo(object $project, string $projectPrefix): string
    {
        $molds = DB::table($projectPrefix.'_pf_molds')->get(['name', 'description', 'fields', 'table_name']);

        if ($molds->isEmpty()) {
            return '';
        }

        $moldList = [];
        foreach ($molds as $mold) {
            $fields = json_decode($mold->fields, true) ?: [];
            $fieldList = [];
            foreach ($fields as $field) {
                $fieldName = $field['field'] ?? '';
                $fieldLabel = $field['label'] ?? '';
                $fieldType = $field['type'] ?? 'text';
                if ($fieldName && $fieldLabel) {
                    $fieldList[] = "{$fieldLabel}、";
                }
            }
            $fieldSection = ! empty($fieldList) ? '字段:'.implode('', $fieldList) : '';
            $desc = $mold->description ? " - {$mold->description}" : '';
            $moldList[] = "  - {$mold->name}{$desc}-{$fieldSection}";
        }

        return "\n  项目名称: {$project->name}\n  项目描述: ".($project->description ?: '暂无')."\n  模型列表:\n".implode("\n", $moldList);
    }

    private function buildSystemPrompt($userProjects, array $agents): string
    {
        $projectList = [];
        foreach ($userProjects as $p) {
            // $projectList[] = "- {$p->name}（prefix: {$p->prefix}）";
            $projectList[] = $this->buildProjectInfo($p, $p->prefix);
        }
        $projectInfo = ! empty($projectList) ? implode("\n", $projectList) : '暂无项目';

        $userProjectsKv = array_column(is_array($userProjects) ? $userProjects : $userProjects->toArray(), null, 'prefix');

        $projectAgents = [];
        $customAgents = [];

        foreach ($agents as $agent) {
            if ($agent['type'] === 'project') {
                $projectItem = $userProjectsKv[$agent['identifier']] ?? null;
                $projectName = $projectItem ? ($projectItem->name ?? '未知项目') : '未知项目';
                $projectAgents[] = "- {$agent['name']}（标识: {$agent['identifier']}）：管理项目{$projectName}";
            } else {
                $customAgents[] = "- {$agent['name']}（类型: {$agent['type']}）：{$agent['description']}";
            }
        }

        $agentList = '';
        if (! empty($projectAgents)) {
            $agentList .= "【项目助手】可查询项目数据：\n".implode("\n", $projectAgents)."\n\n";
        }
        if (! empty($customAgents)) {
            $agentList .= "【自定义助手】其他功能：\n".implode("\n", $customAgents)."\n\n";
        }

        return <<<PROMPT
你是 AI 群聊助手的管理员秘书，负责协调和组织多 Agent 协作。

## 重要规则
1. 如果用户询问项目相关的问题（如某个项目的数据、列表、统计等），必须分发给【项目助手】
2. 常识性问题、计算、聊天等可以直接回答（设置 direct_answer）
3. 关于"有几个项目"、"项目列表"等问题可以直接回答
4. 每个问题只能分发给一个最合适的Agent；如果判断是多个问题，可以分发给多个Agent；如果判断是所有项目的问题可以分发给所有项目Agent；如果判断是所有Agent的问题可以分发给所有Agent；分发时，必须指定每个Agent的任务内容，判断一定要谨慎，大多数情况仅需要一个Agent
5. 只处理最后一条用户消息，不要把历史里其他未完成问题继续拿来分发
6. 如果最后一条是通用知识问题，优先 direct_answer，不要指派给自定义Agent或项目助手
7. 如果最后一条是“设置下/改下/更新下/发布它/删除它”这类承接上一轮项目上下文的操作，即使没有重复写出项目名，也应优先理解为对最近项目对象的继续操作，并分发给对应项目助手

## 秘书可直接回答的问题
- 常识性问题（如"世界上最大的陆地是哪个"）
- 系统概览（根据已有系统信息可以直接判断出来的信息如"当前有几个项目"）
- 通用知识、计算、聊天等

## 用户项目列表
{$projectInfo}

## 可用 Agent 列表
{$agentList}

## 分发决策规则
1. 问题涉及项目数据查询 → 分发给对应的项目助手
2. 常识性问题、通用知识、系统概览 → 直接回答
3. 无法确定时，优先直接回答
4. 除非用户明确@某个Agent或问题确实依赖该Agent能力，否则不要把通用问答分发给自定义Agent
5. 如果最近几轮对话已经明确在讨论某个项目或其模型，最后一条又是对该对象的补充、修改、发布、删除等承接操作，应继续分发给对应项目助手，不要直接回答

## 输出要求
你是一个JSON输出助手，只输出JSON格式，不要输出其他内容：
{
    "answer": "给用户的初步回复，如'好的，我来帮您查询...'",  // 可选
    "dispatch": [  // 要分发的任务列表，如果不需要分发则为空数组
        {
            "agent_name": "目标Agent名称",
            "task": "具体的子问题"
        }
    ],
    "direct_answer": "直接回答的内容"  // 如果不需要分发且可以直接回答时设置
}

注意：dispatch 和 direct_answer 只能选择一个优先使用。
PROMPT;
    }

    private function shouldHandleKnowledgeBase(string $question, array $context = []): bool
    {
        $q = trim($question);
        if ($q === '') {
            return false;
        }

        if (preg_match('/^\/kb(\s+|$)/iu', $q) === 1) {
            return true;
        }

        if (preg_match('/^\d+$/', $q) === 1) {
            $recentText = implode("\n", array_map(fn ($item) => (string) ($item['content'] ?? ''), array_slice($context, -6)));
            if (preg_match('/(选择编号|候选文档|可能的文档|文档列表|need_user_selection)/u', $recentText) === 1) {
                return true;
            }
        }

        return preg_match('/(知识库|文档|笔记|待办|代办|todo|TODO|记录到|记到|写到|追加到|补充到|新建.*文档|创建.*文档|更新.*文档|编辑.*文档|修改.*文档|新增.*(待办|代办|todo|TODO)|添加.*(待办|代办|todo|TODO)|把.+改成|将.+改为|替换.+为)/u', $q) === 1;
    }

    private function handleKnowledgeBase(string $question, array $context, int $userId): string
    {
        $cleanQuestion = trim((string) preg_replace('/^\/kb\s*/iu', '', trim($question)));
        if ($cleanQuestion === '') {
            $cleanQuestion = '查看知识库文档';
        }

        $replacementAnswer = $this->tryHandleKnowledgeBaseReplacement($cleanQuestion, $context);
        if ($replacementAnswer !== null) {
            return $replacementAnswer;
        }

        $directAnswer = $this->tryHandleSimpleKnowledgeBaseQuery($cleanQuestion);
        if ($directAnswer !== null) {
            return $directAnswer;
        }

        $messages = [
            [
                'role' => 'system',
                'content' => <<<PROMPT
你是知识库助手。秘书可以操作全部知识库文档；新建文档默认放到默认知识库（非项目目录）的“默认”目录。

规则：
1. 用户以 /kb 开头时，必须按知识库任务理解。
2. 可以查询、读取、新建、修改、追加文档内容。
3. 可以新建知识库目录；用户说“新建目录/创建目录/新增分类”时调用 kb_global_create_category。
4. 不允许删除文档或目录；用户要求删除时，明确说明当前不支持删除。
5. 用户说“在某目录下新建文档 XXX”时，调用 kb_global_create_doc 并传 categoryPath，例如 categoryPath="/电子商务"；不要先调用目录创建工具，除非目录不存在且用户明确要求创建目录。
6. 用户说“新建一个 XXX 文档”且没有指定目录时，调用 kb_global_create_doc，新文档会放到“默认”目录。
7. 用户说“在 XXX 文档记录/记下/补充/追加 ...”时，优先用 title 查找并调用 kb_global_append_doc；找不到且意图明确时可 createIfMissing=true。
8. 用户要求修改文档正文时，优先调用通用工具 kb_global_modify_doc_content；复杂改写应先读取文档再传 updatedContent，局部“把 A 改成 B”可传 oldText 和 newText，追加可传 appendContent。
9. 修改完整正文前应先读取文档，避免覆盖已有内容；追加记录也可以用 append 工具。
10. 如果工具返回 need_user_selection=true，必须把 candidates 按“1. /path/doc_name（id: xx）”格式列给用户，并请用户回复编号；不要继续新建或修改。
11. 用户只回复编号时，结合上一轮候选文档，用对应候选的 id 执行原本要做的修改或追加。
12. 如果用户说“新增一个待办/代办/todo”但没说文档名，优先理解为要写入已有 todo/待办类文档；先查询相关文档，不要直接回答无法处理。
13. 回答简洁说明已完成的动作和文档标题或目录路径。
PROMPT,
            ],
        ];

        if (! empty($context)) {
            $messages[] = ['role' => 'system', 'content' => '以下历史仅用于理解“它/这个文档”等上下文，只处理最后一条用户消息。'];
            foreach (array_slice($context, -10) as $ctx) {
                $role = ($ctx['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
                $sender = $ctx['sender'] ?? ($role === 'assistant' ? '助手' : '用户');
                $messages[] = [
                    'role' => $role,
                    'content' => "[{$sender}]: {$ctx['content']}",
                ];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $cleanQuestion];

        $tools = $this->filterToolsByPrefix(AiToolRegistry::all(), 'kb_global_');
        try {
            $result = $this->gptService->chatWithTools($messages, $tools, $userId, $cleanQuestion, 6);
            $answer = trim((string) ($result['content'] ?? $result['text'] ?? ''));
        } catch (\Throwable $e) {
            Log::error('SecretaryService: knowledge base tool chat failed', [
                'error' => $e->getMessage(),
                'question' => mb_substr($cleanQuestion, 0, 120),
            ]);

            return '知识库操作暂时失败：AI 服务连接异常。你可以稍后重试，或直接使用“当前知识库有哪些文档”这类查询。';
        }

        return $answer !== '' ? $answer : '知识库操作已处理。';
    }

    private function tryHandleKnowledgeBaseReplacement(string $question, array $context): ?string
    {
        $pattern = '/(?:把|将|替换)\s*[「"“\']?(.*?)[」"”\']?\s*(?:改成|改为|替换为|为)\s*[「"“\']?(.*?)[」"”\']?\s*$/u';
        if (preg_match($pattern, trim($question), $matches) !== 1) {
            return null;
        }

        $oldText = trim($matches[1]);
        $newText = trim($matches[2]);
        if ($oldText === '' || $newText === '') {
            return null;
        }

        $docTitle = $this->extractKnowledgeBaseDocTitle($question, $context);
        if ($docTitle === null) {
            return '请先说明要修改哪个文档，例如：在 亚马逊电商 文档中，把 大单品选品 改成 爆款大单品选周边配件。';
        }

        $result = $this->knowledgeBaseToolService->modifyGlobalDocContent(
            id: null,
            title: $docTitle,
            editInstruction: $question,
            oldText: $oldText,
            newText: $newText
        );
        if (($result['success'] ?? false) === true) {
            $count = (int) ($result['modified_count'] ?? 1);

            return "已在「{$docTitle}」文档中按要求完成修改（修改 {$count} 处）。";
        }

        if (($result['need_user_selection'] ?? false) === true && is_array($result['candidates'] ?? null)) {
            $lines = [];
            foreach ($result['candidates'] as $index => $candidate) {
                $path = (string) ($candidate['path'] ?? $candidate['title'] ?? '');
                $id = (int) ($candidate['id'] ?? 0);
                $lines[] = ($index + 1).". {$path}（id: {$id}）";
            }

            return "找到多个可能的文档，请回复编号后我再修改：\n".implode("\n", $lines);
        }

        return '修改失败：'.(string) ($result['error'] ?? '未能完成修改');
    }

    private function extractKnowledgeBaseDocTitle(string $question, array $context): ?string
    {
        $texts = array_merge(
            [$question],
            array_reverse(array_map(fn ($item) => (string) ($item['content'] ?? ''), array_slice($context, -8)))
        );

        foreach ($texts as $text) {
            if (preg_match('/(?:在|向)\s*[「"“\']?(.+?)\s*文档[」"”\']?\s*(?:中|里|内)?/u', $text, $matches) === 1) {
                $title = trim($matches[1]);
                if ($title !== '') {
                    return $title;
                }
            }

            if (preg_match('/[「"“\'](.+?)[」"”\']文档/u', $text, $matches) === 1) {
                $title = trim($matches[1]);
                if ($title !== '') {
                    return $title;
                }
            }
        }

        return null;
    }

    private function tryHandleSimpleKnowledgeBaseQuery(string $question): ?string
    {
        if (preg_match('/(有哪些|列出|列表|查看|查询).*(知识库|文档)|(知识库|文档).*(有哪些|列表)/u', $question) !== 1) {
            return null;
        }

        $result = $this->knowledgeBaseToolService->listGlobalDocs(null, 1, 50);
        $items = $result['items'] ?? [];
        if (! is_array($items) || $items === []) {
            return '当前知识库还没有文档。';
        }

        $lines = [];
        foreach ($items as $index => $item) {
            $id = (int) ($item['id'] ?? 0);
            $title = (string) ($item['title'] ?? '未命名文档');
            $updatedAt = (string) ($item['updated_at'] ?? '');
            $suffix = $updatedAt !== '' ? "，更新于 {$updatedAt}" : '';
            $lines[] = ($index + 1).". {$title}（id: {$id}{$suffix}）";
        }

        return "当前知识库文档：\n".implode("\n", $lines);
    }

    private function filterToolsByPrefix(array $tools, string $prefix): array
    {
        return array_filter(
            $tools,
            fn ($tool, $name) => str_starts_with((string) $name, $prefix),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function shouldDirectAnswer(string $question, $userProjects, array $agents, ?array $conversationFocus): bool
    {
        $q = trim($question);
        if ($q === '') {
            return false;
        }

        if (str_contains($q, '@')) {
            return false;
        }

        if ($this->containsProjectReference($q, $userProjects, $agents)) {
            return false;
        }

        if ($this->looksLikeProjectAction($q)) {
            return false;
        }

        if ($this->isLikelyFollowUpToProject($q, $conversationFocus)) {
            return false;
        }

        return true;
    }

    private function shouldForceProjectDispatch(string $question, ?array $conversationFocus, $userProjects, array $agents): bool
    {
        $q = trim($question);
        if ($q === '' || str_contains($q, '@')) {
            return false;
        }

        if ($this->containsProjectReference($q, $userProjects, $agents)) {
            return false;
        }

        if (! $this->looksLikeProjectAction($q)) {
            return false;
        }

        if (! $this->isLikelyFollowUpToProject($q, $conversationFocus)) {
            return false;
        }

        return true;
    }

    private function resolveConversationFocus(array $context, $userProjects, array $agents): ?array
    {
        $projectAgents = [];
        $projectsByPrefix = [];
        foreach ($userProjects as $project) {
            $prefix = (string) ($project->prefix ?? '');
            if ($prefix !== '') {
                $projectsByPrefix[$prefix] = $project;
            }
        }

        foreach ($agents as $agent) {
            if (($agent['type'] ?? '') !== 'project') {
                continue;
            }

            $identifier = (string) ($agent['identifier'] ?? '');
            $project = $projectsByPrefix[$identifier] ?? null;
            $projectAgents[] = [
                'agent' => $agent,
                'project_name' => (string) ($project->name ?? ''),
            ];
        }

        if (empty($projectAgents)) {
            return null;
        }

        $scores = [];
        $recentMessages = array_slice($context, -10);
        $count = count($recentMessages);
        foreach ($recentMessages as $index => $item) {
            $content = (string) ($item['content'] ?? '');
            $senderId = (int) ($item['sender_id'] ?? 0);
            $weight = $count - $index;
            foreach ($projectAgents as $candidate) {
                $agent = $candidate['agent'];
                $projectName = $candidate['project_name'];
                $key = (string) $agent['id'];
                $scores[$key] = $scores[$key] ?? ['score' => 0, 'agent' => $agent, 'project_name' => $projectName];

                if ($senderId === (int) $agent['id']) {
                    $scores[$key]['score'] += 6 + $weight;
                }
                if ($projectName !== '' && str_contains($content, $projectName)) {
                    $scores[$key]['score'] += 5 + $weight;
                }
                if (! empty($agent['name']) && str_contains($content, (string) $agent['name'])) {
                    $scores[$key]['score'] += 4 + $weight;
                }
                if (! empty($agent['identifier']) && str_contains($content, (string) $agent['identifier'])) {
                    $scores[$key]['score'] += 3 + $weight;
                }
            }
        }

        if (empty($scores)) {
            return null;
        }

        usort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = $scores[0] ?? null;
        $second = $scores[1] ?? null;
        if (! $top || $top['score'] < 8) {
            return null;
        }
        if ($second && ($top['score'] - $second['score']) < 3) {
            return null;
        }

        return $top;
    }

    private function containsProjectReference(string $question, $userProjects, array $agents): bool
    {
        if (preg_match('/(项目|模型|文章|博客|产品|新闻|案例|分类|标签|发布|下架|删除|统计|列表|数据|内容)/u', $question)) {
            return true;
        }

        foreach ($agents as $agent) {
            $name = (string) ($agent['name'] ?? '');
            $identifier = (string) ($agent['identifier'] ?? '');
            if (($name !== '' && str_contains($question, $name)) || ($identifier !== '' && str_contains($question, $identifier))) {
                return true;
            }
        }

        foreach ($userProjects as $project) {
            $projectName = (string) ($project->name ?? '');
            if ($projectName !== '' && str_contains($question, $projectName)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeProjectAction(string $question): bool
    {
        return preg_match('/(查询|查看|看看|列出|统计|搜索|找出|读取|获取|设置|修改|更新|更改|编辑|新增|添加|创建|填写|补充|完善|保存|设为|改成|改为|替换|启用|禁用|发布|下架|删除)/u', $question) === 1;
    }

    private function isLikelyFollowUpToProject(string $question, ?array $conversationFocus): bool
    {
        if (! $conversationFocus) {
            return false;
        }

        if ($this->looksLikeProjectAction($question)) {
            return true;
        }

        return preg_match('/^(它|这个|该|继续|还有|然后|再|顺便|同时)/u', $question) === 1;
    }

    private function generateDirectAnswer(string $question, int $userId): string
    {
        $messages = [
            [
                'role' => 'system',
                'content' => '你是群聊秘书。对于通用知识、常识、简单问答，直接给出简洁准确答案。不要分发任务，不要提及其他Agent，不要输出JSON。',
            ],
            [
                'role' => 'user',
                'content' => $question,
            ],
        ];

        $response = $this->gptService->chat('default', $messages, $userId, $question, false, 'text');
        $text = trim((string) ($response['text'] ?? $response['content'] ?? ''));

        return $this->sanitizeNonJsonSecretaryReply($text);
    }

    private function sanitizeNonJsonSecretaryReply(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (preg_match('/^\[[^\]]+\][:：]\s*/u', $text, $matches) === 1) {
            $text = trim(substr($text, strlen($matches[0])));
        }

        if (preg_match('/^@\S+\s+/u', $text)) {
            return '';
        }

        return $text;
    }

    private function parseNonJsonDispatchReply(string $text, array $agents): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (preg_match('/^\[[^\]]+\][:：]\s*/u', $text, $matches) === 1) {
            $text = trim(substr($text, strlen($matches[0])));
        }

        if (preg_match('/^@([^\s]+)\s+(.+)$/u', $text, $matches) !== 1) {
            return null;
        }

        $agentName = trim($matches[1]);
        $task = trim($matches[2]);
        if ($agentName === '' || $task === '') {
            return null;
        }

        $targetAgent = $this->findAgentByName($agentName, $agents);
        if (! $targetAgent) {
            return null;
        }

        return [
            'agent_name' => $targetAgent['name'],
            'task' => $task,
        ];
    }

    private function findAgentByName(string $name, array $agents): ?array
    {
        foreach ($agents as $agent) {
            if ($agent['name'] === $name) {
                return $agent;
            }
            if (($agent['identifier'] ?? '') === $name) {
                return $agent;
            }
            if (str_contains($agent['name'], $name) || str_contains($name, $agent['identifier'] ?? '')) {
                return $agent;
            }
        }

        return null;
    }

    private function dispatchToAgents(SysAiMessage $message, SysAiAgent $senderAgent, array $dispatchList, array $agents): void
    {
        $agentService = app(AgentService::class);

        foreach ($dispatchList as $dispatch) {
            $targetAgent = $this->findAgentByName($dispatch['agent_name'], $agents);

            if (! $targetAgent) {
                Log::warning('SecretaryService: agent not found', [
                    'agent_name' => $dispatch['agent_name'],
                ]);

                continue;
            }

            $targetAgentModel = SysAiAgent::find($targetAgent['id']);
            if (! $targetAgentModel) {
                continue;
            }

            Log::info('SecretaryService: dispatching', [
                'to_agent' => $targetAgent['name'],
                'task' => mb_substr($dispatch['task'], 0, 50),
            ]);

            $agentService->dispatchToAgent($message, $dispatch['task'], $senderAgent, $targetAgentModel);
        }
    }
}
