<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\AiSessionService;
use Illuminate\Http\Request;

class AiSessionController extends Controller
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
            'data' => ['items' => $this->service->getSessionsForUser((int) $request->user()->id, (int) $data['project_id'])],
        ]);
    }

    public function ensureProjectGroup(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'min:1'],
        ]);

        $session = $this->service->ensureProjectGroup((int) $request->user()->id, (int) $data['project_id']);

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => ['item' => $this->service->formatSession($session)],
        ]);
    }

    public function delete(Request $request, int $id)
    {
        $ok = $this->service->deleteSession($id, (int) $request->user()->id);
        $status = $ok ? 200 : 404;

        return response()->json([
            'code' => $status,
            'message' => $ok ? 'success' : 'not found',
            'data' => null,
        ], $status);
    }

    public function clearMessages(Request $request, int $id)
    {
        $ok = $this->service->clearSessionMessages($id, (int) $request->user()->id);
        $status = $ok ? 200 : 404;

        return response()->json([
            'code' => $status,
            'message' => $ok ? 'success' : 'not found',
            'data' => null,
        ], $status);
    }
}
