<?php

namespace App\Services;

use App\Models\FunctionEnv;
use App\Models\ProjectFunction;
use App\Models\ProjectFunctionExecution;
use App\Support\SecureOutboundUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

final class SafeHttp
{
    private function client(array $headers = []): PendingRequest
    {
        return Http::withHeaders($headers ?? [])
            ->timeout(15)
            ->withOptions(['allow_redirects' => false]);
    }

    private function guardUrl(string $url): void
    {
        SecureOutboundUrl::assertAllowed($url);
    }

    public function get(string $url, array $query = [], array $headers = [])
    {
        $this->guardUrl($url);

        return $this->client($headers)->get($url, $query);
    }

    public function post(string $url, $data = [], array $headers = [])
    {
        $this->guardUrl($url);

        return $this->client($headers)->post($url, $data);
    }

    public function send(string $method, string $url, array $options = [], array $headers = [])
    {
        $this->guardUrl($url);

        return $this->client($headers)->send($method, $url, $options);
    }
}

final class SafeDb
{
    private string $prefix;

    public function __construct(string $prefix)
    {
        $this->prefix = $prefix;
    }

    private function guardTable(string $table): void
    {
        if ($this->prefix === '' || ! str_starts_with($table, $this->prefix.'_')) {
            throw new \RuntimeException('Table not allowed');
        }
    }

    public function delete(string $table, array $where = [])
    {
        $this->guardTable($table);
        $q = DB::table($table);
        foreach ($where as $k => $v) {
            $q->where($k, $v);
        }

        return $q->delete();
    }

    public function insert(string $table, array $data)
    {
        $this->guardTable($table);

        return DB::table($table)->insert($data);
    }

    public function update(string $table, array $data, array $where = [])
    {
        $this->guardTable($table);
        $q = DB::table($table);
        foreach ($where as $k => $v) {
            $q->where($k, $v);
        }

        return $q->update($data);
    }

    public function select(string $table, array $where = [], array $fields = ['*'], int $limit = 100)
    {
        $this->guardTable($table);
        $q = DB::table($table)->select($fields)->limit(min(max(1, $limit), 1000));
        foreach ($where as $k => $v) {
            $q->where($k, $v);
        }

        return $q->get();
    }

    public function first(string $table, array $where = [], array $fields = ['*'])
    {
        $this->guardTable($table);
        $q = DB::table($table)->select($fields);
        foreach ($where as $k => $v) {
            $q->where($k, $v);
        }

        return $q->first();
    }

    public function count(string $table, array $where = [])
    {
        $this->guardTable($table);
        $q = DB::table($table);
        foreach ($where as $k => $v) {
            $q->where($k, $v);
        }

        return (int) $q->count();
    }

    /**
     * 获取查询构建器，用于复杂查询
     *
     * @param  string  $table  表名
     * @return \Illuminate\Database\Query\Builder
     */
    public function query(string $table)
    {
        $this->guardTable($table);

        return DB::table($table);
    }

    /**
     * 获取安全的查询构建器，支持链式调用
     *
     * @param  string  $table  表名
     */
    public function table(string $table): SafeQueryBuilder
    {
        $this->guardTable($table);

        return new SafeQueryBuilder($table);
    }
}

/**
 * 安全的查询构建器，支持链式调用
 */
class SafeQueryBuilder
{
    private string $table;

    private \Illuminate\Database\Query\Builder $query;

    public function __construct(string $table)
    {
        $this->table = $table;
        $this->query = DB::table($table);
    }

    public function where(string $column, $operator = null, $value = null): self
    {
        $this->query->where($column, $operator, $value);

        return $this;
    }

