<?php

namespace App\Http\Controllers\Admin;

use App\Services\AiAgentService;
use Illuminate\Http\Request;

class AIAgentController extends BaseAdminController
{
    private AiAgentService $agentService;

    public function __construct(AiAgentService $agentService)
    {
        $this->agentService = $agentService;
    }

    public function list(Request $request)
    {
        $userId = $request->user()->id ?? 0;
        $agents = $this->agentService->getAgentsForUser($userId);

        return success(['items' => $agents]);
    }

    public function create(Request $request)
    {
        $userId = $request->user()->id ?? 0;
        $name = $request->input('name');

        if (empty($name)) {
            return error([], '成员名称不能为空');
        }

        try {
            $agent = $this->agentService->createCustomAgent($userId, $request->all());

            return success($this->formatAgent($agent));
        } catch (\Throwable $e) {
            return error([], '创建失败: '.$e->getMessage());
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $this->agentService->updateAgent($id, $request->all());

            return success([]);
        } catch (\Throwable $e) {
            return error([], '修改失败: '.$e->getMessage());
        }
    }

    public function previewPrompt(Request $request)
    {
        $name = $request->input('name', '助手');
        $description = $request->input('description', '');
        $personality = $request->input('personality', []);
        $dialogueStyle = $request->input('dialogue_style', []);
        $corePrompt = $request->input('core_prompt', '');

        if (! empty($corePrompt)) {
            return success(['prompt' => $corePrompt]);
        }

        $prompt = $this->agentService->generateCorePrompt($name, $description, $personality, $dialogueStyle);

        return success(['prompt' => $prompt]);
    }

    public function test(Request $request)
    {
        $userId = $request->user()->id ?? 0;
        $content = $request->input('content', '');
        $agentId = $request->input('agent_id');

        if (empty($content)) {
            return error([], '测试内容不能为空');
        }

        try {
            $agentData = null;
            if (! $agentId) {
                $agentData = [
                    'name' => $request->input('name', '助手'),
                    'description' => $request->input('description', ''),
                    'personality' => $request->input('personality', []),
                    'dialogue_style' => $request->input('dialogue_style', []),
                    'core_prompt' => $request->input('core_prompt', ''),
                ];
            }

            $result = $this->agentService->testAgent($userId, $content, $agentId, $agentData ?? []);

            return success($result);
        } catch (\Throwable $e) {
            return error([], '测试失败: '.$e->getMessage());
        }
    }

    private function formatAgent($agent): array
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
        ];
    }
}
