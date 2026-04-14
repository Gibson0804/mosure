# Blog 插件开发文档

## 插件概述

Blog 插件为 Mosure 系统提供博客文章管理功能，包括文章的创建、编辑、删除、分类、标签和评论等功能。

## 功能设计

### 核心功能

1. **文章管理**
   - 文章列表（分页、搜索、筛选）
   - 文章创建和编辑（富文本编辑器）
   - 文章删除（支持批量删除）
   - 文章发布/草稿状态
   - 文章封面图片上传

2. **分类管理**
   - 分类列表
   - 分类创建和编辑
   - 分类删除
   - 分类层级结构

3. **标签管理**
   - 标签列表
   - 标签创建和编辑
   - 标签删除
   - 标签与文章关联

4. **评论管理**
   - 评论列表
   - 评论审核
   - 评论删除
   - 评论回复

5. **前端展示**
   - 文章列表页
   - 文章详情页
   - 分类文章页
   - 标签文章页
   - 文章搜索

## 模型设计

### 文章模型 (models/article.json)

```json
{
  "name": "article",
  "title": "文章",
  "table_name": "blog_articles",
  "mold_type": 1,
  "fields": [
    {
      "name": "title",
      "type": "input",
      "label": "标题",
      "required": true,
      "rules": "max:255"
    },
    {
      "name": "slug",
      "type": "input",
      "label": "URL 标识",
      "required": false,
      "rules": "max:255|unique:blog_articles,slug"
    },
    {
      "name": "content",
      "type": "editor",
      "label": "内容",
      "required": true
    },
    {
      "name": "excerpt",
      "type": "textarea",
      "label": "摘要",
      "required": false,
      "rules": "max:500"
    },
    {
      "name": "cover_image",
      "type": "image",
      "label": "封面图片",
      "required": false
    },
    {
      "name": "status",
      "type": "select",
      "label": "状态",
      "required": true,
      "options": [
        {"label": "草稿", "value": 0},
        {"label": "已发布", "value": 1}
      ],
      "default": 0
    },
    {
      "name": "published_at",
      "type": "datetime",
      "label": "发布时间",
      "required": false
    },
    {
      "name": "category_id",
      "type": "relation",
      "label": "分类",
      "required": false,
      "relation_model": "category"
    },
    {
      "name": "tags",
      "type": "multiple_select",
      "label": "标签",
      "required": false,
      "relation_model": "tag"
    }
  ],
  "settings": {
    "per_page": 20,
    "allow_comments": true,
    "require_approval": false
  },
  "list_show_fields": ["title", "status", "published_at", "view_count"],
  "search_fields": ["title", "content"]
}
```

### 分类模型 (models/category.json)

```json
{
  "name": "category",
  "title": "分类",
  "table_name": "blog_categories",
  "mold_type": 1,
  "fields": [
    {
      "name": "name",
      "type": "input",
      "label": "分类名称",
      "required": true,
      "rules": "max:100"
    },
    {
      "name": "slug",
      "type": "input",
      "label": "URL 标识",
      "required": false,
      "rules": "max:100|unique:blog_categories,slug"
    },
    {
      "name": "description",
      "type": "textarea",
      "label": "分类描述",
      "required": false
    },
    {
      "name": "parent_id",
      "type": "relation",
      "label": "父分类",
      "required": false,
      "relation_model": "category",
      "allow_null": true
    },
    {
      "name": "sort_order",
      "type": "number",
      "label": "排序",
      "required": false,
      "default": 0
    }
  ],
  "settings": {
    "max_depth": 3
  },
  "list_show_fields": ["name", "sort_order"]
}
```

### 标签模型 (models/tag.json)

```json
{
  "name": "tag",
  "title": "标签",
  "table_name": "blog_tags",
  "mold_type": 1,
  "fields": [
    {
      "name": "name",
      "type": "input",
      "label": "标签名称",
      "required": true,
      "rules": "max:50"
    },
    {
      "name": "slug",
      "type": "input",
      "label": "URL 标识",
      "required": false,
      "rules": "max:50|unique:blog_tags,slug"
    }
  ],
  "settings": {},
  "list_show_fields": ["name"]
}
```

### 评论模型 (models/comment.json)

