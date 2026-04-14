<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Media;
use App\Models\Mold;
use App\Models\Project;
use App\Models\ProjectCron;
use App\Models\ProjectFunction;
use App\Models\ProjectTrigger;

class DashboardService extends BaseService
{
    /**
     * 获取基础统计数据
     */
    public function getBasicStats(): array
    {
        return [
            'totalProjects' => Project::count(),
            'totalMolds' => Mold::count(),
            'totalSubjects' => Mold::where('mold_type', 'single')->count(),
            'totalContents' => Mold::where('mold_type', 'list')->count(),
            'totalMedia' => Media::count(),
            'totalApiKeys' => ApiKey::count(),
        ];
    }

    /**
     * 获取项目级统计数据
     */
    public function getProjectStats(): array
    {
        $projectStats = [];

        // 云函数统计
        $projectStats['totalFunctions'] = ProjectFunction::count();
        $projectStats['enabledFunctions'] = ProjectFunction::where('enabled', true)->count();

        // 触发器统计
        $projectStats['totalTriggers'] = ProjectTrigger::count();
        $projectStats['enabledTriggers'] = ProjectTrigger::where('enabled', true)->count();

        // 定时任务统计
        $projectStats['totalSchedules'] = ProjectCron::count();
        $projectStats['enabledSchedules'] = ProjectCron::where('enabled', true)->count();

        return $projectStats;
    }

    /**
     * 获取最近创建的模型
     */
    public function getRecentMolds(int $limit = 5): array
    {
        return Mold::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get(['id', 'name', 'table_name', 'mold_type', 'created_at'])
            ->map(function ($mold) {
                return [
                    'id' => $mold->id,
                    'name' => $mold->name,
                    'table_name' => $mold->table_name,
                    'type' => $mold->mold_type === 'list' ? '内容模型' : '单页主题',
                    'created_at' => $mold->created_at->format('Y-m-d H:i'),
                ];
            })
            ->toArray();
    }
}
