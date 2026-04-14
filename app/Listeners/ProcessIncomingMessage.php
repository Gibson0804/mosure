<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use App\Models\SysAiAgent;
use App\Models\SysAiMessage;
use App\Models\SysAiSession;
use App\Services\ChatProcessors\AgentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProcessIncomingMessage implements ShouldQueue
{
    private AgentService $agentService;

    public function __construct(AgentService $agentService)
    {
        $this->agentService = $agentService;
    }

    public function handle(MessageReceived $event): void
    {
        Log::info('ProcessIncomingMessage: handle called', [
            'message_id' => $event->messageId,
            'session_id' => $event->sessionId,
            'sender_type' => $event->senderType,
        ]);

        $message = SysAiMessage::find($event->messageId);
        if (! $message) {
            Log::error('ProcessIncomingMessage: message not found', [
                'message_id' => $event->messageId,
            ]);

            return;
        }

        if ($message->status === SysAiMessage::STATUS_PROCESSING || $message->status === SysAiMessage::STATUS_COMPLETED) {
            Log::info('ProcessIncomingMessage: skipped (already processed)', [
                'message_id' => $message->id,
                'status' => $message->status,
            ]);

            return;
        }

        Log::info('ProcessIncomingMessage: received', [
            'message_id' => $message->id,
            'sender_type' => $message->sender_type,
            'content_preview' => mb_substr($message->content, 0, 50),
            'has_mentions' => ! empty($message->mentions),
        ]);

        $sessionId = (int) ($event->sessionId ?? $message->session_id);
        $session = SysAiSession::find($sessionId);

        $mentions = is_string($message->mentions) ? json_decode($message->mentions, true) : $message->mentions;

        if (! empty($mentions)) {
            foreach ($mentions as $mention) {
                $this->agentService->process($message->id, $mention['id']);
            }

            return;
        }

        if ($message->sender_type === 'agent') {
            Log::info('ProcessIncomingMessage: agent message without valid mentions, skipping self-dispatch', [
                'message_id' => $message->id,
                'sender_id' => $message->sender_id,
            ]);
            $message->markAsCompleted();

            return;
        }

        if ($session && $session->session_type === 'private') {
            $memberIds = $session->member_ids ? json_decode($session->member_ids, true) : [];
            if (! empty($memberIds)) {
                $targetAgentId = $memberIds[0];
                $targetAgent = SysAiAgent::find($targetAgentId);

                Log::info('ProcessIncomingMessage: private chat, dispatching to agent directly', [
                    'message_id' => $message->id,
                    'agent_id' => $targetAgentId,
                    'agent_name' => $targetAgent?->name,
                ]);

                $this->agentService->process($message->id, $targetAgentId);

                return;
            }
        }

        $secretary = SysAiAgent::where('type', 'secretary')
            ->where('enabled', true)
            ->first();

        if (! $secretary) {
            Log::error('ProcessIncomingMessage: secretary not found');

            return;
        }

        Log::info('ProcessIncomingMessage: dispatching to secretary', [
            'message_id' => $message->id,
            'secretary_id' => $secretary->id,
        ]);

        $this->agentService->process($message->id, $secretary->id);
    }
}
