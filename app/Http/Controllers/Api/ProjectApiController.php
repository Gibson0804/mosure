<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProjectService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectApiController extends Controller
{
    private $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    /**
     * 获取项目列表
     */
    public function list(Request $request)
    {
        try {
            $user = Auth::user();

            // 如果是admin且账户激活，返回所有项目
            if ($user && $user->is_admin && $user->is_active) {
                $projects = $this->projectService->getProjects();
            } else {
                // 普通用户返回其有权限的项目
                // TODO: 实现用户项目权限关系
                $projects = $this->projectService->getProjects();
            }

            // 格式化返回数据
            $data = [];
            foreach ($projects as $project) {
                $data[] = [
                    'id' => $project->id,
                    'name' => $project->name,
                    'prefix' => $project->prefix,
                    'description' => $project->description ?? '',
                    'template' => $project->template ?? '',
                    'created_at' => $project->created_at,
                ];
            }

            return response()->json([
                'code' => 200,
                'message' => 'success',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '获取项目列表失败: '.$e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * 获取项目详情
     */
    public function detail(Request $request, $id)
    {
        try {
            $project = $this->projectService->getProject($id);

            return response()->json([
                'code' => 200,
                'message' => 'success',
                'data' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'prefix' => $project->prefix,
                    'description' => $project->description ?? '',
                    'template' => $project->template ?? '',
                    'created_at' => $project->created_at,
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'code' => 404,
                'message' => '项目不存在',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => '获取项目详情失败: '.$e->getMessage(),
                'data' => null,
            ], 500);
        }
    }
}
