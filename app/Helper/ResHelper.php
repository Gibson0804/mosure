<?php

use App\Constants\ProjectConstants;
use Inertia\Inertia;

if (! function_exists('getMcTableName')) {
    function getMcTableName($tableName)
    {
        $currentPrefix = session('current_project_prefix');
        $frameworkPrefix = ProjectConstants::MODEL_CONTENT_PREFIX;

        return $currentPrefix.$frameworkPrefix.$tableName;
    }
}
// 去掉模型表名前缀
if (! function_exists('removeMcPrefix')) {
    function removeMcPrefix($tableName)
    {
        $currentPrefix = session('current_project_prefix');
        $frameworkPrefix = ProjectConstants::MODEL_CONTENT_PREFIX;
        $fullPrefix = $currentPrefix.$frameworkPrefix;
        if ($currentPrefix && strpos($tableName, $fullPrefix) === 0) {
            return substr($tableName, strlen($fullPrefix));
        }

        return $tableName;
    }
}

if (! function_exists('success')) {
    function success($data = [], $message = '操作成功')
    {
        return response()->json(['code' => 0, 'message' => $message, 'data' => $data], 200, [], JSON_UNESCAPED_UNICODE);
    }
}

if (! function_exists('error')) {
    function error($data = [], $message = '操作失败')
    {
        return response()->json(['code' => 1, 'message' => $message, 'data' => $data], 200, [], JSON_UNESCAPED_UNICODE);
    }
}

if (! function_exists('viewShow')) {
    function viewShow($view, $data = [], string $errors = '')
    {

        if ($errors) {
            $data['errors'] = ['message' => $errors];
        }

        return Inertia::render($view, $data);
    }
}
