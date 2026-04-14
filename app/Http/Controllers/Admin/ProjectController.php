<?php

namespace App\Http\Controllers\Admin;

use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends BaseAdminController
{
    private $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    /**
     * 显示项目列表页面
     */
    public function index()
    {
        $user = Auth::user();
        $projectList = [];

        // 如果是admin 同时账户激活状态 返回所有项目
        if ($user && $user->is_admin && $user->is_active) {
            $projectList = $this->projectService->getProjects();
        } else {
            // todo::用户项目关系表
            // $projectList = $this->projectService->getProjects(auth()->id());
        }

        return viewShow('Project/ProjectList', [
            'projects' => $projectList,
        ]);
    }

    /**
     * 创建新项目
     */
    public function create(Request $request)
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'name' => ['required', 'max:100'],
                'prefix' => ['required', 'max:20', 'regex:/^[a-z0-9_]+$/'],
                'template' => ['required', 'in:blank,blog,corporate,import'],
                'description' => ['nullable', 'max:500'],
            ]);

            $data = $request->input();

            try {
                // 如果 template 是 import，先创建空白项目，然后导入
                if ($data['template'] === 'import') {
                    // 检查是否上传了模板文件
                    $uploaded = $request->file('template_file');

                    // 处理前端 Upload 组件发送的数组格式
                    if (is_array($uploaded)) {
                        // Upload 组件使用 valuePropName="fileList"，所以文件在数组中
                        // 数组中的 originFileObj 才是真正的 UploadedFile 对象
                        $uploaded = $uploaded[0]['originFileObj'] ?? null;
                    }

                    if (! $uploaded || ! ($uploaded instanceof \Illuminate\Http\UploadedFile)) {
                        return back()->withErrors(['message' => '请选择要导入的模板文件']);
                    }

                    // 保存上传的文件
                    $uploadDir = storage_path('app/import_tmp_uploads');
                    if (! is_dir($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                    }

                    $fileName = uniqid('import_', true).'.zip';
                    $fullPath = $uploadDir.'/'.$fileName;

                    if (! $uploaded->move($uploadDir, $fileName)) {
                        return back()->withErrors(['message' => '文件保存失败']);
                    }

                    // 检查文件是否成功保存
                    if (! file_exists($fullPath)) {
                        return back()->withErrors(['message' => '文件保存后不存在']);
                    }

                    // 先创建空白项目
                    $data['template'] = 'blank';
                    $project = $this->projectService->createProject($data);

                    // 设置当前项目前缀，以便导入时使用正确的表
                    session(['current_project_prefix' => $project->prefix]);

                    // 调用项目导入接口
                    try {
                        $importService = app(\App\Services\ProjectImportService::class);
                        $importService->import($fullPath);
                        @unlink($fullPath);
                    } catch (\Exception $e) {
                        @unlink($fullPath);
                        // 导入失败，但项目已创建，删除项目
                        $project->delete();

                        return back()->withErrors(['message' => '导入模板失败: '.$e->getMessage()]);
                    }
                } else {
                    // 其他模板类型，正常创建项目
                    $project = $this->projectService->createProject($data);
                }

                // 创建成功后重定向到项目列表
                return redirect()->route('project.index');
            } catch (\Exception $e) {
                return back()->withErrors(['message' => $e->getMessage()]);
            }
        }

        return viewShow('Project/ProjectCreate');
    }

    /**
     * 选择项目后进入系统
     */
    public function select($id)
    {
        // 使用项目服务选择项目
        $this->projectService->selectProject($id);

        // 重定向到仪表盘
        return redirect()->route('dashboard');
    }

    /**
     * 编辑项目
     */
    public function edit(Request $request, $id)
    {
        $project = $this->projectService->getProject($id);

        if (! $project) {
            return error('项目不存在');
        }

        if ($request->isMethod('post')) {
            $this->validate($request, [
                'name' => ['required', 'max:100'],
                'description' => ['nullable', 'max:500'],
            ]);

            $data = $request->input();

            // 更新项目
            $this->projectService->updateProject($id, $data);

            return redirect()->route('project.index');
        }

        return viewShow('Project/ProjectEdit', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
            ],
        ]);
    }

    /**
     * 删除项目
     */
    public function delete($id)
    {
        try {
            // 使用项目服务删除项目
            $this->projectService->deleteProject($id);

            // 删除成功后重定向到项目列表
            return redirect()->route('project.index');
        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    /**
     * 生成项目前缀 API
     * /webapi/project/generate-prefix?name=xxx
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generatePrefix(Request $request)
    {
        $name = $request->query('name');
        if (empty($name)) {
            return error([], '项目名称不能为空');
        }
        try {
            $prefix = $this->projectService->generatePrefix($name);

            return success(['prefix' => $prefix]);
        } catch (\Exception $e) {
            return error([], $e->getMessage());
        }
    }
}
