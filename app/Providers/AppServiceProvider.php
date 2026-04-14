<?php

namespace App\Providers;

use App\Services\SystemConfigService;
use App\Support\RequestId;
use App\Support\StructuredLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // not used
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RequestId::share(RequestId::current());

        $sqlLogging = config('logging.sql_logging', []);
        if (($sqlLogging['enabled'] ?? false) === true) {
            DB::listen(function ($query) use ($sqlLogging) {
                $slowMs = max(0, (int) ($sqlLogging['slow_ms'] ?? 0));
                $full = (bool) ($sqlLogging['full'] ?? false);
                $includeBindings = (bool) ($sqlLogging['include_bindings'] ?? false);

                $isSlow = $slowMs > 0 && $query->time >= $slowMs;
                if (! $full && ! $isSlow) {
                    return;
                }

                $context = [
                    'connection' => $query->connectionName,
                    'duration_ms' => round((float) $query->time, 2),
                    'slow' => $isSlow,
                    'sql' => $query->sql,
                    'binding_count' => count($query->bindings),
                ];

                if ($includeBindings) {
                    $context['bindings'] = $query->bindings;
                }

                if ($isSlow) {
                    StructuredLogger::warning('db.query.slow', $context, 'slow_query');
                    if (! $full) {
                        return;
                    }
                }

                StructuredLogger::debug('db.query', $context, 'sql');
            });
        }

        // Apply security runtime config
        try {
            /** @var SystemConfigService $sys */
            $sys = app(SystemConfigService::class);
            $cfg = $sys->getConfigRaw();
            $sec = Arr::get($cfg, 'security', []);
            $lifetime = (int) ($sec['session_lifetime'] ?? 120);
            if ($lifetime > 0) {
                config(['session.lifetime' => $lifetime]);
            }
        } catch (\Throwable $e) {
            // ignore failures during early boot
        }
    }
}
