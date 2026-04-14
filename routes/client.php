<?php

use App\Http\Controllers\Client\AiAgentController;
use App\Http\Controllers\Client\AiMessageController;
use App\Http\Controllers\Client\AiSessionController;
use App\Http\Controllers\Client\AuthController as ClientAuthController;
use App\Http\Controllers\Client\ContentController as ClientContentController;
use App\Http\Controllers\Client\CronController as ClientCronController;
use App\Http\Controllers\Client\FunctionController as ClientFunctionController;
use App\Http\Controllers\Client\KnowledgeBaseController as ClientKbController;
use App\Http\Controllers\Client\MoldController as ClientMoldController;
use App\Http\Controllers\Client\ProjectController as ClientProjectController;
use Illuminate\Support\Facades\Route;

// 客户端（移动 App 等官方客户端）专用接口
// 统一前缀由 bootstrap/app.php 中的 Route::prefix('client') 提供

Route::post('/auth/login', [ClientAuthController::class, 'login']);
Route::post('/auth/qr_login', [ClientAuthController::class, 'qrLogin']);

Route::middleware([\App\Http\Middleware\ClientTokenAuth::class])->group(function () {
    Route::post('/auth/logout', [ClientAuthController::class, 'logout']);
    Route::get('/me', [ClientAuthController::class, 'me']);
    Route::get('/projects', [ClientProjectController::class, 'index']);

    Route::get('/ai/agents', [AiAgentController::class, 'index']);
    Route::post('/ai/agents/{type}/{identifier}/private-chat', [AiAgentController::class, 'privateChat']);

    Route::get('/ai/sessions', [AiSessionController::class, 'index']);
    Route::post('/ai/sessions/project-group', [AiSessionController::class, 'ensureProjectGroup']);
    Route::delete('/ai/sessions/{id}', [AiSessionController::class, 'delete']);
    Route::delete('/ai/sessions/{id}/messages', [AiSessionController::class, 'clearMessages']);

    Route::get('/ai/sessions/{id}/messages', [AiMessageController::class, 'messages']);
    Route::get('/ai/sessions/{id}/poll', [AiMessageController::class, 'poll']);
    Route::post('/ai/sessions/{id}/messages', [AiMessageController::class, 'send']);

    Route::get('/content/list', [ClientContentController::class, 'list']);
    Route::get('/content/detail', [ClientContentController::class, 'detail']);
    Route::get('/content/subject', [ClientContentController::class, 'subject']);
    Route::get('/web_functions', [ClientFunctionController::class, 'webList']);
    Route::get('/crons', [ClientCronController::class, 'list']);
    Route::get('/molds', [ClientMoldController::class, 'index']);

    // 知识库（系统级，无需 project_prefix）
    Route::get('/kb/categories/tree', [ClientKbController::class, 'categoryTree']);
    Route::post('/kb/categories/create', [ClientKbController::class, 'createCategory']);
    Route::post('/kb/categories/update/{id}', [ClientKbController::class, 'updateCategory']);
    Route::post('/kb/categories/delete/{id}', [ClientKbController::class, 'deleteCategory']);
    Route::get('/kb/articles/list', [ClientKbController::class, 'articleList']);
    Route::get('/kb/articles/detail/{id}', [ClientKbController::class, 'articleDetail']);
    Route::post('/kb/articles/create', [ClientKbController::class, 'createArticle']);
    Route::post('/kb/articles/update/{id}', [ClientKbController::class, 'updateArticle']);
    Route::post('/kb/articles/delete/{id}', [ClientKbController::class, 'deleteArticle']);
    Route::post('/kb/articles/toggle/{id}', [ClientKbController::class, 'toggleArticle']);
    Route::post('/kb/upload-image', [ClientKbController::class, 'uploadImage']);
});
