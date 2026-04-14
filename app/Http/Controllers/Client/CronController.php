<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\CloudCronService;
use Illuminate\Http\Request;

class CronController extends Controller
{
    private CloudCronService $cloudCronService;

    public function __construct(CloudCronService $cloudCronService)
    {
        $this->cloudCronService = $cloudCronService;
    }

    public function list(Request $request)
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

        $filters = [];
        if (! empty($data['keyword'])) {
            $filters['keyword'] = $data['keyword'];
        }
        if ($request->has('enabled')) {
            $filters['enabled'] = $request->input('enabled');
        }

        $result = $this->cloudCronService->list($page, $pageSize, $filters);

        $items = collect($result['data'])->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'enabled' => (bool) $item->enabled,
                'schedule_type' => $item->schedule_type,
                'run_at' => $item->run_at,
                'cron_expr' => $item->cron_expr,
                'timezone' => $item->timezone,
                'function_id' => $item->function_id,
                'remark' => $item->remark,
                'next_run_at' => $item->next_run_at,
                'updated_at' => $item->updated_at,
            ];
        })->values();

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'items' => $items,
                'meta' => $result['meta'],
            ],
        ]);
    }
}
