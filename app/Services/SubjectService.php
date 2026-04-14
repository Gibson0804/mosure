<?php

namespace App\Services;

use App\Ai\Attributes\AiTool;
use App\Models\Mold;
use App\Repository\MoldRepository;
use Illuminate\Support\Facades\Auth;

class SubjectService extends BaseService
{
    private MoldRepository $moldRepository;

    private ContentVersionService $versionService;

    private TriggerService $triggerService;

    private RelationDisplayService $relationDisplayService;

    public function __construct(
        MoldRepository $moldRepository,
        ContentVersionService $versionService,
        TriggerService $triggerService,
        RelationDisplayService $relationDisplayService
    ) {
        $this->moldRepository = $moldRepository;
        $this->versionService = $versionService;
        $this->triggerService = $triggerService;
        $this->relationDisplayService = $relationDisplayService;
    }

    public function getAllSubjectMenu()
    {

        $moldList = $this->moldRepository->getAllSubectBase();

        return $moldList;
    }

    public function getSubjectInfo(int $moldId): ?array
    {
        return $this->moldRepository->getMoldInfo($moldId);
    }

    #[AiTool(
        name: 'subject_detail',
        description: '获取单页（subject）详情：通过表标识读取 subject_content 等信息。',
        params: [
            'tableName' => ['type' => 'string', 'required' => true, 'desc' => '模型表标识，如 about_us'],
        ]
    )]
    public function getSubjectByTable(string $tableName): array
    {
        $subject = Mold::query()
            ->where('table_name', $tableName)
            ->where('mold_type', 'single')
            ->first();

        if (! $subject) {
            return [];
        }

        $content = $this->decodeSubjectContent($subject->subject_content);
        // optionsSource=model 的字段，展示时将 id 转为关联值（如 tag_name）
        $content = $this->relationDisplayService->hydrateSubjectContent($tableName, $content);

        return [
            'id' => (int) $subject->id,
            'name' => (string) $subject->name,
            'description' => (string) ($subject->description ?? ''),
            'table_name' => (string) $subject->table_name,
            'subject_content' => $content,
        ];
    }

    #[AiTool(
        name: 'subject_update',
        description: '更新单页（subject）内容：通过表标识写入 subject_content。',
        params: [
            'tableName' => ['type' => 'string', 'required' => true, 'desc' => '模型表标识，如 about_us'],
            'subjectContent' => ['type' => 'object', 'required' => true, 'desc' => '要保存的 subject_content（对象）'],
        ]
    )]
    public function updateSubjectByTable(string $tableName, array $subjectContent): bool
    {
        $subject = Mold::query()
            ->where('table_name', $tableName)
            ->where('mold_type', 'single')
            ->first();

        if (! $subject) {
            return false;
        }

        return $this->updateSubjectById((int) $subject->id, $subjectContent);
    }

    public function updateSubjectById(int $moldId, array $subjectContent): bool
    {
        $infoBefore = $this->moldRepository->getMoldInfo($moldId);
        if (! $infoBefore) {
            return false;
        }

        $beforeMap = (array) ($infoBefore['subject_content_arr'] ?? []);

        try {
            $this->triggerService->dispatch('content.before_update', [
                'mold_id' => (int) $moldId,
                'id' => 0,
                'before' => $beforeMap,
                'data' => (array) $subjectContent,
            ]);
        } catch (\Throwable $e) {
            // ignore trigger failures
        }

        $updatePayload = ['subject_content' => json_encode($subjectContent, JSON_UNESCAPED_UNICODE)];

        $updated = $this->moldRepository->updateById($updatePayload, $moldId);
        if (! $updated) {
            return false;
        }

        try {
            $this->versionService->recordUpdate(
                (int) $moldId,
                0,
                $beforeMap,
                (array) $subjectContent,
                Auth::id()
            );
        } catch (\Throwable $e) {
            // ignore version failures
        }

        try {
            $this->triggerService->dispatch('content.after_update', [
                'mold_id' => (int) $moldId,
                'id' => 0,
                'before' => $beforeMap,
                'after' => (array) $subjectContent,
            ]);
        } catch (\Throwable $e) {
            // ignore trigger failures
        }

        return true;
    }

    public function buildSubjectEditPayload(int $moldId): ?array
    {
        $info = $this->moldRepository->getMoldInfo($moldId);
        if (! $info) {
            return null;
        }

        $subjectContent = (array) ($info['subject_content_arr'] ?? []);
        $fields = (array) ($info['fields_arr'] ?? []);

        foreach ($fields as $key => $value) {
            if (! is_array($value)) {
                continue;
            }
            $fields[$key]['curValue'] = $subjectContent[$value['field'] ?? ''] ?? '';
        }

        return [
            'schema' => $fields,
            'pageId' => $info['id'] ?? null,
            'pageName' => $info['name'] ?? '',
            'tableName' => $info['table_name'] ?? '',
            'moldId' => $info['id'] ?? null,
            'subjectContent' => $subjectContent,
        ];
    }

    private function decodeSubjectContent(?string $content): array
    {
        if (empty($content)) {
            return [];
        }
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}
