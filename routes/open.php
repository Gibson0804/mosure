<?php

use App\Http\Controllers\Open\CloudFunctionController;
use App\Http\Controllers\Open\ContentController;
use App\Http\Controllers\Open\MediaController;
use App\Http\Controllers\Open\PageController;
use App\Http\Controllers\Open\ProjectAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Open Routes
|--------------------------------------------------------------------------
|
| 对外开放的API接口路由，需要通过 API Key 或项目用户登录态认证
| 这些接口主要用于外部应用调用
|
*/


Route::prefix('auth/{projectPrefix}')->group(function () {
    Route::post('/login', [ProjectAuthController::class, 'login'])->name('open.auth.login');
    Route::post('/register', [ProjectAuthController::class, 'register'])->name('open.auth.register');
    Route::get('/me', [ProjectAuthController::class, 'me'])->name('open.auth.me');
    Route::post('/logout', [ProjectAuthController::class, 'logout'])->name('open.auth.logout');
});

Route::prefix('content')->group(function () {

    // 内容管理相关接口 - 使用table_name而不是moldId
    // 获取内容列表
    Route::get('/list/{tableName}', [ContentController::class, 'getList'])->name('open.content.list');

    // 获取内容详情
    Route::get('/detail/{tableName}/{id}', [ContentController::class, 'getDetail'])->name('open.content.detail');

    // 获取内容数量
    Route::get('/count/{tableName}', [ContentController::class, 'getCount'])->name('open.content.count');

    // 创建内容
    Route::post('/create/{tableName}', [ContentController::class, 'create'])->name('open.content.create');

    // 更新内容
    Route::put('/update/{tableName}/{id}', [ContentController::class, 'update'])->name('open.content.update');

    // 删除内容
    Route::delete('/delete/{tableName}/{id}', [ContentController::class, 'delete'])->name('open.content.delete');

});

// 函数网关（自定义 Endpoint）- 项目级
Route::any('/func/{slug}', [CloudFunctionController::class, 'invoke'])->name('open.cloudfunc.invoke');

Route::prefix('page')->group(function () {

    // 单页内容相关接口
    Route::get('/detail/{tableName}', [PageController::class, 'show'])->name('open.page.detail');
    Route::put('/update/{tableName}', [PageController::class, 'update'])->name('open.page.update');

});

Route::prefix('media')->group(function () {

    // 媒体资源相关接口
    // 获取媒体详情（通过ID）
    Route::get('/detail/{id}', [MediaController::class, 'getDetail'])->name('open.media.detail');

    // 获取媒体列表
    Route::get('/list', [MediaController::class, 'getList'])->name('open.media.list');

    // 通过标签获取媒体
    Route::get('/by-tags', [MediaController::class, 'getByTags'])->name('open.media.byTags');

    // 通过文件夹获取媒体
    Route::get('/by-folder/{folderId}', [MediaController::class, 'getByFolder'])->name('open.media.byFolder');

    // 搜索媒体
    Route::get('/search', [MediaController::class, 'search'])->name('open.media.search');

    // 创建媒体
    Route::post('/create', [MediaController::class, 'create'])->name('open.media.create');

    // 更新媒体
    Route::put('/update/{id}', [MediaController::class, 'update'])->name('open.media.update');

    // 删除媒体
    Route::delete('/delete/{id}', [MediaController::class, 'delete'])->name('open.media.delete');

});
