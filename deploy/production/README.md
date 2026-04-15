# Mosure 生产部署配置落点

这套文件用于 Linux 服务器上的 `nginx + php-fpm + supervisor + cron` 部署。

## 目录说明

- `nginx/mosure.app.conf`
  - 单域名部署（推荐）：前后端同站点，Laravel 渲染入口，静态资源由 Nginx 直出。
- `nginx/mosure.split.conf`
  - 分离部署：前端静态站点 + 后端 API/Admin 站点。
- `supervisor/mosure-queue.conf`
  - 队列常驻进程（生产必须）。
- `supervisor/mosure-scheduler.conf`
  - 可选方案：用 supervisor 跑 `schedule:work`。
- `cron/mosure.cron`
  - 推荐方案：每分钟执行 `schedule:run`。

## 建议放置位置（服务器）

1. Nginx
- 复制：`deploy/production/nginx/mosure.app.conf`
- 目标：`/etc/nginx/sites-available/mosure.conf`
- 启用：软链到 `/etc/nginx/sites-enabled/mosure.conf`
- 检查：`nginx -t`
- 重载：`systemctl reload nginx`

2. Supervisor（队列）
- 复制：`deploy/production/supervisor/mosure-queue.conf`
- 目标：`/etc/supervisor/conf.d/mosure-queue.conf`
- 更新：`supervisorctl reread && supervisorctl update`
- 查看：`supervisorctl status`

3. 定时任务（二选一）
- 方案 A（推荐）：crontab
  - 复制：`deploy/production/cron/mosure.cron`
  - 目标：`/etc/cron.d/mosure`
  - 赋权：`chmod 644 /etc/cron.d/mosure`
- 方案 B：supervisor 常驻 `schedule:work`
  - 使用 `supervisor/mosure-scheduler.conf`

## 关键注意

- 把配置中的路径 `/root/www/mosure` 改成你的实际部署目录。
- 把 `fastcgi_pass unix:/run/php/php8.3-fpm.sock;` 改成你的 PHP-FPM 实际 socket。
- 生产不要使用 `./bin/start.sh` 或 `npm run dev`。
- 首次部署后请执行：
  - `php artisan migrate --force`
  - `php artisan config:cache`
  - `php artisan route:cache`
