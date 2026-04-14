<?php

namespace App\Repository;

use App\Models\Mold;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContentRepository extends BaseRepository
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => '待发布',
        self::STATUS_PUBLISHED => '已发布',
        self::STATUS_DISABLED => '已下线',
    ];

    public const STATUS_ORDER = [
        self::STATUS_PENDING => 10,
        self::STATUS_PUBLISHED => 20,
        self::STATUS_DISABLED => 30,
    ];

    private static $modelTabelList;

    public static function getModel($modeTabelName)
    {
        if (! isset(self::$modelTabelList[$modeTabelName])) {
            self::$modelTabelList[$modeTabelName] = new ContentRepository($modeTabelName);
        }

        return self::$modelTabelList[$modeTabelName];
    }

    public static function buildContent($moldId)
    {

        $tableName = Mold::find($moldId)->table_name;

        return self::getModel($tableName);
    }

    private $tableName;

    public function __construct($tableName)
    {
        $this->tableName = $tableName;
        $this->mainModel = DB::table($tableName);
    }

    public function getList($params, $fields, $page, $pageSize)
    {
        // fresh query builder each time
        $query = DB::table($this->tableName);

        // Standardized parameters
        $filters = $params['filter'] ?? [];
        $sort = $params['sort'] ?? '';
        $page = max(1, (int) ($params['page'] ?? $page));
        $pageSize = max(1, (int) ($params['page_size'] ?? $pageSize));

        // Fields can be array or comma-separated string
        if (is_string($fields)) {
            $fields = array_map('trim', explode(',', $fields));
        }
        $fields = $this->sanitizeFields($fields);
        if (empty($fields)) {
            $fields = ['*'];
        }

        // Apply filters and sort
        $this->applyFilters($query, $filters);
        $this->applySort($query, $sort);

        // Count total before pagination
        $total = (clone $query)->count();

        // Pagination
        $items = $query
            ->orderBy('id', 'desc')
            ->limit($pageSize)
            ->offset(($page - 1) * $pageSize)
            ->get($fields)
            ->toArray();

        $pageCount = (int) ceil($total / $pageSize);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'page_count' => $pageCount,
            'fields' => $fields,
        ];

    }

    public function getDetail(int $id, $fields = ['*'])
    {
        if (is_string($fields)) {
            $fields = array_map('trim', explode(',', $fields));
        }
        $fields = $this->sanitizeFields($fields);
        if (empty($fields)) {
            $fields = ['*'];
        }

        return DB::table($this->tableName)
            ->where('id', $id)
            ->first($fields);
    }

    public function getAll()
    {
        $res = DB::table($this->tableName)->get();

        return $res;
    }

    private function sanitizeFields($fields): array
    {
        if (! is_array($fields)) {
            return [];
        }
        $sanitized = [];
        foreach ($fields as $col) {
            if ($col === '*' || Schema::hasColumn($this->tableName, $col)) {
                $sanitized[] = $col;
            }
        }

        return array_values(array_unique($sanitized));
    }

    private function applyFilters($query, $filters): void
    {
        if (! is_array($filters)) {
            return;
        }
        foreach ($filters as $field => $value) {
            if (! Schema::hasColumn($this->tableName, $field)) {
                continue;
            }
            if (is_array($value) && array_key_exists('op', $value)) {
                $op = strtolower((string) ($value['op'] ?? 'eq'));
                $val = $value['value'] ?? null;
                if (in_array($op, ['like', 'like_prefix', 'like_suffix'], true) && is_string($val)) {
                    if ($op === 'like_prefix') {
                        if (strpos($val, '%') === false) {
                            $val = $val.'%';
                        }
                    } elseif ($op === 'like_suffix') {
                        if (strpos($val, '%') === false) {
                            $val = '%'.$val;
                        }
                    } else {
                        if (strpos($val, '%') === false) {
                            $val = '%'.$val.'%';
                        }
                    }
                    $query->where($field, 'like', $val);
                } elseif (in_array($op, ['eq', '=', 'ne', '!=', 'gt', '>', 'gte', '>=', 'lt', '<', 'lte', '<='])) {
                    $map = ['eq' => '=', 'ne' => '!=', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];
                    $operator = $map[$op] ?? $op;
                    $query->where($field, $operator, $val);
                } elseif ($op === 'in') {
                    $vals = $value['value'] ?? [];
                    if (is_string($vals)) {
                        $vals = array_filter(array_map('trim', explode(',', $vals)));
                    }
                    if (is_array($vals)) {
                        $query->whereIn($field, $vals);
                    }
                } elseif (in_array($op, ['contains_any', 'contains_all'], true)) {
                    $vals = $value['value'] ?? [];
                    if (is_string($vals)) {
                        $vals = array_filter(array_map('trim', explode(',', $vals)));
                    }
                    if (! is_array($vals) || empty($vals)) {
                        continue;
                    }
                    $query->where(function ($q) use ($field, $vals, $op) {
                        foreach ($vals as $v) {
                            $pattern = '%'.$v.'%';
                            if ($op === 'contains_any') {
                                $q->orWhere($field, 'like', $pattern);
                            } else {
                                $q->where($field, 'like', $pattern);
                            }
                        }
                    });
                } elseif (in_array($op, ['between', 'range'], true)) {
                    $from = null;
                    $to = null;

                    if (is_array($val) && count($val) >= 2) {
                        $from = $val[0] ?? null;
                        $to = $val[1] ?? null;
                    } elseif (is_array($value)) {
                        $from = $value['from'] ?? null;
                        $to = $value['to'] ?? null;
                    }

                    $from = ($from === '' ? null : $from);
                    $to = ($to === '' ? null : $to);

                    if ($from !== null && $to !== null) {
                        $query->whereBetween($field, [$from, $to]);
                    } elseif ($from !== null) {
                        $query->where($field, '>=', $from);
                    } elseif ($to !== null) {
                        $query->where($field, '<=', $to);
                    }
                }
            } elseif (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, '=', $value);
            }
        }
    }

    private function applySort($query, $sort): void
    {
        if (! is_string($sort) || $sort === '') {
            return;
        }
        $parts = array_filter(array_map('trim', explode(',', $sort)));
        foreach ($parts as $part) {
            $direction = 'asc';
            $field = $part;
            if (strpos($part, '-') === 0) {
                $direction = 'desc';
                $field = substr($part, 1);
            }
            if (Schema::hasColumn($this->tableName, $field)) {
                $query->orderBy($field, $direction);
            }
        }
    }

    public function count()
    {
        $res = DB::table($this->tableName)->count();

        return $res;
    }

    public function create($data)
    {

        if (! array_key_exists('created_at', $data) || empty($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if (! array_key_exists('updated_at', $data) || empty($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        $userName = Auth::user()->name ?? '';
        if (! array_key_exists('updated_by', $data) || empty($data['updated_by'])) {
            $data['updated_by'] = $userName;
        }
        if (! array_key_exists('created_by', $data) || empty($data['created_by'])) {
            $data['created_by'] = $userName;
        }
        if (! array_key_exists('content_status', $data) || empty($data['content_status'])) {
            $data['content_status'] = self::STATUS_PENDING;
        }

        $id = DB::table($this->tableName)->insertGetId($data);

        return DB::table($this->tableName)->where('id', $id)->first();
    }

    public function updateStatusByIds(array $ids, string $status): bool
    {
        $userName = Auth::user()->name ?? '';

        $res = DB::table($this->tableName)
            ->whereIn('id', $ids)
            ->update([
                'content_status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $userName,
            ]);

        return $res;
    }

    public function updateStatusById(int $id, string $status): bool
    {
        $userName = Auth::user()->name ?? '';

        $res = DB::table($this->tableName)
            ->where('id', $id)
            ->update([
                'content_status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $userName,
            ]);

        return $res;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }
}
