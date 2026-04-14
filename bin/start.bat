@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

REM 解析命令行参数
set MODE=dev
set ENABLE_CRON=false

:parse_args
if "%1"=="-prod" (
    set MODE=prod
    shift
    goto parse_args
)
if "%1"=="-cron" (
    set ENABLE_CRON=true
    shift
    goto parse_args
)
if not "%1"=="" (
    shift
    goto parse_args
)

echo ===== 启动 Mosure 系统 =====
echo.

if "%MODE%"=="dev" (
    echo [信息] 当前模式: 开发模式
    echo [提示] 开发模式下使用 queue:listen，修改代码后无需重启即可生效
    echo [提示] 定时任务默认不启动，如需启动请使用 -cron 参数
) else (
    echo [信息] 当前模式: 正式模式
    echo [提示] 正式模式使用 queue:work，性能更优但修改代码后需要重启
    echo [提示] 定时任务默认不启动，如需启动请使用 -cron 参数
)
echo.

REM 检查 PHP 是否安装
where php >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] PHP 未安装或不在 PATH 中
    echo 请先安装 PHP 8.2 或更高版本
    pause
    exit /b 1
)

REM 检查 PHP 版本
for /f "tokens=*" %%i in ('php -r "echo PHP_VERSION;"') do set PHP_VERSION=%%i
echo 当前 PHP 版本: %PHP_VERSION%

REM 检查 Node.js 是否安装
where node >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] Node.js 未安装或不在 PATH 中
    echo 请先安装 Node.js 18 或更高版本
    pause
    exit /b 1
)

REM 检查 npm 是否安装
where npm >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] npm 未安装或不在 PATH 中
    echo 请先安装 npm
    pause
    exit /b 1
)

for /f "tokens=*" %%i in ('node -v') do set NODE_VERSION=%%i
for /f "tokens=*" %%i in ('npm -v') do set NPM_VERSION=%%i
echo 当前 Node.js 版本: %NODE_VERSION%
echo 当前 npm 版本: %NPM_VERSION%

REM 检查 composer 是否安装
where composer >nul 2>&1
if %errorlevel% neq 0 (
    echo [警告] composer 未安装，某些功能可能无法使用
)

REM 检查 .env 文件是否存在
if not exist .env (
    echo [警告] .env 文件不存在，正在从 .env.example 复制...
    if exist .env.example (
        copy .env.example .env >nul
        echo 已创建 .env 文件，请根据需要修改配置
    ) else (
        echo [错误] .env.example 文件不存在
        pause
        exit /b 1
    )
)

REM 检查 vendor 目录是否存在
if not exist vendor (
    echo [警告] vendor 目录不存在，正在运行 composer install...
    where composer >nul 2>&1
    if %errorlevel% equ 0 (
        composer install
    ) else (
        echo [错误] 需要安装 composer 依赖
        pause
        exit /b 1
    )
)

REM 检查 node_modules 是否存在
if not exist node_modules (
    echo [警告] node_modules 目录不存在，正在运行 npm install...
    call npm install
)

REM 启动 PHP Artisan 服务器
echo 正在启动 PHP Artisan 服务器...
start /B php artisan serve --host=0.0.0.0 --port=9445 >nul 2>&1
set PHP_PID=%errorlevel%
echo PHP Artisan 服务器已启动

REM 等待 PHP 服务器启动
timeout /t 2 /nobreak >nul

REM 启动前端开发服务器
echo 正在启动前端开发服务器...
start /B npm run dev >nul 2>&1
set NPM_PID=%errorlevel%
echo 前端开发服务器已启动

REM 等待前端服务器启动
timeout /t 3 /nobreak >nul

REM 启动队列工作进程
echo 正在启动队列工作进程...
if "%MODE%"=="dev" (
    REM 开发模式使用 queue:listen，修改代码后无需重启
    start /B php artisan queue:listen --tries=3 --timeout=300 >nul 2>&1
) else (
    REM 正式模式使用 queue:work，性能更优
    start /B php artisan queue:work --daemon --tries=3 --timeout=300 >nul 2>&1
)
set QUEUE_PID=%errorlevel%
echo 队列工作进程已启动

REM 等待队列进程启动
timeout /t 2 /nobreak >nul

REM 启动定时任务进程（如果启用）
if "%ENABLE_CRON%"=="true" (
    echo 正在启动定时任务进程...
    start /B php artisan schedule:work >nul 2>&1
    echo 定时任务进程已启动
    REM 等待定时任务进程启动
    timeout /t 2 /nobreak >nul
)

echo.
echo ===== Mosure 系统启动成功 =====
echo.
echo 访问地址：
echo   - 管理后台: http://localhost:9445
echo   - 安装向导: http://localhost:9445/install
echo.
echo 运行的服务：
echo   - PHP Artisan 服务器
echo   - 前端开发服务器
echo   - 队列工作进程 [%MODE%模式]
if "%ENABLE_CRON%"=="true" (
    echo   - 定时任务进程
)
echo.
echo 按 Ctrl+C 停止服务
echo.
echo 使用说明：
echo   - 开发模式: start.bat
echo   - 正式模式: start.bat -prod
echo   - 启动定时任务: start.bat -cron 或 start.bat -prod -cron
echo.

REM 等待用户按键
pause >nul

REM 清理进程
echo.
echo 正在停止服务...
taskkill /F /IM php.exe >nul 2>&1
taskkill /F /IM node.exe >nul 2>&1
echo 所有服务已停止

endlocal
