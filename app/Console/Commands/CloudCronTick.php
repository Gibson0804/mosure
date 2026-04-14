<?php

namespace App\Console\Commands;

use App\Jobs\RunCronTask;
use App\Services\CloudCronService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CloudCronTick extends Command
{
    protected $signature = 'cloud-cron:tick {--limit=50}';

    protected $description = 'Scan due project-level cron tasks and dispatch jobs.';

    public function handle(CloudCronService $service)
    {
        $limit = (int) $this->option('limit');
        $projects = DB::table('sys_projects')->get(['id', 'prefix']);
        foreach ($projects as $p) {
            $prefix = (string) $p->prefix;
            if ($prefix === '') {
                continue;
            }
            session(['current_project_prefix' => $prefix]);
            // 判断表存在
            if (! Schema::hasTable(\App\Models\ProjectCron::getfullTableNameByPrefix($prefix))) {
                continue;
            }
            $dues = $service->dueCrons($limit);
            foreach ($dues as $row) {
                try {
                    if (! $row) {
                        Log::warning('定时任务数据为空', ['prefix' => $prefix]);

                        continue;
                    }

                    $cron = $row;
                    if (! isset($cron['id'])) {
                        Log::warning('定时任务缺少 id 字段', [
                            'prefix' => $prefix,
                            'cron_data' => $cron,
                        ]);

                        continue;
                    }

                    $cronId = (int) $cron['id'];
                    $lock = Cache::lock('cron:'.$prefix.':'.$cronId, 30);
                    if (! $lock->get()) {
                        Log::info('定时任务已锁定，跳过', [
                            'prefix' => $prefix,
                            'cron_id' => $cronId,
                        ]);

                        continue;
                    }

                    try {
                        // 计算下一次执行时间；一次性任务到期后自动禁用
                        $next = $service->computeNextRunAt($cron);
                        $update = ['updated_at' => now()];
                        if ($cron['schedule_type'] === 'once') {
                            $update['enabled'] = 0;
                            $update['next_run_at'] = null;
                        } else {
                            $update['next_run_at'] = $next;
                        }

                        // 使用 service 更新定时任务执行信息
                        $service->updateExecutionInfo($cronId, $update);

                        // 记录日志
                        Log::info('定时任务已派发执行', [
                            'prefix' => $prefix,
                            'cron_id' => $cronId,
                            'cron_name' => $cron['name'] ?? '',
                            'next_run_at' => $update['next_run_at'] ?? null,
                        ]);

                        // 派发执行任务
                        RunCronTask::dispatch($prefix, $cronId)->onQueue(null);
                    } catch (\Throwable $e) {
                        Log::error('定时任务处理失败', [
                            'prefix' => $prefix,
                            'cron_id' => $cronId,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    } finally {
                        $lock->release();
                    }
                } catch (\Throwable $e) {
                    Log::error('定时任务遍历异常', [
                        'prefix' => $prefix,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }

        return self::SUCCESS;
    }
}
