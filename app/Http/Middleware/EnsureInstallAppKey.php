<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstallAppKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('install') || $request->is('install/*') || $request->is('api/install/*')) {
            $appKey = (string) config('app.key', '');

            if ($appKey === '') {
                // Installer fallback key for pre-install requests when .env/APP_KEY is missing.
                $seed = 'mosure-install:'.base_path();
                $rawKey = hash('sha256', $seed, true);
                config(['app.key' => 'base64:'.base64_encode($rawKey)]);
            }

            // Installer requests should not depend on DB-backed session state.
            // This prevents /install from crashing when DB/session tables are not ready yet.
            config(['session.driver' => 'file']);
        }

        return $next($request);
    }
}
