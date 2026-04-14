<?php

namespace App\Http\Controllers\Admin;

use App\Services\ProjectConfigService;
use App\Services\ProjectAuthService;
use App\Services\ProjectService;
use App\Services\ProjectTableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectConfigController extends BaseAdminController
{
    private ProjectConfigService $service;

    private ProjectTableService $tableService;

    private ProjectService $projectService;

    private ProjectAuthService $authService;

    public function __construct(ProjectConfigService $service, ProjectTableService $tableService, ProjectService $projectService, ProjectAuthService $authService)
    {
        $this->service = $service;
        $this->tableService = $tableService;
        $this->projectService = $projectService;
        $this->authService = $authService;
    }

    public function mcpGenerateToken(Request $request): JsonResponse
    {
        try {
            $prefix = session('current_project_prefix');
            if (! $prefix) {
                return error([], '未选择项目，无法生成令牌');
            }

            $token = $prefix.bin2hex(random_bytes(8)); // 16位十六进制

            // 保存到项目配置 mcp.token
            \App\Models\ProjectConfig::updateOrCreate(
                ['config_group' => 'mcp', 'config_key' => 'token'],
                ['config_value' => $token]
            );

            return success(['token' => $token], '令牌已生成');
        } catch (\Throwable $e) {
            return error([], '生成令牌失败: '.$e->getMessage());
        }
    }

    public function mcpClientConfig(Request $request): JsonResponse
    {
        try {
            $cfg = $this->service->getConfig();
            $enabled = (bool) ($cfg['mcp']['enabled'] ?? false);
            $host = $request->getSchemeAndHttpHost();
            $serverUrl = rtrim($host, '/').'/mcp';

            $client = [
                'mcpServers' => [
                    'mosure' => [
                        'disabled' => ! $enabled ? true : false,
                        'headers' => [
                            'Authorization' => 'Bearer '.$cfg['mcp']['token'] ?? '',
                        ],
                        'serverUrl' => $serverUrl,
                    ],
                ],
            ];

            return success([
                'json' => json_encode($client, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                'serverUrl' => $serverUrl,
                'enabled' => $enabled,
                'token' => $cfg['mcp']['token'] ?? '',
            ]);
        } catch (\Throwable $e) {
            return error([], '获取客户端配置失败: '.$e->getMessage());
        }
    }

    public function index()
    {
        return viewShow('Manage/ProjectConfig');
    }

    public function show(): JsonResponse
    {
        try {
            $config = $this->service->getConfig();
            $prefix = session('current_project_prefix');

            return success([
                'config' => $config,
                'project_prefix' => $prefix,
            ]);
        } catch (\Throwable $e) {
            return error([], '获取项目配置失败: '.$e->getMessage());
        }
    }

    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'basic' => 'array',
            'api' => 'array',
            'security' => 'array',
            'audit' => 'array',
            'content' => 'array',
            'mcp' => 'array',
            'auth' => 'array',
            // 'advanced' removed per new design
        ]);

        try {
            $data = $this->service->saveConfig($validated);
            if ((bool) data_get($validated, 'auth.enabled', false)) {
                $this->authService->ensureSchema();
            }

            return success($data, '保存成功');
        } catch (\Throwable $e) {
            return error([], '保存项目配置失败: '.$e->getMessage());
        }
    }

    public function repair(): JsonResponse
    {
        try {
            $prefix = session('current_project_prefix');
            if (! $prefix) {
                return error([], '未选择项目，无法修复');
            }

            $this->tableService->createProjectFrameworkTables($prefix);
            if (method_exists($this->tableService, 'updateExistingMoldsTables')) {
                $this->tableService->updateExistingMoldsTables($prefix);
            }
            // 重建/补齐 content相关  表结构
            if (method_exists($this->tableService, 'updateExistingContentTables')) {
                $this->tableService->updateExistingContentTables($prefix);
            }

            // 修复项目 AI 助手
            $agentResult = $this->projectService->repairProjectAgentByPrefix($prefix);
            if (! $agentResult['success']) {
                return error([], 'AI 助手修复失败: '.$agentResult['message']);
            }

            return success(['agent' => $agentResult], '项目表结构已检查并修复，'.$agentResult['message']);
        } catch (\Throwable $e) {
            return error([], '修复失败: '.$e->getMessage());
        }
    }

    public function purge(): JsonResponse
    {
        try {
            $prefix = session('current_project_prefix');
            if (! $prefix) {
                return error([], '未选择项目，无法清空');
            }

            // 清空项目数据
            $result = $this->tableService->purgeProjectData($prefix);

            return success($result, '项目数据已清空，表结构保留');
        } catch (\Throwable $e) {
            return error([], '清空失败: '.$e->getMessage());
        }
    }
}
