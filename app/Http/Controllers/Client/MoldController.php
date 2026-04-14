<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Repository\MoldRepository;
use App\Services\MoldService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MoldController extends Controller
{
    private MoldService $moldService;

    public function __construct(MoldService $moldService)
    {
        $this->moldService = $moldService;
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'project_prefix' => ['required', 'string', 'max:100'],
        ]);

        session(['current_project_prefix' => $data['project_prefix']]);

        $all = collect($this->moldService->getAllMold())
            ->map(function ($item) {
                if (is_array($item)) {
                    return $item;
                }

                if (is_object($item) && method_exists($item, 'toArray')) {
                    return $item->toArray();
                }

                return (array) $item;
            });

        $normalized = $all->map(function (array $payload) {
            $fieldsRaw = Arr::get($payload, 'fields');
            if (! is_array($fieldsRaw)) {
                $fieldsRaw = json_decode($fieldsRaw ?? '[]', true);
            }
            $fields = is_array($fieldsRaw) ? $fieldsRaw : [];

            $updatedAt = Arr::get($payload, 'updated_at');
            if (! is_string($updatedAt)) {
                $updatedAt = $updatedAt instanceof \DateTimeInterface
                    ? $updatedAt->format('Y-m-d H:i:s')
                    : '';
            }

            return [
                'id' => Arr::get($payload, 'id'),
                'name' => Arr::get($payload, 'name'),
                'description' => Arr::get($payload, 'description'),
                'table_name' => Arr::get($payload, 'table_name'),
                'mold_type' => Arr::get($payload, 'mold_type', 'list'),
                'fields' => $fields,
                'updated_at' => $updatedAt,
            ];
        });

        $contentList = $normalized
            ->where('mold_type', MoldRepository::CONTENT_MOLD_TYPE)
            ->values();

        $contentSingle = $normalized
            ->where('mold_type', MoldRepository::SUBJECT_MOLD_TYPE)
            ->values();

        return response()->json([
            'code' => 200,
            'message' => 'success',
            'data' => [
                'content_list' => $contentList,
                'content_single' => $contentSingle,
            ],
        ]);
    }
}
