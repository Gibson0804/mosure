<?php

namespace App\Http\Controllers\Open;

use App\Http\Controllers\Controller;
use App\Services\FunctionService;
use Illuminate\Http\Request;

class CloudFunctionController extends Controller
{
    private $functionService;

    public function __construct(FunctionService $functionService)
    {
        $this->functionService = $functionService;
    }

    public function invoke(Request $request, string $slug)
    {
        $prefix = session('current_project_prefix');
        if (! $prefix) {
            return response()->json(['code' => 403, 'message' => 'Project prefix not found', 'data' => null], 403);
        }

        [$code, $payload] = $this->functionService->invokeBySlug($request, $prefix, $slug);

        return response()->json($payload, $code);
    }
}
