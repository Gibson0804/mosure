<?php

namespace App\Events;

use App\Models\SysAiMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MessageReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $enableSSE;

    public function __construct(
        public int $messageId,
        public int $sessionId,
        public string $senderType,
        public ?array $mentions = null
    ) {
        Log::info('MessageReceived: constructor', [
            'message_id' => $this->messageId,
            'session_id' => $this->sessionId,
            'sender_type' => $this->senderType,
        ]);
    }

    public static function fromMessage(SysAiMessage $message): self
    {
        return new self(
            $message->id,
            $message->session_id,
            $message->sender_type,
            $message->mentions,
        );
    }
}
