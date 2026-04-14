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

fix_runtime_permissions() {
    echo "正在修复运行目录权限..."

    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache database

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

if [ -f package.json ] && [ ! -f public/build/manifest.json ]; then
    if command -v npm >/dev/null 2>&1; then
        if [ ! -d node_modules ]; then
            echo "正在安装 npm 依赖..."
            npm install
        fi
        echo "正在构建前端资源..."
        npm run build
    else
        echo "警告: 未检测到 npm，跳过前端构建。请确认 public/build 已存在。"
    fi
fi

php artisan mosure:install --no-interaction "$@"

fix_runtime_permissions

echo ""
echo "安装完成。可执行 ./bin/start.sh 启动本地开发环境。"
