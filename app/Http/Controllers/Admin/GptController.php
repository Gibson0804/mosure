<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\ProcessSysTaskJob;
use App\Models\SysTask;
use App\Repository\SysTaskRepository;
use App\Services\GptService;
use App\Services\ProjectConfigService;
use App\Services\SystemConfigService;
use Illuminate\Http\Request;

class GptController extends BaseAdminController
{
    private $gptService;

    private ProjectConfigService $projectConfigService;

    private SystemConfigService $systemConfigService;

    private SysTaskRepository $sysTaskRepository;

    public function __construct(GptService $gptService, ProjectConfigService $projectConfigService, SystemConfigService $systemConfigService, SysTaskRepository $sysTaskRepository)
    {
        $this->gptService = $gptService;
        $this->projectConfigService = $projectConfigService;
        $this->systemConfigService = $systemConfigService;
        $this->sysTaskRepository = $sysTaskRepository;
    }

    public function listModels()
    {
        $res = $this->gptService->listModels();

        return success($res);
    }

    public function doAsk(Request $request)
    {
        $question = (string) ($request->input('question') ?? '');

        return success($this->gptService->chat('', [
            ['role' => 'user', 'content' => $question],
        ]));
    }

    /**
     * 富文本AI编辑：传入编辑需求与原始HTML，返回 result_text 和 html
     */
    public function richTextEdit(Request $request)
    {
        $instruction = (string) ($request->input('instruction') ?? '');
        $html = (string) ($request->input('html') ?? '');
        $targetLength = $request->input('target_length');
        $targetLength = $targetLength === null ? null : (int) $targetLength;

        if ($instruction === '' || $html === '') {
            return success([
                'task_id' => 0,
                'error_message' => '缺少必要参数：instruction 或 html',
            ]);
        }

        $task = $this->sysTaskRepository->createTask([
            'domain' => 'gpt',
            'type' => SysTask::TYPE_RICH_TEXT_EDIT,
            'status' => SysTask::STATUS_PENDING,
            'title' => 'AI修改富文本',
            'payload' => [
                'instruction' => $instruction,
                'html' => $html,
                'target_length' => $targetLength,
            ],
        ]);

        ProcessSysTaskJob::dispatch($task->id);

        return success([
            'task_id' => $task->id,
        ]);
    }

    /**
     * Markdown AI编辑：传入编辑需求与原始Markdown，返回 result_text 和 markdown
     */
    public function markdownEdit(Request $request)
    {
        $instruction = (string) ($request->input('instruction') ?? '');
        $markdown = (string) ($request->input('markdown') ?? '');

        if ($instruction === '' || $markdown === '') {
            return success([
                'task_id' => 0,
                'error_message' => '缺少必要参数：instruction 或 markdown',
            ]);
        }

        $task = $this->sysTaskRepository->createTask([
            'domain' => 'gpt',
            'type' => SysTask::TYPE_MARKDOWN_EDIT,
            'status' => SysTask::STATUS_PENDING,
            'title' => 'AI修改Markdown',
            'payload' => [
                'instruction' => $instruction,
                'markdown' => $markdown,
            ],
        ]);

        ProcessSysTaskJob::dispatch($task->id);

        return success([
            'task_id' => $task->id,
        ]);
    }
}
