<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SysAiAgent;
use App\Repository\SysAiAgentRepository;

class AiAgentService
{
    public const RUNTIME_MODE_GENERAL = 'general';

    public const RUNTIME_MODE_FRONTEND_PAGE_DEVELOPER = 'frontend_page_developer';

    private SysAiAgentRepository $agentRepo;

    public function __construct(SysAiAgentRepository $agentRepo)
    {
        $this->agentRepo = $agentRepo;
    }

    public function getAgentsForUser(): array
    {
        $agents = [];

        $secretary = SysAiAgent::where('type', 'secretary')->first();
        if (! $secretary) {
            $secretary = $this->createSecretaryAgent();
        }
        if ($secretary->enabled) {
            $agents[] = $this->formatAgent($secretary);
        }

        $this->ensureProjectAgents();

        $allAgents = SysAiAgent::where('enabled', true)
            ->where('type', '!=', 'secretary')
            ->get();

        foreach ($allAgents as $agent) {
            $agents[] = $this->formatAgent($agent);
        }

        return $agents;
    }

    private function ensureProjectAgents(): void
    {
        $projects = Project::query()->get(['id', 'prefix', 'name', 'user_id']);
        $existingProjectIds = SysAiAgent::query()
            ->where('type', 'project')
            ->pluck('project_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($projects as $project) {
            if (in_array((int) $project->id, $existingProjectIds, true)) {
                continue;
            }

            SysAiAgent::create([
                'type' => 'project',
                'identifier' => $project->prefix,
                'user_id' => $project->user_id,
                'project_id' => $project->id,
                'name' => $project->name.'助手',
                'description' => '项目 '.$project->name.' 的 AI 助手',
                'avatar' => '',
                'personality' => [
                    'tone' => 'professional',
                    'traits' => ['专业', '高效', '严谨'],
                    'greeting' => '你好！我是'.$project->name.'助手，有什么可以帮你的？',
                ],
                'dialogue_style' => [
                    'length' => 'medium',
                    'format' => 'markdown',
                    'emoji_usage' => 'normal',
                ],
                'core_prompt' => '你是'.$project->name.'项目的专业助手，帮助用户处理与该项目相关的问题。',
                'enabled' => true,
            ]);
        }
    }

    private function createSecretaryAgent(): SysAiAgent
    {
        return SysAiAgent::create([
            'type' => 'secretary',
            'identifier' => 'secretary',
            'user_id' => null,
            'project_id' => null,
            'name' => '智能秘书',
            'description' => '您的专属智能助手',
            'avatar' => '',
            'personality' => [
                'tone' => 'friendly',
                'traits' => ['耐心', '专业', '热情'],
                'greeting' => '你好！我是智能秘书，有什么可以帮你的？',
            ],
            'dialogue_style' => [
                'length' => 'medium',
                'format' => 'markdown',
                'emoji_usage' => 'normal',
            ],
            'core_prompt' => '你是一个友好、专业的智能助手，帮助用户解答问题、提供信息和完成任务。',
            'enabled' => true,
        ]);
    }

    public function createCustomAgent(int $userId, array $data): SysAiAgent
    {
        $runtimeMode = $this->normalizeRuntimeMode($data['runtime_mode'] ?? null);
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $personality = $data['personality'] ?? [];
        $dialogueStyle = $data['dialogue_style'] ?? [];
        $corePrompt = $data['core_prompt'] ?? '';
        $projectId = $this->resolveProjectBinding($runtimeMode, $data);

        if (empty($corePrompt)) {
            $corePrompt = $this->generatePromptByRuntimeMode($runtimeMode, $name, $description, $personality, $dialogueStyle);
        }

        return SysAiAgent::create([
            'user_id' => $userId,
            'type' => 'custom',
            'identifier' => 'custom_'.uniqid(),
            'project_id' => $projectId,
            'name' => $name,
            'description' => $this->resolveDescriptionByRuntimeMode($runtimeMode, $description),
            'avatar' => $data['avatar'] ?? '',
            'personality' => $personality,
            'dialogue_style' => $dialogueStyle,
            'enabled' => true,
            'core_prompt' => $corePrompt,
            'runtime_mode' => $runtimeMode,
        ]);
    }

