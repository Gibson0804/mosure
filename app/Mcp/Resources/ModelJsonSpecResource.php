<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Server\Resource;

class ModelJsonSpecResource extends Resource
{
    protected string $description = 'Mosure 模型 JSON 结构规范与示例，用于指导 MCP 调用模型相关工具时构造参数。';

    public function read(): string
    {
        return <<<'MD'
# Mosure 模型 JSON 规范

本规范用于说明通过 MCP 使用 `CreateModelTool`、`UpdateModelTool`、`ValidateModelDefinitionTool` 时，模型 JSON 的推荐结构和约定。

## 1. 顶层结构

一个完整的模型定义推荐包含以下字段：

```json
{
  "name": "博客文章",
  "table_name": "article",
  "mold_type": "content",
  "fields": [
    {
      "field": "title",
      "label": "标题",
      "type": "string"
    }
  ]
}
```

- `name` *(string)*：模型名称（中文名），会显示在管理界面中。
- `table_name` *(string)*：**短表名标识**，不包含项目前缀，只能包含字母、数字和下划线，必须以字母开头，例如：`article`。
  - 实际物理表名会在服务端自动拼接当前项目前缀和内容前缀，例如：`demo_mc_article`。
- `mold_type` *(string，可选)*：模型类型，常见值为 `content`。
- `fields` *(array)*：字段定义数组，至少包含一个字段。

## 2. 字段定义 `fields[]`

`fields` 是一个数组，每个元素代表一个字段定义，推荐结构如下：

```json
{
  "field": "title",
  "label": "标题",
  "type": "string"
}
```

- `field` *(string, 必填)*：字段名，只能包含字母、数字和下划线，必须以字母开头。
  - **禁止使用系统保留字段名**：`id`, `created_at`, `updated_at`, `deleted_at`, `updated_by`, `created_by`, `content_status`。
- `label` *(string, 推荐)*：字段在界面上的展示名称（中文标题）。
- `type` *(string, 推荐)*：字段类型，对应数据库字段类型和后台逻辑。
- 其余字段（如 `required`、`default`、`hint`、`options` 等）为可选，自定义配置，服务端会作为 JSON 存储或忽略，不参与建表逻辑，由前端或插件自行约定使用。

### 2.1 支持的字段类型

下列类型与系统内置的建表逻辑直接对应：

- 文本类：`text`, `textarea`, `richText`
- 数字类：`number`, `int`, `integer`, `float`, `decimal`, `double`
- 布尔：`boolean`
- 时间日期：`date`, `datetime`, `timestamp`
- 结构化：`json`, `array`
- 选择类：`select`, `check_box`, `radio`
- 字符串：`string`

> 若使用其他类型，`ValidateModelDefinitionTool` 可能返回 **warning**，实际建表时通常会按字符串类型处理。

## 3. 命名规则

### 3.1 模型表名标识 `table_name`

- 仅使用：字母、数字、下划线 (`[a-zA-Z][a-zA-Z0-9_]*`)。
- 不包含项目前缀和内容前缀，由服务端自动拼接。
- 建议简短、有语义，例如：`article`, `page`, `product`。

### 3.2 字段名 `field`

- 仅使用：字母、数字、下划线 (`[a-zA-Z][a-zA-Z0-9_]*`)。
- 避免与系统保留字段冲突（见上文）。
- 建议英文小写加下划线，例如：`title`, `cover_image`, `published_at`。

## 4. 示例：博客文章模型

```json
{
  "name": "博客文章",
  "table_name": "article",
  "mold_type": "content",
  "fields": [
    {
      "field": "title",
      "label": "标题",
      "type": "string"
    },
    {
      "field": "slug",
      "label": "URL 标识",
      "type": "string"
    },
    {
      "field": "content",
      "label": "正文",
      "type": "richText"
    },
    {
      "field": "cover_image",
      "label": "封面图",
      "type": "string"
    },
    {
      "field": "published_at",
      "label": "发布时间",
      "type": "datetime"
    }
  ]
}
```

## 5. 与 MCP 工具的关系

- `CreateModelTool`
  - 需要：`name`, `table_name`, `fields`；`mold_type` 可选。
  - 建议在调用前，先使用 `ValidateModelDefinitionTool` 校验模型定义。
- `UpdateModelTool`
  - 通过 `mold_id` 或 `model_key` 定位模型，携带要更新的字段（例如新的 `fields` 数组）。
  - 更新前同样可以先调用 `ValidateModelDefinitionTool`，查看 `field_diff` 中的字段新增/删除/变更摘要。
- `ValidateModelDefinitionTool`
  - 按本规范检查命名、保留字段、字段类型等，返回 `errors` 和 `warnings`，以及在更新场景下的字段差异信息。

该文档可作为 MCP 客户端和 LLM 在构造模型 JSON 时的权威参考。
MD;
    }

    public function mimeType(): string
    {
        return 'text/markdown';
    }

    public function uri(): string
    {
        return 'file://resources/mosure-model-json-spec.md';
    }
}
