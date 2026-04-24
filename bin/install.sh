#!/bin/bash

set -e

echo "===== Mosure 一键安装 ====="

if ! command -v php >/dev/null 2>&1; then
    echo "错误: 未找到 PHP，请先安装 PHP 8.2 或更高版本"
    exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
    echo "错误: 未找到 Composer"
    exit 1
fi

echo "PHP: $(php -r 'echo PHP_VERSION;')"

detect_default_app_url() {
    local host
    host=$(hostname -I 2>/dev/null | awk '{print $1}')
    if [ -z "$host" ]; then
        host="127.0.0.1"
    fi
    echo "http://${host}:9445"
}

fix_runtime_permissions() {
    echo "正在修复运行目录权限..."

    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache database
    
    # 修复运行目录权限，如果实际非www-data用户自行修改
    # chown -R "www-data:www-data" storage bootstrap/cache

    # Laravel 运行目录必须允许 Web/PHP 用户写入缓存、会话、日志等文件。
    chmod -R ug+rwX storage bootstrap/cache database 2>/dev/null || true

    # SQLite 还要求数据库目录可写，用于创建 journal/wal/shm 文件。
    # 远程服务器上安装用户和 Web/PHP-FPM 用户可能不同，所以 SQLite 默认放宽到 777/666。
    chmod 777 database 2>/dev/null || true

    if [ -f database/mosure.db ]; then
        chmod 666 database/mosure.db 2>/dev/null || true
    fi

    for sqlite_runtime_file in database/mosure.db-journal database/mosure.db-wal database/mosure.db-shm; do
        if [ -f "$sqlite_runtime_file" ]; then
            chmod 666 "$sqlite_runtime_file" 2>/dev/null || true
        fi
    done
}

fix_runtime_permissions

if [ ! -d vendor ]; then
    echo "正在安装 Composer 依赖..."
    composer install
fi

if [ ! -f .env ]; then
    echo "正在初始化 .env..."
    cp .env.example .env
fi

APP_URL_INPUT="${INSTALL_APP_URL:-${APP_URL:-$(detect_default_app_url)}}"
DB_CONNECTION_INPUT="${INSTALL_DB_CONNECTION:-${DB_CONNECTION:-sqlite}}"
DB_CONNECTION_INPUT=$(echo "$DB_CONNECTION_INPUT" | tr '[:upper:]' '[:lower:]')

INSTALL_ARGS=(
    "--no-interaction"
    "--app-url=${APP_URL_INPUT}"
    "--db=${DB_CONNECTION_INPUT}"
)

if [ "$DB_CONNECTION_INPUT" = "mysql" ]; then
    DB_HOST_INPUT="${INSTALL_DB_HOST:-${DB_HOST:-127.0.0.1}}"
    DB_PORT_INPUT="${INSTALL_DB_PORT:-${DB_PORT:-3306}}"
    DB_NAME_INPUT="${INSTALL_DB_DATABASE:-${DB_DATABASE:-mosure}}"
    DB_USER_INPUT="${INSTALL_DB_USERNAME:-${DB_USERNAME:-root}}"
    DB_PASSWORD_INPUT="${INSTALL_DB_PASSWORD:-${DB_PASSWORD:-}}"

    INSTALL_ARGS+=(
        "--db-host=${DB_HOST_INPUT}"
        "--db-port=${DB_PORT_INPUT}"
        "--db-name=${DB_NAME_INPUT}"
        "--db-user=${DB_USER_INPUT}"
        "--db-password=${DB_PASSWORD_INPUT}"
    )
fi

php artisan mosure:install "${INSTALL_ARGS[@]}" "$@"

fix_runtime_permissions

echo ""
echo "安装完成。可执行 ./bin/start.sh 启动本地开发环境。"