    public function updateAgent(int $agentId, array $data): bool
    {
        $runtimeMode = $this->normalizeRuntimeMode($data['runtime_mode'] ?? null);
        $agent = SysAiAgent::find($agentId);
        if (! $agent) {
            return false;
        }

        if (isset($data['core_prompt']) && empty($data['core_prompt'])) {
            $name = $data['name'] ?? '';
            $description = $data['description'] ?? '';
            $personality = $data['personality'] ?? [];
            $dialogueStyle = $data['dialogue_style'] ?? [];
            $data['core_prompt'] = $this->generatePromptByRuntimeMode($runtimeMode, $name, $description, $personality, $dialogueStyle);
        }

        $data['runtime_mode'] = $runtimeMode;
        $data['description'] = $this->resolveDescriptionByRuntimeMode($runtimeMode, $data['description'] ?? '');
        $data['project_id'] = $this->resolveProjectBinding($runtimeMode, $data, false);

        $agent->fill($data);

        return $agent->save();
    }

    public function deleteAgent(int $agentId): bool
    {
        return SysAiAgent::where('id', $agentId)
            ->where('type', 'custom')
            ->delete() > 0;
    }

    public function testAgent(int $userId, string $content, ?int $agentId, array $agentData): array
    {
        $gptService = app(GptService::class);

        if ($agentId) {
            $agent = SysAiAgent::find($agentId);
            if ($agent) {
                $agentData = [
                    'name' => $agent->name,
                    'type' => $agent->type,
                    'description' => $agent->description ?? '',
                    'personality' => $agent->personality ?? [],
                    'dialogue_style' => $agent->dialogue_style ?? [],
                    'core_prompt' => $agent->core_prompt ?? '',
                ];
            }
        }

        $agentData['name'] = $agentData['name'] ?? '助手';
        $agentData['description'] = $agentData['description'] ?? '';
        $agentData['personality'] = $agentData['personality'] ?? [];
        $agentData['dialogue_style'] = $agentData['dialogue_style'] ?? [];
        $runtimeMode = $this->normalizeRuntimeMode($agentData['runtime_mode'] ?? null);

        if (empty($agentData['core_prompt'])) {
            $agentData['core_prompt'] = $this->generatePromptByRuntimeMode(
                $runtimeMode,
                $agentData['name'],
                $agentData['description'],
                $agentData['personality'],
                $agentData['dialogue_style']
            );
        }

        $response = $gptService->chat('default', [
            ['role' => 'system', 'content' => $agentData['core_prompt']],
            ['role' => 'user', 'content' => $content],
        ], $userId, $content, false, 'text');

        $answer = $response['text'] ?? $response['content'] ?? '抱歉，暂时无法回答。';

        return [
            'answer' => $answer,
            'agent_name' => $agentData['name'] ?? '助手',
        ];
    }

    public function generateCorePrompt(string $name, ?string $description, array $personality, array $dialogueStyle): string
    {
        $description = $description ?? '';

        $toneMap = [
            'friendly' => '友好、热情、亲切',
            'professional' => '专业、严谨、权威',
            'humorous' => '幽默、风趣、轻松',
            'warm' => '温暖、关怀、有同理心',
        ];
        $tone = $toneMap[$personality['tone'] ?? 'friendly'] ?? '友好';

        $traits = implode('、', $personality['traits'] ?? ['友善']);

        $lengthMap = ['short' => '简短', 'medium' => '适中', 'long' => '详细'];
        $length = $lengthMap[$dialogueStyle['length'] ?? 'medium'] ?? '适中';

        $formatMap = ['plain' => '纯文本', 'markdown' => 'Markdown', 'structured' => '结构化'];
        $format = $formatMap[$dialogueStyle['format'] ?? 'markdown'] ?? 'Markdown';

        $emojiMap = ['none' => '不使用', 'sparse' => '偶尔使用', 'normal' => '适度使用'];
        $emoji = $emojiMap[$dialogueStyle['emoji_usage'] ?? 'normal'] ?? '适度使用';

        $greeting = $personality['greeting'] ?? "你好！我是{$name}，有什么可以帮你的？";
        $greeting = str_replace('{name}', $name, $greeting);

        return <<<PROMPT
你是一个{$name}。

## 基本信息
{$description}

## 性格特点
- 语气风格：{$tone}
- 性格：{$traits}

## 对话风格
- 回复长度：{$length}
- 格式偏好：{$format}
- 表情使用：{$emoji}

## 开场白
{$greeting}

## 行为准则
1. 只回答在自身职责范围内的问题
2. 如果问题超出职责范围，礼貌说明并建议咨询相关人员
3. 回答要专业、清晰、易懂
4. 保持设定的语气和风格

## 你的职责
{$description}
PROMPT;
    }

