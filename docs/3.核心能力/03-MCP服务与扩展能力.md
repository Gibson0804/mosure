# MCP 服务与扩展能力

## 什么是 MCP？

MCP（Model Context Protocol）是一个开放协议，允许 AI 助手（Agent）安全地访问外部系统的工具和资源。Mosure 内置了 MCP Server，可以将你的内容模型、数据、云函数等能力开放给 AI 助手调用。

### 核心价值

- **AI 集成**：让 AI 助手能够读取和操作你的内容数据
- **能力开放**：通过标准协议，任何支持 MCP 的客户端都能访问
- **安全控制**：项目级别的 Token 验证和开关控制
- **灵活扩展**：可以自定义 Tools 和 Resources

---

## MCP 能力概览

Mosure 的 MCP Server 提供了以下能力：

### Tools（工具）

工具是 AI 助手可以调用的功能：

| 工具名称 | 功能 | 说明 |
|---------|------|------|
| **内容模型相关** |
| `get_models` | 获取模型列表 | 获取当前项目的所有模型列表（包含用于后续操作的 model_key 短标识） |
| `create_model` | 创建模型 | 新建模型，需提供 name、table_name、fields 等参数 |
| `update_model` | 更新模型 | 修改模型（通过 mold_id 或 model_key 定位模型，并提供要更新的字段） |
| `get_model` | 获取模型详情 | 获取指定模型的详细信息 |
| `validate_model_definition` | 验证模型定义 | 验证模型定义的合法性 |
| **内容相关** |
| `content_crud` | 内容 CRUD | 内容的增删改查操作（list/create/update/delete/get） |
| `media_manage` | 媒体管理 | 媒体文件的列表、上传、删除操作 |
| **Web函数相关** |
| `create_web_function` | 创建Web云函数 | 创建Web云函数，用于提供HTTP API接口 |
| `update_web_function_code` | 更新Web云函数代码 | 修改Web云函数的代码 |
| `get_web_functions` | 获取Web云函数列表 | 获取所有Web云函数 |
| **Hook触发函数相关** |
| `create_hook_function` | 创建触发函数 | 创建触发函数，用于响应事件（如内容创建、更新、删除等） |
| `update_hook_function_code` | 更新触发函数代码 | 修改触发函数的代码 |
| `get_hook_functions` | 获取触发函数列表 | 获取所有触发函数 |
| **API相关** |
| `get_api_env` | 获取API环境 | 获取当前项目的API环境配置（项目前缀、开放API基础地址、可用API密钥） |
| `list_open_apis` | 列出开放API | 获取当前项目的所有开放API列表 |
| **触发器相关** |
| `create_trigger` | 创建触发器 | 创建触发器，用于在特定事件发生时自动执行触发函数 |
| `update_trigger` | 修改触发器 | 修改触发器（模型内容、触发时机、是否启用、触发函数、备注） |
| `get_triggers` | 获取触发器列表 | 获取所有触发器 |
| `get_trigger_payload_example` | 获取触发参数示例 | 根据内容模型和触发事件获取触发参数示例 |
| **定时任务相关** |
| `create_schedule` | 创建定时任务 | 创建定时任务，用于按计划自动执行触发函数（默认关闭状态） |
| `update_schedule` | 修改定时任务 | 修改定时任务（调度类型、运行时间、Cron表达式、时区、负载参数等） |
| `get_schedules` | 获取定时任务列表 | 获取所有定时任务 |

### Resources（资源）

资源是 AI 助手可以读取的数据：

| 资源名称 | 功能 | 说明 |
|---------|------|------|
| `open_api_doc` | OpenAPI 文档 | 当前项目的完整 OpenAPI 规范文档 |
| `model_json_spec` | 模型 JSON 规范 | 当前项目的所有内容模型的 JSON 规范 |

### Prompts（提示词）

预设的 AI 提示词模板：

| 提示词名称 | 功能 | 说明 |
|-----------|------|------|
| `model_generate` | 模型生成 | 用于 AI 辅助生成内容模型的提示词模板 |

