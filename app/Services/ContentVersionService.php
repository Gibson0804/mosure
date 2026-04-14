<?php

namespace App\Services;

use App\Models\SysContentVersion;
use App\Repository\ContentRepository;
use App\Repository\MoldRepository;
use Illuminate\Support\Facades\DB;

class ContentVersionService extends BaseService
{
    private MoldRepository $moldRepository;

    public function __construct(MoldRepository $moldRepository)
    {
        $this->moldRepository = $moldRepository;
    }

    public function recordCreate(int $moldId, int $contentId, $row, ?int $operatorId = null): SysContentVersion
    {
        $version = $this->nextVersion($moldId, $contentId);
        $dataMap = $this->rowToDataMap($moldId, $row);

        return $this->insert($moldId, $contentId, $version, 'create', (string) ($row->content_status ?? ''), $dataMap, [], 0, $operatorId);
    }

    public function recordUpdate(int $moldId, int $contentId, $beforeRow, $afterRow, ?int $operatorId = null): SysContentVersion
    {
        $version = $this->nextVersion($moldId, $contentId);
        $before = $this->rowToDataMap($moldId, $beforeRow);
        $after = $this->rowToDataMap($moldId, $afterRow);
        $diff = $this->diffMaps($before, $after);

        return $this->insert($moldId, $contentId, $version, 'update', (string) ($afterRow->content_status ?? ''), $after, $diff, 0, $operatorId);
    }

    public function recordPublish(int $moldId, int $contentId, $row, ?int $operatorId = null): SysContentVersion
    {
        $version = $this->nextVersion($moldId, $contentId);
        $data = $this->rowToDataMap($moldId, $row);
        $rec = $this->insert($moldId, $contentId, $version, 'publish', (string) ($row->content_status ?? ''), $data, [], 1, $operatorId);
        $this->markPublishedUnique($moldId, $contentId, $rec->version);

        return $rec;
    }

    public function recordUnpublish(int $moldId, int $contentId, $row, ?int $operatorId = null): SysContentVersion
    {
        $version = $this->nextVersion($moldId, $contentId);
        $data = $this->rowToDataMap($moldId, $row);
        $rec = $this->insert($moldId, $contentId, $version, 'unpublish', (string) ($row->content_status ?? ''), $data, [], 0, $operatorId);
        // 取消已发布标记
        $this->clearPublishedMark($moldId, $contentId);

        return $rec;
    }

    public function recordRollback(int $moldId, int $contentId, array $rolledData, string $status, ?bool $publish = false, ?int $operatorId = null): SysContentVersion
    {
        $version = $this->nextVersion($moldId, $contentId);
        $rec = $this->insert($moldId, $contentId, $version, 'rollback', $status, $rolledData, [], $publish ? 1 : 0, $operatorId);
        if ($publish) {
            $this->markPublishedUnique($moldId, $contentId, $rec->version);
        }

        return $rec;
    }

    public function listVersions(int $moldId, int $contentId, int $limit = 100)
    {
        $versions = SysContentVersion::query()
            ->where('mold_id', $moldId)
            ->where('content_id', $contentId)
            ->orderByDesc('version')
            ->limit($limit)
            ->get(['version', 'event', 'is_published', 'content_status', 'created_by', 'created_at']);

        if ($versions->isEmpty()) {
            return [];
        }

        // 将 created_by ID 转换为用户名
        $userIds = $versions->pluck('created_by')->filter()->unique()->toArray();
        $userMap = [];
        if (! empty($userIds)) {
            $users = \App\Models\User::whereIn('id', $userIds)->get(['id', 'name']);
            foreach ($users as $user) {
                $userMap[$user->id] = $user->name;
            }
        }

        // 替换 created_by 为用户名，转换为数组处理
        $versionsArray = $versions->toArray();
        foreach ($versionsArray as &$version) {
            $version['created_by'] = $userMap[$version['created_by']] ?? null;
        }
        unset($version);

        return $versionsArray;
    }

    public function getVersion(int $moldId, int $contentId, int $version): ?SysContentVersion
    {
        return SysContentVersion::query()
            ->where(['mold_id' => $moldId, 'content_id' => $contentId, 'version' => $version])
            ->first();
    }

    public function diffVersions(int $moldId, int $contentId, int $v1, int $v2): array
    {
        $a = $this->getVersion($moldId, $contentId, $v1);
        $b = $this->getVersion($moldId, $contentId, $v2);
        if (! $a || ! $b) {
            return [];
        }
        $m1Raw = (array) ($a->data_json ?? []);
        $m2Raw = (array) ($b->data_json ?? []);
        $n1 = $this->normalizeForDiff($moldId, $m1Raw);
        $n2 = $this->normalizeForDiff($moldId, $m2Raw);
        $diff = $this->diffMapsNormalized($n1, $n2, $m1Raw, $m2Raw);
        // 映射字段label
        try {
            $mold = $this->moldRepository->getMoldInfo($moldId);
            $defs = is_array($mold['fields_arr'] ?? null) ? $mold['fields_arr'] : [];
            $labelMap = [];
            foreach ($defs as $d) {
                $f = $d['field'] ?? null;
                if ($f) {
                    $labelMap[$f] = (string) ($d['label'] ?? $f);
                }
            }
            foreach ($diff as &$it) {
                $f = (string) ($it['field'] ?? '');
                $it['label'] = $labelMap[$f] ?? $f;
            }
            unset($it);
        } catch (\Throwable $e) {
        }

        return $diff;
    }

