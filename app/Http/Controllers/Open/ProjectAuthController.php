<?php

namespace App\Http\Controllers\Open;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectAuthController extends Controller
{
    public function __construct(private ProjectAuthService $authService) {}

    public function login(Request $request, string $projectPrefix): JsonResponse
    {
        try {
            $this->bootstrapProject($projectPrefix);
            $account = (string) ($request->input('account') ?? $request->input('email') ?? $request->input('username') ?? '');
            $password = (string) $request->input('password', '');
            $result = $this->authService->login($account, $password, $request);

            return response()->json(['code' => 200, 'message' => 'login_success', 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['code' => 401, 'message' => $e->getMessage(), 'data' => null], 401);
        }
    }

    public function register(Request $request, string $projectPrefix): JsonResponse
    {
        try {
            $this->bootstrapProject($projectPrefix);
            $result = $this->authService->register($request->all(), $request);

            return response()->json(['code' => 200, 'message' => 'register_success', 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['code' => 403, 'message' => $e->getMessage(), 'data' => null], 403);
        }
    }

    public function me(Request $request, string $projectPrefix): JsonResponse
    {
        try {
            $this->bootstrapProject($projectPrefix);
            $user = $this->currentUser($request);
            if (! $user) {
                return response()->json(['code' => 401, 'message' => 'Unauthenticated', 'data' => null], 401);
            }

            return response()->json(['code' => 200, 'message' => 'success', 'data' => ['user' => $this->authService->serializeUser($user)]]);
        } catch (\Throwable $e) {
            return response()->json(['code' => 400, 'message' => $e->getMessage(), 'data' => null], 400);
        }
    }

    public function logout(Request $request, string $projectPrefix): JsonResponse
    {
        try {
            $this->bootstrapProject($projectPrefix);
            $this->authService->logout($this->extractBearer($request));

            return response()->json(['code' => 200, 'message' => 'logout_success', 'data' => null]);
        } catch (\Throwable $e) {
            return response()->json(['code' => 400, 'message' => $e->getMessage(), 'data' => null], 400);
        }
    }

    private function bootstrapProject(string $projectPrefix): void
    {
        if (! Project::where('prefix', $projectPrefix)->exists()) {
            throw new \RuntimeException('项目不存在');
        }
        session(['current_project_prefix' => $projectPrefix]);
        $this->authService->ensureSchema();
    }

    private function currentUser(Request $request)
    {
        return $this->authService->authenticateToken($this->extractBearer($request));
    }

    private function extractBearer(Request $request): ?string
    {
        $auth = (string) $request->header('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }

        $token = (string) $request->header('X-Project-User-Token', '');

        return $token !== '' ? $token : null;
    }
}
