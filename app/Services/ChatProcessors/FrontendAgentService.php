<?php

namespace App\Services\ChatProcessors;

use App\Adapter\Prompts;
use App\Helper\AiJsonParser;
use App\Models\ProjectPage;
use App\Models\SysAiAgent;
use App\Models\SysAiMessage;
use App\Repository\SysAiSessionRepository;
use App\Services\GptService;
use App\Services\PageHostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FrontendAgentService
{
    public const TASK_PREFIX = 'FRONTEND_TASK:';

    public function __construct(
        private GptService $gptService,
        private PageHostingService $pageHostingService,
        private SysAiSessionRepository $sessionRepo
    ) {}

    public function process(SysAiMessage $message, SysAiAgent $agent): void
    {
        Log::info('FrontendAgentService: processing', [
            'message_id' => $message->id,
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
        ]);

        $session = DB::table('sys_ai_sessions')->where('id', $message->session_id)->first();
        if (! $session) {
            Log::error('FrontendAgentService: session not found', ['session_id' => $message->session_id]);

            return;
        }

        $taskPayload = $this->extractTaskPayload($message->content, $agent->name);
        $userRequest = trim((string) ($taskPayload['user_request'] ?? $message->content));
        [$project, $projectPrompt] = $this->resolveProjectContext($session, $taskPayload, $userRequest);
        if (! $project) {
            $this->replyToUser($message, $agent, $projectPrompt !== '' ? $projectPrompt : '请先说明您要操作的是哪个项目。');

            return;
        }

        $previous = [
            'current_project_id' => session('current_project_id'),
            'current_project_name' => session('current_project_name'),
            'current_project_prefix' => session('current_project_prefix'),
        ];

        session([
            'current_project_id' => (int) $project->id,
            'current_project_name' => (string) $project->name,
            'current_project_prefix' => (string) $project->prefix,
        ]);

        try {
            if ($this->looksLikeModelChangeRequest($userRequest)) {
                $this->replyToUser($message, $agent, '这个需求同时涉及内容模型调整。请先让项目助手处理模型部分，确认模型后我再继续页面开发。');

                return;
            }

            if (! $this->isFrontendRelatedQuestion($userRequest)) {
                $this->replyToUser($message, $agent, '我只负责当前项目的前端页面创建、修改与发布。如果您需要处理内容模型、业务逻辑或其他问题，请联系项目助手或秘书。');

                return;
            }

            $pages = $this->listProjectPages();
            $sessionMeta = $this->normalizeSessionMeta($session->meta_json ?? null);
            $currentPageSlug = (string) ($taskPayload['current_page_slug'] ?? $sessionMeta['current_page_slug'] ?? '');

            $plan = $this->planTask($userRequest, $pages, $currentPageSlug);
            if (! $plan) {
                $this->replyToUser($message, $agent, '我暂时无法理解这个页面需求，请换一种更具体的说法。');

                return;
            }

            $action = (string) ($plan['action'] ?? 'clarify');
            if ($action === 'reject') {
                $this->replyToUser($message, $agent, '我只负责当前项目的前端页面创建、修改与发布。如果您需要处理其他问题，请联系项目助手或秘书。');

                return;
            }

            if ($action === 'clarify') {
                $clarification = trim((string) ($plan['clarification'] ?? '请再明确一下您要创建或修改的是哪个页面。'));
                $this->replyToUser($message, $agent, $clarification);

                return;
            }

            $models = $this->getProjectModelsSummary((string) $project->prefix);
            if ($action === 'create') {
                $this->handleCreate($message, $agent, $userRequest, $plan, $models, $sessionMeta);

                return;
            }

            if ($action === 'update') {
                $this->handleUpdate($message, $agent, $userRequest, $plan, $pages, $models, $sessionMeta);

                return;
            }

            $this->replyToUser($message, $agent, '我暂时无法判断该页面需求应该新建还是修改，请再具体说明。');
        } catch (\Throwable $e) {
            Log::error('FrontendAgentService: error', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->replyToUser($message, $agent, '抱歉，处理前端页面需求时发生错误：'.$e->getMessage());
        } finally {
            session($previous);
        }
    }

    private function handleCreate(SysAiMessage $message, SysAiAgent $agent, string $userRequest, array $plan, array $models, array $sessionMeta): void
    {
        $targetTitle = trim((string) ($plan['target_title'] ?? '新页面'));
        $targetSlug = $this->ensureUniqueSlug($this->normalizeSlug((string) ($plan['target_slug'] ?? ''), $targetTitle));
        $requirements = is_array($plan['requirements'] ?? null) ? $plan['requirements'] : [];
        $description = trim((string) ($plan['description'] ?? ''));

        $prompt = Prompts::getFrontendPageCreatePrompt(
            $userRequest,
            $models,
            $targetSlug,
            $targetTitle,
            $requirements,
            $description
        );

        $generated = $this->generatePagePayload($prompt, $userRequest);
        if (! $generated) {
            $this->replyToUser($message, $agent, '页面生成失败，AI 没有返回可解析的页面结果。');

            return;
        }

        $slug = $this->ensureUniqueSlug($this->normalizeSlug((string) ($generated['slug'] ?? ''), $targetTitle));
        $title = trim((string) ($generated['title'] ?? $targetTitle));
        $htmlContent = trim((string) ($generated['html_content'] ?? ''));
        if ($htmlContent === '') {
            $this->replyToUser($message, $agent, '页面生成失败，返回结果缺少 html_content。');

            return;
        }

        $result = $this->pageHostingService->create([
            'slug' => $slug,
            'title' => $title,
            'description' => trim((string) ($generated['description'] ?? $description)),
            'html_content' => $htmlContent,
            'page_type' => 'single',
            'status' => 'published',
            'created_by' => $message->sender_id,
        ]);

        $this->updateSessionPageFocus($message->session_id, $slug, $title, $sessionMeta);
        $summary = $this->formatChangeSummary($generated['change_summary'] ?? []);
        $content = trim($result['message'].($summary !== '' ? "\n\n本次生成：\n".$summary : ''));

        $this->replyToUser($message, $agent, $content);
    }

    private function handleUpdate(SysAiMessage $message, SysAiAgent $agent, string $userRequest, array $plan, array $pages, array $models, array $sessionMeta): void
    {
        $page = $this->resolveTargetPage($plan, $pages, (string) ($sessionMeta['current_page_slug'] ?? ''));
        if (! $page) {
            $clarification = trim((string) ($plan['clarification'] ?? '我还不能确定您要修改哪个页面，请明确告诉我是页面 A、页面 B，或者提供页面标题。'));
            $this->replyToUser($message, $agent, $clarification);

            return;
        }

        $current = $this->pageHostingService->get((string) $page['slug']);
        $prompt = Prompts::getFrontendPageUpdatePrompt(
            $userRequest,
            $models,
            (string) $page['slug'],
            (string) $page['title'],
            (string) ($current['html_content'] ?? ''),
            is_array($plan['change_summary'] ?? null) ? $plan['change_summary'] : []
        );

        $generated = $this->generatePagePayload($prompt, $userRequest);
        if (! $generated) {
            $this->replyToUser($message, $agent, '页面修改失败，AI 没有返回可解析的页面结果。');

            return;
        }

        $htmlContent = trim((string) ($generated['html_content'] ?? ''));
        if ($htmlContent === '') {
            $this->replyToUser($message, $agent, '页面修改失败，返回结果缺少 html_content。');

            return;
        }

        $result = $this->pageHostingService->update((string) $page['slug'], [
            'title' => trim((string) ($generated['title'] ?? $page['title'])),
            'description' => trim((string) ($generated['description'] ?? ($current['description'] ?? ''))),
            'html_content' => $htmlContent,
            'status' => 'published',
        ]);

        $this->updateSessionPageFocus($message->session_id, (string) $page['slug'], (string) ($generated['title'] ?? $page['title']), $sessionMeta);
        $summary = $this->formatChangeSummary($generated['change_summary'] ?? []);
        $content = trim($result['message'].($summary !== '' ? "\n\n本次修改：\n".$summary : ''));

        $this->replyToUser($message, $agent, $content);
    }

    private function planTask(string $userRequest, array $pages, string $currentPageSlug): ?array
    {
        $prompt = Prompts::getFrontendPagePlanPrompt($userRequest, $pages, $currentPageSlug !== '' ? $currentPageSlug : null);
        $result = $this->gptService->chat('default', [
            ['role' => 'user', 'content' => $prompt],
        ], null, $userRequest, false, 'text', true);

        $text = (string) ($result['text'] ?? $result['content'] ?? '');

        return $text !== '' ? AiJsonParser::parseLenientJson($text) : null;
    }

    private function generatePagePayload(string $prompt, string $question): ?array
    {
        $result = $this->gptService->chat('default', [
            ['role' => 'user', 'content' => $prompt],
        ], null, $question, false, 'text', true);

        $text = (string) ($result['text'] ?? $result['content'] ?? '');

        return $text !== '' ? AiJsonParser::parseLenientJson($text) : null;
    }

    private function extractTaskPayload(string $content, string $agentName): array
    {
        $content = trim($content);
        if (str_starts_with($content, '@'.$agentName)) {
            $content = trim(substr($content, strlen('@'.$agentName)));
        }

        if (! str_starts_with($content, self::TASK_PREFIX)) {
            return [];
        }

        $json = trim(substr($content, strlen(self::TASK_PREFIX)));

        return AiJsonParser::parseLenientJson($json) ?? [];
    }

    private function isFrontendRelatedQuestion(string $question): bool
    {
        return preg_match('/页面|首页|落地页|前端|布局|样式|UI|按钮|hero|banner|播放器|导航|卡片|表单|颜色|背景|页头|页脚/i', $question) === 1;
    }

    private function looksLikeModelChangeRequest(string $question): bool
    {
        // Only block when user explicitly asks to change model structure.
        // Mentions like "use model data" / "use Mosure SDK" should stay in frontend lane.
        return preg_match('/(新增|创建|修改|调整|设计|删除).{0,6}(内容模型|模型|字段|表结构|table_name)|内容模型.{0,8}(新增|创建|修改|调整|删除)|字段配置|表单字段/i', $question) === 1;
    }

    private function listProjectPages(): array
    {
        return ProjectPage::query()
            ->orderByDesc('updated_at')
            ->get(['slug', 'title', 'description', 'page_type', 'status'])
            ->map(fn (ProjectPage $page) => [
                'slug' => $page->slug,
                'title' => $page->title,
                'description' => $page->description,
                'page_type' => $page->page_type,
                'status' => $page->status,
            ])
            ->values()
            ->all();
    }

    private function resolveTargetPage(array $plan, array $pages, string $currentPageSlug): ?array
    {
        $targetSlug = trim((string) ($plan['target_slug'] ?? ''));
        if ($targetSlug !== '') {
            foreach ($pages as $page) {
                if (($page['slug'] ?? '') === $targetSlug) {
                    return $page;
                }
            }
        }

        $targetTitle = trim((string) ($plan['target_title'] ?? ''));
        if ($targetTitle !== '') {
            foreach ($pages as $page) {
                if (($page['title'] ?? '') === $targetTitle) {
                    return $page;
                }
            }
            foreach ($pages as $page) {
                if (str_contains((string) ($page['title'] ?? ''), $targetTitle) || str_contains($targetTitle, (string) ($page['title'] ?? ''))) {
                    return $page;
                }
            }
        }

        if ($currentPageSlug !== '') {
            foreach ($pages as $page) {
                if (($page['slug'] ?? '') === $currentPageSlug) {
                    return $page;
                }
            }
        }

        return null;
    }

    private function getProjectModelsSummary(string $projectPrefix): array
    {
        try {
            return DB::table($projectPrefix.'_pf_molds')
                ->orderBy('id')
                ->get(['name', 'table_name', 'mold_type', 'fields'])
                ->map(function ($mold) {
                    $fields = is_string($mold->fields) ? json_decode($mold->fields, true) : $mold->fields;
                    $fieldSummary = [];
                    if (is_array($fields)) {
                        foreach ($fields as $field) {
                            if (! is_array($field) || ($field['type'] ?? '') === 'dividingLine') {
                                continue;
                            }
                            $fieldSummary[] = [
                                'field' => $field['field'] ?? '',
                                'label' => $field['label'] ?? '',
                                'type' => $field['type'] ?? '',
                            ];
                        }
                    }

                    return [
                        'name' => $mold->name,
                        'table_name' => $mold->table_name,
                        'mold_type' => $mold->mold_type,
                        'fields' => $fieldSummary,
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('FrontendAgentService: getProjectModelsSummary failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function normalizeSlug(string $slug, string $fallbackTitle): string
    {
        $slug = Str::slug($slug);
        if ($slug !== '') {
            return $slug;
        }

        $fallback = Str::slug($fallbackTitle);

        return $fallback !== '' ? $fallback : 'page-'.date('YmdHis');
    }

    private function ensureUniqueSlug(string $slug): string
    {
        $base = $slug;
        $counter = 2;

        while (ProjectPage::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function normalizeSessionMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function resolveProjectContext(object $session, array $taskPayload, string $userRequest): array
    {
        $projectId = (int) ($taskPayload['project_id'] ?? ($session->project_id ?? 0));
        if ($projectId > 0) {
            $project = DB::table('sys_projects')->where('id', $projectId)->first();
            if ($project) {
                return [$project, ''];
            }
        }

        $projectPrefix = trim((string) ($taskPayload['project_prefix'] ?? ''));
        if ($projectPrefix !== '') {
            $project = DB::table('sys_projects')->where('prefix', $projectPrefix)->first();
            if ($project) {
                return [$project, ''];
            }
        }

        $userId = (int) ($session->user_id ?? 0);
        $projects = DB::table('sys_projects')
            ->when($userId > 0, fn ($query) => $query->where('user_id', $userId))
            ->get(['id', 'name', 'prefix']);

        $matched = [];
        foreach ($projects as $project) {
            if (
                str_contains($userRequest, (string) $project->name) ||
                str_contains($userRequest, (string) $project->prefix)
            ) {
                $matched[] = $project;
            }
        }

        if (count($matched) === 1) {
            return [$matched[0], ''];
        }

        if ($projects->count() === 0) {
            return [null, '当前没有可用项目，请先创建或选择项目后再让我处理页面需求。'];
        }

        $projectNames = $projects->map(fn ($project) => "{$project->name}（{$project->prefix}）")->implode('、');

        return [null, "请先说明您要操作的是哪个项目。可选项目有：{$projectNames}。"];
    }

    private function updateSessionPageFocus(int $sessionId, string $slug, string $title, array $meta): void
    {
        $recentPages = is_array($meta['recent_pages'] ?? null) ? $meta['recent_pages'] : [];
        $recentPages = array_values(array_filter($recentPages, fn ($page) => ($page['slug'] ?? '') !== $slug));
        array_unshift($recentPages, ['slug' => $slug, 'title' => $title]);

        $meta['current_page_slug'] = $slug;
        $meta['recent_pages'] = array_slice($recentPages, 0, 8);

        $this->sessionRepo->updateMeta($sessionId, $meta);
    }

    private function formatChangeSummary(mixed $changeSummary): string
    {
        if (! is_array($changeSummary) || $changeSummary === []) {
            return '';
        }

        return implode("\n", array_map(
            fn ($item) => '- '.trim((string) $item),
            array_filter($changeSummary, fn ($item) => trim((string) $item) !== '')
        ));
    }

    private function replyToUser(SysAiMessage $message, SysAiAgent $agent, string $content): void
    {
        app(AgentService::class)->replyToUser($message, $content, $agent);
    }
}
