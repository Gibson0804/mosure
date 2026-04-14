# 开放接口与接口自动生成

## OpenAPI开放接口

Mosure 会根据内容模型自动生成对外接口，并为内容模型与云函数生成 API 文档，无需手动编写。

### 核心价值

- **自动生成**：根据模型和函数自动生成 API 文档，保持同步
- **标准格式**：API格式标准，兼容各种工具
- **实时更新**：模型或函数变化时，文档自动更新
- **多端支持**：管理端查看、MCP 资源、外部调用

---

## API 文档页面

### 访问 API 文档

1. 进入管理后台
2. 点击左侧菜单的"API 文档"
3. 选择要查看的 API 类型：
   - **内容 API**：基于内容模型自动生成的 CRUD 接口
   - **函数 API**：云函数的调用接口

### 文档内容

API 文档包含以下信息：

- **接口地址**：完整的请求路径
- **请求方法**：GET、POST、PUT、DELETE 等
- **请求参数**：路径参数、查询参数、请求体
- **响应格式**：成功和错误的响应示例
- **鉴权方式**：API 密钥的使用方法

### 示例文档

**内容列表接口**：

```
GET /open/{project_prefix}/content/list/{table_name}
```

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| project_prefix | string | 是 | 项目前缀 |
| table_name | string | 是 | 模型标识ID |
| page | number | 否 | 页码，默认 1 |
| page_size | number | 否 | 每页数量，默认 20 |

**响应示例**：

```json
{
  "code": 200,
  "data": {
    "list": [...],
    "total": 100,
    "page": 1,
    "page_size": 20
  }
}
```

---

## 自动生成的 API

### 内容 API

对于每个内容模型，系统会自动生成以下接口：

| 接口 | 方法 | 功能 |
|------|------|------|
| `/content/list/{table_name}` | GET | 获取内容列表 |
| `/content/detail/{table_name}/{id}` | GET | 获取内容详情 |
| `/content/create/{table_name}` | POST | 创建内容 |
| `/content/update/{table_name}/{id}` | PUT | 更新内容 |
| `/content/delete/{table_name}/{id}` | DELETE | 删除内容 |
| `/content/publish/{table_name}/{id}` | POST | 发布内容 |
| `/content/unpublish/{table_name}/{id}` | POST | 取消发布 |

### 函数 API

对于每个云函数，系统会自动生成以下接口：

| 接口 | 方法 | 功能 |
|------|------|------|
| `/func/{slug}` | POST | 调用云函数 |

云函数的参数和响应格式根据函数定义自动生成。

---

## API 鉴权

### API 密钥

所有开放 API 都需要使用 API 密钥进行鉴权：

1. 进入"API 与密钥"页面
2. 点击"新建密钥"按钮
3. 填写密钥信息并保存
4. 复制生成的密钥字符串

### 请求头

在调用 API 时，需要在请求头中携带 API 密钥：

```
X-API-Key: your_api_key_here
```

### 示例请求

```bash
curl -X GET "http://127.0.0.1:9445/open/blog/content/list/article" \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json"
```

---

## OpenAPI 导出

### 导出 JSON 格式

你可以将 OpenAPI 文档导出为 JSON 格式，用于：

- 导入到 API 测试工具（如 Postman）
- 生成客户端 SDK
- 与其他系统集成

在 API 文档页面，点击"导出 OpenAPI"按钮即可下载。

---

## MCP 集成

Mosure 的 MCP Server 提供了 OpenAPI 文档作为资源，AI 助手可以通过 MCP 读取 API 文档：

**资源 URI**：`openapi://doc`

**使用场景**：

1. AI 助手通过 MCP 读取 OpenAPI 文档
2. AI 理解可用的 API 接口
3. AI 根据需求调用相应的 API

---

## 自定义 云函数API 文档

### 添加接口描述

你可以在云函数的配置中添加接口描述，这些描述会出现在 OpenAPI 文档中：

- **接口描述**：函数的功能说明
- **参数描述**：每个参数的含义和约束

---

## 常见问题

### API 文档会自动更新吗？

是的。当你修改了模型定义或云函数后，API 文档会自动更新，无需手动操作。

### 如何测试 API？

你可以使用以下工具测试 API：

- **curl**：命令行工具
- **Postman**：图形化 API 测试工具
- **Insomnia**：轻量级 API 客户端
- **浏览器开发者工具**：简单的 GET 请求

### API 密钥可以共享吗？

不建议。每个应用或服务应该使用独立的 API 密钥，这样便于管理和撤销权限。

### 如何限制 API 访问频率？

在"API 与密钥"页面，可以为每个密钥配置访问频率限制，防止滥用。

---

**上一页**: [MCP服务与扩展能力](03-MCP服务与扩展能力.md) | **下一页**: [任务化能力与异步体验设计](05-任务化能力与异步体验设计.md)
