# 企业网站

- 插件目录：`Plugins/companysite/v1`
- 插件 ID：`companysite_v1`
- 适用场景：企业官网、品牌站、服务型公司站点、方案展示型官网

## 这版包含什么

- 8 个可管理模型
- 6 个常用 endpoint
- 2 个 hook
- 1 套企业官网后台菜单
- 8 个前端页面模板
- 一批可直接看到效果的演示数据

## 模型结构

- `company_profile`
  - 首页品牌主叙事、核心介绍、主视觉图片
- `site_settings`
  - 联系方式、地址、办公时间、CTA 文案、社媒链接
- `service_item`
  - 服务能力、方案介绍、图标与排序
- `case_study`
  - 客户案例、结果亮点、行业标签
- `team_member`
  - 团队成员、职位、简介、头像
- `faq_item`
  - 常见问题与回答
- `news_article`
  - 新闻动态、封面、作者、摘要、正文、发布时间
- `contact_message`
  - 线索收集、咨询留言、预算、来源页、处理状态

## 接口能力

- `companysite_contact_submit`
  - 前端咨询表单提交
- `companysite_home_payload`
  - 首页聚合数据接口示例
- `companysite_news_list`
  - 新闻列表接口示例
- `companysite_news_detail`
  - 新闻详情接口
- `companysite_case_list`
  - 案例列表接口
- `companysite_case_detail`
  - 案例详情接口

## 前端页面

- `index.html`
- `about.html`
- `services.html`
- `cases.html`
- `case-detail.html`
- `news.html`
- `news-detail.html`
- `contact.html`

这套前端默认就是可展示的静态官网模板，安装后会把 `config.js` 中的 `{$domain}` 与 `{$apiKey}` 自动替换，后续可继续改成真实动态渲染。