    public function whereBetween(string $column, array $values): self
    {
        $this->query->whereBetween($column, $values);

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->query->whereIn($column, $values);

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->query->whereNull($column);

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->query->whereNotNull($column);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    public function limit(int $value): self
    {
        $this->query->limit($value);

        return $this;
    }

    public function offset(int $value): self
    {
        $this->query->offset($value);

        return $this;
    }

    public function get(array $columns = ['*'])
    {
        return $this->query->get($columns);
    }

    public function first(array $columns = ['*'])
    {
        return $this->query->first($columns);
    }

    public function count(): int
    {
        return $this->query->count();
    }

    public function pluck(string $column, ?string $key = null)
    {
        return $this->query->pluck($column, $key);
    }

    public function value(string $column)
    {
        return $this->query->value($column);
    }

    public function exists(): bool
    {
        return $this->query->exists();
    }

    public function avg(string $column)
    {
        return $this->query->avg($column);
    }

    public function sum(string $column)
    {
        return $this->query->sum($column);
    }

    public function max(string $column)
    {
        return $this->query->max($column);
    }

    public function min(string $column)
    {
        return $this->query->min($column);
    }
}

class FunctionService
{
    public function invokeBySlug(Request $request, string $prefix, string $slug, bool $checkEnabled = true): array
    {
        // 从项目级 functions 表读取函数（HasProjectPrefix）
        $query = ProjectFunction::where('slug', $slug);
        if ($checkEnabled) {
            $query->where('enabled', 1);
        }
        $fnRow = $query->first();
        if (! $fnRow) {
            return [404, ['code' => 404, 'message' => 'Function not found', 'data' => null]];
        }
        $fn = $fnRow->toArray();
        $projectEnvs = $this->getProjectEnvs($prefix);

        // 校验入参（仅做最小校验：required 字段）
        $payload = $request->all();
        $valid = $this->validateInput($payload, $this->asArray($fn['input_schema'] ?? null));
        if ($valid !== true) {
            return [422, ['code' => 422, 'message' => (string) $valid, 'data' => null]];
        }

        // 每函数/每API Key 限流（每分钟）
        if (! empty($fn['rate_limit'])) {
            $actorKey = $request->attributes->get('api_key_id')
                ? 'api:'.$request->attributes->get('api_key_id')
                : ($request->attributes->get('project_user_id') ? 'project_user:'.$request->attributes->get('project_user_id') : 'anon');
            $limiterKey = sprintf('pf:%s:func:%d:%s', $prefix, (int) $fn['id'], $actorKey);
            if (RateLimiter::tooManyAttempts($limiterKey, (int) $fn['rate_limit'])) {
                return [429, ['code' => 429, 'message' => 'Too Many Requests', 'data' => null]];
            }
            RateLimiter::hit($limiterKey, 60);
        }

        // 触发器：函数执行前（通知型，不改写 payload）
        try {
            app(TriggerService::class)->dispatch('function.before_execute', [
                'function_id' => (int) $fn['id'],
                'slug' => (string) ($fn['slug'] ?? ''),
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) { /* ignore */
        }

        $start = microtime(true);
        $status = 'success';
        $error = null;
        $result = null;

        try {
            // 本地 PHP 运行时：优先支持在线代码段；兼容 class::method 形式
            $codeStr = (string) ($fn['code'] ?? '');
            $env = $projectEnvs;
            if ($codeStr) {
                $timeoutMs = max(100, (int) ($fn['timeout_ms'] ?? 3000));
                $maxMemMb = max(16, (int) ($fn['max_mem_mb'] ?? 64));
                $result = (array) $this->runPhpSnippet($codeStr, $payload, $env, [
                    'prefix' => $prefix,
                    'request' => $request,
                    'auth_subject_type' => $request->attributes->get('auth_subject_type'),
                    'project_user_id' => $request->attributes->get('project_user_id'),
                    'api_key_id' => $request->attributes->get('api_key_id'),
                    'timeout_ms' => $timeoutMs,
                    'max_mem_mb' => $maxMemMb,
                ]);
                $result = $this->truncateResult($result, 100 * 1024);
            }
        } catch (\Throwable $e) {
            $status = 'fail';
            $error = $e->getMessage();
        } finally {
            $duration = (int) round((microtime(true) - $start) * 1000);

            // 触发器：函数执行后（通知型）
            try {
                app(TriggerService::class)->dispatch('function.after_execute', [
                    'function_id' => (int) $fn['id'],
                    'slug' => (string) ($fn['slug'] ?? ''),
                    'payload' => $payload,
                    'result' => $result,
                    'status' => $status,
                    'error' => $error,
                ]);
            } catch (\Throwable $e) { /* ignore */
            }
            try {
                ProjectFunctionExecution::create([
                    'function_id' => (int) $fn['id'],
                    'trigger' => 'endpoint',
                    'status' => $status,
                    'duration_ms' => $duration,
                    'error' => $error,
                    'payload' => $payload,
                    'result' => $result,
                    'request_id' => (string) $request->header('X-Request-Id'),
                    'api_key_id' => $request->attributes->get('api_key_id'),
                ]);
            } catch (\Throwable $e) {
                // 记录创建失败不影响函数执行，仅记录日志
                Log::error('Failed to create function execution record', [
                    'function_id' => (int) $fn['id'],
                    'error' => $e->getMessage(),
                ]);
            }

            app(AuditLogger::class)->log(
                action: 'invoke',
                module: 'function',
                resourceTable: ProjectFunctionExecution::tableName(),
                resourceId: (int) $fn['id'],
                before: null,
                after: ['slug' => $fn['slug'] ?? '', 'status' => $status],
                request: $request,
                resourceType: 'function',
                status: $status === 'success' ? 'success' : 'fail',
                errorMessage: $error,
                meta: [
                    'api_key_id' => $request->attributes->get('api_key_id'),
                    'project_user_id' => $request->attributes->get('project_user_id'),
                    'auth_subject_type' => $request->attributes->get('auth_subject_type'),
                ]
            );
        }

        $httpCode = $status === 'success' ? 200 : 500;

        return [$httpCode, [
            'code' => $httpCode,
            'message' => $status === 'success' ? 'ok' : ($error ?: 'error'),
            'data' => $result,
        ]];
    }

    /**
     * 管理端测试：通过 slug 调用函数（允许测试禁用的函数）
     */
    public function invokeBySlugForAdmin(Request $request, string $prefix, string $slug): array
    {
        return $this->invokeBySlug($request, $prefix, $slug, false);
    }

    /**
     * 最小输入校验：支持 { required: ["field1", ...] }
     */
    private function validateInput(array $payload, $schema)
    {
        if (! is_array($schema)) {
            return true;
        }
        if (! empty($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $field) {
                if (! array_key_exists($field, $payload) || $payload[$field] === null) {
                    return "Missing required field: {$field}";
                }
            }
        }

        return true;
    }

    private function asArray($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $d = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $d;
            }
        }

        return null;
    }

