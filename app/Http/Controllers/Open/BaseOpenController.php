<?php

namespace App\Http\Controllers\Open;

use App\Constants\ProjectConstants;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;

class BaseOpenController extends Controller
{
    // 对外接口控制器基类
    // 可以在这里添加对外接口通用的逻辑

    protected function success($data = null, string $message = 'success', int $code = 200): JsonResponse
    {
        $response = [
            'code' => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code, [], JSON_UNESCAPED_UNICODE);
    }

    protected function error(string $message, int $code = 400): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
        ], $code, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 规范化并验证表名
     * 1. 自动添加项目前缀（如果需要）
     * 2. 验证表名格式和表是否存在
     * 3. 返回规范化后的表名或 null（验证失败时）
     */
    protected function normalizeAndValidateTableName(string $tableName, bool $validate = true): ?string
    {
        // 规范化表名，自动添加项目前缀
        $currentPrefix = session('current_project_prefix');
        if ($currentPrefix) {
            $frameworkPrefix = ProjectConstants::MODEL_CONTENT_PREFIX;
            $fullPrefix = $currentPrefix.$frameworkPrefix;

            // 如果表名已经包含完整前缀，直接返回
            if (! str_starts_with($tableName, $fullPrefix)) {
                $tableName = $fullPrefix.$tableName;
            }
        }

        if ($validate) {
            // 验证表名格式（只能包含字母、数字、下划线）
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
                return null;
            }

            // 验证表是否存在
            if (! Schema::hasTable($tableName)) {
                return null;
            }

            // 验证表是否是内容表（必须有id字段）
            if (! Schema::hasColumn($tableName, 'id')) {
                return null;
            }
        }

        return $tableName;
    }
}
