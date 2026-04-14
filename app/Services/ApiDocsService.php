<?php

namespace App\Services;

use App\Models\ProjectFunction;
use App\Repository\MoldRepository;

class ApiDocsService
{
    private MoldRepository $moldRepository;

    public function __construct(MoldRepository $moldRepository)
    {
        $this->moldRepository = $moldRepository;
    }

    /**
     * 将前端组件类型映射为JSON Schema类型
     *
     * @param  string  $fieldType  前端组件类型
     * @return string JSON Schema类型
     */
    private function mapFieldTypeToJsonType(string $fieldType): string
    {
        $map = [
            // 文本类型
            'input' => 'string',
            'textarea' => 'string',
            'richText' => 'string',

            // 数字类型
            'numInput' => 'number',
            'slider' => 'number',
            'rate' => 'number',

            // 布尔类型
            'switch' => 'boolean',

            // 日期时间类型
            'dateTimePicker' => 'string',
            'datePicker' => 'string',
            'timePicker' => 'string',
            'dateRangePicker' => 'string',

            // 选择类型
            'select' => 'string',
            'radio' => 'string',
            'checkbox' => 'string',
            'cascader' => 'string',

            // 特殊类型
            'colorPicker' => 'string',
            'fileUpload' => 'string',
            'picUpload' => 'string',
            'picGallery' => 'array',
            'tags' => 'array',
            'dividingLine' => 'null',
        ];

        return $map[$fieldType] ?? 'string';
    }

    public function listApis(array $filters = []): array
    {
        $prefix = (string) (session('current_project_prefix') ?? '');
        if ($prefix === '') {
            throw new \RuntimeException('未选择项目，无法获取 API 文档');
        }

        $kind = (string) ($filters['kind'] ?? 'all');
        $kinds = $kind === 'all' || $kind === '' ? ['content', 'page', 'media', 'function', 'auth'] : [$kind];

        $endpoints = [];

        if (in_array('content', $kinds, true)) {
            $endpoints = array_merge($endpoints, $this->buildContentEndpoints($prefix));
        }
        if (in_array('page', $kinds, true)) {
            $endpoints = array_merge($endpoints, $this->buildPageEndpoints($prefix));
        }
        if (in_array('media', $kinds, true)) {
            $endpoints = array_merge($endpoints, $this->buildMediaEndpoints($prefix));
        }
        if (in_array('function', $kinds, true)) {
            $endpoints = array_merge($endpoints, $this->buildFunctionEndpoints($prefix));
        }
        if (in_array('auth', $kinds, true) && $this->projectAuthEnabled()) {
            $endpoints = array_merge($endpoints, $this->buildAuthEndpoints($prefix));
        }

        $method = isset($filters['method']) ? strtoupper((string) $filters['method']) : null;
        if ($method) {
            $endpoints = array_values(array_filter($endpoints, function (array $ep) use ($method) {
                return strtoupper((string) ($ep['http_method'] ?? '')) === $method;
            }));
        }

        $operation = isset($filters['operation']) ? strtolower((string) $filters['operation']) : null;
        if ($operation) {
            $endpoints = array_values(array_filter($endpoints, function (array $ep) use ($operation) {
                return strtolower((string) ($ep['operation'] ?? '')) === $operation;
            }));
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $lower = mb_strtolower($keyword);
            $endpoints = array_values(array_filter($endpoints, function (array $ep) use ($lower) {
                $haystack = mb_strtolower(
                    (string) ($ep['id'] ?? '').' '.
                    (string) ($ep['name'] ?? '').' '.
                    (string) ($ep['description'] ?? '').' '.
                    (string) ($ep['path'] ?? '')
                );

                return $haystack !== '' && str_contains($haystack, $lower);
            }));
        }

        $total = count($endpoints);
        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 50;
        $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;

        $paged = array_slice($endpoints, $offset, $limit);

        return [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'endpoints' => array_values($paged),
        ];
    }

