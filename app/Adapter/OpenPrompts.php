<?php

namespace App\Adapter;

class OpenPrompts
{
    /**
     * 生成完整插件的提示词
     */
    public static function getPluginGeneratePrompt(string $pluginName, string $description = ''): string
    {
        $pluginName = self::formatQuestion($pluginName);
        $description = $description ? self::formatQuestion($description) : '';

        $descPart = $description ? "\n插件描述：{$description}\n" : '';

        $content = <<<PROMPT
你是一个ZHICLOUND CMS插件开发专家。请根据需求生成一个完整的插件。

插件名称：{$pluginName}{$descPart}
系统说明：
- ZHICLOUND CMS是一个无头CMS系统，基于Laravel + React
- 插件采用JSON配置方式，无需编写PHP代码
- 支持模型(models)、Web函数(functions/web)、触发器(functions/triggers)、菜单(menus)、定时任务(schedules)

输出要求：
1. 仅输出JSON对象，不要包含任何解释或Markdown标记
2. JSON结构如下：
{
  "plugin": {
    "name": "插件中文名",
    "slug": "plugin-slug",
    "version": "1.0.0",
    "description": "插件描述",
    "author": "ZHICLOUND CMS"
  },
  "models": [
    {
      "name": "模型名称",
      "slug": "model_slug",
      "description": "模型描述",
      "mold_type": "list",
      "fields": [
        {
          "id": "fld_xxx",
          "field": "field_name",
          "type": "input",
          "label": "字段标签",
          "required": true
        }
      ]
    }
  ],
  "web_functions": [
    {
      "name": "函数名称",
      "slug": "function_slug",
      "description": "函数描述",
      "method": "POST",
      "path": "/api/path",
      "parameters": []
    }
  ],
  "triggers": [
    {
      "name": "触发器名称",
      "slug": "trigger_slug",
      "event": "content.after_create",
      "model": "model_slug"
    }
  ],
  "menus": [
    {
      "name": "菜单名称",
      "icon": "IconName",
      "children": [
        {
          "name": "子菜单",
          "path": "/path",
          "model": "model_slug"
        }
      ]
    }
  ]
}

字段类型(type)可选值：
input, textarea, numInput, radio, checkbox, select, switch, datePicker, dateTimePicker, timePicker, colorPicker, picUpload, picGallery, fileUpload, richText, slider, rate, cascader, dateRangePicker, tags

模型类型(mold_type)：
list = 列表模型（多条记录）
single = 单页模型（单条记录，如设置页）

触发事件(event)可选值：
content.after_create, content.after_update, content.after_delete

请根据插件需求，设计合理的模型结构、字段、功能函数和触发器。
PROMPT;

        return $content;
    }

    /**
     * 生成模型的提示词
     */
    public static function getModelGeneratePrompt(string $modelName, string $description = ''): string
    {
        $modelName = self::formatQuestion($modelName);
        $description = $description ? self::formatQuestion($description) : '';

        $descPart = $description ? "\n模型描述：{$description}\n" : '';

        $content = <<<PROMPT
你是一个Mosure模型设计专家。请根据需求设计一个数据模型。

模型名称：{$modelName}{$descPart}
输出要求：
1. 仅输出JSON对象，不要包含任何解释或Markdown标记
2. JSON结构如下：
{
  "name": "模型中文名",
  "slug": "model_slug",
  "description": "模型描述",
  "mold_type": "list",
  "fields": [
    {
      "id": "fld_xxx",
      "field": "field_name",
      "type": "input",
      "label": "字段标签",
      "required": true,
      "placeholder": "提示文本",
      "default": "默认值",
      "help": "帮助说明"
    }
  ]
}

字段类型(type)可选值：
- input: 单行文本
- textarea: 多行文本
- numInput: 数字输入
- radio: 单选框
- checkbox: 多选框
- select: 下拉选择
- switch: 开关
- datePicker: 日期选择
- dateTimePicker: 日期时间选择
- timePicker: 时间选择
- colorPicker: 颜色选择
- picUpload: 图片上传
- fileUpload: 文件上传
- richText: 富文本编辑器
- slider: 滑块（需配置min, max, step）
- rate: 评分（需配置count, allowHalf）
- cascader: 级联选择（需配置options）
- dateRangePicker: 日期区间
- tags: 标签输入
- dividingLine: 分割线（非输入字段）

模型类型(mold_type)：
list = 列表模型（多条记录）
single = 单页模型（单条记录，如设置页）

字段ID命名规则：fld_ + 英文描述
字段名(field)命名规则：小写英文+下划线

请根据模型需求，设计合理完整的字段结构。
PROMPT;

        return $content;
    }

