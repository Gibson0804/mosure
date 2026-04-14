# Mosure Plugin Creator Skill

## 概述

此 Skill 用于为 `Mosure` 创建能被当前代码真实发现、加载和安装的插件。

优先级规则：

- 以 `PluginService`、`AbstractPlugin`、安装流程的真实代码行为为准
- 新插件默认使用版本化目录结构

## 何时使用

当用户请求：

- 创建 Mosure 插件
- 生成 Mosure 插件脚手架
- 为 Mosure 项目扩展模型、函数、菜单、定时任务
- 查看当前 Mosure 插件结构规范

## 必须遵守的代码事实

### 1. 插件目录发现规则

`PluginService::discover()` 会扫描 `Plugins/*`。

当前代码可扫描多种目录结构，但新插件默认必须生成：

```text
Plugins/{plugin_id}/v1/
```

### 2. 插件类加载规则

`PluginService::getPluginClass()` 会尝试以下类名：

- `Plugins\{plugin}\{version}\{plugin}`
- `Plugins\{plugin}\{version}\{Plugin}`
- `Plugins\{plugin}\{version}\{Plugin}Plugin`

如果都没命中，还会扫描该目录下的 PHP 文件。

推荐生成：

- 文件名：`{plugin_id}.php`
- 类名：`{plugin_id}`
- 命名空间：`Plugins\{plugin_id}\v1`

### 3. plugin.json 的实际要求

`AbstractPlugin` 只会直接读取插件目录下的 `plugin.json`。

当前代码真正硬性依赖的字段只有：

- `id`
- `name`
- `version`

其他字段如 `description`、`author`、`has_frontend`、`has_src`、`provides` 都属于推荐字段。

### 4. 函数和资源的真实读取路径

安装器实际读取这些路径：

- 模型：`models/*.json`
- Endpoint：`functions/endpoints/*.json`
- Hook：`functions/hooks/*.json`
- 变量：`functions/variables.json`
- 触发器：`functions/triggers.json`
- 定时任务：`functions/schedules.json`
- 菜单：`menus/*.json`
- 前端：`frontend/dist/*`
- 源码目录：`src/`，仅在 `has_src=true` 时有意义

不要生成这些路径：

- `functions/web`
- `functions/triggers/` 作为 Hook JSON 目录
- `config/variables.json`
- `config/triggers.json`
- `config/schedules.json`

### 5. provides 的真实地位

`provides` 是插件声明性元数据，不是安装器的唯一驱动来源。

当前安装逻辑主要依赖目录扫描：

- `installModels()` 扫 `models/`
- `installFunctions()` 扫 `functions/endpoints` 和 `functions/hooks`
- `installVariables()` 读 `functions/variables.json`
- `installSchedules()` 读 `functions/schedules.json`
- `installTriggers()` 读 `functions/triggers.json`
- `installMenus()` 扫 `menus/`

所以 `provides` 必须和真实文件保持一致，但不能替代真实文件。

## 推荐目录结构

```text
Plugins/{plugin_id}/v1/
├── plugin.json
├── {plugin_id}.php
├── README.md
├── models/
│   └── {model}.json
├── functions/
│   ├── endpoints/
│   │   └── {endpoint}.json
│   ├── hooks/
│   │   └── {hook}.json
│   ├── variables.json
│   ├── triggers.json
│   └── schedules.json
├── menus/
│   └── {menu}.json
├── data/
│   └── {model}/
│       └── 1.json
├── frontend/
│   ├── manifest.json
│   └── dist/
└── src/
```

说明：

- `frontend/` 仅在用户明确需要前端时创建
- `src/` 仅在插件需要额外 PHP 源文件时创建
- 默认骨架不强制创建模型、菜单、前端示例文件

## 推荐生成规则

### 插件目录名

- 只使用小写字母和数字，且必须以小写字母开头
- 不要使用连字符、下划线、大写字母
- 原因：目录名会直接参与 PHP 命名空间和类名解析，连字符不安全
- 示例：`blog`、`todolist`、`companysite`

### 版本

- 默认 `v1`
- 版本目录直接使用 `v1`、`v2`

### plugin.json.id

新插件统一使用：

```json
"id": "{plugin_id}_{version}"
```

示例：

```json
"id": "blog_v1"
```

### 插件主类

推荐最小实现：

```php
<?php

namespace Plugins\blog\v1;

use Plugins\AbstractPlugin;
use Illuminate\Support\Facades\Log;

class blog extends AbstractPlugin
{
    public function install(string $projectPrefix): bool
    {
        Log::info("blog_v1 installing for {$projectPrefix}");
        return parent::install($projectPrefix);
    }
}
```

## 推荐的 plugin.json 模板

