<?php

namespace App\Http\Controllers\Admin;

use App\Services\ContentVersionService;
use Illuminate\Http\Request;

class ContentVersionController extends BaseAdminController
{
    private ContentVersionService $service;

    public function __construct(ContentVersionService $service)
    {
        $this->service = $service;
    }

    // 版本列表
    public function list(Request $request, int $moldId, int $id)
    {
        $limit = (int) ($request->input('limit') ?? 100);
        $rows = $this->service->listVersions($moldId, $id, max(1, min(200, $limit)));

        return success($rows);
    }

    // 单个版本详情
    public function show(Request $request, int $moldId, int $id, int $version)
    {
        $ver = $this->service->getVersion($moldId, $id, $version);
        if (! $ver) {
            return error([], '版本不存在');
        }

        return success($ver);
    }

    // 版本对比
    public function diff(Request $request)
    {
        $moldId = (int) ($request->input('mold_id') ?? 0);
        $id = (int) ($request->input('id') ?? 0);
        $v1 = (int) ($request->input('v1') ?? 0);
        $v2 = (int) ($request->input('v2') ?? 0);
        if ($moldId <= 0 || $id < 0 || $v1 <= 0 || $v2 <= 0) {
            return error([], '参数错误');
        }
        $diff = $this->service->diffVersions($moldId, $id, $v1, $v2);

        return success($diff);
    }

    // 回滚到指定版本
    public function rollback(Request $request, int $moldId, int $id, int $version)
    {
        $publish = (bool) $request->input('publish', false);
        // 如果目标版本与当前内容一致，直接返回友好提示
        try {
            $diff = $this->service->diffCurrentWithVersion($moldId, $id, $version);
            if (empty($diff)) {
                return success([], '无需回滚：当前内容与所选版本一致');
            }
        } catch (\Throwable $e) {
        }

        $ok = $this->service->rollbackTo($moldId, $id, $version, $publish, optional($request->user())->id);
        if (! $ok) {
            return error([], '回滚失败');
        }

        return success([], '回滚成功');
    }
}