    /**
     * 生成Web函数的提示词
     */
    public static function getWebFunctionGeneratePrompt(string $functionName, string $description = ''): string
    {
        $functionName = self::formatQuestion($functionName);
        $description = $description ? self::formatQuestion($description) : '';

        $descPart = $description ? "\n函数描述：{$description}\n" : '';

        $content = <<<PROMPT
你是一个Mosure API函数设计专家。请根据需求设计一个Web函数。

函数名称：{$functionName}{$descPart}
输出要求：
1. 仅输出JSON对象，不要包含任何解释或Markdown标记
2. JSON结构如下：
{
  "name": "函数中文名",
  "slug": "function_slug",
  "description": "函数描述",
  "method": "POST",
  "path": "/api/path",
  "auth_required": true,
  "parameters": [
    {
      "name": "param_name",
      "type": "string",
      "required": true,
      "description": "参数描述"
    }
  ],
  "response": {
    "success": {
      "code": 200,
      "message": "成功消息",
      "data": {}
    },
    "error": {
      "code": 400,
      "message": "错误消息"
    }
  },
  "code": "<?php\\n// PHP代码\\n\$param = \$request->input('param_name');\\nreturn ['code' => 200, 'data' => []];"
}

HTTP方法(method)可选值：
GET, POST, PUT, DELETE

参数类型(type)可选值：
string, integer, boolean, array, object

代码说明：
- 使用\$request->input('参数名')获取参数
- 使用\$this->getModelList('模型slug')查询列表
- 使用\$this->getModelContent('模型slug', ['field' => 'value'])查询单条
- 使用\$this->createModelContent('模型slug', \$data)创建
- 使用\$this->updateModelContent('模型slug', \$id, \$data)更新
- 使用\$this->deleteModelContent('模型slug', \$id)删除
- 返回格式：['code' => 200, 'message' => '消息', 'data' => []]

请根据函数需求，设计合理的参数、响应和实现代码。
PROMPT;

        return $content;
    }

    /**
     * 生成触发器的提示词
     */
    public static function getTriggerGeneratePrompt(string $triggerName, string $description = ''): string
    {
        $triggerName = self::formatQuestion($triggerName);
        $description = $description ? self::formatQuestion($description) : '';

        $descPart = $description ? "\n触发器描述：{$description}\n" : '';

        $content = <<<PROMPT
你是一个Mosure触发器设计专家。请根据需求设计一个触发器。

触发器名称：{$triggerName}{$descPart}
输出要求：
1. 仅输出JSON对象，不要包含任何解释或Markdown标记
2. JSON结构如下：
{
  "name": "触发器中文名",
  "slug": "trigger_slug",
  "description": "触发器描述",
  "trigger_event": "content.after_create",
  "trigger_model": "model_slug",
  "conditions": {
    "field_name": "value"
  },
  "code": "<?php\\n// PHP代码\\n\$content = \$event->content;\\n// 处理逻辑"
}

触发事件(trigger_event)可选值：
- content.after_create: 内容创建后
- content.after_update: 内容更新后
- content.after_delete: 内容删除后
- content.before_create: 内容创建前
- content.before_update: 内容更新前
- content.before_delete: 内容删除前

触发条件(conditions)：
可选，用于过滤触发条件，如：{"status": "published"}

代码说明：
- 通过\$event->content获取内容数据
- 通过\$event->model获取模型名称
- 可调用\$this->getModelContent()等方法
- 可调用外部API
- 使用\$this->log('消息')记录日志

请根据触发器需求，设计合理的触发事件、条件和实现代码。
PROMPT;

        return $content;
    }

    /**
     * 生成定时任务的提示词
     */
    public static function getScheduleGeneratePrompt(string $scheduleName, string $description = ''): string
    {
        $scheduleName = self::formatQuestion($scheduleName);
        $description = $description ? self::formatQuestion($description) : '';

        $descPart = $description ? "\n任务描述：{$description}\n" : '';

        $content = <<<PROMPT
你是一个Mosure定时任务设计专家。请根据需求设计一个定时任务。

任务名称：{$scheduleName}{$descPart}
输出要求：
1. 仅输出JSON对象，不要包含任何解释或Markdown标记
2. JSON结构如下：
{
  "name": "任务中文名",
  "slug": "schedule_slug",
  "description": "任务描述",
  "schedule": "0 3 * * *",
  "enabled": true,
  "action": {
    "type": "custom",
    "code": "<?php\\n// PHP代码\\n// 执行定时任务逻辑"
  }
}

定时规则(schedule)使用Cron表达式：
- "* * * * *": 每分钟
- "0 * * * *": 每小时
- "0 0 * * *": 每天0点
- "0 3 * * *": 每天凌晨3点
- "0 0 * * 0": 每周日0点
- "0 0 1 * *": 每月1号0点

动作类型(action.type)：
- custom: 自定义代码
- web_function: 调用Web函数（需指定function字段）

代码说明：
- 可调用\$this->getModelList()等方法
- 可调用外部API
- 使用\$this->log('消息')记录日志
- 适合执行数据清理、同步、统计等任务

请根据任务需求，设计合理的执行时间和实现代码。
PROMPT;

        return $content;
    }

    /**
     * 生成菜单配置的提示词
     */
    public static function getMenuGeneratePrompt(string $pluginName): string
    {
        $pluginName = self::formatQuestion($pluginName);

        $content = <<<PROMPT
你是一个Mosure菜单设计专家。请为插件设计菜单结构。

插件名称：{$pluginName}

输出要求：
1. 仅输出JSON对象，不要包含任何解释或Markdown标记
2. JSON结构如下：
{
  "name": "菜单名称",
  "icon": "IconName",
  "children": [
    {
      "name": "子菜单名称",
      "path": "/path",
      "model": "model_slug"
    }
  ]
}

图标(icon)可选值（Ant Design图标）：
AppstoreOutlined, BookOutlined, FileTextOutlined, FolderOutlined, 
SettingOutlined, UserOutlined, TeamOutlined, ShoppingOutlined,
WechatOutlined, DatabaseOutlined, CloudOutlined, etc.

菜单项说明：
- name: 菜单显示名称
- path: 路由路径
- model: 关联的模型slug
- icon: 仅一级菜单需要

请根据插件功能，设计合理的菜单层级结构。
PROMPT;

        return $content;
    }

    // 清洗用户提问内容，格式转义等
    private static function formatQuestion($question)
    {
        $question = str_replace("'", "\\'", $question);
        $question = str_replace('"', '\\"', $question);

        return $question;
    }
}
