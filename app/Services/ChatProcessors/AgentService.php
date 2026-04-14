<?php

namespace App\Services\ChatProcessors;

use App\Events\MessageReceived;
use App\Models\SysAiAgent;
use App\Models\SysAiMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgentService
{
    private static $meaninglessMessages = [
        '好的，收到您的问题',
        '稍等，我会尽快回答',
        '收到了，我查看下',
    ];

    public function process(int $messageId, int $agentId): void
    {
        $message = SysAiMessage::find($messageId);
        if (! $message) {
            Log::error('AgentService: message not found', ['message_id' => $messageId]);

            return;
        }

        $agent = SysAiAgent::find($agentId);
        if (! $agent) {
            Log::error('AgentService: agent not found', ['agent_id' => $agentId]);

            return;
        }

        Log::info('Agent processing', [
            'message_id' => $messageId,
            'agent_id' => $agentId,
            'agent_type' => $agent->type,
            'agent_name' => $agent->name,
        ]);

        $message->markAsProcessing();

        $this->replyToUser($message, self::$meaninglessMessages[rand(0, count(self::$meaninglessMessages) - 1)], $agent);

        match ($agent->type) {
            'secretary' => app(SecretaryService::class)->process($message, $agent),
            'project' => app(ProjectAgentService::class)->process($message, $agent),
            'custom' => app(CustomAgentService::class)->process($message, $agent),
            default => Log::error('Unknown agent type', ['type' => $agent->type]),
        };

        $message->markAsCompleted();
    }

    public static function getConversationContext(int $sessionId, int $limit = 20): array
    {
        $messages = DB::table('sys_ai_messages')
            ->where('session_id', $sessionId)
            ->where('is_system', 0)
            ->where('is_meaningless', 0)
            ->whereIn('status', [SysAiMessage::STATUS_COMPLETED])
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get(['id', 'sender_id', 'sender_type', 'sender_name', 'content', 'created_at']);

        Log::info('SecretaryAgentService: messages '.json_encode($messages, JSON_UNESCAPED_UNICODE));
        $context = [];
        foreach ($messages->reverse() as $msg) {
            $role = $msg->sender_type === 'user' ? 'user' : 'assistant';
            $context[] = [
                'role' => $role,
                'content' => $msg->content,
                'sender' => $msg->sender_name,
                'sender_id' => $msg->sender_id,
            ];
        }

        return $context;
    }

    public function sendMessage(
        int $sessionId,
        int $senderId,
        string $senderType,
        string $senderName,
        string $content,
        ?array $mentions = null,
        bool $isSystem = false,
        bool $dispatch = true,
        bool $enableSSE = false
    ): SysAiMessage {

        $status = SysAiMessage::STATUS_PENDING;
        $isMeaningless = 0;
        if (in_array($content, self::$meaninglessMessages)) {
            $isMeaningless = 1;
            $status = SysAiMessage::STATUS_COMPLETED;
        }

        $message = SysAiMessage::create([
            'session_id' => $sessionId,
            'sender_id' => $senderId,
            'sender_type' => $senderType,
            'sender_name' => $senderName,
            'content' => $content,
            'mentions' => $mentions ? json_encode($mentions) : null,
            'is_system' => $isSystem ? 1 : 0,
            'is_meaningless' => $isMeaningless,
            'status' => $status,
        ]);

        Log::info('Message created', [
            'message_id' => $message->id,
            'session_id' => $sessionId,
            'sender_type' => $senderType,
            'has_mentions' => ! empty($mentions),
            'dispatch' => $dispatch,
        ]);

        if ($dispatch) {
            event(new MessageReceived(
                $message->id,
                $sessionId,
                $senderType,
                $mentions
            ));
        }

        return $message;
    }

    public function replyToUser(SysAiMessage $originalMessage, string $content, SysAiAgent $agent): SysAiMessage
    {
        return $this->sendMessage(
            sessionId: $originalMessage->session_id,
            senderId: $agent->id,
            senderType: 'agent',
            senderName: $agent->name,
            content: $content,
            mentions: null,
            dispatch: false
        );
    }

    public function dispatchToAgent(SysAiMessage $originalMessage, string $taskContent, SysAiAgent $senderAgent, SysAiAgent $targetAgent): SysAiMessage
    {
        return $this->sendMessage(
            sessionId: $originalMessage->session_id,
            senderId: $senderAgent->id,
            senderType: 'agent',
            senderName: $senderAgent->name,
            content: "@{$targetAgent->name} {$taskContent}",
            mentions: [
                ['id' => $targetAgent->id, 'type' => 'agent', 'name' => $targetAgent->name],
            ],
            dispatch: true
        );
    }
}
