@echo off
chcp 65001 >nul
setlocal

echo ===== Mosure 一键安装 =====

where php >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] 未找到 PHP，请先安装 PHP 8.2 或更高版本
    exit /b 1
)

where composer >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] 未找到 Composer
    exit /b 1
)

if not exist vendor (
    echo 正在安装 Composer 依赖...
    call composer install
    if %errorlevel% neq 0 exit /b 1
)

if not exist .env (
    echo 正在初始化 .env...
    copy .env.example .env >nul
)

if exist package.json (
    if not exist public\build\manifest.json (
        where npm >nul 2>&1
        if %errorlevel% equ 0 (
            if not exist node_modules (
                echo 正在安装 npm 依赖...
                call npm install
                if %errorlevel% neq 0 exit /b 1
            )
            echo 正在构建前端资源...
            call npm run build
            if %errorlevel% neq 0 exit /b 1
        ) else (
            echo [警告] 未检测到 npm，跳过前端构建。请确认 public\build 已存在。
        )
    )
)

call php artisan mosure:install --no-interaction %*
if %errorlevel% neq 0 exit /b 1

echo.
echo 安装完成。可执行 bin\start.bat 启动本地开发环境。

endlocal
