<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\PluginService;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    private ProjectService $projectService;

    private PluginService $pluginService;

    public function __construct(ProjectService $projectService, PluginService $pluginService)
    {
        $this->projectService = $projectService;
        $this->pluginService = $pluginService;
    }

    /**
     * 客户端获取当前用户可访问的项目列表。
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'code' => 401,
                    'message' => 'Unauthenticated',
                    'data' => null,
                ], 401);
            }

            // 目前和后台逻辑保持一致：
            // - admin + active: 返回所有项目
            // - 普通用户：暂时也返回所有项目，后续可以接入用户项目权限表
            if ($user->is_admin && $user->is_active) {
                $projects = $this->projectService->getProjects();
            } else {
                // TODO: 实现用户项目权限关系后，这里按用户过滤
                $projects = $this->projectService->getProjects();
            }

            $data = [];
            $unreadByProject = DB::table('sys_ai_sessions as sessions')
                ->leftJoin('sys_ai_messages as messages', function ($join) {
                    $join->on('messages.session_id', '=', 'sessions.id')
                        ->where('messages.is_system', 0)
                        ->where('messages.sender_type', 'agent')
                        ->whereRaw('messages.id > COALESCE(sessions.last_read_message_id, 0)');
                })
                ->where('sessions.user_id', $user->id)
                ->select('sessions.project_id', DB::raw('COUNT(messages.id) as unread_count'))
                ->groupBy('sessions.project_id')
                ->pluck('unread_count', 'sessions.project_id');

            foreach ($projects as $project) {
                session(['current_project_prefix' => $project->prefix]);
                // 从 service 层获取前端入口（插件前端 + 托管页面）
                $frontends = $this->projectService->getProjectFrontends($project->prefix);

                $data[] = [
                    'id' => $project->id,
                    'name' => $project->name,
                    'prefix' => $project->prefix,
                    'description' => $project->description ?? '',
                    'template' => $project->template ?? '',
                    'created_at' => $project->created_at,
                    'frontends' => $frontends,
                    'unread_count' => (int) ($unreadByProject[$project->id] ?? 0),
                ];
            }

            return response()->json([
                'code' => 200,
                'message' => 'success',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'code' => 500,
                'message' => '获取项目列表失败: '.$e->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}
