<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\CloudFunctionService;
use Illuminate\Http\Request;

class FunctionController extends Controller
{
    private CloudFunctionService $cloudFunctionService;

    public function __construct(CloudFunctionService $cloudFunctionService)
    {
        $this->cloudFunctionService = $cloudFunctionService;
    }

    public function webList(Request $request)
    {
        $data = $request->validate([
            'project_prefix' => ['required', 'string', 'max:100'],
            'keyword' => ['string', 'max:255', 'nullable'],
            'enabled' => ['nullable'],
            'page' => ['integer', 'min:1', 'nullable'],
            'per_page' => ['integer', 'min:1', 'max:100', 'nullable'],
        ]);

        session(['current_project_prefix' => $data['project_prefix']]);

        $page = $data['page'] ?? (int) $request->input('page', 1);
        $pageSize = $data['per_page'] ?? (int) $request->input('per_page', 20);
        $pageSize = max(1, min(100, $pageSize));

        $filters = [
            'type' => 'endpoint',
        ];
        if (! empty($data['keyword'])) {
            $filters['keyword'] = $data['keyword'];
        }
        if ($request->has('enabled')) {
            $filters['enabled'] = $request->input('enabled');
        }

        $result = $this->cloudFunctionService->list($page, $pageSize, $filters);

        $items = collect($result['data'])->map(function ($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'slug' => $item['slug'],
                'enabled' => (bool) $item['enabled'],
                'http_method' => $item['http_method'],
                'remark' => $item['remark'],
                'updated_at' => $item['updated_at'],
            ];
        })->values();

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'items' => $items,
                // 'meta' => $result['meta'],
            ],
        ]);
    }
}
