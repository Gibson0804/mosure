# 内容模型定义规范

## 完整示例

```json
{
    "id": 3,
    "name": "待办事项",
    "description": null,
    "table_name": "todo",
    "mold_type": "list",
    "fields": [
        {
            "id": "title",
            "type": "input",
            "field": "title",
            "label": "待办事项",
            "chosen": false,
            "isPick": false,
            "selected": false
        },
        {
            "id": "switch_WekgFP",
            "type": "switch",
            "field": "completed",
            "label": "已经完成",
            "chosen": false,
            "isPick": false,
            "selected": false
        },
        {
            "id": "dateTimePicker_gIU6st",
            "type": "dateTimePicker",
            "field": "finish_time",
            "label": "完成时间",
            "chosen": false,
            "isPick": true,
            "selected": false
        }
    ],
    "settings": [],
    "subject_content": [],
    "list_show_fields": ["title", "completed"]
}
```

## 字段说明

### 根级字段

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `id` | number | 否 | 模型 ID（系统自动分配） |
| `name` | string | 是 | 模型显示名称 |
| `description` | string | 否 | 模型描述 |
| `table_name` | string | 是 | 数据表名（不含前缀） |
| `mold_type` | string | 是 | 模型类型 |
| `fields` | array | 是 | 字段列表 |
| `settings` | array | 否 | 模型设置 |
| `subject_content` | array | 否 | 专题内容配置 |
| `list_show_fields` | array | 是 | 列表显示字段 |

### mold_type 模型类型

| 类型 | 说明 | 使用场景 |
|------|------|----------|
| `list` | 列表模型 | 文章、产品、订单等 |
| `single` | 单页模型 | 关于我们、联系方式等 |
| `tree` | 树形模型 | 分类、部门、菜单等 |

### fields 字段定义

每个字段对象包含：

| 属性 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `id` | string | 是 | 字段唯一标识 |
| `type` | string | 是 | 字段类型（见字段类型参考） |
| `field` | string | 是 | 数据库字段名 |
| `label` | string | 是 | 显示标签 |
| `chosen` | boolean | 否 | 是否为筛选字段 |
| `isPick` | boolean | 否 | 是否可选取 |
| `selected` | boolean | 否 | 默认是否选中 |

### 常用字段类型

```json
// 文本输入
{
    "id": "title",
    "type": "input",
    "field": "title",
    "label": "标题"
}

// 多行文本
{
    "id": "content",
    "type": "textarea",
    "field": "content",
    "label": "内容"
}

// 开关
{
    "id": "switch_xxx",
    "type": "switch",
    "field": "is_active",
    "label": "是否启用"
}

// 日期时间
{
    "id": "dateTimePicker_xxx",
    "type": "dateTimePicker",
    "field": "created_at",
    "label": "创建时间",
    "isPick": true
}

// 数字
{
    "id": "numberInput_xxx",
    "type": "numberInput",
    "field": "price",
    "label": "价格"
}

// 选择器
{
    "id": "select_xxx",
    "type": "select",
    "field": "status",
    "label": "状态",
    "options": [
        {"label": "启用", "value": 1},
        {"label": "禁用", "value": 0}
    ]
}

// 富文本
{
    "id": "richText_xxx",
    "type": "richText",
    "field": "description",
    "label": "详细描述"
}

// 图片上传
{
    "id": "imageUploader_xxx",
    "type": "imageUploader",
    "field": "cover",
    "label": "封面图"
}
```

## 数据表命名

- 系统会自动添加前缀：`{project_prefix}_mc_{table_name}`
- 例如：`project_mc_todo`

## list_show_fields

指定在后台列表页面显示的字段：

```json
{
    "list_show_fields": ["title", "completed", "created_at"]
}
```

## 完整模型示例

### 文章模型

```json
{
    "name": "文章",
    "table_name": "article",
    "mold_type": "list",
    "fields": [
        {
            "id": "input_title",
            "type": "input",
            "field": "title",
            "label": "标题"
        },
        {
            "id": "input_author",
            "type": "input",
            "field": "author",
            "label": "作者"
        },
        {
            "id": "imageUploader_cover",
            "type": "imageUploader",
            "field": "cover",
            "label": "封面"
        },
        {
            "id": "richText_content",
            "type": "richText",
            "field": "content",
            "label": "内容"
        },
        {
            "id": "select_status",
            "type": "select",
            "field": "status",
            "label": "状态",
            "options": [
                {"label": "草稿", "value": 0},
                {"label": "发布", "value": 1}
            ]
        }
    ],
    "settings": [],
    "list_show_fields": ["title", "author", "status"]
}
```

### 分类模型（树形）

```json
{
    "name": "分类",
    "table_name": "category",
    "mold_type": "tree",
    "fields": [
        {
            "id": "input_name",
            "type": "input",
            "field": "name",
            "label": "分类名称"
        },
        {
            "id": "numberInput_sort",
            "type": "numberInput",
            "field": "sort",
            "label": "排序"
        }
    ],
    "settings": [],
    "list_show_fields": ["name", "sort"]
}
```
