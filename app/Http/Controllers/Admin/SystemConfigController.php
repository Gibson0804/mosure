<?php

namespace App\Http\Controllers\Admin;

use App\Services\SystemConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemConfigController extends BaseAdminController
{
    private SystemConfigService $service;

    public function __construct(SystemConfigService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return viewShow('System/SystemConfig');
    }

    public function show(): JsonResponse
    {
        try {
            $cfg = $this->service->getConfig();

            return success(['config' => $cfg]);
        } catch (\Throwable $e) {
            return error([], '获取系统配置失败: '.$e->getMessage());
        }
    }

    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_providers' => 'array',
            'mail' => 'array',
            'storage' => 'array',
            'security' => 'array',
        ]);

        try {
            $data = $this->service->saveConfig($validated);

            return success($data, '保存成功');
        } catch (\Throwable $e) {
            return error([], '保存系统配置失败: '.$e->getMessage());
        }
    }

    public function testMail(Request $request): JsonResponse
    {
        try {
            $payload = $request->input('mail', []);
            $res = $this->service->testMail(is_array($payload) ? $payload : []);
            if ($res['ok'] ?? false) {
                return success($res, $res['message'] ?? 'OK');
            }

            return error($res, $res['message'] ?? '测试失败');
        } catch (\Throwable $e) {
            return error([], '测试邮件失败: '.$e->getMessage());
        }
    }

    public function testProvider(Request $request): JsonResponse
    {
        try {
            $provider = (string) $request->input('provider');
            $cfg = $request->input('config', []);
            $res = $this->service->testProvider($provider, is_array($cfg) ? $cfg : []);
            if ($res['ok'] ?? false) {
                return success($res, $res['message'] ?? 'OK');
            }

            return error($res, $res['message'] ?? '测试失败');
        } catch (\Throwable $e) {
            return error([], '测试提供商失败: '.$e->getMessage());
        }
    }

    public function testStorage(Request $request): JsonResponse
    {
        try {
            $payload = $request->input('storage', []);
            $res = $this->service->testStorage(is_array($payload) ? $payload : []);
            if ($res['ok'] ?? false) {
                return success($res, $res['message'] ?? 'OK');
            }

            return error($res, $res['message'] ?? '测试失败');
        } catch (\Throwable $e) {
            return error([], '测试存储失败: '.$e->getMessage());
        }
    }
}