---

## 启用 MCP 服务

### 1. 配置 MCP Token

每个项目都有独立的 MCP Token，用于验证访问权限：

1. 进入"项目管理"
2. 选择一个项目
3. 点击"项目配置"
4. 找到"MCP 配置"部分
5. 点击"生成 Token"按钮
6. 复制生成的 Token

### 2. 启用 MCP 服务

在项目配置中，勾选"启用 MCP 服务"开关即可启用。

> ⚠️ **注意**：启用 MCP 服务后，任何持有 Token 的客户端都能访问项目的数据，请确保 Token 安全，建议定期更换。

---

## MCP 接口地址

Mosure 的 MCP Server 通过以下地址访问：

```
https://your-domain.com/mcp
```

### 请求头

所有 MCP 请求都需要携带以下请求头：

```
Authorization: Bearer {mcp_token}
```

---

## 客户端配置示例

### Claude Desktop

在 Claude Desktop 的配置文件中添加：

**macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`

**Windows**: `%APPDATA%\Claude\claude_desktop_config.json`

```json
{
  "mcpServers": {
    "mosure": {
      "url": "https://your-domain.com/mcp",
      "headers": {
        "Authorization": "Bearer your_mcp_token_here"
      }
    }
  }
}
```

### Cursor

在 Cursor 的 MCP 设置中添加：

```json
{
  "mcpServers": {
    "mosure": {
      "url": "https://your-domain.com/mcp",
      "headers": {
        "Authorization": "Bearer your_mcp_token_here"
      }
    }
  }
}
```

### Windsurf

在 Windsurf 的MCP设置中添加

```json
{
  "mcpServers": {
    "mosure": {
      "disabled": false,
      "headers": {
        "Authorization": "Bearer your_mcp_token_here"
      },
      "serverUrl": "http://your-domain.com/mcp"
    }
  }
}
```

---

## 使用场景

根据AI大模型的性能，效果可能不同。


### 场景 1：和氛围编程搭配，存储模型数据，提供开发所需接口

生成内容模型，存储数据，并提供openapi接口

**工作流程**：
1.用户使用AI开发前端页面，需要后端提供接口数据
2.用户告诉AI：“帮我创建页面A所需的数据对应的内容模型，字段包括：字段1、字段2、字段3等”
3.AI调用`create_model`工具创建内容模型
4.用户告诉Ai：“帮我添加页面A的数据，数据包括：数据1、数据2、数据3等”
5.AI调用`content_crud`工具，使用`create`操作添加内容
6.用户告诉Ai：”页面A调用openapi接口获取数据，接口地址为：http://your-domain.com/api/pageA“
7.前端页面直接调用openapi接口，用户可以直接在 Mosure 中查看和操作数据

**实际应用**：
- 氛围编程
- 快速开发最简单可行产品
- AI生成openapi接口

### 场景 2：AI 助手智能问答

让 AI 助手基于你的内容库回答用户问题

**工作流程**：
1. 用户向 AI 提问："我们最近发布的关于 AI 的文章有哪些？"
2. AI 调用 `get_models` 工具获取模型列表，找到文章模型
3. AI 调用 `content_crud` 工具，使用 `list` 操作筛选包含"AI"关键词的文章
4. AI 基于返回的文章内容生成回答

**实际应用**：
- 客户服务智能问答
- 产品文档智能检索
- 知识库智能查询

### 场景 3：AI 辅助内容创作

让 AI 助手帮助你创建和管理内容

**工作流程**：
1. 用户告诉 AI："帮我创建一个产品介绍模型，包含产品名称、描述、价格、图片等字段"
2. AI 调用 `create_model` 工具创建产品模型
3. 用户继续："添加一个新产品，名称是'智能手表'，价格是 2999 元"
4. AI 调用 `content_crud` 工具，使用 `create` 操作添加产品内容
5. 用户："上传产品图片"
6. AI 调用 `media_manage` 工具上传图片，并更新产品内容

**实际应用**：
- 电商产品管理
- 博客文章发布
- 内容库批量导入

### 场景 4：AI 驱动的数据分析

让 AI 助手分析你的内容数据，生成报告和洞察。

**工作流程**：
1. 用户请求："分析我们上个月的文章阅读量趋势"
2. AI 调用 `content_crud` 工具获取文章列表和统计数据
3. AI 分析数据并生成可视化报告
4. AI 调用 `cloud_function` 工具执行自定义分析逻辑

**实际应用**：
- 内容运营分析
- 用户行为分析
- 业务数据报表

### 场景 5：AI 自动化工作流

让 AI 助手通过 MCP 自动执行一系列任务。

**工作流程**：
1. 用户设置规则："每天早上 9 点，自动创建一篇日报文章模板"
2. AI 调用 `content_crud` 工具创建日报模板
3. AI 调用 `cloud_function` 工具执行自动化任务
4. AI 通过 `media_manage` 工具处理相关媒体文件

**实际应用**：
- 定时内容生成
- 自动化内容审核
- 批量数据处理

---

## 安全控制

### Token 验证

所有 MCP 请求都需要提供有效的 Token：

- Token 是项目级别的，每个项目有独立的 Token
- Token 格式为 `{project_prefix}{16位随机字符}`
- Token 可以随时重新生成
- 建议定期更换 Token 以提高安全性

### 项目前缀自动识别

中间件会自动从 Token 中提取项目前缀：
- 无需客户端额外传递 `X-Project-Prefix`
- 简化了客户端配置
- 降低了配置错误的风险

### 权限控制

MCP 服务遵循项目的权限设置：

- 只有启用了 MCP 服务的项目才能访问
- 访问的数据范围受项目权限限制
- 敏感操作（如删除内容）需要额外的权限验证

---

## 自定义扩展

### 添加自定义 Tool

如果你需要添加自定义工具，可以创建新的 Tool 类：

```php
// app/Mcp/Tools/CustomTool.php

