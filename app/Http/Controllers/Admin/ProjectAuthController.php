<?php

namespace App\Http\Controllers\Admin;

use App\Services\ProjectAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectAuthController extends BaseAdminController
{
    public function __construct(private ProjectAuthService $service) {}

    public function index()
    {
        return viewShow('Manage/ProjectAuthUsers');
    }

    public function users(Request $request): JsonResponse
    {
        try {
            return success($this->service->listUsers((int) $request->input('page', 1), (int) $request->input('page_size', 20)));
        } catch (\Throwable $e) {
            return error([], '获取项目用户失败: '.$e->getMessage());
        }
    }

    public function createUser(Request $request): JsonResponse
    {
        try {
            $user = $this->service->createUser($request->all());

            return success($this->service->serializeUser($user), '创建项目用户成功');
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        try {
            $user = $this->service->updateUser($id, $request->all());

            return success($this->service->serializeUser($user), '更新项目用户成功');
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    public function deleteUser(int $id): JsonResponse
    {
        try {
            $this->service->deleteUser($id);

            return success([], '删除项目用户成功');
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    public function roles(): JsonResponse
    {
        try {
            return success($this->service->listRoles());
        } catch (\Throwable $e) {
            return error([], '获取项目角色失败: '.$e->getMessage());
        }
    }

    public function createRole(Request $request): JsonResponse
    {
        try {
            return success($this->service->createRole($request->all()), '创建项目角色成功');
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        try {
            return success($this->service->updateRole($id, $request->all()), '更新项目角色成功');
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }

    public function deleteRole(int $id): JsonResponse
    {
        try {
            $this->service->deleteRole($id);

            return success([], '删除项目角色成功');
        } catch (\Throwable $e) {
            return error([], $e->getMessage());
        }
    }
}
