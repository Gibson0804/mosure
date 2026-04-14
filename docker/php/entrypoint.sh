#!/usr/bin/env sh
set -e

cd /var/www/html

if [ ! -f ".env" ] && [ -f ".env.example" ]; then
    cp .env.example .env
    chown www-data:www-data .env
    chmod 664 .env
fi

php artisan key:generate --force >/dev/null 2>&1 || true
php artisan storage:link >/dev/null 2>&1 || true

php artisan config:clear >/dev/null 2>&1 || true
php artisan cache:clear >/dev/null 2>&1 || true
php artisan config:cache >/dev/null 2>&1 || true
php artisan route:cache >/dev/null 2>&1 || true
php artisan view:cache >/dev/null 2>&1 || true

if [ ! -L "public/storage" ]; then
    ln -sf /var/www/html/storage/app/public public/storage
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

[ -d storage ] && chown -R www-data:www-data storage bootstrap/cache public/storage && chmod -R 775 storage bootstrap/cache public/storage

exec "$@"
