<?php

namespace App\Http\Controllers\Admin;

use App\Services\DashboardService;

class DashboardController extends BaseAdminController
{
    private $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * 显示仪表盘页面
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        // 基础统计
        $stats = $this->dashboardService->getBasicStats();

        // 项目级统计
        $projectStats = $this->dashboardService->getProjectStats();

        // 最近创建的模型
        $recentMolds = $this->dashboardService->getRecentMolds(5);

        return viewShow('Dashboard/Dashboard', [
            'stats' => $stats,
            'projectStats' => $projectStats,
            'recentMolds' => $recentMolds,
        ]);
    }
}
