<?php

namespace App\Http\Controllers\Admin;

use App\Services\MediaService;
use App\Support\StructuredLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;

class MediaController extends BaseAdminController
{
    protected $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * 批量移动媒体
     */
    public function batchMove(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'to_folder_id' => 'required|integer',
            ]);
            $this->mediaService->batchMove($validated['ids'], (int) $validated['to_folder_id']);

            return success(true, '移动成功');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors([
                'message' => $e->validator->errors()->first(),
            ]);
        } catch (\Exception $e) {
            StructuredLogger::error('media.batch_move.failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['message' => '批量移动失败']);
        }
    }

    /**
     * 显示媒体资源列表
     */
    public function index(Request $request)
    {
        try {
            $media = $this->mediaService->getMediaList($request->all());
            if ($media) {
                foreach ($media as $item) {
                    $item->original_filename = $item->id.'.'.$item->original_filename;
                }
            }

            return viewShow('Media/MediaList', [
                'media' => $media,
                'filters' => $request->only(['type', 'search', 'folder_id', 'tag']),
            ]);
        } catch (\Exception $e) {
            StructuredLogger::error('media.list.failed', [
                'error' => $e->getMessage(),
                'filters' => $request->only(['type', 'search', 'folder_id', 'tag']),
            ]);

            return redirect()->back()->withErrors(['message' => '获取媒体列表失败']);
        }
    }

    public function upload(Request $request)
    {
        $uploadedFile = $request->file('file');
        if ($uploadedFile instanceof UploadedFile && ! $uploadedFile->isValid()) {
            return error([
                'php_upload_error' => $uploadedFile->getError(),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
            ], '文件上传失败: '.$uploadedFile->getErrorMessage());
        }

        try {
            $request->validate([
                'file' => 'required|file|max:102400',
                'description' => 'nullable|string|max:500',
                'filename' => 'nullable|string|max:255',
                'folder_id' => 'nullable|integer',
                'tags' => 'nullable',
            ], [
                'file.uploaded' => '文件上传失败（可能超过服务器上传限制，或临时目录无写权限）',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $fileErrors = $errors['file'] ?? [];
            $message = $fileErrors[0] ?? '参数验证失败';

            return error([
                'errors' => $errors,
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
            ], $message);
        }

        if (! $request->hasFile('file')) {
            return error([
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
            ], '没有接收到有效上传文件');
        }

        $res = $this->mediaService->createMedia(
            $request->file('file'),
            $request->description,
            $request->input('filename'),
            [
                'folder_id' => $request->input('folder_id'),
                'tags' => $request->input('tags'),
            ]
        );

        StructuredLogger::info('media.upload.success', [
            'media_id' => $res['id'] ?? null,
            'folder_id' => $request->input('folder_id'),
            'filename' => $request->input('filename') ?: $request->file('file')?->getClientOriginalName(),
        ]);

        return success($res, '上传成功');
    }

    /**
     * 显示上传媒体资源页面
     */
    public function create(Request $request)
    {
        if ($request->isMethod('post')) {
            try {
                $request->validate([
                    'file' => 'required|file|max:102400',
                    'description' => 'nullable|string|max:500',
                    'filename' => 'nullable|string|max:255',
                    'folder_id' => 'nullable|integer',
                    'tags' => 'nullable',
                ]);

                if (! $request->hasFile('file')) {
                    return back()->withInput()
                        ->withErrors(['message' => '没有文件被上传']);
                }

                $this->mediaService->createMedia(
                    $request->file('file'),
                    $request->description,
                    $request->input('filename'),
                    [
                        'folder_id' => $request->input('folder_id'),
                        'tags' => $request->input('tags'),
                    ]
                );

                return redirect()->route('media.index');
            } catch (\Exception $e) {
                StructuredLogger::error('media.create.failed', [
                    'error' => $e->getMessage(),
                    'filename' => $request->input('filename') ?: $request->file('file')?->getClientOriginalName(),
                ]);

                return back()->withErrors(['message' => '媒体文件上传失败: '.$e->getMessage()]);
            }
        }

        return viewShow('Media/MediaUpload');
    }

    /**
     * 显示媒体资源详情
     */
    public function show($id)
    {
        try {
            $media = $this->mediaService->getMediaById($id);

            return viewShow('Media/MediaDetail', ['media' => $media]);
        } catch (\Exception $e) {
            StructuredLogger::error('media.detail.failed', [
                'media_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['message' => '获取媒体详情失败']);
        }
    }

    /**
     * 显示编辑媒体资源页面
     */
    public function edit(Request $request, $id)
    {
        if ($request->isMethod('post')) {
            try {
                $validated = $request->validate([
                    'file' => 'nullable|file|max:102400',
                    'filename' => 'required|string|max:255',
                    'description' => 'nullable|string|max:500',
                    'folder_id' => 'nullable|integer',
                    'tags' => 'nullable',
                ], [
                    'file.max' => '文件大小不能超过100MB',
                    'filename.required' => '文件名不能为空',
                    'filename.max' => '文件名不能超过255个字符',
                    'description.max' => '描述不能超过500个字符',
                ]);

                // 如果有新文件，先处理文件替换
                if ($request->hasFile('file')) {
                    $this->mediaService->replaceMediaFile($id, $request->file('file'));
                }

                // 更新其他信息
                $this->mediaService->updateMedia($id, $validated);

                return back();
            } catch (\Illuminate\Validation\ValidationException $e) {
                return back()->withInput()->withErrors([
                    'message' => $e->validator->errors()->first(),
                ]);
            } catch (\Exception $e) {
                StructuredLogger::error('media.update.failed', [
                    'media_id' => $id,
                    'error' => $e->getMessage(),
                ]);

                return back()->withInput()->withErrors(['message' => '更新媒体资源失败: '.$e->getMessage()]);
            }
        }

        try {
            $media = $this->mediaService->getMediaById($id);

            return viewShow('Media/MediaEdit', ['media' => $media]);
        } catch (\Exception $e) {
            StructuredLogger::error('media.edit_page.failed', [
                'media_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['message' => '获取编辑页面失败']);
        }
    }

    /**
     * 删除媒体资源
     */
    public function delete($id)
    {
        try {
            $this->mediaService->deleteMedia($id);

            StructuredLogger::info('media.delete.success', [
                'media_id' => $id,
            ]);

            return redirect()->route('media.index')
                ->with('success', '媒体资源删除成功');
        } catch (\Exception $e) {
            StructuredLogger::error('media.delete.failed', [
                'media_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withErrors(['message' => '删除媒体资源失败']);
        }
    }

    /**
     * 批量删除媒体资源
     */
    public function batchDelete(Request $request)
    {
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
            ]);

            $deleted = $this->mediaService->batchDelete($validated['ids']);

            StructuredLogger::info('media.batch_delete.success', [
                'requested_count' => count($validated['ids']),
                'deleted' => $deleted,
            ]);

            return back()->with('success', '批量删除成功');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors([
                'message' => $e->validator->errors()->first(),
            ]);
        } catch (\Exception $e) {
            StructuredLogger::error('media.batch_delete.failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['message' => '批量删除失败']);
        }
    }
}
