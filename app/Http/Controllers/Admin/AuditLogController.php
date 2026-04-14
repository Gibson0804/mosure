<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index()
    {
        return viewShow('Manage/AuditLogs', [
            'project' => [
                'name' => session('current_project_name'),
                'prefix' => session('current_project_prefix'),
            ],
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        try {
            $query = AuditLog::query();

            // Params
            $page = max(1, (int) $request->input('page', 1));
            $pageSize = max(1, (int) $request->input('page_size', 15));
            $fields = $request->input('fields');
            $sort = (string) $request->input('sort', '-created_at');
            $filters = (array) $request->input('filter', []);

            // Select fields
            $allowed = [
                'id', 'actor_type', 'actor_id', 'actor_name', 'api_key', 'action', 'module', 'resource_type', 'resource_table', 'resource_id',
                'request_method', 'request_path', 'request_ip', 'user_agent', 'request_id', 'status', 'error_message', 'created_at',
            ];
            $select = ['*'];
            if (is_string($fields) && trim($fields) !== '') {
                $list = array_map('trim', explode(',', $fields));
                $select = array_values(array_intersect($list, $allowed));
                if (empty($select)) {
                    $select = ['*'];
                }
            }

            // Filters
            // 特殊字段：api_key_id 存储在 meta JSON 中
            if (array_key_exists('api_key_id', $filters)) {
                $value = $filters['api_key_id'];
                if (is_array($value) && array_key_exists('op', $value)) {
                    $op = strtolower((string) ($value['op'] ?? 'eq'));
                    $val = $value['value'] ?? null;
                    if ($op === 'in') {
                        $vals = $val;
                        if (is_string($vals)) {
                            $vals = array_filter(array_map('trim', explode(',', $vals)));
                        }
                        if (is_array($vals)) {
                            $query->whereIn('meta->api_key_id', $vals);
                        }
                    } else { // 默认等于
                        $query->where('meta->api_key_id', '=', $val);
                    }
                } else {
                    $query->where('meta->api_key_id', '=', $value);
                }
                unset($filters['api_key_id']);
            }

            foreach ($filters as $field => $value) {
                if (! in_array($field, array_merge($allowed, ['created_at']), true)) {
                    continue;
                }
                if (is_array($value) && array_key_exists('op', $value)) {
                    $op = strtolower((string) ($value['op'] ?? 'eq'));
                    $val = $value['value'] ?? null;
                    if ($op === 'like' && is_string($val)) {
                        $query->where($field, 'like', '%'.$val.'%');
                    } elseif (in_array($op, ['eq', '=', 'ne', '!=', 'gt', '>', 'gte', '>=', 'lt', '<', 'lte', '<='])) {
                        $map = ['eq' => '=', 'ne' => '!=', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];
                        $query->where($field, $map[$op] ?? $op, $val);
                    } elseif ($op === 'in') {
                        $vals = $val;
                        if (is_string($vals)) {
                            $vals = array_filter(array_map('trim', explode(',', $vals)));
                        }
                        if (is_array($vals)) {
                            $query->whereIn($field, $vals);
                        }
                    } elseif ($op === 'between') {
                        if (is_array($val) && count($val) === 2) {
                            $query->whereBetween($field, [$val[0], $val[1]]);
                        }
                    }
                } elseif (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, '=', $value);
                }
            }

            // Sort
            if ($sort) {
                $parts = array_filter(array_map('trim', explode(',', $sort)));
                foreach ($parts as $part) {
                    $dir = 'asc';
                    $field = $part;
                    if (strpos($part, '-') === 0) {
                        $dir = 'desc';
                        $field = substr($part, 1);
                    }
                    if (in_array($field, array_merge($allowed, ['created_at']), true)) {
                        $query->orderBy($field, $dir);
                    }
                }
            }

            $total = (clone $query)->count();
            $items = $query->limit($pageSize)->offset(($page - 1) * $pageSize)->get($select);
            $pageCount = (int) ceil($total / $pageSize);

            $meta = [
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'page_count' => $pageCount,
                'fields' => $select,
            ];

            $links = [
                'self' => $request->fullUrl(),
                'first' => $this->buildPageUrl($request, 1),
                'last' => $this->buildPageUrl($request, max(1, $pageCount)),
                'prev' => $page > 1 ? $this->buildPageUrl($request, $page - 1) : null,
                'next' => $page < $pageCount ? $this->buildPageUrl($request, $page + 1) : null,
            ];

            return success([
                'data' => $items,
                'meta' => $meta,
                'links' => $links,
            ]);
        } catch (\Throwable $e) {
            return error([], '获取审计日志失败: '.$e->getMessage());
        }
    }

    private function buildPageUrl(Request $request, int $page): string
    {
        $query = $request->query();
        $query['page'] = $page;
        $path = $request->path();

        return url($path).(empty($query) ? '' : ('?'.http_build_query($query)));
    }
}
