<?php

namespace App\Http\Controllers\Open;

use App\Models\Mold;
use App\Services\AuditLogger;
use App\Services\SubjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PageController extends BaseOpenController
{
    private SubjectService $subjectService;

    public function __construct(SubjectService $subjectService)
    {
        $this->subjectService = $subjectService;
    }

    /**
     * 获取单页内容详情
     */
    public function show(Request $request, string $tableName): JsonResponse
    {
        // 规范化并验证表名
        $tableName = $this->normalizeAndValidateTableName($tableName, false);
        if (! $tableName) {
            return $this->error('无效的表名', 400);
        }

        $page = $this->findPageByTableName($tableName);

        if (! $page) {
            return $this->error('页面不存在', 404);
        }

        $data = $this->decodePageContent($page->subject_content);

        return $this->success($data);
    }

    /**
     * 更新单页内容
     */
    public function update(Request $request, string $tableName): JsonResponse
    {
        // 规范化并验证表名
        $tableName = $this->normalizeAndValidateTableName($tableName, false);
        if (! $tableName) {
            return $this->error('无效的表名', 400);
        }

        $page = $this->findPageByTableName($tableName);

        if (! $page) {
            return $this->error('页面不存在', 404);
        }

        $validator = Validator::make($request->all(), [
            'subject_content' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->error('参数验证失败: '.$validator->errors()->first(), 422);
        }

        $beforeContent = $this->decodePageContent($page->subject_content);
        $subjectContent = $request->input('subject_content', []);

        // 调用 SubjectService 更新单页内容（会触发触发器）
        $this->subjectService->updateSubjectById($page->id, $subjectContent);

        // 审计日志（更新单页内容）
        app(AuditLogger::class)->log(
            'update',
            'page',
            Mold::tableName(),
            $page->id,
            ['subject_content' => $beforeContent],
            ['subject_content' => $subjectContent],
            $request,
            'page',
            'success'
        );

        return $this->success([]);
    }

    private function findPageByTableName(string $tableName): ?Mold
    {
        return Mold::query()
            ->where('table_name', $tableName)
            ->where('mold_type', 'single')
            ->first();
    }

    private function decodePageContent(?string $content): array
    {
        if (empty($content)) {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}
