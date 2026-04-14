# Mosure 内容模型生成器 Skill

## 概述

此 Skill 用于生成可直接在 `/mold/add` 页面使用的内容模型 JSON。

## 输出要求

1. **仅输出 JSON 对象**，不要包含任何解释或注释
2. **JSON 中不要有注释**
3. **可以换行格式化**，方便用户查看

---

## JSON 格式

```json
{
  "page_id": "模型英文标识",
  "page_name": "模型中文名称",
  "mold_type": "list 或 single",
  "children": [
    {
      "id": "字段唯一标识",
      "label": "字段中文名",
      "field": "字段英文名",
      "type": "字段类型",
      "options": ["选项1", "选项2"]
    }
  ]
}
```

---

## 字段说明

### 根级字段

| 字段 | 类型 | 说明 |
|------|------|------|
| `page_id` | string | 模型英文标识（小写字母和下划线，如 article、product_list） |
| `page_name` | string | 模型中文名称（如 文章管理、商品列表） |
| `mold_type` | string | `list`（列表模型）或 `single`（单页模型） |
| `children` | array | 字段列表 |

### children 字段属性

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `id` | string | 是 | 字段唯一标识（如 input_abc123） |
| `label` | string | 是 | 字段中文名 |
| `field` | string | 是 | 字段英文名（小写字母和下划线） |
| `type` | string | 是 | 字段类型（见下方） |
| `options` | array | 否 | 选项列表（仅 select/radio/checkbox 需要） |

---

## 可用字段类型

| type | 说明 | 用途 |
|------|------|------|
| `input` | 单行文本输入 | 标题、名称、链接 |
| `textarea` | 多行文本输入 | 描述、简介 |
| `numInput` | 数字输入 | 价格、数量、排序 |
| `switch` | 开关 | 启用/禁用、是/否 |
| `select` | 下拉选择 | 状态、类型（需 options） |
| `radio` | 单选按钮 | 性别、类型（需 options） |
| `checkbox` | 多选框 | 标签、特性（需 options） |
| `datePicker` | 日期选择 | 生日、截止日期 |
| `timePicker` | 时间选择 | 开始时间、结束时间 |
| `colorPicker` | 颜色选择器 | 主题色、标签颜色 |
| `fileUpload` | 文件上传 | 附件、文档 |
| `picUpload` | 图片上传 | 封面、头像 |
| `picGallery` | 图片集 | 商品图片、相册 |
| `richText` | 富文本编辑器 | 文章内容、详情 |

---

## 模型类型判断

| 用户描述 | mold_type |
|----------|-----------|
| "列表"、"多个"、"管理"、"文章"、"商品"、"订单" | `list` |
| "单页"、"配置"、"设置"、"关于"、"联系我们" | `single` |

---

## 智能字段推断

### 文章/博客

```
字段：标题、作者、封面、状态、发布时间、内容
```

### 商品/产品

```
字段：名称、价格、库存、图片、详情
```

### 订单

```
字段：订单号、金额、状态、客户姓名、联系电话
```

### 人员/用户

```
字段：姓名、性别、手机、邮箱、头像
```

### 广告/轮播

```
字段：标题、描述、图片、链接、开始时间、结束时间、展示位置
```

### 分类/标签

```
字段：名称、排序、图标
```

---

## 输出示例

### 需求：设计一个广告宣传页

```json
{
  "page_id": "ad",
  "page_name": "广告宣传页",
  "mold_type": "list",
  "children": [
    {"id": "input_abc123", "label": "广告标题", "type": "input", "field": "title"},
    {"id": "textarea_def456", "label": "广告描述", "type": "textarea", "field": "description"},
    {"id": "picUpload_ghi789", "label": "广告图片", "type": "picUpload", "field": "pic"},
    {"id": "input_jkl012", "label": "广告链接", "type": "input", "field": "link"},
    {"id": "datePicker_mno345", "label": "开始时间", "type": "datePicker", "field": "start_time"},
    {"id": "datePicker_pqr678", "label": "结束时间", "type": "datePicker", "field": "end_time"},
    {"id": "select_stu901", "label": "展示位置", "type": "select", "field": "position", "options": ["首页", "列表页"]}
  ]
}
```

### 需求：创建一个商品模型

```json
{
  "page_id": "product",
  "page_name": "商品管理",
  "mold_type": "list",
  "children": [
    {"id": "input_title", "label": "商品名称", "type": "input", "field": "name"},
    {"id": "numInput_price", "label": "价格", "type": "numInput", "field": "price"},
    {"id": "numInput_stock", "label": "库存", "type": "numInput", "field": "stock"},
    {"id": "picGallery_images", "label": "商品图片", "type": "picGallery", "field": "images"},
    {"id": "select_status", "label": "状态", "type": "select", "field": "status", "options": ["上架", "下架"]},
    {"id": "richText_detail", "label": "商品详情", "type": "richText", "field": "detail"}
  ]
}
```

### 需求：网站配置单页

```json
{
  "page_id": "site_config",
  "page_name": "网站配置",
  "mold_type": "single",
  "children": [
    {"id": "input_site_name", "label": "网站名称", "type": "input", "field": "site_name"},
    {"id": "textarea_description", "label": "网站描述", "type": "textarea", "field": "description"},
    {"id": "picUpload_logo", "label": "网站Logo", "type": "picUpload", "field": "logo"},
    {"id": "input_icp", "label": "备案号", "type": "input", "field": "icp"},
    {"id": "textarea_footer", "label": "页脚信息", "type": "textarea", "field": "footer"}
  ]
}
```

---

## 重要规则

1. **格式化输出**：JSON 可以换行格式化，方便查看
2. **无注释**：JSON 中不能有注释
3. **字段 ID 唯一**：每个字段的 id 必须唯一
4. **options 格式**：字符串数组 `["选项1", "选项2"]`

## 字段 ID 命名建议

使用 `{type}_{field}` 格式确保唯一性：
- `input_title`
- `select_status`
- `picUpload_cover`
- `richText_content`
