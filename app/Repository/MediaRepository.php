<?php

namespace App\Repository;

use App\Models\Media;
use Illuminate\Support\Collection;

class MediaRepository
{
    /**
     * 根据ID集合获取媒体列表
     *
     * @param  array<int,int>  $ids
     * @return \Illuminate\Support\Collection<int,Media>
     */
    public function getByIds(array $ids): Collection
    {
        return Media::whereIn('id', $ids)->get();
    }

    /**
     * 根据ID集合删除媒体记录
     *
     * @param  array<int,int>  $ids
     * @return int 受影响的行数
     */
    public function deleteByIds(array $ids): int
    {
        return Media::whereIn('id', $ids)->delete();
    }
}
