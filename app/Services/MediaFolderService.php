<?php

namespace App\Services;

use App\Models\Media;
use App\Models\MediaFolder;
use Illuminate\Support\Facades\DB;

class MediaFolderService
{
    public function getTree(): array
    {
        $all = MediaFolder::orderBy('sort')->orderBy('id')->get()->toArray();
        $byParent = [];
        foreach ($all as $node) {
            $byParent[$node['parent_id'] ?? 0][] = $node;
        }
        $build = function ($parentId) use (&$build, &$byParent) {
            $children = $byParent[$parentId ?? 0] ?? [];
            foreach ($children as &$c) {
                $c['children'] = $build($c['id']);
            }

            return $children;
        };

        return $build(null);
    }

    public function ensureUncategorized(): MediaFolder
    {
        $uncat = MediaFolder::where('is_system', true)->where('name', '未分类')->first();
        if ($uncat) {
            return $uncat;
        }

        return DB::transaction(function () {
            $f = MediaFolder::create([
                'name' => '未分类',
                'parent_id' => null,
                'mpath' => '',
                'depth' => 0,
                'sort' => 0,
                'is_system' => true,
            ]);
            $f->mpath = '/'.$f->id.'/';
            $f->depth = 1;
            $f->save();

            return $f;
        });
    }

    public function create(string $name, ?int $parentId = null): MediaFolder
    {
        return DB::transaction(function () use ($name, $parentId) {
            $parent = $parentId ? MediaFolder::findOrFail($parentId) : null;
            $depth = $parent ? ($parent->depth + 1) : 1;
            $folder = MediaFolder::create([
                'name' => $name,
                'parent_id' => $parentId,
                'mpath' => '',
                'depth' => $depth,
            ]);
            $folder->mpath = ($parent ? $parent->mpath : '/').$folder->id.'/';
            $folder->save();

            return $folder;
        });
    }

    public function rename(int $id, string $name): MediaFolder
    {
        $f = MediaFolder::findOrFail($id);
        $f->name = $name;
        $f->save();

        return $f;
    }

    public function move(int $id, ?int $toParentId): MediaFolder
    {
        return DB::transaction(function () use ($id, $toParentId) {
            $node = MediaFolder::findOrFail($id);
            $newParent = $toParentId ? MediaFolder::findOrFail($toParentId) : null;

            // 防环
            if ($newParent && strpos($newParent->mpath, '/'.$node->id.'/') !== false) {
                throw new \RuntimeException('不能移动到自己的子孙节点下');
            }

            $oldMpath = $node->mpath;
            $oldDepth = $node->depth;
            $node->parent_id = $toParentId;
            $node->depth = $newParent ? ($newParent->depth + 1) : 1;
            $node->mpath = ($newParent ? $newParent->mpath : '/').$node->id.'/';
            $node->save();

            // 更新子孙节点 mpath 与 depth
            $descendants = MediaFolder::where('mpath', 'like', $oldMpath.'%')
                ->where('id', '!=', $node->id)
                ->get();
            $delta = $node->depth - $oldDepth;
            foreach ($descendants as $d) {
                $d->mpath = preg_replace('#^'.preg_quote($oldMpath, '#').'#', $node->mpath, $d->mpath);
                $d->depth = $d->depth + $delta;
                $d->save();
            }

            return $node;
        });
    }

    public function delete(int $id, string $strategy = 'keep', ?int $targetFolderId = null): void
    {
        DB::transaction(function () use ($id, $strategy, $targetFolderId) {
            $folder = MediaFolder::findOrFail($id);
            if ($folder->is_system) {
                throw new \RuntimeException('系统文件夹不允许删除');
            }

            // 不允许删除有子文件夹的节点
            $hasChildren = MediaFolder::where('parent_id', $id)->exists();
            if ($hasChildren) {
                throw new \RuntimeException('请先删除子文件夹');
            }

            $uncat = $this->ensureUncategorized();
            $destId = $strategy === 'move' ? ($targetFolderId ?: $uncat->id) : $uncat->id;

            // 处理媒体
            Media::where('folder_id', $id)->update(['folder_id' => $destId]);

            $folder->delete();
        });
    }
}
