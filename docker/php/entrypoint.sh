#!/usr/bin/env sh
set -e

cd /var/www/html

if [ -d ".locked" ]; then
    rm -rf .locked
    touch .locked
fi

# 初始化 .env
if [ ! -f ".env" ] && [ -f ".env.example" ]; then
    cp .env.example .env
    chown www-data:www-data .env
    chmod 664 .env
fi

# 生成 APP_KEY（如果 .env 中不存在）
php artisan key:generate --force >/dev/null 2>&1 || true

# 创建 storage 链接
php artisan storage:link >/dev/null 2>&1 || true

# 数据库迁移 + 首次安装
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force >/dev/null 2>&1 || true

    # 检测关键表是否存在，不存在则执行完整安装
    HAS_TABLE=$(php artisan tinker --execute="echo \\Schema::hasTable('sys_projects') ? 'yes' : 'no';" 2>/dev/null || echo "no")
    if [ "$HAS_TABLE" != "yes" ]; then
        echo "[entrypoint] First run detected, running mosure:install..."
        php artisan mosure:install --no-interaction \
            --app-url="${APP_URL:-http://localhost:9445}" \
            --db=sqlite
        echo "[entrypoint] Installation complete."
    fi
fi

# 清除并重建缓存
php artisan config:clear >/dev/null 2>&1 || true
php artisan cache:clear >/dev/null 2>&1 || true
php artisan config:cache >/dev/null 2>&1 || true
php artisan route:cache >/dev/null 2>&1 || true
php artisan view:cache >/dev/null 2>&1 || true

# storage 符号链接
if [ ! -L "public/storage" ]; then
    ln -sf /var/www/html/storage/app/public public/storage
fi

# 修复权限（不处理 dangling symlink）
if [ -d storage ]; then
    chown -R www-data:www-data storage bootstrap/cache public/storage
    chmod -R 775 storage bootstrap/cache
    chmod -h 775 public/storage 2>/dev/null || true
fi

exec "$@"
