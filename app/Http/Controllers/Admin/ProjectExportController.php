<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProjectFunction;
use App\Services\MediaFolderService;
use App\Services\MenuService;
use App\Services\MoldService;
use App\Services\ProjectExportService;
use App\Services\ProjectImportService;
use Illuminate\Http\Request;

class ProjectExportController extends BaseAdminController
{
    protected $moldService;

    protected $exportService;

    protected $importService;

    protected $mediaFolderService;

    protected $menuService;

    public function __construct(MoldService $moldService, ProjectExportService $exportService, ProjectImportService $importService, MediaFolderService $mediaFolderService, MenuService $menuService)
    {
        $this->moldService = $moldService;
        $this->exportService = $exportService;
        $this->importService = $importService;
        $this->mediaFolderService = $mediaFolderService;
        $this->menuService = $menuService;
    }

    public function index(Request $request)
    {
        // 获取所有模型作为可选项
        $molds = $this->moldService->getAllMold();
        $models = [];
        foreach ($molds as $m) {
            $models[] = [
                'id' => $m['id'] ?? $m->id,
                'name' => $m['name'] ?? $m->name,
                'slug' => $m['table_name'] ?? $m->table_name,
            ];
        }

        // 获取云函数列表（按类型分类）
        $functions = ProjectFunction::get();
        $functionsByType = [
            'endpoints' => [],
            'hooks' => [],
        ];
        foreach ($functions as $fn) {
            $type = $fn->type; // 'endpoint' 或 'hook'
            if ($type === 'endpoint') {
                $functionsByType['endpoints'][] = [
                    'id' => $fn->id,
                    'name' => $fn->name,
                    'slug' => $fn->slug,
                ];
            } elseif ($type === 'hook') {
                $functionsByType['hooks'][] = [
                    'id' => $fn->id,
                    'name' => $fn->name,
                    'slug' => $fn->slug,
                ];
            }
        }

        // 获取媒体文件夹列表
        $mediaFolders = $this->mediaFolderService->getTree();

        // 获取用户自定义菜单列表（仅一级菜单）
        $menus = $this->menuService->getUserDefinedMenus();

        return viewShow('Manage/Export', [
            'models' => $models,
            'functions' => $functionsByType,
            'mediaFolders' => $mediaFolders,
            'menus' => $menus,
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'modelIds' => 'nullable|array',
            'includeMenus' => 'nullable|array',
            'includeFunctions' => 'nullable|array',
            'mediaFolders' => 'nullable|array',
            'modelDataMap' => 'nullable|array',
        ]);

        // 直接触发导出并返回下载
        $result = $this->exportService->buildZip(
            $validated['modelIds'],
            [
                'includeMenus' => (array) ($validated['includeMenus'] ?? []),
                'includeFunctions' => (array) ($validated['includeFunctions'] ?? []),
                'mediaFolders' => (array) ($validated['mediaFolders'] ?? []),
                'modelDataMap' => (array) ($validated['modelDataMap'] ?? []),
            ]
        );

        // 返回 JSON，包含签名的临时下载链接
        return success([
            'download_url' => $result['download_url'] ?? null,
            'filename' => $result['filename'] ?? null,
        ]);
    }

    // 解析导入文件，检测冲突
    public function parseImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:zip|max:51200', // 50MB
        ]);

        $uploaded = $request->file('file');
        if (! $uploaded || ! $uploaded->isValid()) {
            return error('文件上传失败: '.($uploaded ? $uploaded->getErrorMessage() : '未知错误'));
        }

        // 确保目录存在
        $uploadDir = storage_path('app/import_tmp_uploads');
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        // 手动保存文件
        $fileName = uniqid('import_', true).'.zip';
        $fullPath = $uploadDir.'/'.$fileName;

        if (! $uploaded->move($uploadDir, $fileName)) {
            return error('文件保存失败');
        }

        // 检查文件是否成功保存
        if (! file_exists($fullPath)) {
            return error('文件保存后不存在: '.$fullPath);
        }

        try {
            $result = $this->importService->parseImport($fullPath);
            @unlink($fullPath);

            return success($result);
        } catch (\Exception $e) {
            @unlink($fullPath);

            return error('解析文件失败: '.$e->getMessage());
        }
    }

    // 执行导入
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:zip|max:51200', // 50MB
            'options' => 'nullable|string',
        ]);

        $uploaded = $request->file('file');
        if (! $uploaded || ! $uploaded->isValid()) {
            return error('文件上传失败: '.($uploaded ? $uploaded->getErrorMessage() : '未知错误'));
        }

        // 确保目录存在
        $uploadDir = storage_path('app/import_tmp_uploads');
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        // 手动保存文件
        $fileName = uniqid('import_', true).'.zip';
        $fullPath = $uploadDir.'/'.$fileName;

        if (! $uploaded->move($uploadDir, $fileName)) {
            return error('文件保存失败');
        }

        // 检查文件是否成功保存
        if (! file_exists($fullPath)) {
            return error('文件保存后不存在: '.$fullPath);
        }

        // 解析 options
        $options = [];
        $optionsString = $request->input('options');
        if ($optionsString) {
            $decoded = json_decode($optionsString, true);
            if (is_array($decoded)) {
                $options = $decoded;
            }
        }

        try {
            $result = $this->importService->import($fullPath, $options);
            @unlink($fullPath);

            return success($result);
        } catch (\Exception $e) {
            @unlink($fullPath);

            return error('导入失败: '.$e->getMessage());
        }
    }

    public function download(Request $request)
    {
        // 该接口只负责下载现有文件，必须带有签名参数（路由中间件）
        $validated = $request->validate([
            'file' => ['required', 'regex:/^[A-Za-z0-9._\-]+$/'],
        ]);

        $filename = $validated['file'];
        $path = storage_path('app/exports/'.$filename);
        if (! file_exists($path)) {
            abort(404, 'File not found');
        }

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }
}
