<?php

namespace App\Http\Controllers\Admin;

use App\Services\TaskService;

class TaskController extends BaseAdminController
{
    private $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    /**
     * 查询任务状态
     */
    public function aiGenerateStatus(int $taskId)
    {
        $status = $this->taskService->getTaskStatus($taskId);

        return success($status);
    }
}