```json
{
  "id": "blog_v1",
  "name": "博客插件",
  "description": "提供博客相关模型、函数和菜单",
  "author": "Mosure Team",
  "version": "v1",
  "has_frontend": false,
  "has_src": false,
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

规则：

- `provides.models` 写模型名，不写文件路径
- `provides.functions.endpoints` 写 endpoint slug
- `provides.functions.hooks` 写 hook slug
- `variables/triggers/schedules` 用布尔值表示是否存在对应 JSON 文件

## 资源生成细则

### 模型生成细则

生成模型时，优先先判断是 `list`、`single` 还是 `tree`：

- `list`：新闻、产品、案例、职位、留言、下载、轮播图等多条数据
- `single`：关于我们、公司介绍、联系方式、SEO 配置等单页内容
- `tree`：栏目、分类、组织架构、导航层级等树状数据

生成模型时遵循这些规则：

- `table_name` 使用短 slug，只含小写字母和下划线
- 不要写项目表前缀，系统会在安装时拼接真实表名
- 每个模型至少给出 1 个核心字段和 `list_show_fields`
- 如果模型用于后台列表，`list_show_fields` 至少要覆盖标题、状态、时间中的关键信息
- 如果模型要被菜单访问，菜单里的 `mold_slug` 应与 `table_name` 对应

企业网站类插件常见模型建议：

- 公司介绍：`single`
- 新闻动态：`list`
- 产品中心：`list`
- 案例展示：`list`
- 联系方式或留言：`single` 或 `list`

字段设计优先级：

- 标题类内容优先用 `input`
- 正文内容优先用 `richText`
- 摘要说明优先用 `textarea`
- 封面图优先用 `imageUploader`
- 发布状态优先用 `select` 或 `switch`
- 排序值优先用 `numberInput`
- 时间字段优先用 `datePicker` 或 `dateTimePicker`

### endpoint 生成细则

endpoint 适合这些场景：

- 联系表单提交
- 官网公开查询接口
- 首页统计数据接口
- 外部系统调用入口

生成 endpoint 时遵循这些规则：

- `slug` 使用稳定短名，推荐 `{plugin_id}_{action}`
- `type` 固定为 `endpoint`
- `http_method` 默认为 `POST`
- `input_schema` 至少声明常用参数
- `code` 要直接返回数组，不要输出页面片段
- 如果 endpoint 写入模型数据，优先按模型 `table_name` 推导目标表

### hook 生成细则

hook 适合这些场景：

- 记录创建后补充字段
- 状态变化后写日志
- 提交留言后通知或审计
- 保存内容后联动其他模型

生成 hook 时遵循这些规则：

- `slug` 推荐 `{plugin_id}_{target}_{timing}`
- `type` 固定为 `hook`
- `code` 里优先读取 `payload.before`、`payload.after`
- 返回值保持简洁，不要混入 HTTP 响应结构

### 菜单生成细则

只要插件包含后台可管理模型，默认生成菜单。

生成菜单时遵循这些规则：

- 根菜单默认 `target_type: "group"`
- 单页模型默认生成 `mold_single`
- 列表模型默认生成 `mold_list`
- 分类树模型默认也从 `mold_list` 或对应管理入口开始，不要自造目标类型
- 子菜单里的 `target_payload` 优先使用 `mold_slug`
- 菜单 `slug` 推荐与插件名和目标资源保持一致

常见映射：

- `single` 模型 -> `mold_single`
- `list` 模型 -> `mold_list`
- 纯函数型插件 -> 可以不生成菜单

### data 生成细则

只有在用户明确要求初始演示数据、默认栏目或种子内容时，才生成 `data/`。

生成 `data/` 时遵循这些规则：

- 按模型目录分组，例如 `data/news_article/1.json`
- 数据内容与模型字段对应
- 不要生成无意义的随机占位数据

## 工作流程

### 第一步：理解需求

根据用户描述自动判断是否需要：

- 内容模型
- endpoint
- hook
- schedule
- 菜单
- 前端
- src

仅在关键命名信息缺失时再询问。

优先使用这些推断规则：

- 如果插件要提供后台可管理的数据，默认生成至少一个 `models/*.json`
- 如果插件生成了可管理模型，默认同时生成 `menus/*.json`，除非用户明确说不要后台入口
- 如果插件要暴露 HTTP 能力、统计接口、第三方调用入口，生成 `functions/endpoints/*.json`
- 如果插件要响应数据创建、更新、删除等生命周期事件，生成 `functions/hooks/*.json`
- 如果插件要做自动化执行，按需补 `functions/triggers.json` 和 `functions/schedules.json`
- 如果插件只做纯数据模型扩展，不要强行生成 frontend 和 src
- 如果插件是企业网站、官网、品牌站、门户这类需求，默认优先考虑：
  - 一个 `single` 的公司介绍模型
  - 一个 `list` 的新闻或案例模型
  - 如有线索收集需求，再补一个联系留言模型和提交 endpoint

### 第二步：确定基础信息

至少确定：

- `plugin_id`
- 插件显示名称
- 版本号，默认 `v1`
- 是否需要前端
- 是否需要额外 `src`

### 第三步：生成最小可安装骨架

至少生成：

- `plugin.json`
- 主类 PHP 文件
- `README.md`
- `functions/variables.json`
- `functions/triggers.json`
- `functions/schedules.json`

### 第四步：按需补资源

如有业务需求，再生成：

- `models/*.json`
- `functions/endpoints/*.json`
- `functions/hooks/*.json`
- `menus/*.json`
- `data/*`

### 第五步：补足业务细节

如果用户给出的需求是具体业务插件，不要停在空壳结构，要补到至少能表达业务的程度：

- 模型字段要体现真实业务含义
- 菜单要能进入主要模型
- endpoint/hook 要有最小可运行逻辑
- `README.md` 要写清插件提供了什么资源

## 最小可用模板

以下模板用于生成“可安装、可识别、可继续扩展”的最小插件，不要输出空泛占位文件。

### 最小模型模板

当插件需要后台可管理数据时，优先生成一个最小 list 模型：

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

规则：

- `table_name` 使用短 slug，不带项目前缀
- 至少提供一个可展示字段
- 不要手写真实运行表名，安装器会按项目规则生成实际表

### 最小 endpoint 模板

当插件要提供外部调用或统计接口时，生成：

```json
{
  "name": "获取待办统计",
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
  "code": "<?php\nreturn ['ok' => true];"
}
```

规则：

- `slug` 必须稳定、可读、全局唯一
- `type` 固定为 `endpoint`
- `runtime` 默认 `php`

### 最小 hook 模板

当插件要响应模型变更时，生成：

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

规则：

- `slug` 要表达触发时机和目标对象
- `type` 固定为 `hook`
- 不要生成当前运行时不识别的函数类型

### 最小菜单模板

当插件创建了后台模型时，默认生成一个分组菜单和一个子菜单。根菜单示例：

```json
{
  "name": "待办管理",
  "slug": "todo_group",
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

- 根菜单默认 `target_type: "group"`，不要给根菜单硬写 `target_payload`
- 子菜单访问模型列表时，用 `target_type: "mold_list"`
- 子菜单里优先写 `target_payload.mold_slug`，不要预先写死 `mold_id`
- 如果插件只扩展函数、不需要后台入口，可以不生成菜单

## 生成后自检

生成完成后，必须逐项核对：

- 目录是否为 `Plugins/{plugin_id}/{version}/`
- `plugin.json.id` 是否等于 `{plugin_id}_{version}`
- `plugin.json.version` 是否与目录版本一致，例如 `v1`
- 主类命名空间是否为 `Plugins\{plugin_id}\{version}`
- `provides.models` 是否与 `models/*.json` 一致
- `provides.functions.endpoints` 是否与 `functions/endpoints/*.json` 中的 `slug` 一致
- `provides.functions.hooks` 是否与 `functions/hooks/*.json` 中的 `slug` 一致
- `variables/triggers/schedules` 布尔值是否与对应 JSON 文件实际存在性一致
- `has_frontend` 和 `has_src` 是否与 `frontend/`、`src/` 实际目录一致
- 菜单访问模型时，是否使用 `mold_slug` 而不是预填 `mold_id`
- 不要生成 `functions/web`、`config/variables.json`、`config/triggers.json`、`config/schedules.json`

## 输出要求

向用户生成插件时：

- 优先输出可直接写入仓库的完整目录和文件内容
- 如果需求不复杂，默认给出“最小可用插件”，不要一开始生成过多无关资源
- 如果自动推断出了模型、菜单、endpoint、hook，要在结果里简短说明推断依据
- 除非用户要求兼容旧结构，否则不要讨论旧脚本、旧目录、旧示例
- `frontend/*`

### 第五步：交付说明

生成完成后必须说明：

1. 插件目录路径
2. `plugin.json.id`
3. 哪些资源已经生成
4. 该插件依赖 Mosure 后台安装，而不是仅靠放文件自动生效

## 重要规则

1. 不要把运行时数据库前缀规则写成插件文件规范
   - `_mc_`、`_pf_` 是项目运行时表前缀逻辑
   - 插件作者不需要在目录结构里处理这些前缀

2. Endpoint 和 Hook 的 JSON 文件必须放在代码实际读取的目录

3. 不要生成当前安装器不认识的 `config/*.json`

4. `provides` 必须和实际生成的文件一致

5. 默认生成版本化目录结构

## 默认行为

如果用户没有额外说明：

- 默认创建 `Plugins/{plugin_id}/v1/`
- 默认不创建前端
- 默认不创建 `src`
- 默认只生成最小插件骨架
- 默认使用 `plugin.json.id = {plugin_id}_{version}`

## 参考文档

- `references/plugin-json-schema.md`
- `references/model-definition.md`
- `references/cloud-function-guide.md`
- `references/field-types.md`
- `references/example-todolist.md`