    public function generateFrontendCorePrompt(string $name, ?string $description = null): string
    {
        $description = trim((string) ($description ?? ''));
        if ($description === '') {
            $description = '负责当前项目的前端页面创建、修改和发布。';
        }

        return <<<PROMPT
你是 {$name}，是当前项目的前端页面开发成员。

## 你的唯一职责
1. 处理当前项目的前端页面新建与修改需求。
2. 只生成和修改 HTML、CSS、JavaScript 页面代码。
3. 页面必须符合 Mosure 当前页面托管方式，可发布并返回访问地址。

## 你的边界
1. 你不负责内容模型结构设计，不负责数据库结构调整。
2. 你不回答与前端页面无关的问题。
3. 你不操作其他项目资源。
4. 你不生成 Vue、React、Svelte 等源码，不生成构建脚本。

## 页面实现约束
1. 只输出可直接运行的 HTML、CSS、JavaScript。
2. CSS 与 JavaScript 优先内联。
3. 若页面需要数据交互，只能通过 window.Mosure。
4. 修改已有页面时应优先保留原结构并做最小必要改动。

## 视觉设计约束
1. 页面必须有清晰视觉层级，不做通用后台模板感。
2. 配色需收敛，主色、辅色、中性色清晰。
3. 避免默认紫色风格、默认暗黑风和生硬系统字体组合。
4. 背景需要层次，不能只是纯白空板。
5. 仅保留少量必要动画，不堆无意义微交互。
6. 移动端必须优先可用。

## 无关问题处理
如果用户的问题与前端页面开发无关，请礼貌拒绝，并提示对方联系项目助手或秘书。

## 你的职责说明
{$description}
PROMPT;
    }

    public function generatePromptByRuntimeMode(string $runtimeMode, string $name, ?string $description, array $personality, array $dialogueStyle): string
    {
        if ($runtimeMode === self::RUNTIME_MODE_FRONTEND_PAGE_DEVELOPER) {
            return $this->generateFrontendCorePrompt($name, $description);
        }

        return $this->generateCorePrompt($name, $description, $personality, $dialogueStyle);
    }

    private function formatAgent(SysAiAgent $agent): array
    {
        return [
            'id' => $agent->id,
            'type' => $agent->type,
            'identifier' => $agent->identifier,
            'user_id' => $agent->user_id,
            'project_id' => $agent->project_id,
            'name' => $agent->name,
            'description' => $agent->description,
            'avatar' => $agent->avatar,
            'personality' => $agent->personality,
            'dialogue_style' => $agent->dialogue_style,
            'enabled' => $agent->enabled,
            'core_prompt' => $agent->core_prompt,
            'tools' => $agent->tools,
            'capabilities' => $agent->capabilities,
            'runtime_mode' => $agent->runtime_mode ?? self::RUNTIME_MODE_GENERAL,
        ];
    }

    private function normalizeRuntimeMode(mixed $runtimeMode): string
    {
        $runtimeMode = is_string($runtimeMode) ? trim($runtimeMode) : '';

        return $runtimeMode === self::RUNTIME_MODE_FRONTEND_PAGE_DEVELOPER
            ? self::RUNTIME_MODE_FRONTEND_PAGE_DEVELOPER
            : self::RUNTIME_MODE_GENERAL;
    }

    private function resolveProjectBinding(string $runtimeMode, array $data, bool $strict = true): ?int
    {
        if ($runtimeMode !== self::RUNTIME_MODE_FRONTEND_PAGE_DEVELOPER) {
            return isset($data['project_id']) ? (int) $data['project_id'] : null;
        }

        $projectId = isset($data['project_id']) ? (int) $data['project_id'] : 0;
        if ($projectId <= 0) {
            return null;
        }

        return $projectId;
    }

    private function resolveDescriptionByRuntimeMode(string $runtimeMode, string $description): string
    {
        if ($runtimeMode !== self::RUNTIME_MODE_FRONTEND_PAGE_DEVELOPER) {
            return $description;
        }

        $description = trim($description);

        return $description !== ''
            ? $description
            : '负责当前项目的前端页面创建、修改与发布，只处理页面相关问题。';
    }

    private function getDefaultSecretary(): array
    {
        return [
            'id' => 0,
            'type' => 'secretary',
            'identifier' => 'secretary',
            'name' => '秘书',
            'description' => 'AI 助手，负责回答问题和协调对话',
            'avatar' => null,
            'enabled' => true,
        ];
    }

    private function formatProjectAgent(Project $project): array
    {
        return [
            'id' => 10000 + $project->id,
            'type' => 'project',
            'identifier' => $project->prefix,
            'project_id' => $project->id,
            'name' => $project->name,
            'description' => $project->description ?: $project->name.'项目助手',
            'avatar' => null,
            'enabled' => true,
        ];
    }
}
