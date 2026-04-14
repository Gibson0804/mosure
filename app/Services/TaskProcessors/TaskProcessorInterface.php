<?php

namespace App\Services\TaskProcessors;

use App\Models\SysTask;

/**
 * 任务处理器接口
 */
interface TaskProcessorInterface
{
    /**
     * 处理任务
     */
    public function process(SysTask $task): void;
}
