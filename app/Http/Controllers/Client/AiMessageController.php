<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\AiSessionService;
use Illuminate\Http\Request;

class AiMessageController extends Controller
{
    public function __construct(private AiSessionService $service) {}

    public function messages(Request $request, int $id)
    {
        $data = $request->validate([
            'last_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'mark_read' => ['nullable', 'boolean'],
        ]);

        $result = $this->service->getSessionMessagesWithReadOption(
            $id,
            (int) $request->user()->id,
            (int) ($data['last_id'] ?? 0),
            (int) ($data['limit'] ?? 20),
            (bool) ($data['mark_read'] ?? false)
        );

        if (isset($result['error'])) {
            return response()->json(['code' => $result['code'], 'message' => $result['error'], 'data' => null], $result['code']);
        }

        return response()->json(['code' => 200, 'message' => 'success', 'data' => $result]);
    }

    public function poll(Request $request, int $id)
    {
        $data = $request->validate([
            'last_id' => ['nullable', 'integer', 'min:0'],
            'mark_read' => ['nullable', 'boolean'],
        ]);

        $result = $this->service->getSessionMessagesWithReadOption(
            $id,
            (int) $request->user()->id,
            (int) ($data['last_id'] ?? 0),
            20,
            (bool) ($data['mark_read'] ?? false)
        );

        if (isset($result['error'])) {
            return response()->json(['code' => $result['code'], 'message' => $result['error'], 'data' => null], $result['code']);
        }

        return response()->json(['code' => 200, 'message' => 'success', 'data' => $result]);
    }

    public function send(Request $request, int $id)
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
            'mentions' => ['nullable', 'array'],
        ]);

        $result = $this->service->sendMessage(
            $id,
            (int) $request->user()->id,
            (string) $data['content'],
            $data['mentions'] ?? []
        );

        if (isset($result['error'])) {
            return response()->json(['code' => $result['code'], 'message' => $result['error'], 'data' => null], $result['code']);
        }

        return response()->json(['code' => 200, 'message' => 'success', 'data' => $result]);
    }
}
