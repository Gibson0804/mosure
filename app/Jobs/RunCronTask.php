<?php

namespace App\Jobs;

use App\Models\ProjectCron;
use App\Models\ProjectCronExecution;
use App\Services\FunctionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunCronTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $prefix;

    public int $cronId;

    public $tries = 1;

    public function __construct(string $prefix, int $cronId)
    {
        $this->prefix = $prefix;
        $this->cronId = $cronId;
    }

    public function handle(FunctionService $functionService)
    {
        if ($this->prefix !== '') {
            session(['current_project_prefix' => $this->prefix]);
        }
        $row = ProjectCron::where('id', $this->cronId)->first();
        if (! $row) {
            return;
        }
        $cron = $row->toArray();
        $functionId = (int) ($cron['function_id'] ?? 0);
        $payload = $this->asArray($cron['payload'] ?? null) ?: [];
        $start = microtime(true);
        $status = 'success';
        $error = null;
        $result = null;
        try {
            $res = $functionService->runFunctionById($this->prefix, $functionId, $payload);
            if (($res['code'] ?? 500) !== 200) {
                $status = 'fail';
                $error = (string) ($res['message'] ?? 'error');
            } else {
                $result = $res['data'] ?? null;
            }
        } catch (\Throwable $e) {
            $status = 'fail';
            $error = $e->getMessage();
        } finally {
            $duration = (int) round((microtime(true) - $start) * 1000);
            ProjectCronExecution::create([
                'cron_id' => (int) $this->cronId,
                'function_id' => $functionId,
                'status' => $status,
                'duration_ms' => $duration,
                'error' => $error,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function asArray($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $d = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $d;
            }
        }

        return null;
    }
}