    /**
     * 对比当前内容与指定版本的差异
     */
    public function diffCurrentWithVersion(int $moldId, int $contentId, int $version): array
    {
        $ver = $this->getVersion($moldId, $contentId, $version);
        if (! $ver) {
            return [];
        }
        $repo = ContentRepository::buildContent($moldId);
        $row = $repo->find($contentId);
        if (! $row) {
            return [];
        }
        $curRaw = $this->rowToDataMap($moldId, $row);
        $targetRaw = (array) ($ver->data_json ?? []);
        $nCur = $this->normalizeForDiff($moldId, $curRaw);
        $nTarget = $this->normalizeForDiff($moldId, $targetRaw);

        return $this->diffMapsNormalized($nCur, $nTarget, $curRaw, $targetRaw);
    }

    public function rollbackTo(int $moldId, int $contentId, int $version, bool $publish = false, ?int $operatorId = null): bool
    {
        $ver = $this->getVersion($moldId, $contentId, $version);
        if (! $ver) {
            return false;
        }
        $data = (array) ($ver->data_json ?? []);
        // 回滚入库前，按字段类型归一化数据格式
        $data = $this->normalizeDataForSave($moldId, $data);

        // 落库
        $repo = ContentRepository::buildContent($moldId);
        $ok = $repo->editById($data, $contentId);

        // 状态策略：保持当前状态，或可根据需要复原 ver->content_status
        $row = $repo->find($contentId);
        $this->recordRollback($moldId, $contentId, $this->rowToDataMap($moldId, $row), (string) ($row->content_status ?? ''), $publish, $operatorId);
        if ($publish) {
            $this->markPublishedUnique($moldId, $contentId, $this->getMaxVersion($moldId, $contentId));
        }

        return (bool) $ok;
    }

    /**
     * 将版本数据按模型字段类型归一化为库内存储格式
     * - picUpload/fileUpload: 字符串URL（无需转换）
     * - picGallery: 数组 -> JSON 字符串
     * - checkbox: 数组 -> 逗号分隔字符串
     */
    private function normalizeDataForSave(int $moldId, array $data): array
    {
        try {
            $mold = $this->moldRepository->getMoldInfo($moldId);
            $defs = is_array($mold['fields_arr'] ?? null) ? $mold['fields_arr'] : [];
            foreach ($defs as $def) {
                $field = $def['field'] ?? null;
                if (! $field || ! array_key_exists($field, $data)) {
                    continue;
                }
                $type = (string) ($def['type'] ?? '');
                $val = $data[$field];
                if ($type === 'picGallery') {
                    if (is_array($val) || is_object($val)) {
                        $data[$field] = json_encode($val, JSON_UNESCAPED_UNICODE);
                    }
                } elseif ($type === 'checkbox') {
                    if (is_array($val)) {
                        $data[$field] = implode(',', array_map('strval', $val));
                    }
                }
                // picUpload/fileUpload: 已经是字符串URL，无需转换
            }
        } catch (\Throwable $e) {
            // 忽略归一化异常，保持原值
        }

        return $data;
    }

    private function insert(int $moldId, int $contentId, int $version, string $event, string $status, array $data, array $changed, int $isPublished, ?int $operatorId): SysContentVersion
    {
        $prefix = session('current_project_prefix');

        return SysContentVersion::query()->create([
            'project_prefix' => $prefix,
            'mold_id' => $moldId,
            'content_id' => $contentId,
            'version' => $version,
            'event' => $event,
            'content_status' => $status,
            'data_json' => $data,
            'changed_fields' => $changed,
            'is_published' => $isPublished ? 1 : 0,
            'created_by' => $operatorId,
        ]);
    }

    private function nextVersion(int $moldId, int $contentId): int
    {
        $max = SysContentVersion::query()
            ->where('mold_id', $moldId)
            ->where('content_id', $contentId)
            ->max('version');

        return ((int) $max) + 1;
    }

    private function getMaxVersion(int $moldId, int $contentId): int
    {
        $max = SysContentVersion::query()
            ->where('mold_id', $moldId)
            ->where('content_id', $contentId)
            ->max('version');

        return (int) $max;
    }

    private function markPublishedUnique(int $moldId, int $contentId, int $version): void
    {
        DB::table('sys_content_versions')
            ->where(['mold_id' => $moldId, 'content_id' => $contentId])
            ->update(['is_published' => 0]);
        DB::table('sys_content_versions')
            ->where(['mold_id' => $moldId, 'content_id' => $contentId, 'version' => $version])
            ->update(['is_published' => 1]);
    }

    private function clearPublishedMark(int $moldId, int $contentId): void
    {
        DB::table('sys_content_versions')
            ->where(['mold_id' => $moldId, 'content_id' => $contentId])
            ->update(['is_published' => 0]);
    }