    private function buildContentEndpoints(string $prefix): array
    {
        $all = $this->moldRepository->getAllMold();
        $items = $all->where('mold_type', MoldRepository::CONTENT_MOLD_TYPE);

        $result = [];
        foreach ($items as $mold) {
            $tableName = removeMcPrefix($mold->table_name);
            $baseName = (string) ($mold->name ?? $tableName);
            $description = (string) ($mold->description ?? '');

            $pathParams = [
                'tableName' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => '内容模型表名，例如 '.$tableName,
                ],
            ];

            $commonQuery = [
                'page' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => '页码，默认 1',
                ],
                'page_size' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => '每页数量，默认 15',
                ],
            ];

            // 获取模型字段定义
            $fieldsArr = json_decode($mold->fields, true) ?: [];

            // 构建字段属性
            $fieldProperties = [
                'id' => ['type' => 'integer', 'description' => '记录ID'],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'description' => '创建时间'],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'description' => '更新时间'],
                'content_status' => ['type' => 'string', 'description' => '内容状态：pending(待发布)、published(已发布)、disabled(已下线)'],
            ];

            // 添加模型自定义字段
            foreach ($fieldsArr as $field) {
                if (isset($field['field']) && isset($field['label']) && isset($field['type'])) {
                    $fieldName = $field['field'];
                    $fieldType = $this->mapFieldTypeToJsonType($field['type']);
                    $fieldProperties[$fieldName] = [
                        'type' => $fieldType,
                        'description' => $field['label'].' ('.$field['type'].')',
                    ];

                    // 添加特定字段的格式信息
                    if ($field['type'] === 'dateTimePicker') {
                        $fieldProperties[$fieldName]['format'] = 'date-time';
                    } elseif ($field['type'] === 'datePicker') {
                        $fieldProperties[$fieldName]['format'] = 'date';
                    }
                }
            }

            $result[] = [
                'id' => 'content.list.'.$tableName,
                'kind' => 'content',
                'operation' => 'list',
                'name' => $baseName.' - 列表',
                'description' => $description,
                'http_method' => 'GET',
                'path' => '/open/content/list/{tableName}',
                'resolved_path' => "/open/content/list/{$tableName}",
                'path_params' => $pathParams,
                'query_params' => $commonQuery,
                'body_schema' => null,
                'response_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                        'message' => ['type' => 'string', 'description' => '提示信息'],
                        'data' => [
                            'type' => 'object',
                            'properties' => [
                                'total' => ['type' => 'integer', 'description' => '总记录数'],
                                'page' => ['type' => 'integer', 'description' => '当前页码'],
                                'page_size' => ['type' => 'integer', 'description' => '每页记录数'],
                                'data' => [
                                    'type' => 'array',
                                    'description' => '内容列表',
                                    'items' => [
                                        'type' => 'object',
                                        'description' => '内容项',
                                        'properties' => $fieldProperties,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'tags' => ['content', $tableName, 'list'],
            ];

            $result[] = [
                'id' => 'content.detail.'.$tableName,
                'kind' => 'content',
                'operation' => 'detail',
                'name' => $baseName.' - 详情',
                'description' => $description,
                'http_method' => 'GET',
                'path' => '/open/content/detail/{tableName}/{id}',
                'resolved_path' => "/open/content/detail/{$tableName}/{id}",
                'path_params' => $pathParams + [
                    'id' => [
                        'type' => 'integer',
                        'required' => true,
                        'description' => '内容 ID',
                    ],
                ],
                'query_params' => [],
                'body_schema' => null,
                'response_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                        'message' => ['type' => 'string', 'description' => '提示信息'],
                        'data' => [
                            'type' => 'object',
                            'description' => '内容详情',
                            'properties' => $fieldProperties,
                        ],
                    ],
                ],
                'tags' => ['content', $tableName, 'detail'],
            ];

            $result[] = [
                'id' => 'content.count.'.$tableName,
                'kind' => 'content',
                'operation' => 'count',
                'name' => $baseName.' - 计数',
                'description' => $description,
                'http_method' => 'GET',
                'path' => '/open/content/count/{tableName}',
                'resolved_path' => "/open/content/count/{$tableName}",
                'path_params' => $pathParams,
                'query_params' => [],
                'body_schema' => null,
                'response_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                        'message' => ['type' => 'string', 'description' => '提示信息'],
                        'data' => [
                            'type' => 'object',
                            'properties' => [
                                'count' => ['type' => 'integer', 'description' => '记录总数'],
                            ],
                        ],
                    ],
                ],
                'tags' => ['content', $tableName, 'count'],
            ];

            // 构建请求体字段属性（去除系统字段）
            $requestBodyProperties = [];
            foreach ($fieldsArr as $field) {
                if (isset($field['field']) && isset($field['label']) && isset($field['type'])) {
                    $fieldName = $field['field'];
                    // 排除系统字段
                    if (in_array($fieldName, ['id', 'created_at', 'updated_at', 'content_status', 'created_by', 'updated_by'])) {
                        continue;
                    }

                    $fieldType = $this->mapFieldTypeToJsonType($field['type']);
                    $requestBodyProperties[$fieldName] = [
                        'type' => $fieldType,
                        'description' => $field['label'].' ('.$field['type'].')',
                    ];

                    // 添加特定字段的格式信息
                    if ($field['type'] === 'dateTimePicker') {
                        $requestBodyProperties[$fieldName]['format'] = 'date-time';
                    } elseif ($field['type'] === 'datePicker') {
                        $requestBodyProperties[$fieldName]['format'] = 'date';
                    }
                }
            }

            $result[] = [
                'id' => 'content.create.'.$tableName,
                'kind' => 'content',
                'operation' => 'create',
                'name' => $baseName.' - 创建',
                'description' => $description,
                'http_method' => 'POST',
                'path' => '/open/content/create/{tableName}',
                'resolved_path' => "/open/content/create/{$tableName}",
                'path_params' => $pathParams,
                'query_params' => [],
                'body_schema' => [
                    'type' => 'object',
                    'description' => '根据模型字段提交 JSON 结构',
                    'properties' => $requestBodyProperties,
                ],
                'response_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                        'message' => ['type' => 'string', 'description' => '提示信息'],
                        'data' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer', 'description' => '新创建的内容ID'],
                            ],
                        ],
                    ],
                ],
                'tags' => ['content', $tableName, 'create'],
            ];

            $result[] = [
                'id' => 'content.update.'.$tableName,
                'kind' => 'content',
                'operation' => 'update',
                'name' => $baseName.' - 更新',
                'description' => $description,
                'http_method' => 'PUT',
                'path' => '/open/content/update/{tableName}/{id}',
                'resolved_path' => "/open/content/update/{$tableName}/{id}",
                'path_params' => $pathParams + [
                    'id' => [
                        'type' => 'integer',
                        'required' => true,
                        'description' => '内容 ID',
                    ],
                ],
                'query_params' => [],
                'body_schema' => [
                    'type' => 'object',
                    'description' => '要更新的字段 JSON 结构',
                    'properties' => $requestBodyProperties,
                ],
                'response_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                        'message' => ['type' => 'string', 'description' => '提示信息'],
                        'data' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer', 'description' => '更新的内容ID'],
                            ],
                        ],
                    ],
                ],
                'tags' => ['content', $tableName, 'update'],
            ];

            $result[] = [
                'id' => 'content.delete.'.$tableName,
                'kind' => 'content',
                'operation' => 'delete',
                'name' => $baseName.' - 删除',
                'description' => $description,
                'http_method' => 'DELETE',
                'path' => '/open/content/delete/{tableName}/{id}',
                'resolved_path' => "/open/content/delete/{$tableName}/{id}",
                'path_params' => $pathParams + [
                    'id' => [
                        'type' => 'integer',
                        'required' => true,
                        'description' => '内容 ID',
                    ],
                ],
                'query_params' => [],
                'body_schema' => null,
                'response_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                        'message' => ['type' => 'string', 'description' => '提示信息'],
                        'data' => [
                            'type' => 'object',
                            'properties' => [
                                'success' => ['type' => 'boolean', 'description' => '是否删除成功'],
                            ],
                        ],
                    ],
                ],
                'tags' => ['content', $tableName, 'delete'],
            ];
        }

        return $result;
    }

    private function buildPageEndpoints(string $prefix): array
    {
        $all = $this->moldRepository->getAllMold();
        $items = $all->where('mold_type', MoldRepository::SUBJECT_MOLD_TYPE);

        $result = [];
        foreach ($items as $mold) {
            $tableName = removeMcPrefix($mold->table_name);
            $baseName = (string) ($mold->name ?? $tableName);
            $description = (string) ($mold->description ?? '');

            $pathParams = [
                'tableName' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => '单页内容表名，例如 '.$tableName,
                ],
            ];

            $result[] = [
                'id' => 'page.detail.'.$tableName,
                'kind' => 'page',
                'operation' => 'detail',
                'name' => $baseName.' - 单页详情',
                'description' => $description,
                'http_method' => 'GET',
                'path' => '/open/page/detail/{tableName}',
                'resolved_path' => "/open/page/detail/{$tableName}",
                'path_params' => $pathParams,
                'query_params' => [],
                'body_schema' => null,
                'response_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                        'message' => ['type' => 'string', 'description' => '提示信息'],
                        'data' => [
                            'type' => 'object',
                            'description' => '单页内容',
                            'properties' => [
                                'id' => ['type' => 'integer', 'description' => '页面ID'],
                                'subject_content' => ['type' => 'object', 'description' => '页面内容JSON对象'],
                                'created_at' => ['type' => 'string', 'format' => 'date-time', 'description' => '创建时间'],
                                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'description' => '更新时间'],
                            ],
                        ],
                    ],
                ],
                'tags' => ['page', $tableName, 'detail'],
            ];

            $result[] = [
                'id' => 'page.update.'.$tableName,
                'kind' => 'page',
                'operation' => 'update',
                'name' => $baseName.' - 单页更新',
                'description' => $description,
                'http_method' => 'PUT',
                'path' => '/open/page/update/{tableName}',
                'resolved_path' => "/open/page/update/{$tableName}",
                'path_params' => $pathParams,
                'query_params' => [],
                'body_schema' => [
                    'type' => 'object',
                    'description' => 'subject_content 字段 JSON 结构',
                ],
                'response_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                        'message' => ['type' => 'string', 'description' => '提示信息'],
                        'data' => [
                            'type' => 'object',
                            'properties' => [
                                'success' => ['type' => 'boolean', 'description' => '是否更新成功'],
                                'id' => ['type' => 'integer', 'description' => '页面ID'],
                            ],
                        ],
                    ],
                ],
                'tags' => ['page', $tableName, 'update'],
            ];
        }

        return $result;
    }

    private function buildMediaEndpoints(string $prefix): array
    {
        $pathPrefix = '/open/media';

        $result = [];

        $result[] = [
            'id' => 'media.detail',
            'kind' => 'media',
            'operation' => 'detail',
            'name' => '媒体 - 详情',
            'description' => '通过 ID 获取媒体资源详情',
            'http_method' => 'GET',
            'path' => '/open/media/detail/{id}',
            'resolved_path' => $pathPrefix.'/detail/{id}',
            'path_params' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'description' => '媒体 ID',
                ],
            ],
            'query_params' => [],
            'body_schema' => null,
            'response_schema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                    'message' => ['type' => 'string', 'description' => '提示信息'],
                    'data' => [
                        'type' => 'object',
                        'description' => '媒体资源详情',
                        'properties' => [
                            'id' => ['type' => 'integer', 'description' => '媒体ID'],
                            'title' => ['type' => 'string', 'description' => '标题'],
                            'filename' => ['type' => 'string', 'description' => '文件名'],
                            'path' => ['type' => 'string', 'description' => '相对路径'],
                            'url' => ['type' => 'string', 'description' => '完整URL'],
                            'type' => ['type' => 'string', 'description' => '媒体类型'],
                            'mime_type' => ['type' => 'string', 'description' => 'MIME类型'],
                            'size' => ['type' => 'integer', 'description' => '文件大小（字节）'],
                            'folder_id' => ['type' => 'integer', 'description' => '文件夹ID'],
                            'tags' => ['type' => 'array', 'description' => '标签数组'],
                            'created_at' => ['type' => 'string', 'format' => 'date-time', 'description' => '创建时间'],
                            'updated_at' => ['type' => 'string', 'format' => 'date-time', 'description' => '更新时间'],
                        ],
                    ],
                ],
            ],
            'tags' => ['media', 'detail'],
        ];

        $result[] = [
            'id' => 'media.list',
            'kind' => 'media',
            'operation' => 'list',
            'name' => '媒体 - 列表',
            'description' => '按分页获取媒体资源列表，可按类型、文件夹等筛选',
            'http_method' => 'GET',
            'path' => '/open/media/list',
            'resolved_path' => $pathPrefix.'/list',
            'path_params' => [],
            'query_params' => [
                'page' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => '页码，默认 1',
                ],
                'page_size' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => '每页数量，默认 15，最大 100',
                ],
                'keyword' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => '按文件名等关键字搜索',
                ],
                'type' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => '媒体类型：image|video|audio|document',
                ],
                'folder_id' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => '文件夹 ID',
                ],
            ],
            'body_schema' => null,
            'response_schema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                    'message' => ['type' => 'string', 'description' => '提示信息'],
                    'data' => [
                        'type' => 'object',
                        'properties' => [
                            'total' => ['type' => 'integer', 'description' => '总记录数'],
                            'page' => ['type' => 'integer', 'description' => '当前页码'],
                            'page_size' => ['type' => 'integer', 'description' => '每页记录数'],
                            'items' => [
                                'type' => 'array',
                                'description' => '媒体列表',
                                'items' => [
                                    'type' => 'object',
                                    'description' => '媒体项',
                                    'properties' => [
                                        'id' => ['type' => 'integer', 'description' => '媒体ID'],
                                        'title' => ['type' => 'string', 'description' => '标题'],
                                        'url' => ['type' => 'string', 'description' => '完整URL'],
                                        'type' => ['type' => 'string', 'description' => '媒体类型'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'tags' => ['media', 'list'],
        ];

        $result[] = [
            'id' => 'media.by_tags',
            'kind' => 'media',
            'operation' => 'list',
            'name' => '媒体 - 按标签查询',
            'description' => '按标签获取媒体资源列表',
            'http_method' => 'GET',
            'path' => '/open/media/by-tags',
            'resolved_path' => $pathPrefix.'/by-tags',
            'path_params' => [],
            'query_params' => [
                'tags' => [
                    'type' => 'string|array',
                    'required' => true,
                    'description' => '标签数组或以逗号分隔的字符串',
                ],
                'type' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => '媒体类型：image|video|audio|document',
                ],
                'page' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => '页码，默认 1',
                ],
                'page_size' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => '每页数量，默认 15，最大 100',
                ],
            ],
            'body_schema' => null,
            'response_schema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                    'message' => ['type' => 'string', 'description' => '提示信息'],
                    'data' => [
                        'type' => 'object',
                        'properties' => [
                            'total' => ['type' => 'integer', 'description' => '总记录数'],
                            'page' => ['type' => 'integer', 'description' => '当前页码'],
                            'page_size' => ['type' => 'integer', 'description' => '每页记录数'],
                            'items' => [
                                'type' => 'array',
                                'description' => '媒体列表',
                                'items' => [
                                    'type' => 'object',
                                    'description' => '媒体项',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'tags' => ['media', 'list', 'tags'],
        ];

        $result[] = [
            'id' => 'media.by_folder',
            'kind' => 'media',
            'operation' => 'list',
            'name' => '媒体 - 按文件夹查询',
            'description' => '按文件夹获取媒体资源列表',
            'http_method' => 'GET',
            'path' => '/open/media/by-folder/{folderId}',
            'resolved_path' => $pathPrefix.'/by-folder/{folderId}',
            'path_params' => [
                'folderId' => [
                    'type' => 'integer',
                    'required' => true,
                    'description' => '文件夹 ID',
                ],
            ],
            'query_params' => [
                'type' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => '媒体类型：image|video|audio|document',
                ],
                'page' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => '页码，默认 1',
                ],
                'page_size' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => '每页数量，默认 15，最大 100',
                ],
            ],
            'body_schema' => null,
            'response_schema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                    'message' => ['type' => 'string', 'description' => '提示信息'],
                    'data' => [
                        'type' => 'object',
                        'properties' => [
                            'total' => ['type' => 'integer', 'description' => '总记录数'],
                            'page' => ['type' => 'integer', 'description' => '当前页码'],
                            'page_size' => ['type' => 'integer', 'description' => '每页记录数'],
                            'items' => [
                                'type' => 'array',
                                'description' => '媒体列表',
                                'items' => [
                                    'type' => 'object',
                                    'description' => '媒体项',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'tags' => ['media', 'list', 'folder'],
        ];

        $result[] = [
            'id' => 'media.search',
            'kind' => 'media',
            'operation' => 'list',
            'name' => '媒体 - 关键字搜索',
            'description' => '按关键字搜索媒体资源',
            'http_method' => 'GET',
            'path' => '/open/media/search',
            'resolved_path' => $pathPrefix.'/search',
            'path_params' => [],
            'query_params' => [
                'keyword' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => '搜索关键字',
                ],
                'type' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => '媒体类型：image|video|audio|document',
                ],
                'page' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => '页码，默认 1',
                ],
                'page_size' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => '每页数量，默认 15，最大 100',
                ],
            ],
            'body_schema' => null,
            'response_schema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                    'message' => ['type' => 'string', 'description' => '提示信息'],
                    'data' => [
                        'type' => 'object',
                        'properties' => [
                            'total' => ['type' => 'integer', 'description' => '总记录数'],
                            'page' => ['type' => 'integer', 'description' => '当前页码'],
                            'page_size' => ['type' => 'integer', 'description' => '每页记录数'],
                            'items' => [
                                'type' => 'array',
                                'description' => '媒体列表',
                                'items' => [
                                    'type' => 'object',
                                    'description' => '媒体项',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'tags' => ['media', 'list', 'search'],
        ];

        $result[] = [
            'id' => 'media.create',
            'kind' => 'media',
            'operation' => 'create',
            'name' => '媒体 - 上传',
            'description' => '上传新媒体资源',
            'http_method' => 'POST',
            'path' => '/open/media/create',
            'resolved_path' => $pathPrefix.'/create',
            'path_params' => [],
            'query_params' => [],
            'body_schema' => [
                'type' => 'object',
                'description' => 'multipart/form-data，包含 file、title、alt、description、tags、folder_id',
            ],
            'response_schema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                    'message' => ['type' => 'string', 'description' => '提示信息'],
                    'data' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'description' => '新创建的媒体ID'],
                            'url' => ['type' => 'string', 'description' => '媒体资源URL'],
                        ],
                    ],
                ],
            ],
            'tags' => ['media', 'create'],
        ];

        $result[] = [
            'id' => 'media.update',
            'kind' => 'media',
            'operation' => 'update',
            'name' => '媒体 - 更新',
            'description' => '更新媒体元信息（标题、描述、标签、文件夹等）',
            'http_method' => 'PUT',
            'path' => '/open/media/update/{id}',
            'resolved_path' => $pathPrefix.'/update/{id}',
            'path_params' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'description' => '媒体 ID',
                ],
            ],
            'query_params' => [],
            'body_schema' => [
                'type' => 'object',
                'description' => '包含 title、alt、description、tags、folder_id 等字段',
            ],
            'response_schema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                    'message' => ['type' => 'string', 'description' => '提示信息'],
                    'data' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'description' => '更新的媒体ID'],
                            'success' => ['type' => 'boolean', 'description' => '是否更新成功'],
                        ],
                    ],
                ],
            ],
            'tags' => ['media', 'update'],
        ];

        $result[] = [
            'id' => 'media.delete',
            'kind' => 'media',
            'operation' => 'delete',
            'name' => '媒体 - 删除',
            'description' => '按 ID 删除媒体资源',
            'http_method' => 'DELETE',
            'path' => '/open/media/delete/{id}',
            'resolved_path' => $pathPrefix.'/delete/{id}',
            'path_params' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'description' => '媒体 ID',
                ],
            ],
            'query_params' => [],
            'body_schema' => null,
            'response_schema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                    'message' => ['type' => 'string', 'description' => '提示信息'],
                    'data' => [
                        'type' => 'object',
                        'properties' => [
                            'success' => ['type' => 'boolean', 'description' => '是否删除成功'],
                        ],
                    ],
                ],
            ],
            'tags' => ['media', 'delete'],
        ];

        return $result;
    }

    private function projectAuthEnabled(): bool
    {
        try {
            return (bool) ((app(ProjectConfigService::class)->getConfig()['auth']['enabled'] ?? false));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function buildAuthEndpoints(string $prefix): array
    {
        $base = '/open/auth/'.$prefix;
        $commonResponse = [
            'type' => 'object',
            'properties' => [
                'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                'message' => ['type' => 'string', 'description' => '提示信息'],
                'data' => ['type' => 'object', 'description' => '响应数据'],
            ],
        ];

        return [
            [
                'id' => 'auth.login',
                'kind' => 'auth',
                'operation' => 'login',
                'name' => '项目用户 - 登录',
                'description' => '项目用户登录，成功后返回 pu_* 登录态 token',
                'http_method' => 'POST',
                'path' => '/open/auth/{projectPrefix}/login',
                'resolved_path' => $base.'/login',
                'path_params' => ['projectPrefix' => ['type' => 'string', 'required' => true, 'description' => '项目前缀']],
                'query_params' => [],
                'body_schema' => [
                    'type' => 'object',
                    'required' => ['account', 'password'],
                    'properties' => [
                        'account' => ['type' => 'string', 'description' => '邮箱、用户名或手机号'],
                        'password' => ['type' => 'string', 'description' => '密码'],
                    ],
                ],
                'response_schema' => $commonResponse,
                'tags' => ['auth', 'project_user', 'login'],
            ],
            [
                'id' => 'auth.register',
                'kind' => 'auth',
                'operation' => 'register',
                'name' => '项目用户 - 注册',
                'description' => '项目用户公开注册；需项目配置开启允许公开注册',
                'http_method' => 'POST',
                'path' => '/open/auth/{projectPrefix}/register',
                'resolved_path' => $base.'/register',
                'path_params' => ['projectPrefix' => ['type' => 'string', 'required' => true, 'description' => '项目前缀']],
                'query_params' => [],
                'body_schema' => [
                    'type' => 'object',
                    'required' => ['password'],
                    'properties' => [
                        'email' => ['type' => 'string', 'description' => '邮箱，和 username 至少填一项'],
                        'username' => ['type' => 'string', 'description' => '用户名，和 email 至少填一项'],
                        'name' => ['type' => 'string', 'description' => '显示名称'],
                        'password' => ['type' => 'string', 'description' => '密码'],
                    ],
                ],
                'response_schema' => $commonResponse,
                'tags' => ['auth', 'project_user', 'register'],
            ],
            [
                'id' => 'auth.me',
                'kind' => 'auth',
                'operation' => 'me',
                'name' => '项目用户 - 当前用户',
                'description' => '通过 pu_* 登录态获取当前项目用户信息',
                'http_method' => 'GET',
                'path' => '/open/auth/{projectPrefix}/me',
                'resolved_path' => $base.'/me',
                'path_params' => ['projectPrefix' => ['type' => 'string', 'required' => true, 'description' => '项目前缀']],
                'query_params' => [],
                'body_schema' => null,
                'response_schema' => $commonResponse,
                'tags' => ['auth', 'project_user', 'me'],
            ],
            [
                'id' => 'auth.logout',
                'kind' => 'auth',
                'operation' => 'logout',
                'name' => '项目用户 - 退出登录',
                'description' => '撤销当前 pu_* 登录态',
                'http_method' => 'POST',
                'path' => '/open/auth/{projectPrefix}/logout',
                'resolved_path' => $base.'/logout',
                'path_params' => ['projectPrefix' => ['type' => 'string', 'required' => true, 'description' => '项目前缀']],
                'query_params' => [],
                'body_schema' => null,
                'response_schema' => $commonResponse,
                'tags' => ['auth', 'project_user', 'logout'],
            ],
        ];
    }

    private function buildFunctionEndpoints(string $prefix): array
    {
        $functions = ProjectFunction::query()
            ->where('type', 'endpoint')
            ->where('enabled', 1)
            ->orderBy('name')
            ->get();

        $result = [];
        foreach ($functions as $fn) {
            $slug = (string) $fn->slug;
            if ($slug === '') {
                continue;
            }

            $pathTemplate = '/open/func/{slug}';
            $resolved = "/open/func/{$slug}";
            $name = (string) ($fn->name ?? $slug);
            $description = (string) ($fn->remark ?? '自定义 Web 函数');
            $method = strtoupper((string) ($fn->http_method ?? 'POST'));

            $bodySchema = null;
            if (is_array($fn->input_schema)) {
                $bodySchema = $fn->input_schema;
            }

            $responseSchema = [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'integer', 'description' => '状态码，200表示成功'],
                    'message' => ['type' => 'string', 'description' => '提示信息'],
                    'data' => [
                        'type' => 'object',
                        'description' => '函数返回数据',
                    ],
                ],
            ];

            if (is_array($fn->output_schema)) {
                $responseSchema['properties']['data'] = $fn->output_schema;
            }

            $result[] = [
                'id' => 'function.endpoint.'.$slug,
                'kind' => 'function',
                'operation' => 'invoke',
                'name' => $name,
                'description' => $description,
                'http_method' => $method,
                'path' => $pathTemplate,
                'resolved_path' => $resolved,
                'path_params' => [
                    'slug' => [
                        'type' => 'string',
                        'required' => true,
                        'description' => '函数 slug 标识，例如 '.$slug,
                    ],
                ],
                'query_params' => [],
                'body_schema' => $bodySchema,
                'response_schema' => $responseSchema,
                'tags' => ['function', 'endpoint'],
            ];
        }

        return $result;
    }
}
