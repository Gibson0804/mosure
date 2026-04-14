<?php

namespace App\Http\Controllers\Admin;

use App\Services\ContentVersionService;
use App\Services\MoldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class SubjectController extends BaseAdminController
{
    private $moldService;

    private ContentVersionService $versionService;

    public function __construct(
        MoldService $moldService,
        ContentVersionService $versionService
    ) {
        $this->moldService = $moldService;
        $this->versionService = $versionService;
    }

    public function subjectEdit(Request $request, int $moldId): Response|RedirectResponse
    {

        if ($request->isMethod('post')) {
            $data = $request->input();

            $updated = app(\App\Services\SubjectService::class)->updateSubjectById($moldId, (array) $data);

            if (! $updated) {
                return back()->withErrors(['message' => '更新单页失败']);
            }
        }

        $payload = app(\App\Services\SubjectService::class)->buildSubjectEditPayload($moldId);

        if (! $payload) {
            return back()->withErrors(['message' => '单页信息不存在']);
        }

        return viewShow('Subject/SubjectEdit', $payload);
    }
}
