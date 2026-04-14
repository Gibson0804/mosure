<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\AiSessionService;
use Illuminate\Http\Request;

class AiAgentController extends Controller
{
    public function __construct(private AiSessionService $service) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => ['items' => $this->service->listAgents((int) $data['project_id'])],
        ]);
    }

    public function privateChat(Request $request, string $type, string $identifier)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'min:1'],
        ]);

        $session = $this->service->ensurePrivateSession(
            (int) $request->user()->id,
            (int) $data['project_id'],
            $type,
            $identifier
        );

        if (! $session) {
            return response()->json(['code' => 404, 'message' => '成员不存在', 'data' => null], 404);
        }

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => ['item' => $this->service->formatSession($session)],
        ]);
    }
}
