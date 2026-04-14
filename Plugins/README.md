# Mosure 插件系统

## 当前规范

本项目的新插件以当前代码逻辑为准，目录和配置统一采用以下约定：

```text
Plugins/{plugin_id}/v1/
├── plugin.json
├── {plugin_id}.php
├── README.md
├── models/
├── functions/
│   ├── endpoints/
│   ├── hooks/
│   ├── variables.json
│   ├── triggers.json
│   └── schedules.json
├── menus/
├── data/
├── frontend/
└── src/
```

## 强约束

### 1. 版本目录

- 版本目录必须使用 `v1`、`v2` 这种格式

### 2. plugin.json 基本字段

最少必须包含：

- `id`
- `name`
- `version`

如果插件前端 `config.js` 中会使用 `{$apiKey}` 占位符，建议同时声明：

- `api_scopes`

### 3. plugin.json.id 规范

新项目统一要求：

```json
"id": "{plugin_id}_{version}"
```

示例：

```json
"id": "blog_v1"
```

如果插件目录是：

```text
Plugins/blog/v1/
```

那么 `plugin.json` 中必须写：

```json
{
  "id": "blog_v1",
  "version": "v1"
}
```

当前代码在加载插件时会校验这条规则，不符合将拒绝加载。

## 安装器实际识别的路径

插件安装时实际扫描这些位置：

- `models/*.json`
- `functions/endpoints/*.json`
- `functions/hooks/*.json`
- `functions/variables.json`
- `functions/triggers.json`
- `functions/schedules.json`
- `menus/*.json`
- `frontend/dist/*`

以下旧路径不要再使用：

- `functions/web`
- `functions/triggers/` 作为 Hook 目录
- `config/variables.json`
- `config/triggers.json`
- `config/schedules.json`

## 推荐的 plugin.json

```json
{
  "id": "blog_v1",
  "name": "博客插件",
  "description": "提供博客相关模型、函数和菜单",
  "author": "Mosure Team",
  "version": "v1",
  "has_frontend": false,
  "has_src": false,
  "api_scopes": ["content.read", "page.read", "media.read"],
  "provides": {
    "models": [],
    "functions": {
      "endpoints": [],
      "hooks": [],
      "variables": false,
      "triggers": false,
      "schedules": false
    },
    "data": [],
    "menus": []
  }
}
```

### `api_scopes` 说明

- 类型：`string[]`
- 可选
- 用于声明插件安装时自动生成的前端 API Key 权限
- 未声明时默认只读：
  - `content.read`
  - `page.read`
  - `media.read`
- 可选值：
  - `content.read`
  - `content.write`
  - `page.read`
  - `page.write`
  - `media.read`
  - `media.write`
  - `function.invoke`

## 创建方式

优先使用：

```bash
bin/create-plugin.sh
```

它会生成与当前 `PluginService` 兼容的目录结构和 `plugin.json` 初始内容。