```json
{
  "name": "comment",
  "title": "评论",
  "table_name": "blog_comments",
  "mold_type": 1,
  "fields": [
    {
      "name": "article_id",
      "type": "relation",
      "label": "文章",
      "required": true,
      "relation_model": "article"
    },
    {
      "name": "author_name",
      "type": "input",
      "label": "作者名称",
      "required": true,
      "rules": "max:100"
    },
    {
      "name": "author_email",
      "type": "input",
      "label": "作者邮箱",
      "required": false,
      "rules": "email|max:255"
    },
    {
      "name": "content",
      "type": "textarea",
      "label": "评论内容",
      "required": true,
      "rules": "max:1000"
    },
    {
      "name": "status",
      "type": "select",
      "label": "状态",
      "required": true,
      "options": [
        {"label": "待审核", "value": 0},
        {"label": "已通过", "value": 1},
        {"label": "已拒绝", "value": 2}
      ],
      "default": 0
    },
    {
      "name": "parent_id",
      "type": "relation",
      "label": "父评论",
      "required": false,
      "relation_model": "comment",
      "allow_null": true
    }
  ],
  "settings": {
    "require_approval": true,
    "max_depth": 3
  },
  "list_show_fields": ["author_name", "content", "status", "created_at"]
}
```

## 云函数设计

### 文章统计函数 (functions/endpoints/article_stats.json)

```json
{
  "name": "文章统计",
  "slug": "article-stats",
  "type": "endpoint",
  "description": "获取文章统计数据",
  "runtime": "php",
  "code": "<?php\n$articleCount = \\App\\Models\\Mold::where('table_name', 'blog_articles')->count();\n$categoryCount = \\App\\Models\\Mold::where('table_name', 'blog_categories')->count();\n$tagCount = \\App\\Models\\Mold::where('table_name', 'blog_tags')->count();\nreturn ['code' => 200, 'data' => [\n  'article_count' => $articleCount,\n  'category_count' => $categoryCount,\n  'tag_count' => $tagCount\n]];",
  "http_method": "GET",
  "timeout_ms": 5000,
  "max_mem_mb": 64
}
```

### 文章浏览计数函数 (functions/hooks/on_article_view.json)

```json
{
  "name": "文章浏览计数",
  "slug": "on-article-view",
  "type": "hook",
  "description": "文章被浏览时触发",
  "runtime": "php",
  "code": "<?php\n$articleId = $data['article_id'] ?? null;\nif ($articleId) {\n  \\App\\Models\\MoldData::where('mold_id', function($query) {\n    return $query->where('table_name', 'blog_articles')->select('id');\n  })->where('id', $articleId)->increment('view_count');\n}\nreturn ['code' => 200, 'data' => ['incremented' => true]];"
}
```

## 菜单设计

### 博客管理菜单 (menus/blog.json)

```json
{
  "title": "博客管理",
  "icon": "blog",
  "target_type": "group",
  "order": 800,
  "visible": true,
  "children": [
    {
      "title": "文章管理",
      "icon": "file-text",
      "target_type": "model",
      "target_slug": "article",
      "order": 10
    },
    {
      "title": "分类管理",
      "icon": "folder",
      "target_type": "model",
      "target_slug": "category",
      "order": 20
    },
    {
      "title": "标签管理",
      "icon": "tags",
      "target_type": "model",
      "target_slug": "tag",
      "order": 30
    },
    {
      "title": "评论管理",
      "icon": "message",
      "target_type": "model",
      "target_slug": "comment",
      "order": 40
    }
  ]
}
```

## 前端设计

### 页面结构

```
frontend/
├── index.html          # 文章列表页
├── article.html       # 文章详情页
├── category.html      # 分类文章页
├── tag.html          # 标签文章页
└── search.html       # 搜索结果页
```

### API 接口说明

系统会根据模型自动生成以下 RESTful API 接口：

#### 文章模型 (blog_articles)

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/open/content/list/blog_articles` | 获取文章列表 |
| GET | `/open/content/detail/blog_articles/{id}` | 获取文章详情 |
| GET | `/open/content/count/blog_articles` | 获取文章数量 |
| GET | `/open/content/find/blog_articles` | 根据条件查询文章 |
| POST | `/open/content/create/blog_articles` | 创建文章 |
| PUT | `/open/content/update/blog_articles/{id}` | 更新文章 |
| DELETE | `/open/content/delete/blog_articles/{id}` | 删除文章 |

#### 分类模型 (blog_categories)

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/open/content/list/blog_categories` | 获取分类列表 |
| GET | `/open/content/detail/blog_categories/{id}` | 获取分类详情 |
| POST | `/open/content/create/blog_categories` | 创建分类 |
| PUT | `/open/content/update/blog_categories/{id}` | 更新分类 |
| DELETE | `/open/content/delete/blog_categories/{id}` | 删除分类 |

