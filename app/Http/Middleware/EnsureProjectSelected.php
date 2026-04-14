<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $prefix = (string) session('current_project_prefix', '');
        if ($prefix === '') {
            return redirect()->route('project.index');
        }

        return $next($request);
    }
}
