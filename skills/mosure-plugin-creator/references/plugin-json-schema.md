# plugin.json 字段说明

## 完整示例

```json
{
  "id": "todolist_v1",
  "name": "代办清单",
  "description": "好看简单的代办清单",
  "author": "Gibson",
  "version": "v1",
  "has_frontend": true,
  "has_src": false,
  "api_scopes": ["content.read", "content.write", "page.read", "page.write", "media.read"],
  "homepage": "https://gitee.com/gibson_0822/mosure_plugins/edit/master/todolist/",
  "tags": ["演示", "示例", "教程"],
  "snapshot": true,
  "provides": {
    "models": ["statistics", "todo"],
    "functions": {
      "endpoints": ["day_undo"],
      "hooks": ["end_time", "send_msg"],
      "variables": true,
      "triggers": true,
      "schedules": true
    },
    "data": ["todo"],
    "menus": ["menu_a9a4ecab"]
  }
}
```

## 字段详解

### 必填字段

| 字段 | 类型 | 说明 | 示例 |
|------|------|------|------|
| `id` | string | 插件唯一标识，新项目统一使用 `{plugin_id}_{version}` | `"todolist_v1"` |
| `name` | string | 插件显示名称 | `"代办清单"` |
| `description` | string | 插件功能描述 | `"好看简单的代办清单"` |
| `author` | string | 作者名称 | `"Gibson"` |
| `version` | string | 版本号，格式 `v{n}` | `"v1"` |
| `provides` | object | 插件提供的资源声明 | 见下方 |

### 可选字段

| 字段 | 类型 | 说明 | 默认值 |
|------|------|------|--------|
| `has_frontend` | boolean | 是否包含前端页面 | `false` |
| `has_src` | boolean | 是否包含 PHP 源码目录 | `false` |
| `api_scopes` | array | 插件前端自动生成 API Key 的权限声明 | 只读权限 |
| `homepage` | string | 插件主页 URL | - |
| `tags` | array | 标签列表 | `[]` |
| `snapshot` | boolean | 是否包含快照数据 | `false` |

### plugin.json.api_scopes

- 类型：`string[]`
- 可选
- 用于声明插件安装时自动生成的前端 API Key 权限
- 未声明时系统默认仅授予：
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

### provides 字段结构

```json
{
  "provides": {
    "models": ["model1", "model2"],
    "functions": {
      "endpoints": ["endpoint1"],
      "hooks": ["hook1"],
      "variables": true,
      "triggers": true,
      "schedules": true
    },
    "data": ["model1"],
    "menus": ["menu1"]
  }
}
```

#### provides.models

- **类型**：`string[]`
- **说明**：内容模型名称列表（不含 `.json` 后缀）
- **示例**：`["todo", "statistics"]`
- **文件位置**：`models/{name}.json`

#### provides.functions

| 子字段 | 类型 | 说明 |
|--------|------|------|
| `endpoints` | string[] | Web API 函数列表 |
| `hooks` | string[] | 触发函数列表 |
| `variables` | boolean | 是否包含环境变量 |
| `triggers` | boolean | 是否包含触发器配置 |
| `schedules` | boolean | 是否包含定时任务 |

#### provides.data

- **类型**：`string[]`
- **说明**：初始数据对应的模型名称
- **文件位置**：`data/{model_name}/`

#### provides.menus

- **类型**：`string[]`
- **说明**：菜单配置 ID 列表
- **文件位置**：`menus/{menu_id}.json`

## 命名规范

### 插件目录名

- 必须以小写字母开头
- 只能包含小写字母、数字、连字符
- 长度建议 3-30 字符

```
✅ 正确: my-plugin, blog, article-manager
❌ 错误: MyPlugin, 123plugin, my_plugin
```

### plugin.json.id

- 新项目统一使用 `{plugin_id}_{version}`
- 其中 `plugin_id` 对应插件目录名，`version` 对应版本目录

```text
目录: Plugins/todolist/v1/
id: todolist_v1
```

### 版本号

- 格式：`v{n}`，n 为数字
- 主版本递增表示不兼容更新

```
✅ 正确: v1, v2, v10
❌ 错误: 1.0.0, V1, version1
```

## 目录映射

| provides 字段 | 文件位置 |
|---------------|----------|
| `models: ["todo"]` | `models/todo.json` |
| `functions.endpoints: ["day_undo"]` | `functions/endpoints/day_undo.json` |
| `functions.hooks: ["end_time"]` | `functions/hooks/end_time.json` |
| `functions.variables: true` | `functions/variables.json` |
| `functions.triggers: true` | `functions/triggers.json` |
| `functions.schedules: true` | `functions/schedules.json` |
| `data: ["todo"]` | `data/todo/*.json` |
