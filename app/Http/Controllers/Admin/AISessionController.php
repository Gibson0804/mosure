<?php

namespace App\Http\Controllers\Admin;

use App\Services\AiSessionService;
use Illuminate\Http\Request;

class AISessionController extends BaseAdminController
{
    private AiSessionService $sessionService;

    public function __construct(AiSessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    private function currentProjectId(): int
    {
        return (int) session('current_project_id', 0);
    }

    public function list(Request $request)
    {
        $userId = $request->user()->id ?? 0;
        $projectId = $this->currentProjectId();

        $this->sessionService->ensureAllProjectGroups($userId);
        $sessions = $this->sessionService->getAdminSessionsForUser($userId, $projectId);

        return success(['items' => $sessions]);
    }

    public function create(Request $request)
    {
        $userId = $request->user()->id ?? 0;
        $sessionType = $request->input('session_type', 'group');
        $memberIds = $request->input('member_ids', []);
        $title = $request->input('title');

        if (empty($memberIds)) {
            return error([], '请选择至少一个成员');
        }

        try {
            $projectId = $this->currentProjectId();
            if ($projectId <= 0) {
                return error([], '未选择项目，无法创建会话');
            }

            $session = $this->sessionService->createSession($userId, $title ?? '', $sessionType, $memberIds, $projectId);
            if (! $session) {
                return error([], '创建会话失败');
            }

            return success(['item' => $this->sessionService->formatSession($session)]);
        } catch (\Throwable $e) {
            return error([], '创建会话失败: '.$e->getMessage());
        }
    }

    public function update(Request $request, int $id)
    {
        $userId = $request->user()->id ?? 0;
        $title = $request->input('title');
        $avatar = $request->input('avatar');

        $updateData = [];
        if ($title !== null) {
            $updateData['title'] = $title;
        }
        if ($avatar !== null) {
            $updateData['avatar'] = $avatar;
        }

        try {
            $session = $this->sessionService->updateSession($id, $userId, $updateData);

            return $session ? success($this->sessionService->formatSession($session)) : error([], '会话不存在');
        } catch (\Throwable $e) {
            return error([], '更新失败: '.$e->getMessage());
        }
    }

    public function delete(Request $request, int $id)
    {
        $userId = $request->user()->id ?? 0;

        try {
            $deleted = $this->sessionService->deleteSession($id, $userId);

            return $deleted ? success([]) : error([], '会话不存在或无法删除');
        } catch (\Throwable $e) {
            return error([], '删除失败: '.$e->getMessage());
        }
    }

    public function clearMessages(Request $request, int $id)
    {
        $userId = $request->user()->id ?? 0;

        try {
            $this->sessionService->clearSessionMessages($id, $userId);

            return success([]);
        } catch (\Throwable $e) {
            return error([], '清空失败: '.$e->getMessage());
        }
    }

    public function messages(Request $request, int $id)
    {
        $userId = $request->user()->id ?? 0;
        $lastId = $request->input('last_id', 0);
        $limit = (int) $request->input('limit', 10);

        try {
            $result = $this->sessionService->getSessionMessages($id, $userId, (int) $lastId, $limit);

            if (isset($result['error'])) {
                return error([], $result['error'], $result['code'] ?? 400);
            }

            return success($result);
        } catch (\Throwable $e) {
            return error([], '获取消息失败: '.$e->getMessage());
        }
    }

    public function send(Request $request, int $id)
    {
        $userId = $request->user()->id ?? 0;
        $content = $request->input('content');
        $mentions = $request->input('mentions');

        if (empty($content)) {
            return error([], '消息内容不能为空');
        }

        try {
            $result = $this->sessionService->sendMessage($id, $userId, $content, $mentions);

            if (isset($result['error'])) {
                return error([], $result['error'], $result['code'] ?? 400);
            }

            return success($result);
        } catch (\Throwable $e) {
            return error([], '发送消息失败: '.$e->getMessage());
        }
    }

    public function poll(Request $request, int $id)
    {
        $userId = $request->user()->id ?? 0;
        $lastId = $request->input('last_id', 0);

        try {
            $result = $this->sessionService->getSessionMessages($id, $userId, (int) $lastId);

            if (isset($result['error'])) {
                return error([], $result['error'], $result['code'] ?? 400);
            }

            $messages = $result['items'];

            return success([
                'messages' => $messages,
                'count' => count($messages),
            ]);
        } catch (\Throwable $e) {
            return error([], '轮询失败: '.$e->getMessage());
        }
    }

    public function privateChat(Request $request, string $type, string $identifier)
    {
        $userId = $request->user()->id ?? 0;
        $projectId = $this->currentProjectId();

        try {
            $session = $this->sessionService->ensurePrivateSession($userId, $projectId, $type, $identifier);

            if (! $session) {
                return error([], '成员不存在或无法创建私聊');
            }

            return success(['item' => $this->sessionService->formatSession($session)]);
        } catch (\Throwable $e) {
            return error([], '创建私聊失败: '.$e->getMessage());
        }
    }
}
