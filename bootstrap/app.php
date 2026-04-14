<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Facades\Mcp;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function () {
            // 公开路由：前端页面访问（无需认证）
            Route::get('/frontend/{project_prefix}/{plugin}/{path?}', [\App\Http\Controllers\FrontendController::class, 'serve'])
                ->where('project_prefix', '[a-z0-9_-]+')
                ->where('plugin', '[a-z0-9_@.-]+')
                ->where('path', '.*')
                ->name('frontend.serve');

            // 公开路由：前端托管页面访问（无需认证）
            Route::get('/sites/{project_prefix}/{slug}/{path?}', [\App\Http\Controllers\PageHostingController::class, 'serve'])
                ->where('project_prefix', '[a-z0-9_-]+')
                ->where('slug', '[a-z0-9-]+')
                ->where('path', '.*')
                ->name('sites.serve');

            // MCP 服务路由
            Route::prefix('mcp')
                ->middleware(\App\Http\Middleware\VerifyMcpAccess::class)
                ->group(function () {
                    Mcp::web('/', \App\Mcp\Servers\mcpServer::class);
                });

            Route::middleware(
                [
                    'api',
                    \Illuminate\Session\Middleware\StartSession::class,
                    \App\Http\Middleware\ValidateApiKey::class,
                ]
            )
                ->prefix('open')
                ->group(base_path('routes/open.php'));

            Route::middleware('client')
                ->prefix('client')
                ->group(base_path('routes/client.php'));
            Route::middleware('client')
                ->prefix('api/chrome')
                ->group(base_path('routes/chrome.php'));
        },
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\CloudCronTick::class,
    ])
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('cloud-cron:tick')->everyMinute();
    })
    ->withMiddleware(function (Middleware $middleware) {
        // 全局中间件
        $middleware->append(\App\Http\Middleware\LogRequestId::class);
        $middleware->append(\App\Http\Middleware\OpenCors::class);

        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
        ])->validateCsrfTokens(except: [
            'install/*',
            'm/*',
        ]);

        $middleware->group('api', [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            // 'throttle:api',
            // \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // \App\Http\Middleware\ValidateApiKey::class,
        ]);

        $middleware->group('client', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\ClientCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 统一处理验证异常
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('client/*') || $request->is('open/*')) {
                return response()->json([
                    'code' => 400,
                    'message' => '参数错误：'.collect($e->errors())->flatten()->first(),
                    'data' => null,
                ], 400);
            }
        });
    })->create();
