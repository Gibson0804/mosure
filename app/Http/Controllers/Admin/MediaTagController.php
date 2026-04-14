<?php

namespace App\Http\Controllers\Admin;

use App\Models\MediaTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MediaTagController extends BaseAdminController
{
    /**
     * 获取所有标签
     */
    public function list(Request $request)
    {
        try {
            $tags = MediaTag::orderBy('sort', 'asc')
                ->orderBy('created_at', 'desc')
                ->get();

            return success($tags);
        } catch (\Exception $e) {
            Log::error('获取标签列表失败: '.$e->getMessage());

            return error([], '获取标签列表失败');
        }
    }

    /**
     * 创建标签
     */
    public function create(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:50',
                'color' => 'nullable|string|max:20',
                'sort' => 'nullable|integer',
            ]);

            // 检查标签名是否已存在
            $exists = MediaTag::where('name', $validated['name'])->exists();
            if ($exists) {
                return error([], '标签名称已存在');
            }

            $tag = MediaTag::create([
                'name' => $validated['name'],
                'color' => $validated['color'] ?? '#1890ff',
                'sort' => $validated['sort'] ?? 0,
            ]);

            return success($tag, '创建成功');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return error([], $e->validator->errors()->first());
        } catch (\Exception $e) {
            Log::error('创建标签失败: '.$e->getMessage());

            return error([], '创建标签失败');
        }
    }

    /**
     * 更新标签
     */
    public function update(Request $request, $id)
    {
        try {
            $tag = MediaTag::findOrFail($id);

            $validated = $request->validate([
                'name' => 'nullable|string|max:50',
                'color' => 'nullable|string|max:20',
                'sort' => 'nullable|integer',
            ]);

            // 如果修改了名称，检查是否与其他标签重复
            if (isset($validated['name']) && $validated['name'] !== $tag->name) {
                $exists = MediaTag::where('name', $validated['name'])
                    ->where('id', '!=', $id)
                    ->exists();
                if ($exists) {
                    return error([], '标签名称已存在');
                }
            }

            $tag->update(array_filter($validated, function ($value) {
                return $value !== null;
            }));

            return success($tag, '更新成功');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return error([], $e->validator->errors()->first());
        } catch (\Exception $e) {
            Log::error('更新标签失败: '.$e->getMessage());

            return error([], '更新标签失败');
        }
    }

    /**
     * 删除标签
     */
    public function delete($id)
    {
        try {
            $tag = MediaTag::findOrFail($id);
            $tag->delete();

            return success(true, '删除成功');
        } catch (\Exception $e) {
            Log::error('删除标签失败: '.$e->getMessage());

            return error([], '删除标签失败');
        }
    }
}
