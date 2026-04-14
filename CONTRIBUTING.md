# Contributing to Mosure

感谢你关注 Mosure。

## 开始之前

- 提交问题前，请先搜索现有 Issues
- 提交代码前，请先 fork 仓库并从独立分支开发
- 请尽量保持变更聚焦，避免一次 PR 混入多类修改

## 本地开发

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
./bin/start.sh
```

## 提交前检查

建议至少执行：

```bash
composer validate --no-check-publish
php artisan test
npm run build
```

## 提交规范

推荐使用：

```text
<type>(<scope>): <subject>
```

常见 `type`：

- `feat`
- `fix`
- `docs`
- `refactor`
- `test`
- `chore`

## PR 要求

- 描述清楚背景、改动内容与影响范围
- 如涉及 UI，请附截图
- 如涉及 breaking change，请明确说明
- 不要顺手修改无关文件

## 更多说明

详细贡献说明见：

- [docs/1.前言/03-贡献指南.md](docs/1.前言/03-贡献指南.md)
