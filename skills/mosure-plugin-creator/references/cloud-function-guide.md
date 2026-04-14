# 云函数开发指南

## 概述

Mosure 云函数分为两类：
- **Endpoint（Web API）**：通过 HTTP 请求调用的函数
- **Hook（触发函数）**：由系统事件自动触发的函数

## 可用变量

在云函数代码中，可以使用以下预定义变量：

| 变量 | 类型 | 说明 |
|------|------|------|
| `$db` | object | 数据库操作对象 |
| `$prefix` | string | 项目表前缀 |
| `$payload` | array | 请求参数/触发数据 |
| `$env` | array | 环境变量（从 variables.json） |
| `$user` | object | 当前用户信息 |
| `Log` | class | 日志类 |

## 数据库操作

### 查询

```php
// 基础查询
$results = $db->query($prefix . '_mc_todo')
    ->where('completed', 0)
    ->get();

// 条件查询
$results = $db->query($tableName)
    ->where('status', 'active')
    ->where('created_at', '>', '2024-01-01')
    ->get();

// 复杂查询
$results = $db->query($tableName)
    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
    ->where('completed', 0)
    ->whereBetween('created_at', [$startDay, $endDay])
    ->groupByRaw('DATE(created_at)')
    ->orderBy('date', 'asc')
    ->get();

// 单条查询
$item = $db->query($tableName)->where('id', 1)->first();
```

### 插入

```php
$id = $db->insert($prefix . '_mc_todo', [
    'title' => '新任务',
    'completed' => 0,
    'created_at' => date('Y-m-d H:i:s')
]);
```

### 更新

```php
$affected = $db->update($prefix . '_mc_todo', 
    ['completed' => 1, 'finish_time' => date('Y-m-d H:i:s')], 
    ['id' => $id]
);
```

### 删除

```php
$affected = $db->delete($prefix . '_mc_todo', ['id' => $id]);
```

---

## Endpoint（Web API）函数

### JSON 定义

```json
{
    "id": 1,
    "name": "每日未完成数量",
    "slug": "day_undo",
    "type": "endpoint",
    "enabled": true,
    "runtime": "php",
    "code": "<?php ...",
    "timeout_ms": 5000,
    "max_mem_mb": 128,
    "http_method": "POST",
    "input_schema": {
        "type": "object",
        "required": [],
        "properties": {
            "start_day": {
                "type": "string",
                "description": "查询开始日期"
            },
            "end_day": {
                "type": "string",
                "description": "查询结束日期"
            }
        }
    },
    "remark": "统计某段时间未完成数量"
}
```

### 代码示例

```php
<?php
// 接收参数
$startDay = $payload['start_day'] ?? null;
$endDay = $payload['end_day'] ?? null;

// 参数验证
if (!$startDay || !$endDay) {
    return [
        'code' => 400,
        'message' => '缺少必要参数：start_day 和 end_day'
    ];
}

// 表名（带项目前缀）
$tableName = $prefix . '_mc_todo';

// 查询
$results = $db->query($tableName)
    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
    ->where('completed', 0)
    ->whereBetween('created_at', [$startDay . ' 00:00:00', $endDay . ' 23:59:59'])
    ->groupByRaw('DATE(created_at)')
    ->orderBy('date', 'asc')
    ->get();

// 返回结果
return [
    'data' => $results,
    'summary' => [
        'start_day' => $startDay,
        'end_day' => $endDay,
        'total' => count($results)
    ]
];
```

### 必填字段

| 字段 | 说明 |
|------|------|
| `name` | 函数名称 |
| `slug` | 函数标识（URL 路径） |
| `type` | 固定为 `endpoint` |
| `runtime` | 固定为 `php` |
| `code` | PHP 代码 |
| `http_method` | HTTP 方法（GET/POST） |

---

## Hook（触发函数）

### JSON 定义

```json
{
    "id": 2,
    "name": "记录第一次完成时间",
    "slug": "end_time",
    "type": "hook",
    "enabled": true,
    "runtime": "php",
    "code": "<?php ...",
    "timeout_ms": 5000,
    "remark": "记录todo的第一次完成时间"
}
```

### $payload 结构

```php
$payload = [
    'mold_id' => 3,          // 模型 ID
    'id' => 2,               // 内容 ID
    'before' => [            // 更新前的数据
        'id' => 2,
        'title' => '吃饭',
        'completed' => 0,
        'finish_time' => null
    ],
    'after' => [             // 更新后的数据
        'id' => 2,
        'title' => '吃饭',
        'completed' => 1,
        'finish_time' => null
    ]
];
```

### 代码示例

```php
<?php
// 获取前后数据
$before = $payload['before'] ?? [];
$after = $payload['after'] ?? [];

// 检查字段变化
$oldCompleted = (int)($before['completed'] ?? 0);
$newCompleted = (int)($after['completed'] ?? 0);

// 当 completed 从 0 变为 1 时
if ($oldCompleted === 0 && $newCompleted === 1) {
    // 第一次完成，记录时间
    if (empty($after['finish_time'])) {
        $id = (int)($after['id'] ?? 0);
        $db->update($prefix . '_mc_todo', [
            'finish_time' => date('Y-m-d H:i:s')
        ], ['id' => $id]);
    }
}

return ['ok' => true];
```

---

## 环境变量 (variables.json)

```json
[
    {
        "name": "offset_time",
        "value": "10",
        "remark": "完成时间偏移（分钟）"
    },
    {
        "name": "to_email",
        "value": "admin@example.com",
        "remark": "通知邮件"
    }
]
```

在代码中使用：

```php
$offsetTime = $env['offset_time'] ?? 0;
$toEmail = $env['to_email'] ?? '';
```

---

## 触发器配置 (triggers.json)

```json
[
    {
        "id": 1,
        "name": "记录第一次完成时间",
        "enabled": true,
        "trigger_type": "content_model",
        "events": ["after_update"],
        "action_function_slug": "end_time",
        "action_mold_slug": "todo",
        "input_schema": {
            "required": ["title", "completed", "finish_time"]
        },
        "remark": "当 todo 更新时触发"
    }
]
```

### 触发器事件

| 事件 | 说明 |
|------|------|
| `before_create` | 创建前 |
| `after_create` | 创建后 |
| `before_update` | 更新前 |
| `after_update` | 更新后 |
| `before_delete` | 删除前 |
| `after_delete` | 删除后 |

---

## 定时任务 (schedules.json)

```json
[
    {
        "id": 1,
        "name": "每日邮件通知",
        "enabled": true,
        "function_slug": "send_msg",
        "schedule_type": "cron",
        "cron_expr": "30 7 * * *",
        "timezone": "Asia/Shanghai",
        "payload": [],
        "remark": "每天早上7点半发送邮件"
    }
]
```

### Cron 表达式

```
┌───────────── 分钟 (0 - 59)
│ ┌───────────── 小时 (0 - 23)
│ │ ┌───────────── 日期 (1 - 31)
│ │ │ ┌───────────── 月份 (1 - 12)
│ │ │ │ ┌───────────── 星期几 (0 - 6) (周日=0)
│ │ │ │ │
* * * * *
```

常用示例：
- `0 * * * *` - 每小时
- `0 0 * * *` - 每天零点
- `0 9 * * 1` - 每周一早9点
- `*/5 * * * *` - 每5分钟
