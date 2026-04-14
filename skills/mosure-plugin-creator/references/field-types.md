# 字段类型参考

## 基础输入类型

### input - 单行文本

```json
{
    "id": "input_title",
    "type": "input",
    "field": "title",
    "label": "标题",
    "placeholder": "请输入标题",
    "required": true
}
```

**数据库类型**: VARCHAR(255)

---

### textarea - 多行文本

```json
{
    "id": "textarea_desc",
    "type": "textarea",
    "field": "description",
    "label": "描述",
    "placeholder": "请输入描述",
    "rows": 4
}
```

**数据库类型**: TEXT

---

### numberInput - 数字输入

```json
{
    "id": "numberInput_price",
    "type": "numberInput",
    "field": "price",
    "label": "价格",
    "min": 0,
    "max": 999999,
    "step": 0.01
}
```

**数据库类型**: DECIMAL(10,2)

---

### switch - 开关

```json
{
    "id": "switch_active",
    "type": "switch",
    "field": "is_active",
    "label": "是否启用"
}
```

**数据库类型**: TINYINT(1)

---

## 日期时间类型

### datePicker - 日期选择

```json
{
    "id": "datePicker_birthday",
    "type": "datePicker",
    "field": "birthday",
    "label": "生日",
    "format": "YYYY-MM-DD"
}
```

**数据库类型**: DATE

---

### dateTimePicker - 日期时间选择

```json
{
    "id": "dateTimePicker_created",
    "type": "dateTimePicker",
    "field": "created_at",
    "label": "创建时间",
    "format": "YYYY-MM-DD HH:mm:ss",
    "isPick": true
}
```

**数据库类型**: DATETIME

---

### timePicker - 时间选择

```json
{
    "id": "timePicker_start",
    "type": "timePicker",
    "field": "start_time",
    "label": "开始时间",
    "format": "HH:mm:ss"
}
```

**数据库类型**: TIME

---

## 选择类型

### select - 下拉选择

```json
{
    "id": "select_status",
    "type": "select",
    "field": "status",
    "label": "状态",
    "options": [
        {"label": "草稿", "value": 0},
        {"label": "已发布", "value": 1},
        {"label": "已下架", "value": 2}
    ]
}
```

**数据库类型**: INT

---

### radio - 单选框

```json
{
    "id": "radio_gender",
    "type": "radio",
    "field": "gender",
    "label": "性别",
    "options": [
        {"label": "男", "value": "male"},
        {"label": "女", "value": "female"}
    ]
}
```

**数据库类型**: VARCHAR(20)

---

### checkbox - 多选框

```json
{
    "id": "checkbox_tags",
    "type": "checkbox",
    "field": "tags",
    "label": "标签",
    "options": [
        {"label": "技术", "value": "tech"},
        {"label": "生活", "value": "life"},
        {"label": "旅行", "value": "travel"}
    ]
}
```

**数据库类型**: JSON

---

## 富媒体类型

### richText - 富文本编辑器

```json
{
    "id": "richText_content",
    "type": "richText",
    "field": "content",
    "label": "内容"
}
```

**数据库类型**: LONGTEXT

---

### imageUploader - 图片上传

```json
{
    "id": "imageUploader_cover",
    "type": "imageUploader",
    "field": "cover",
    "label": "封面图",
    "max_count": 1,
    "max_size": 2048
}
```

**数据库类型**: VARCHAR(500)

---

### fileUploader - 文件上传

```json
{
    "id": "fileUploader_attachment",
    "type": "fileUploader",
    "field": "attachment",
    "label": "附件",
    "accept": ".pdf,.doc,.docx",
    "max_size": 10240
}
```

**数据库类型**: VARCHAR(500)

---

### videoUploader - 视频上传

```json
{
    "id": "videoUploader_video",
    "type": "videoUploader",
    "field": "video_url",
    "label": "视频",
    "max_size": 102400
}
```

**数据库类型**: VARCHAR(500)

---

## 关联类型

### selectModel - 模型关联

```json
{
    "id": "selectModel_category",
    "type": "selectModel",
    "field": "category_id",
    "label": "分类",
    "target_model": "category",
    "display_field": "name"
}
```

**数据库类型**: INT（外键）

---

### cascader - 级联选择

```json
{
    "id": "cascader_region",
    "type": "cascader",
    "field": "region",
    "label": "地区",
    "options": [
        {
            "label": "北京",
            "value": "beijing",
            "children": [
                {"label": "朝阳区", "value": "chaoyang"},
                {"label": "海淀区", "value": "haidian"}
            ]
        }
    ]
}
```

**数据库类型**: JSON

---

## 特殊类型

### colorPicker - 颜色选择

```json
{
    "id": "colorPicker_theme",
    "type": "colorPicker",
    "field": "theme_color",
    "label": "主题色"
}
```

**数据库类型**: VARCHAR(20)

---

### iconPicker - 图标选择

```json
{
    "id": "iconPicker_icon",
    "type": "iconPicker",
    "field": "icon",
    "label": "图标"
}
```

**数据库类型**: VARCHAR(50)

---

### slider - 滑块

```json
{
    "id": "slider_rating",
    "type": "slider",
    "field": "rating",
    "label": "评分",
    "min": 0,
    "max": 100,
    "step": 1
}
```

**数据库类型**: INT

---

### rate - 评分

```json
{
    "id": "rate_score",
    "type": "rate",
    "field": "score",
    "label": "评分",
    "max": 5,
    "allow_half": true
}
```

**数据库类型**: DECIMAL(2,1)

---

## 字段 ID 命名规范

推荐格式：`{type}_{field_name}` 或 `{type}_{random_suffix}`

示例：
- `input_title` - 标题输入框
- `switch_WekgFP` - 开关（随机后缀）
- `dateTimePicker_gIU6st` - 日期时间选择器

---

## 公共属性

所有字段类型都支持以下公共属性：

| 属性 | 类型 | 说明 |
|------|------|------|
| `id` | string | 字段唯一标识 |
| `type` | string | 字段类型 |
| `field` | string | 数据库字段名 |
| `label` | string | 显示标签 |
| `placeholder` | string | 占位文本 |
| `required` | boolean | 是否必填 |
| `disabled` | boolean | 是否禁用 |
| `hidden` | boolean | 是否隐藏 |
| `default` | mixed | 默认值 |
| `chosen` | boolean | 是否为筛选字段 |
| `isPick` | boolean | 是否可选取 |
| `selected` | boolean | 默认是否选中 |
