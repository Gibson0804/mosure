<?php

use App\Http\Controllers\Api\ChromeCaptureController;
use App\Http\Controllers\Client\AuthController as ClientAuthController;
use App\Http\Controllers\Client\ContentController as ClientContentController;
use App\Http\Controllers\Client\MoldController as ClientMoldController;
use App\Http\Controllers\Client\ProjectController as ClientProjectController;
use App\Http\Middleware\ClientTokenAuth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Chrome Extension API Routes
|--------------------------------------------------------------------------
|
| Chrome浏览器插件专用API路由
|
*/

// 不需要认证的接口
Route::post('/auth/login', [ClientAuthController::class, 'login']);

// 处理所有预检 OPTIONS 请求
Route::options('/{any?}', function () {
    return response()->noContent();
})->where('any', '.*');

// 需要认证的接口（使用客户端 token 鉴权）
Route::middleware([ClientTokenAuth::class])->group(function () {
    // Chrome插件专用采集接口
    Route::post('/capture', [ChromeCaptureController::class, 'capture']);
    Route::post('/capture-ai', [ChromeCaptureController::class, 'captureWithAI']);
    Route::post('/quick-capture', [ChromeCaptureController::class, 'quickCapture']);
    Route::post('/capture-media', [ChromeCaptureController::class, 'captureMedia']);
    Route::get('/task/{taskId}', [ChromeCaptureController::class, 'getTaskStatus']);

    // Chrome插件认证接口
    Route::post('/auth/logout', [ClientAuthController::class, 'logout']);
    Route::get('/me', [ClientAuthController::class, 'me']);

    // Chrome插件数据接口（复用 Client Controller）
    Route::get('/projects', [ClientProjectController::class, 'index']);
    Route::get('/molds', [ClientMoldController::class, 'index']);
    Route::get('/content/list', [ClientContentController::class, 'list']);
    Route::get('/content/detail', [ClientContentController::class, 'detail']);
});
