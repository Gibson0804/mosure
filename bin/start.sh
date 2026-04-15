#!/bin/bash

# 设置错误处理
set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 解析命令行参数
MODE="dev"  # 默认开发模式
ENABLE_CRON=false

for arg in "$@"
do
    case $arg in
        -prod)
            MODE="prod"
            shift
            ;;
        -cron)
            ENABLE_CRON=true
            shift
            ;;
        *)
            # 未知参数
            ;;
    esac
done

echo "===== 启动 Mosure 系统 ====="
echo ""

if [ "$MODE" = "dev" ]; then
    echo -e "${BLUE}当前模式: 开发模式${NC}"
    echo -e "${YELLOW}提示: 开发模式下使用 queue:listen，修改代码后无需重启即可生效${NC}"
    echo -e "${YELLOW}提示: 定时任务默认不启动，如需启动请使用 -cron 参数${NC}"
else
    echo -e "${GREEN}当前模式: 正式模式${NC}"
    echo -e "${YELLOW}提示: 正式模式使用 queue:work，性能更优但修改代码后需要重启${NC}"
    echo -e "${YELLOW}提示: 定时任务默认不启动，如需启动请使用 -cron 参数${NC}"
fi
echo ""

# 检查 PHP 是否安装
if ! command -v php &> /dev/null; then
    echo -e "${RED}错误: PHP 未安装或不在 PATH 中${NC}"
    echo "请先安装 PHP 8.2 或更高版本"
    exit 1
fi

# 检查 PHP 版本
PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "当前 PHP 版本: $PHP_VERSION"

# 检查 composer 是否安装
if ! command -v composer &> /dev/null; then
    echo -e "${YELLOW}警告: composer 未安装，某些功能可能无法使用${NC}"
fi

# 检查 .env 文件是否存在
if [ ! -f .env ]; then
    echo -e "${YELLOW}警告: .env 文件不存在，正在从 .env.example 复制...${NC}"
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "已创建 .env 文件，请根据需要修改配置"
    else
        echo -e "${RED}错误: .env.example 文件不存在${NC}"
        exit 1
    fi
fi

# 检查 vendor 目录是否存在
if [ ! -d vendor ]; then
    echo -e "${YELLOW}警告: vendor 目录不存在，正在运行 composer install...${NC}"
    if command -v composer &> /dev/null; then
        composer install
    else
        echo -e "${RED}错误: 需要安装 composer 依赖${NC}"
        exit 1
    fi
fi

# 定义清理函数
cleanup() {
    echo ""
    echo -e "${YELLOW}正在停止服务...${NC}"
    
    # 终止 PHP 服务器
    if [ ! -z "$PHP_PID" ]; then
        kill $PHP_PID 2>/dev/null || true
        echo "PHP 服务器已停止"
    fi
    
    # 终止队列进程
    if [ ! -z "$QUEUE_PID" ]; then
        kill $QUEUE_PID 2>/dev/null || true
        echo "队列工作进程已停止"
    fi
    
    # 终止定时任务进程
    if [ ! -z "$CRON_PID" ]; then
        kill $CRON_PID 2>/dev/null || true
        echo "定时任务进程已停止"
    fi
    
    exit 0
}

# 捕获信号
trap cleanup SIGINT SIGTERM

# 启动 PHP Artisan 服务器
echo "正在启动 PHP Artisan 服务器..."
php artisan serve --host=0.0.0.0 --port=9445 > /dev/null 2>&1 &
PHP_PID=$!
echo "PHP Artisan 服务器已启动 (PID: $PHP_PID)"

# 等待 PHP 服务器启动
sleep 2

# 检查 PHP 服务器是否成功启动
if ! kill -0 $PHP_PID 2>/dev/null; then
    echo -e "${RED}错误: PHP 服务器启动失败${NC}"
    echo "请检查端口 9445 是否被占用"
    exit 1
fi

# 启动队列工作进程
echo "正在启动队列工作进程..."
if [ "$MODE" = "dev" ]; then
    # 开发模式使用 queue:listen，修改代码后无需重启
    # php artisan queue:listen --tries=3 --timeout=300 > /dev/null 2>&1 &
    php artisan queue:listen --tries=5 --timeout=600 >> storage/logs/queue.log 2>&1 &
else
    # 正式模式使用 queue:work，性能更优
    php artisan queue:work --daemon --tries=3 --timeout=300 > /dev/null 2>&1 &
fi
QUEUE_PID=$!
echo "队列工作进程已启动 (PID: $QUEUE_PID)"

# 等待队列进程启动
sleep 2

# 检查队列进程是否成功启动
if ! kill -0 $QUEUE_PID 2>/dev/null; then
    echo -e "${YELLOW}警告: 队列工作进程启动失败，某些功能可能无法正常工作${NC}"
    QUEUE_PID=""
else
    echo -e "${GREEN}队列工作进程运行正常${NC}"
fi

# 启动定时任务进程（如果启用）
if [ "$ENABLE_CRON" = "true" ]; then
    echo "正在启动定时任务进程..."
    php artisan schedule:work > /dev/null 2>&1 &
    CRON_PID=$!
    echo "定时任务进程已启动 (PID: $CRON_PID)"
    
    # 等待定时任务进程启动
    sleep 2
    
    # 检查定时任务进程是否成功启动
    if ! kill -0 $CRON_PID 2>/dev/null; then
        echo -e "${YELLOW}警告: 定时任务进程启动失败${NC}"
        CRON_PID=""
    else
        echo -e "${GREEN}定时任务进程运行正常${NC}"
    fi
fi

echo ""
echo -e "${GREEN}===== Mosure 系统启动成功 =====${NC}"
echo ""
echo "访问地址："
echo "  - 管理后台: http://localhost:9445"
echo "  - 安装向导: http://localhost:9445/install"
echo ""
echo "运行的服务："
echo "  - PHP Artisan 服务器 (PID: $PHP_PID)"
echo "  - 队列工作进程 (PID: $QUEUE_PID) [${MODE}模式]"
if [ ! -z "$CRON_PID" ]; then
    echo "  - 定时任务进程 (PID: $CRON_PID)"
fi
echo ""
echo -e "${YELLOW}提示: 按 Ctrl+C 停止服务${NC}"
echo ""
echo "使用说明："
echo "  - 开发模式: ./start.sh"
echo "  - 正式模式: ./start.sh -prod"
echo "  - 启动定时任务: ./start.sh -cron 或 ./start.sh -prod -cron"
echo ""

# 等待任意一个进程结束
wait $PHP_PID $QUEUE_PID $CRON_PID
