# 最小完整示例：todolist

这个示例只保留当前 `Mosure` 插件创建最有参考价值的部分：

- 版本化目录结构
- `plugin.json` 最小可用写法
- 一个模型
- 一个 endpoint
- 一个 hook
- 一个菜单

不要把它当成业务功能模板；它的作用是帮助大模型快速复用正确结构。

## 目录结构

```text
Plugins/todolist/v1/
├── plugin.json
├── todolist.php
├── README.md
├── models/
│   └── todo.json
├── functions/
│   ├── endpoints/
│   │   └── todo_stats.json
│   ├── hooks/
│   │   └── todo_after_save.json
│   ├── variables.json
│   ├── triggers.json
│   └── schedules.json
└── menus/
    └── todo_menu.json
```

## plugin.json

```json
{
  "id": "todolist_v1",
  "name": "待办清单",
  "description": "提供待办事项的模型、函数和菜单",
  "author": "Mosure Team",
  "version": "v1",
  "has_frontend": false,
  "has_src": false,
  "api_scopes": ["content.read", "content.write", "page.read", "page.write", "media.read"],
  "provides": {
    "models": ["todo"],
    "functions": {
      "endpoints": ["todo_stats"],
      "hooks": ["todo_after_save"],
      "variables": true,
      "triggers": false,
      "schedules": false
    },
    "data": [],
    "menus": ["todo_menu"]
  }
}
```

规则：

- `id` 使用 `{plugin_id}_{version}`
- `version` 与目录一致，例如 `v1`
- `provides` 要与真实文件保持一致

## 主类

```php
<?php

namespace Plugins\todolist\v1;

use Plugins\AbstractPlugin;

class todolist extends AbstractPlugin
{
}
```

规则：

- 文件名推荐与目录名一致
- 命名空间使用 `Plugins\{plugin_id}\{version}`
- 最小插件不必重写 `install()`，直接继承即可

## 模型示例

`models/todo.json`

```json
{
  "name": "待办事项",
  "table_name": "todo",
  "mold_type": "list",
  "fields": [
    {
      "id": "title",
      "type": "input",
      "field": "title",
      "label": "标题"
    }
  ],
  "settings": [],
  "list_show_fields": ["title"]
}
```

## endpoint 示例

`functions/endpoints/todo_stats.json`

```json
{
  "name": "待办统计",
  "slug": "todo_stats",
  "type": "endpoint",
  "enabled": true,
  "runtime": "php",
  "http_method": "POST",
  "timeout_ms": 5000,
  "input_schema": {
    "type": "object",
    "properties": {}
  },
  "code": "<?php\nreturn ['count' => 0];"
}
```

## hook 示例

`functions/hooks/todo_after_save.json`

```json
{
  "name": "待办保存后处理",
  "slug": "todo_after_save",
  "type": "hook",
  "enabled": true,
  "runtime": "php",
  "code": "<?php\n$after = $payload['after'] ?? [];\nreturn $after;"
}
```

## 菜单示例

`menus/todo_menu.json`

```json
{
  "name": "待办管理",
  "slug": "todo_menu",
  "icon": "ListChecks",
  "sort": 100,
  "target_type": "group",
  "children": [
    {
      "name": "待办事项",
      "slug": "todo_list",
      "icon": "CheckSquare",
      "sort": 10,
      "target_type": "mold_list",
      "target_payload": {
        "mold_slug": "todo"
      }
    }
  ]
}
```

规则：

- 根菜单默认 `target_type: "group"`
- 子菜单访问模型时用 `mold_slug`，不要手写 `mold_id`

## 辅助文件

`functions/variables.json`

```json
[]
```

`functions/triggers.json`

```json
[]
```

`functions/schedules.json`

```json
[]
```

## 生成时的最小判断

如果需求只是“做一个可管理的待办插件”，通常至少应生成：

- `plugin.json`
- 主类
- 一个模型
- 一个菜单

只有在用户明确需要 API、自动化或事件逻辑时，才继续补：

- endpoint
- hook
- trigger
- schedule
- frontend
- src
