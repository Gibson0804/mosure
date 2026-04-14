<?php

namespace App\Services;

use App\Repository\MoldRepository;
use Illuminate\Support\Facades\DB;

/**
 * 将模型中 optionsSource=model 的字段值（通常保存为 id / id列表）
 * 统一转换为“关联显示值”（如 tag_name）。
 *
 * 仅用于“读取/展示”场景（getListApi/getDetailApi/getSubjectByTable），
 * 不影响写入与更新接口的数据结构。
 */
class RelationDisplayService extends BaseService
{
    public function __construct(
        private MoldRepository $moldRepository
    ) {}

    /**
     * @param  string  $moldTableName  模型表名（molds.table_name），通常就是内容表名（带项目前缀）
     * @param  array  $items  ContentRepository::getList 返回的 items（stdClass[]）
     */
    public function hydrateListItems(string $moldTableName, array $items): array
    {
        if (empty($items)) {
            return $items;
        }

        $moldInfo = $this->moldRepository->getMoldInfoByTableName($moldTableName);
        if (! $moldInfo) {
            return $items;
        }

        $defs = $this->getModelSourceFieldDefs($moldInfo['fields_arr'] ?? []);
        if (empty($defs)) {
            return $items;
        }

        $labelMaps = $this->buildLabelMapsForRows($defs, $items);
        if (empty($labelMaps)) {
            return $items;
        }

        foreach ($items as $idx => $row) {
            if (! is_object($row) && ! is_array($row)) {
                continue;
            }
            foreach ($defs as $def) {
                $field = (string) $def['field'];
                $raw = $this->getValue($row, $field);
                if ($raw === null || $raw === '') {
                    continue;
                }
                $mapKey = $this->mapKey($def);
                $idToLabel = $labelMaps[$mapKey] ?? [];
                if (empty($idToLabel)) {
                    continue;
                }
                $converted = $this->convertValueToLabels($raw, $idToLabel);
                $this->setValue($row, $field, $converted);
            }
            $items[$idx] = $row;
        }

        return $items;
    }

    /**
     * @param  string  $moldTableName  模型表名（molds.table_name）
     * @param  object|array|null  $detail  ContentRepository::getDetail 返回的 stdClass|null
     */
    public function hydrateDetail(string $moldTableName, object|array|null $detail): object|array|null
    {
        if (! $detail) {
            return $detail;
        }

        $moldInfo = $this->moldRepository->getMoldInfoByTableName($moldTableName);
        if (! $moldInfo) {
            return $detail;
        }

        $defs = $this->getModelSourceFieldDefs($moldInfo['fields_arr'] ?? []);
        if (empty($defs)) {
            return $detail;
        }

        $labelMaps = $this->buildLabelMapsForRows($defs, [$detail]);
        foreach ($defs as $def) {
            $field = (string) $def['field'];
            $raw = $this->getValue($detail, $field);
            if ($raw === null || $raw === '') {
                continue;
            }
            $mapKey = $this->mapKey($def);
            $idToLabel = $labelMaps[$mapKey] ?? [];
            if (empty($idToLabel)) {
                continue;
            }
            $converted = $this->convertValueToLabels($raw, $idToLabel);
            $this->setValue($detail, $field, $converted);
        }

        return $detail;
    }

    /**
     * 单页 subject_content（数组）中的关联字段转换。
     */
    public function hydrateSubjectContent(string $moldTableName, array $subjectContent): array
    {
        if (empty($subjectContent)) {
            return $subjectContent;
        }

        $moldInfo = $this->moldRepository->getMoldInfoByTableName($moldTableName);
        if (! $moldInfo) {
            return $subjectContent;
        }

        $defs = $this->getModelSourceFieldDefs($moldInfo['fields_arr'] ?? []);
        if (empty($defs)) {
            return $subjectContent;
        }

        // 用“单行”来复用批量查询逻辑
        $fakeRow = (object) $subjectContent;
        $labelMaps = $this->buildLabelMapsForRows($defs, [$fakeRow]);

        foreach ($defs as $def) {
            $field = (string) $def['field'];
            if (! array_key_exists($field, $subjectContent)) {
                continue;
            }
            $raw = $subjectContent[$field];
            if ($raw === null || $raw === '') {
                continue;
            }
            $mapKey = $this->mapKey($def);
            $idToLabel = $labelMaps[$mapKey] ?? [];
            if (empty($idToLabel)) {
                continue;
            }
            $subjectContent[$field] = $this->convertValueToLabels($raw, $idToLabel);
        }

        return $subjectContent;
    }

