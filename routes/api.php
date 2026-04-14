<?php

use App\Http\Controllers\Admin\Install\InstallController;
use App\Http\Controllers\Api\ProjectApiController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'install', 'as' => 'install.'], function () {
    Route::post('/testDbConnection', [InstallController::class, 'testDbConnection'])->name('testDbConnection');
    Route::post('/doInstall', [InstallController::class, 'install'])->name('install');
});

// 项目API接口（需要认证）
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/projects', [ProjectApiController::class, 'list'])->name('api.projects.list');
    Route::get('/projects/{id}', [ProjectApiController::class, 'detail'])->name('api.projects.detail');
});