    private function getProjectEnvs(string $prefix): array
    {
        if ($prefix === '') {
            return [];
        }
        try {
            // 会使用 session('current_project_prefix') 自动选择表
            $rows = FunctionEnv::query()->get(['name', 'value']);
            $out = [];
            foreach ($rows as $r) {
                $out[$r->name] = $r->value;
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 根据 ID 本地执行云函数（仅支持 runtime=php，不走 HTTP）。
     * 默认尊重函数自身 enabled 开关；管理端调试可显式传入 $checkEnabled=false。
     */
    public function runFunctionById(string $prefix, int $id, array $payload, ?string $event = null, bool $checkEnabled = true): array
    {
        if (! empty($prefix)) {
            session(['current_project_prefix' => $prefix]);
        }
        $row = ProjectFunction::find($id);
        if (! $row) {
            return ['code' => 404, 'message' => 'Function not found'];
        }
        if ($checkEnabled && ! (bool) $row->enabled) {
            return ['code' => 403, 'message' => 'Function disabled'];
        }
        $fn = $row->toArray();
        $codeStr = (string) ($fn['code'] ?? '');

        $env = $this->getProjectEnvs($prefix);

        // 触发器：函数执行前（通知型）
        try {
            app(TriggerService::class)->dispatch('function.before_execute', [
                'function_id' => (int) $fn['id'],
                'slug' => (string) ($fn['slug'] ?? ''),
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) { /* ignore */
        }

        $status = 'success';
        $error = null;
        $result = null;
        try {
            if ($codeStr) {
                $timeoutMs = max(100, (int) ($fn['timeout_ms'] ?? 3000));
                $maxMemMb = max(16, (int) ($fn['max_mem_mb'] ?? 64));

                $result = (array) $this->runPhpSnippet($codeStr, $payload, $env, [
                    'prefix' => $prefix,
                    'event' => $event,
                    'timeout_ms' => $timeoutMs,
                    'max_mem_mb' => $maxMemMb,
                ]);
                $result = $this->truncateResult($result, 100 * 1024);
            }
        } catch (\Throwable $e) {
            $status = 'fail';
            $error = $e->getMessage();
        }

        // 触发器：函数执行后（通知型）
        try {
            app(TriggerService::class)->dispatch('function.after_execute', [
                'function_id' => (int) $fn['id'],
                'slug' => (string) ($fn['slug'] ?? ''),
                'payload' => $payload,
                'result' => $result,
                'status' => $status,
                'error' => $error,
            ]);
        } catch (\Throwable $e) { /* ignore */
        }

        if ($status === 'success') {
            return ['code' => 200, 'message' => 'ok', 'data' => $result];
        }

        return ['code' => 500, 'message' => $error ?: 'error'];
    }

    /**
     * 运行在线 PHP 代码段。代码段在闭包上下文内执行，可直接使用 $payload/$env/$ctx 变量，并需 return 结果数组。
     */
    private function runPhpSnippet(string $code, array $payload, array $env, array $ctx): array
    {
        // 清理可能存在的 PHP 起止标签
        $clean = ltrim($code);
        $clean = preg_replace('/^<\?(php)?/i', '', $clean ?? '') ?? '';
        $clean = preg_replace('/\?>\s*$/', '', $clean) ?? $clean;
        $this->assertSnippetSafe($clean);

        $timeoutMs = (int) ($ctx['timeout_ms'] ?? 3000);
        $maxMemMb = (int) ($ctx['max_mem_mb'] ?? 64);
        $oldLimit = ini_get('memory_limit');
        if ($maxMemMb > 0) {
            @ini_set('memory_limit', $maxMemMb.'M');
        }
        $sec = max(1, (int) ceil($timeoutMs / 1000));
        @set_time_limit($sec);

        $HttpWrapper = new SafeHttp;
        $dbWrapper = new SafeDb((string) ($ctx['prefix'] ?? ''));

        $wrapped = 'return (function(array $payload, array $env, array $ctx, $Http, $db, $plugin) { '
                 .' $request = $ctx["request"] ?? null; $prefix = $ctx["prefix"] ?? null; $event = $ctx["event"] ?? null; $envs = $ctx["envs"] ?? []; '
                 .$clean.' });';
        try {
            $callable = eval($wrapped);
        } catch (\Throwable $e) {
            @ini_set('memory_limit', (string) $oldLimit);
            throw new \RuntimeException('PHP snippet compile error: '.$e->getMessage());
        }
        if (! is_callable($callable)) {
            @ini_set('memory_limit', (string) $oldLimit);
            throw new \RuntimeException('Invalid PHP snippet');
        }
        try {
            $res = $callable($payload, $env, $ctx, $HttpWrapper, $dbWrapper, null);
        } finally {
            @ini_set('memory_limit', (string) $oldLimit);
        }
        if (is_array($res)) {
            return $res;
        }
        if ($res === null) {
            return [];
        }

        return ['data' => $res];
    }

    private function assertSnippetSafe(string $code): void
    {
        $deny = [
            'resolve(', 'container(',
            'DB::', 'Storage::', 'File::', 'Redis::', 'Cache::', 'Auth::', 'Gate::',
            'Http::',
            'exec', 'shell_exec', 'system(', 'passthru', 'proc_open', 'popen', 'proc_close', 'proc_get_status',
            'curl_exec', 'curl_multi_exec', 'pcntl_', 'posix_', 'dl', 'putenv',
            'fopen', 'file_put_contents', 'unlink', 'rename', 'mkdir', 'rmdir', 'symlink', 'chmod', 'chown', 'copy',
            'stream_socket_server', 'fsockopen',
            '$_SERVER', '$_ENV', '$_FILES', '$_COOKIE', '$_SESSION', '$_POST', '$_GET', '$_REQUEST',
        ];

        foreach ($deny as $kw) {
            if (stripos($code, $kw) !== false) {
                throw new \RuntimeException('检测到不允许的代码片段：'.$kw);
            }
        }

        $tokens = token_get_all($code);
        $forbidden = [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_NAMESPACE, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG];
        foreach ($tokens as $t) {
            if (is_array($t) && in_array($t[0], $forbidden, true)) {
                throw new \RuntimeException('检测到不允许的语法：'.token_name($t[0]));
            }
        }
    }

    private function parsePluginNamespaces(array $env): array
    {
        $raw = $env['plugin_allowed_namespaces'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $j = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
                return $j;
            }
            $parts = array_map('trim', explode(',', $raw));

            return array_values(array_filter($parts, fn ($v) => $v !== ''));
        }

        return ['Plugins\\'];
    }

    private function parsePluginPaths(array $env): array
    {
        $raw = $env['plugin_allowed_paths'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $j = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
                return $j;
            }
            $parts = array_map('trim', explode(',', $raw));

            return array_values(array_filter($parts, fn ($v) => $v !== ''));
        }
        // 默认 plugins 目录（如果存在）
        try {
            return [base_path('plugins')];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function parseHttpWhitelist(array $env): array
    {
        $raw = $env['http_whitelist'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $j = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
                return $j;
            }
            $parts = array_map('trim', explode(',', $raw));

            return array_values(array_filter($parts, fn ($v) => $v !== ''));
        }

        return [];
    }

    private function truncateResult($result, int $maxBytes = 102400)
    {
        try {
            $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return $result;
            }
            if (strlen($json) <= $maxBytes) {
                return $result;
            }
            $preview = substr($json, 0, $maxBytes);

            return ['truncated' => true, 'preview' => $preview, 'omitted' => strlen($json) - $maxBytes];
        } catch (\Throwable $e) {
            return $result;
        }
    }
}