    private function getModelSourceFieldDefs(array $fieldDefs): array
    {
        $defs = [];
        foreach ($fieldDefs as $def) {
            if (! is_array($def)) {
                continue;
            }
            $field = $def['field'] ?? null;
            if (! $field) {
                continue;
            }
            $optionsSource = (string) ($def['optionsSource'] ?? '');
            $sourceModelId = $def['sourceModelId'] ?? null;
            $sourceFieldName = $def['sourceFieldName'] ?? null;
            if ($optionsSource !== 'model' || empty($sourceModelId) || empty($sourceFieldName)) {
                continue;
            }
            $type = (string) ($def['type'] ?? '');
            // 目前主要用于选择类组件；tags 也常以“多选”方式存储
            if (! in_array($type, ['select', 'radio', 'checkbox', 'tags'], true)) {
                continue;
            }
            $defs[] = $def;
        }

        return $defs;
    }

    /**
     * 为一组 rows 预先批量构建：
     * mapKey(sourceModelId+sourceFieldName) => [id => label]
     */
    private function buildLabelMapsForRows(array $defs, array $rows): array
    {
        $needIdsByMapKey = [];

        foreach ($defs as $def) {
            $field = (string) $def['field'];
            $mapKey = $this->mapKey($def);
            foreach ($rows as $row) {
                $raw = $this->getValue($row, $field);
                if ($raw === null || $raw === '') {
                    continue;
                }
                $ids = $this->extractIds($raw);
                foreach ($ids as $id) {
                    $needIdsByMapKey[$mapKey][(string) $id] = true;
                }
            }
        }

        $labelMaps = [];
        foreach ($defs as $def) {
            $mapKey = $this->mapKey($def);
            if (isset($labelMaps[$mapKey])) {
                continue; // 已构建
            }
            $idsSet = $needIdsByMapKey[$mapKey] ?? [];
            $ids = array_keys($idsSet);
            if (empty($ids)) {
                $labelMaps[$mapKey] = [];

                continue;
            }

            try {
                $relatedMold = $this->moldRepository->getMoldInfo((int) $def['sourceModelId']);
                $tableName = $relatedMold['table_name'] ?? null;
                $labelField = (string) $def['sourceFieldName'];
                if (! $tableName || $labelField === '') {
                    $labelMaps[$mapKey] = [];

                    continue;
                }

                $rows2 = DB::table($tableName)
                    ->whereIn('id', $ids)
                    ->get(['id', $labelField]);

                $idToLabel = [];
                foreach ($rows2 as $r) {
                    $idToLabel[(string) $r->id] = (string) ($r->$labelField ?? '');
                }
                $labelMaps[$mapKey] = $idToLabel;
            } catch (\Throwable $e) {
                $labelMaps[$mapKey] = [];
            }
        }

        return $labelMaps;
    }

    private function convertValueToLabels(mixed $raw, array $idToLabel): mixed
    {
        $ids = $this->extractIds($raw);
        if (empty($ids)) {
            return $raw;
        }

        $labels = [];
        foreach ($ids as $id) {
            $label = $idToLabel[(string) $id] ?? '';
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        // 形状保持：数组 -> 数组；多值字符串 -> 逗号字符串；单值 -> 单字符串
        if (is_array($raw)) {
            return $labels;
        }

        if (is_string($raw)) {
            $trim = trim($raw);
            if ($trim !== '' && $this->looksLikeJsonArray($trim)) {
                return implode(',', $labels);
            }
            if (str_contains($raw, ',')) {
                return implode(',', $labels);
            }

            return $labels[0] ?? '';
        }

        return $labels[0] ?? '';
    }

    private function extractIds(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (is_int($raw) || is_float($raw)) {
            return [(string) $raw];
        }
        if (is_array($raw)) {
            $out = [];
            foreach ($raw as $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                $out[] = (string) $v;
            }

            return array_values(array_unique($out));
        }
        if (! is_string($raw)) {
            return [];
        }

        $s = trim($raw);
        if ($s === '') {
            return [];
        }

        if ($this->looksLikeJsonArray($s)) {
            $decoded = json_decode($s, true);
            if (is_array($decoded)) {
                return $this->extractIds($decoded);
            }
        }

        $parts = array_map('trim', explode(',', $s));
        $parts = array_values(array_filter($parts, fn ($v) => $v !== ''));

        return array_values(array_unique($parts));
    }

    private function looksLikeJsonArray(string $s): bool
    {
        return str_starts_with($s, '[') && str_ends_with($s, ']');
    }

    private function mapKey(array $def): string
    {
        return (string) $def['sourceModelId'].'|'.(string) $def['sourceFieldName'];
    }

    private function getValue(object|array $row, string $field): mixed
    {
        if (is_array($row)) {
            return $row[$field] ?? null;
        }

        return $row->$field ?? null;
    }

    private function setValue(object|array &$row, string $field, mixed $value): void
    {
        if (is_array($row)) {
            $row[$field] = $value;

            return;
        }
        $row->$field = $value;
    }
}