    private function rowToDataMap(int $moldId, $row): array
    {
        if (! $row) {
            return [];
        }
        $mold = $this->moldRepository->getMoldInfo($moldId);
        $defs = is_array($mold['fields_arr'] ?? null) ? $mold['fields_arr'] : [];
        $data = [];
        foreach ($defs as $d) {
            $f = $d['field'] ?? null;
            if (! $f) {
                continue;
            }
            $val = is_array($row) ? ($row[$f] ?? null) : ($row->$f ?? null);
            // checkbox存为字符串，仍保留字符串
            $data[$f] = $val;
        }

        return $data;
    }

    private function diffMaps(array $before, array $after): array
    {
        $allKeys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $diff = [];
        foreach ($allKeys as $k) {
            $b = $before[$k] ?? null;
            $a = $after[$k] ?? null;
            // 简单比较（字符串化）
            $bs = is_array($b) ? json_encode($b, JSON_UNESCAPED_UNICODE) : (string) $b;
            $as = is_array($a) ? json_encode($a, JSON_UNESCAPED_UNICODE) : (string) $a;
            if ($bs !== $as) {
                $diff[] = ['field' => $k, 'old' => $b, 'new' => $a];
            }
        }

        return $diff;
    }

    /**
     * 类型感知的 Diff：使用归一化后的值进行比较，但返回原值
     */
    private function diffMapsNormalized(array $beforeNorm, array $afterNorm, array $beforeRaw, array $afterRaw): array
    {
        $allKeys = array_unique(array_merge(array_keys($beforeNorm), array_keys($afterNorm)));
        $diff = [];
        foreach ($allKeys as $k) {
            $bs = (string) ($beforeNorm[$k] ?? '');
            $as = (string) ($afterNorm[$k] ?? '');
            if ($bs !== $as) {
                $diff[] = [
                    'field' => $k,
                    'old' => $beforeRaw[$k] ?? null,
                    'new' => $afterRaw[$k] ?? null,
                ];
            }
        }

        return $diff;
    }

    /**
     * 根据字段类型将值归一化为可稳定比较的字符串
     */
    private function normalizeForDiff(int $moldId, array $map): array
    {
        try {
            $mold = $this->moldRepository->getMoldInfo($moldId);
            $defs = is_array($mold['fields_arr'] ?? null) ? $mold['fields_arr'] : [];
            $typeMap = [];
            foreach ($defs as $d) {
                if (! empty($d['field'])) {
                    $typeMap[$d['field']] = (string) ($d['type'] ?? '');
                }
            }
            $norm = [];
            foreach ($map as $field => $val) {
                $t = $typeMap[$field] ?? '';
                switch ($t) {
                    case 'checkbox':
                    case 'select':
                        if (is_array($val)) {
                            $arr = array_map('strval', $val);
                        } else {
                            $arr = array_values(array_filter(array_map('trim', explode(',', (string) $val)), fn ($s) => $s !== ''));
                        }
                        sort($arr);
                        $norm[$field] = implode(',', $arr);
                        break;
                    case 'picUpload':
                    case 'fileUpload':
                        // 单文件/单图：字符串URL
                        $norm[$field] = is_string($val) ? $val : '';
                        break;
                    case 'picGallery':
                        // 图片集：JSON数组
                        $arr = [];
                        if (is_string($val)) {
                            $decoded = json_decode($val, true);
                            if (is_array($decoded)) {
                                $arr = $decoded;
                            } else {
                                $arr = [];
                            }
                        } elseif (is_array($val)) {
                            $arr = $val;
                        }
                        $urls = [];
                        foreach ($arr as $it) {
                            if (is_string($it)) {
                                $u = $it;
                            } elseif (is_array($it)) {
                                $u = (string) ($it['url'] ?? '');
                            } elseif (is_object($it)) {
                                $u = (string) ($it->url ?? '');
                            } else {
                                $u = '';
                            }
                            if ($u !== '') {
                                $urls[] = $u;
                            }
                        }
                        sort($urls);
                        $norm[$field] = implode(',', $urls);
                        break;
                    case 'richText':
                        $s = (string) $val;
                        // 去标签
                        $s = strip_tags($s);
                        // 处理&nbsp; 和不间断空格
                        $s = str_replace(['&nbsp;', chr(194).chr(160)], ' ', $s);
                        // HTML 实体解码
                        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        // 压缩空白
                        $s = preg_replace('/\s+/u', ' ', $s);
                        $norm[$field] = trim((string) $s);
                        break;
                    case 'numInput':
                    case 'number':
                        if ($val === null || $val === '') {
                            $norm[$field] = '';
                        } else {
                            $norm[$field] = (string) (0 + $val);
                        }
                        break;
                    default:
                        if (is_array($val)) {
                            $norm[$field] = json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        } else {
                            $norm[$field] = (string) $val;
                        }
                }
            }

            return $norm;
        } catch (\Throwable $e) {
            // 失败时回退：全部字符串化
            $res = [];
            foreach ($map as $k => $v) {
                $res[$k] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v;
            }

            return $res;
        }
    }
}
