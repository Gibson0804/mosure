<?php

namespace App\Services\ChatProcessors;

use App\Models\SysAiAgent;
use App\Models\SysAiMessage;
use App\Services\GptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomAgentService
{
    private GptService $gptService;

    public function __construct()
    {
        $this->gptService = app(GptService::class);
    }

    public function process(SysAiMessage $message, SysAiAgent $agent): void
    {
        Log::info('CustomAgentService: processing', [
            'message_id' => $message->id,
            'agent_name' => $agent->name,
        ]);

        $sessionId = $message->session_id;
        $session = DB::table('sys_ai_sessions')->where('id', $sessionId)->first();
        if (! $session) {
            Log::error('CustomAgentService: session not found', ['session_id' => $sessionId]);

            return;
        }

        $userId = $session->user_id;
        $question = $message->content;

        $prompt = $this->buildPrompt($agent, $question);

        $response = $this->gptService->chat('default', [
            ['role' => 'system', 'content' => $agent->core_prompt ?? '你是一个AI助手。'],
            ['role' => 'user', 'content' => $prompt],
        ], $userId, $question, false, 'text');

        $answer = $response['text'] ?? $response['content'] ?? '抱歉，我无法回答您的问题。';

        Log::info('CustomAgentService: response', [
            'message_id' => $message->id,
            'answer_preview' => mb_substr($answer, 0, 100),
        ]);

        $this->replyToUser($message, $agent, $answer);
    }

    private function buildPrompt(SysAiAgent $agent, string $question): string
    {
        $prompt = '';

        if ($agent->capabilities) {
            $capabilities = is_string($agent->capabilities) ? json_decode($agent->capabilities, true) : $agent->capabilities;
            if (is_array($capabilities)) {
                $prompt .= "你的能力：\n";
                foreach ($capabilities as $cap) {
                    $prompt .= "- {$cap}\n";
                }
                $prompt .= "\n";
            }
        }

        $prompt .= "用户问题：{$question}";

        return $prompt;
    }

    private function replyToUser(SysAiMessage $message, SysAiAgent $agent, string $content): void
    {
        $agentService = app(AgentService::class);
        $agentService->replyToUser($message, $content, $agent);
    }
}
