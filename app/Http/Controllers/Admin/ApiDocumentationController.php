<?php

namespace App\Http\Controllers\Admin;

use App\Models\Mold;
use App\Models\ProjectFunction;
use App\Repository\MoldRepository;
use App\Services\ProjectConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApiDocumentationController extends BaseAdminController
{
    private MoldRepository $moldRepository;

    private ProjectConfigService $projectConfigService;

    public function __construct(MoldRepository $moldRepository, ProjectConfigService $projectConfigService)
    {
        $this->moldRepository = $moldRepository;
        $this->projectConfigService = $projectConfigService;
    }

    public function index()
    {
        $contents = $this->prepareMolds(MoldRepository::CONTENT_MOLD_TYPE);
        $subjects = $this->prepareMolds(MoldRepository::SUBJECT_MOLD_TYPE);
        $functions = $this->prepareFunctions();

        $projectAuthEnabled = false;
        try {
            $projectAuthEnabled = (bool) (($this->projectConfigService->getConfig()['auth']['enabled'] ?? false));
        } catch (\Throwable $e) {
            $projectAuthEnabled = false;
        }

        return viewShow('ApiDocumentation/Index', [
            'contents' => $contents,
            'subjects' => $subjects,
            'functions' => $functions,
            'openContentBase' => url('/open/content'),
            'openSubjectBase' => url('/open/page'),
            'openFunctionBase' => url('/open/func'),
            'openMediaBase' => url('/open/media'),
            'openAuthBase' => url('/open/auth/'.session('current_project_prefix', '')),
            'projectAuthEnabled' => $projectAuthEnabled,
        ]);
    }

    public function getApiExamples(string $type, int $id): JsonResponse
    {
        $expectedType = $type === 'subject'
            ? MoldRepository::SUBJECT_MOLD_TYPE
            : MoldRepository::CONTENT_MOLD_TYPE;

        $mold = Mold::find($id);

        Log::info('getApiExamples', ['mold' => $mold, 'expectedType' => $expectedType]);
        if (! $mold || $mold->mold_type !== $expectedType) {
            return error([], '未找到对应的模型');
        }

        return success($this->transformMold($mold));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function prepareMolds($moldType): array
    {
        $all = $this->moldRepository->getAllMold();

        return $all
            ->where('mold_type', $moldType)
            ->map(function (Mold $mold) {
                return $this->transformMold($mold);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function transformMold(Mold $mold): array
    {
        $fields = json_decode($mold->fields ?? '[]', true);
        $fields = is_array($fields) ? $fields : [];

        $subjectContent = json_decode($mold->subject_content ?? '{}', true);
        $subjectContent = is_array($subjectContent) ? $subjectContent : [];

        $listShowFields = json_decode($mold->list_show_fields ?? '[]', true);
        $listShowFields = is_array($listShowFields) ? $listShowFields : [];

        return [
            'id' => $mold->id,
            'name' => $mold->name,
            'description' => $mold->description,
            'table_name' => removeMcPrefix($mold->table_name),
            'mold_type' => $mold->mold_type,
            'fields' => $fields,
            'subject_content' => $subjectContent,
            'list_show_fields' => $listShowFields,
            'updated_at' => optional($mold->updated_at)->toDateTimeString(),
        ];
    }

    /**
     * 准备Web函数列表
     *
     * @return array<int, array<string, mixed>>
     */
    private function prepareFunctions(): array
    {
        $prefix = session('current_project_prefix', '');
        if (! $prefix) {
            return [];
        }

        $table = ProjectFunction::getfullTableNameByPrefix($prefix);

        try {
            $functions = DB::table($table)
                ->where('type', 'endpoint')
                ->where('enabled', 1)
                ->orderBy('name')
                ->get();

            return $functions->map(function ($func) {
                $inputSchema = null;
                $outputSchema = null;

                if (! empty($func->input_schema)) {
                    $inputSchema = is_string($func->input_schema)
                        ? json_decode($func->input_schema, true)
                        : $func->input_schema;
                }

                if (! empty($func->output_schema)) {
                    $outputSchema = is_string($func->output_schema)
                        ? json_decode($func->output_schema, true)
                        : $func->output_schema;
                }

                // 从 input_schema 中提取字段定义
                $fields = [];
                if (is_array($inputSchema) && isset($inputSchema['properties'])) {
                    foreach ($inputSchema['properties'] as $fieldName => $fieldDef) {
                        $fields[] = [
                            'name' => $fieldName,
                            'type' => $fieldDef['type'] ?? 'string',
                            'comment' => $fieldDef['description'] ?? '',
                            'required' => isset($inputSchema['required']) && in_array($fieldName, $inputSchema['required']),
                        ];
                    }
                }

                return [
                    'id' => $func->id,
                    'name' => $func->name,
                    'slug' => $func->slug,
                    'description' => $func->remark ?? '',
                    'http_method' => $func->http_method ?? 'POST',
                    'input_schema' => $inputSchema,
                    'output_schema' => $outputSchema,
                    'fields' => $fields,
                ];
            })->values()->all();
        } catch (\Exception $e) {
            return [];
        }
    }
}