#### 标签模型 (blog_tags)

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/open/content/list/blog_tags` | 获取标签列表 |
| GET | `/open/content/detail/blog_tags/{id}` | 获取标签详情 |
| POST | `/open/content/create/blog_tags` | 创建标签 |
| PUT | `/open/content/update/blog_tags/{id}` | 更新标签 |
| DELETE | `/open/content/delete/blog_tags/{id}` | 删除标签 |

#### 评论模型 (blog_comments)

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/open/content/list/blog_comments` | 获取评论列表 |
| GET | `/open/content/detail/blog_comments/{id}` | 获取评论详情 |
| POST | `/open/content/create/blog_comments` | 创建评论 |
| PUT | `/open/content/update/blog_comments/{id}` | 更新评论 |
| DELETE | `/open/content/delete/blog_comments/{id}` | 删除评论 |

### API 调用示例

前端页面通过系统自动生成的 RESTful API 调用数据：

```javascript
// 获取文章列表
fetch('/open/content/list/blog_articles')
  .then(res => res.json())
  .then(data => {
    // 渲染文章列表
  });

// 获取文章详情
fetch('/open/content/detail/blog_articles/{id}')
  .then(res => res.json())
  .then(data => {
    // 渲染文章详情
  });

// 获取分类列表
fetch('/open/content/list/blog_categories')
  .then(res => res.json())
  .then(data => {
    // 渲染分类列表
  });

// 根据条件获取文章（如按分类筛选）
fetch('/open/content/find/blog_articles?category_id=1&status=1')
  .then(res => res.json())
  .then(data => {
    // 渲染筛选后的文章列表
  });

// 获取文章数量
fetch('/open/content/count/blog_articles')
  .then(res => res.json())
  .then(data => {
    // 显示文章总数
  });
```

### 功能特性

1. **响应式设计**
   - 适配桌面端和移动端
   - 使用 CSS Grid 和 Flexbox 布局

2. **SEO 优化**
   - 语义化 HTML
   - Meta 标签
   - 结构化数据

3. **性能优化**
   - 图片懒加载
   - 代码分割
   - 缓存策略

## 开发规范

### 命名规范

- 类名：大驼峰（PascalCase），如 `BlogArticle`
- 方法名：小驼峰（camelCase），如 `getArticleById`
- 变量名：小驼峰（camelCase），如 `articleId`
- 常量名：全大写下划线分隔，如 `MAX_ARTICLES_PER_PAGE`

### 代码风格

- 遵循 PSR-12 编码规范
- 使用 PHP 8.0+ 特性
- 添加必要的注释和文档

### 测试规范

- 单元测试覆盖核心逻辑
- 集成测试覆盖 API 接口
- 端到端测试覆盖关键流程

## 开发计划

### Phase 1: 模型定义
- [ ] 创建文章模型 (models/article.json)
- [ ] 创建分类模型 (models/category.json)
- [ ] 创建标签模型 (models/tag.json)
- [ ] 创建评论模型 (models/comment.json)

### Phase 2: 云函数
- [ ] 创建文章统计函数
- [ ] 创建文章浏览计数钩子
- [ ] 创建其他业务逻辑函数

### Phase 3: 菜单配置
- [ ] 创建博客管理菜单 (menus/blog.json)

### Phase 4: 前端开发
- [ ] 开发文章列表页
- [ ] 开发文章详情页
- [ ] 开发分类和标签页
- [ ] 实现响应式设计

### Phase 5: 优化和完善
- [ ] 性能优化
- [ ] SEO 优化
- [ ] 安全加固
- [ ] 文档完善

## 注意事项

1. **模型字段类型**
   - 使用系统支持的字段类型（input, textarea, editor, image, relation 等）
   - 正确设置字段验证规则
   - 合理设置默认值

2. **云函数规范**
   - 遵循系统定义的函数格式
   - 正确设置运行时和超时时间
   - 返回统一的响应格式

3. **菜单配置**
   - 使用系统支持的图标类型
   - 合理设置菜单排序
   - 正确配置目标类型和标识

4. **前端开发**
   - 使用系统自动生成的 API 接口
   - 遵循 RESTful API 规范
   - 实现错误处理和加载状态

## 参考资料

- [Mosure 插件开发指南](../README.md)
- [插件开发文档](../../../docs/5.开发者指南/02-插件开发.md)
- [Laravel 文档](https://laravel.com/docs)
- [Ant Design](https://ant.design/)