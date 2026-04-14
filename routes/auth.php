<?php

use App\Http\Controllers\Admin\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// ========================
// 1. 认证与用户相关路由
// ========================
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');

Route::post('/doLogin', [AuthController::class, 'doLogin'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request')->middleware('guest');
Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->name('password.email')->middleware('guest');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetForm'])->name('password.reset')->middleware('guest');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update')->middleware('guest');
Route::get('/change-password', [AuthController::class, 'showChangePassword'])->name('password.change')->middleware('auth');
Route::post('/change-password', [AuthController::class, 'changePassword'])->name('password.change.update')->middleware('auth');
// 用户资料
Route::get('/profile', [AuthController::class, 'showProfile'])->name('profile.show')->middleware('auth');
Route::post('/profile', [AuthController::class, 'updateProfile'])->name('profile.update')->middleware('auth');
