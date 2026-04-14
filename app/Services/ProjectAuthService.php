<?php

namespace App\Services;

use App\Exceptions\PageNoticeException;
use App\Models\ProjectAuthRole;
use App\Models\ProjectAuthSession;
use App\Models\ProjectAuthUser;
use App\Models\ProjectAuthUserRole;
use App\Models\ProjectConfig;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProjectAuthService
{
    public const DEFAULT_PERMISSIONS = [
        'content.read',
        'content.create',
        'content.update',
        'content.delete',
        'page.read',
        'page.update',
        'media.read',
        'media.create',
        'media.update',
        'media.delete',
        'function.invoke',
    ];

    public function ensureSchema(): void
    {
        $this->ensureTable(new ProjectAuthUser, ProjectAuthUser::getTableSchema());
        $this->ensureTable(new ProjectAuthRole, ProjectAuthRole::getTableSchema());
        $this->ensureTable(new ProjectAuthUserRole, ProjectAuthUserRole::getTableSchema());
        $this->ensureTable(new ProjectAuthSession, ProjectAuthSession::getTableSchema());
        $this->ensureDefaultRoles();
    }

    private function ensureTable($model, callable $schema): void
    {
        $table = $model->getTable();
        if (! Schema::hasTable($table)) {
            Schema::create($table, $schema);
        }
    }

    public function isEnabled(): bool
    {
        $cfg = app(ProjectConfigService::class)->getConfig();

        return (bool) Arr::get($cfg, 'auth.enabled', false);
    }

    public function authConfig(): array
    {
        $cfg = app(ProjectConfigService::class)->getConfig();

        return (array) ($cfg['auth'] ?? []);
    }

    public function listUsers(int $page = 1, int $pageSize = 20): array
    {
        $this->ensureSchema();
        $query = ProjectAuthUser::query()->orderByDesc('created_at');

        return [
            'data' => $query->skip(($page - 1) * $pageSize)->take($pageSize)->get()->map(fn ($user) => $this->serializeUser($user))->values(),
            'total' => ProjectAuthUser::query()->count(),
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    public function listRoles(): array
    {
        $this->ensureSchema();

        return ProjectAuthRole::query()->orderBy('id')->get()->toArray();
    }

    public function createUser(array $data): ProjectAuthUser
    {
        $this->ensureSchema();
        $email = trim((string) ($data['email'] ?? ''));
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($email === '' && $username === '') {
            throw new PageNoticeException('邮箱或用户名至少填写一项');
        }
        if ($password === '') {
            throw new PageNoticeException('密码不能为空');
        }

        $user = ProjectAuthUser::create([
            'email' => $email ?: null,
            'username' => $username ?: null,
            'name' => trim((string) ($data['name'] ?? '')) ?: ($username ?: $email),
            'password' => Hash::make($password),
            'status' => (string) ($data['status'] ?? 'active'),
        ]);

        $roleIds = array_filter(array_map('intval', (array) ($data['role_ids'] ?? [])));
        if ($roleIds === []) {
            $member = ProjectAuthRole::where('code', 'member')->first();
            if ($member) {
                $roleIds = [(int) $member->id];
            }
        }
        $this->syncUserRoles((int) $user->id, $roleIds);

        return $user->fresh();
    }

    public function updateUser(int $id, array $data): ProjectAuthUser
    {
        $this->ensureSchema();
        $user = ProjectAuthUser::findOrFail($id);
        $update = [];
        foreach (['email', 'username', 'name', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) $data[$field]);
                $update[$field] = $value !== '' ? $value : null;
            }
        }
        if (! empty($data['password'])) {
            $update['password'] = Hash::make((string) $data['password']);
        }
        if ($update) {
            $user->update($update);
        }
        if (array_key_exists('role_ids', $data)) {
            $this->syncUserRoles((int) $user->id, array_filter(array_map('intval', (array) $data['role_ids'])));
        }

        return $user->fresh();
    }

    public function deleteUser(int $id): void
    {
        $this->ensureSchema();
        ProjectAuthUserRole::where('project_auth_user_id', $id)->delete();
        ProjectAuthSession::where('project_auth_user_id', $id)->delete();
        ProjectAuthUser::findOrFail($id)->delete();
    }

    public function createRole(array $data): ProjectAuthRole
    {
        $this->ensureSchema();
        $permissions = $this->normalizePermissions((array) ($data['permissions'] ?? []));

        return ProjectAuthRole::create([
            'code' => Str::slug((string) ($data['code'] ?? ''), '_') ?: Str::random(8),
            'name' => (string) ($data['name'] ?? '未命名角色'),
            'description' => $data['description'] ?? null,
            'permissions' => $permissions,
            'is_system' => false,
        ]);
    }

    public function updateRole(int $id, array $data): ProjectAuthRole
    {
        $this->ensureSchema();
        $role = ProjectAuthRole::findOrFail($id);
        if ($role->is_system && ($data['code'] ?? $role->code) !== $role->code) {
            throw new PageNoticeException('系统角色编码不可修改');
        }
        $role->update([
            'code' => $role->is_system ? $role->code : (Str::slug((string) ($data['code'] ?? $role->code), '_') ?: $role->code),
            'name' => (string) ($data['name'] ?? $role->name),
            'description' => $data['description'] ?? $role->description,
            'permissions' => $this->normalizePermissions((array) ($data['permissions'] ?? $role->permissions ?? [])),
        ]);

        return $role->fresh();
    }

    public function deleteRole(int $id): void
    {
        $this->ensureSchema();
        $role = ProjectAuthRole::findOrFail($id);
        if ($role->is_system) {
            throw new PageNoticeException('系统角色不可删除');
        }
        ProjectAuthUserRole::where('project_auth_role_id', $id)->delete();
        $role->delete();
    }

    public function register(array $data, Request $request): array
    {
        $this->ensureSchema();
        if (! $this->isEnabled()) {
            throw new PageNoticeException('当前项目未启用用户认证');
        }
        $cfg = $this->authConfig();
        if (! (bool) ($cfg['allow_register'] ?? false)) {
            throw new PageNoticeException('当前项目未开放注册');
        }

        $user = $this->createUser([
            'email' => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
            'name' => $data['name'] ?? null,
            'password' => $data['password'] ?? '',
            'status' => 'active',
        ]);

        $account = (string) ($user->email ?: $user->username ?: $user->phone);

        return $this->login($account, (string) $data['password'], $request);
    }

    public function login(string $account, string $password, Request $request): array
    {
        $this->ensureSchema();
        if (! $this->isEnabled()) {
            throw new PageNoticeException('当前项目未启用用户认证');
        }

        $user = ProjectAuthUser::query()
            ->where(function ($query) use ($account) {
                $query->where('email', $account)->orWhere('username', $account)->orWhere('phone', $account);
            })
            ->first();

        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            throw new PageNoticeException('账号或密码错误');
        }
        if ($user->status !== 'active') {
            throw new PageNoticeException('账户已禁用');
        }

        $cfg = $this->authConfig();
        $ttlMinutes = max(5, min(43200, (int) ($cfg['session_ttl_minutes'] ?? 10080)));
        $prefix = (string) session('current_project_prefix');
        $token = 'pu_'.$prefix.'_'.Str::random(64);
        ProjectAuthSession::create([
            'project_auth_user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'last_used_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
        ]);
        $user->update(['last_login_at' => now()]);

        return ['token' => $token, 'expires_at' => now()->addMinutes($ttlMinutes)->toDateTimeString(), 'user' => $this->serializeUser($user->fresh())];
    }

    public function authenticateToken(?string $token): ?ProjectAuthUser
    {
        $this->ensureSchema();
        if (! $token) {
            return null;
        }
        $session = ProjectAuthSession::where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
        if (! $session) {
            return null;
        }
        $session->update(['last_used_at' => now()]);

        return ProjectAuthUser::where('status', 'active')->find($session->project_auth_user_id);
    }

    public function logout(?string $token): void
    {
        $this->ensureSchema();
        if ($token) {
            ProjectAuthSession::where('token_hash', hash('sha256', $token))->update(['revoked_at' => now()]);
        }
    }

    public function extractPrefixFromToken(string $token): ?string
    {
        if (! preg_match('/^pu_(.+)_[A-Za-z0-9]{64}$/', $token, $matches)) {
            return null;
        }

        return $matches[1] ?: null;
    }

    public function tokenAllowsRequest(ProjectAuthUser $user, Request $request, OpenApiPermissionResolver $resolver): bool
    {
        $required = $resolver->resolve($request);
        $scope = $required['scope'] ?? null;
        $permission = $required['permission'] ?? null;
        if ($scope === null && $permission === null) {
            return true;
        }

        $permissions = $this->permissionsForUser((int) $user->id);

        return ($permission && in_array($permission, $permissions, true))
            || ($scope && in_array($scope, $permissions, true));
    }

    public function permissionsForUser(int $userId): array
    {
        $this->ensureSchema();
        $roleIds = ProjectAuthUserRole::where('project_auth_user_id', $userId)->pluck('project_auth_role_id')->all();
        $permissions = ProjectAuthRole::whereIn('id', $roleIds)->get()->flatMap(fn ($role) => (array) ($role->permissions ?? []))->all();

        return $this->normalizePermissions($permissions);
    }

    public function serializeUser(ProjectAuthUser $user): array
    {
        $roleIds = ProjectAuthUserRole::where('project_auth_user_id', $user->id)->pluck('project_auth_role_id')->map(fn ($id) => (int) $id)->all();

        return [
            'id' => (int) $user->id,
            'email' => $user->email,
            'phone' => $user->phone,
            'username' => $user->username,
            'name' => $user->name,
            'avatar' => $user->avatar,
            'status' => $user->status,
            'role_ids' => $roleIds,
            'permissions' => $this->permissionsForUser((int) $user->id),
            'last_login_at' => $user->last_login_at?->toDateTimeString(),
            'created_at' => $user->created_at?->toDateTimeString(),
        ];
    }

    private function ensureDefaultRoles(): void
    {
        if (! ProjectAuthRole::where('code', 'member')->exists()) {
            ProjectAuthRole::create([
                'code' => 'member',
                'name' => '普通用户',
                'description' => '公开注册用户默认为该角色',
                'permissions' => self::DEFAULT_PERMISSIONS,
                'is_system' => true,
            ]);
        }
        if (! ProjectAuthRole::where('code', 'editor')->exists()) {
            ProjectAuthRole::create([
                'code' => 'editor',
                'name' => '编辑用户',
                'description' => '可进行常见内容写入的项目用户角色',
                'permissions' => ['content.read', 'content.create', 'content.update', 'page.read', 'media.read', 'media.create'],
                'is_system' => true,
            ]);
        }
    }

    private function syncUserRoles(int $userId, array $roleIds): void
    {
        ProjectAuthUserRole::where('project_auth_user_id', $userId)->delete();
        foreach (array_values(array_unique($roleIds)) as $roleId) {
            if (ProjectAuthRole::where('id', $roleId)->exists()) {
                ProjectAuthUserRole::create(['project_auth_user_id' => $userId, 'project_auth_role_id' => $roleId]);
            }
        }
    }

    private function normalizePermissions(array $permissions): array
    {
        $allowed = self::DEFAULT_PERMISSIONS;

        return array_values(array_unique(array_filter(array_map('strval', $permissions), fn ($permission) => in_array($permission, $allowed, true))));
    }
}