namespace App\Mcp\Tools;

use App\Mcp\BaseTool;

class CustomTool extends BaseTool
{
    public function getName(): string
    {
        return 'custom_tool';
    }

    public function getDescription(): string
    {
        return '自定义工具描述';
    }

    public function getArgumentsSchema(): array
    {
        return [
            'param1' => ['type' => 'string', 'description' => '参数1'],
            'param2' => ['type' => 'number', 'description' => '参数2'],
        ];
    }

    public function execute(array $arguments): array
    {
        // 执行你的逻辑
        return [
            'result' => 'success',
            'data' => $arguments
        ];
    }
}
```


---

## 常见问题

### MCP 服务安全吗？

MCP 服务通过 Token 和项目前缀进行双重验证，确保只有授权的客户端才能访问。建议定期更换 Token，并妥善保管。

### MCP 服务会影响性能吗？

MCP 服务的性能取决于请求频率和数据量。对于大量请求，建议使用缓存和异步处理。

### 如何禁用 MCP 服务？

在项目配置中，取消勾选"启用 MCP 服务"开关即可禁用。

### MCP 支持哪些客户端？

任何支持 MCP 协议的客户端都可以连接，包括 Claude Desktop、trae、Cursor、Windsurf 等支持 MCP 的 AI 工具。

### MCP Token 泄露了怎么办？

立即在项目配置中重新生成 Token，旧 Token 会自动失效。

---

## 最佳实践

1. **定期更换 Token**：建议不定期更换 MCP Token，不使用的时候可以关闭MCP服务
2. **使用 HTTPS**：确保 MCP 服务通过 HTTPS 访问，避免数据泄露
3. **合理设计工具**：自定义工具时，明确输入输出，避免歧义

---

**上一页**: [AI辅助建模与表单生成](02-AI辅助建模与表单生成.md) | **下一页**: [OpenAPI与接口自动生成](04-OpenAPI与接口自动生成.md)
