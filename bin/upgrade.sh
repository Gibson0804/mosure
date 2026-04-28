#!/bin/bash

# Allow invocation via "sh bin/upgrade.sh" by re-execing under bash.
if [ -z "${BASH_VERSION:-}" ]; then
    exec /bin/bash "$0" "$@"
fi

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SUPERVISOR_CMD="${SUPERVISOR_CMD:-supervisorctl}"
SUPERVISOR_PROGRAMS="${SUPERVISOR_PROGRAMS:-mosure-queue}"
RUN_COMPOSER="${RUN_COMPOSER:-1}"
ALLOW_DIRTY="${ALLOW_DIRTY:-0}"
SKIP_PULL="${SKIP_PULL:-0}"

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    cat <<'EOF'
Mosure 升级脚本

默认流程：
1) git pull --ff-only
2) composer install --no-dev --optimize-autoloader（可关闭）
3) php artisan migrate --force
4) supervisorctl reread/update + restart 队列/定时任务进程

环境变量：
  ALLOW_DIRTY=1         允许在工作区有本地修改时继续（默认 0）
  SKIP_PULL=1           跳过 git pull（默认 0）
  RUN_COMPOSER=0        跳过 composer install（默认 1）
  SUPERVISOR_CMD=...    supervisor 命令（默认 supervisorctl）
  SUPERVISOR_PROGRAMS   需要重启的进程，空格分隔（默认 "mosure-queue"）

示例：
  ./bin/upgrade.sh
  ALLOW_DIRTY=1 SKIP_PULL=1 ./bin/upgrade.sh
  SUPERVISOR_PROGRAMS="mosure-queue mosure-scheduler" ./bin/upgrade.sh
EOF
    exit 0
fi

echo "===== Mosure 升级开始 ====="

if ! command -v git >/dev/null 2>&1; then
    echo "错误: 未找到 git"
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    echo "错误: 未找到 php"
    exit 1
fi

ensure_git_safe_directory() {
    local git_check_output

    if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        return 0
    fi

    git_check_output="$(git rev-parse --is-inside-work-tree 2>&1 || true)"
    if echo "$git_check_output" | grep -q "detected dubious ownership"; then
        echo "检测到 Git safe.directory 限制，正在自动授权: $ROOT_DIR"
        git config --global --add safe.directory "$ROOT_DIR"
        git rev-parse --is-inside-work-tree >/dev/null
        return 0
    fi

    echo "错误: Git 仓库检查失败"
    echo "$git_check_output"
    exit 1
}

ensure_git_safe_directory

if [[ "$ALLOW_DIRTY" != "1" ]]; then
    if [[ -n "$(git status --porcelain)" ]]; then
        echo "错误: 当前工作区有未提交修改。请先提交/暂存后再升级，或设置 ALLOW_DIRTY=1。"
        exit 1
    fi
fi

if [[ "$SKIP_PULL" != "1" ]]; then
    echo "[1/4] 拉取远程代码..."
    git fetch --all --prune
    git pull --ff-only
else
    echo "[1/4] 已跳过 git pull (SKIP_PULL=1)"
fi

if [[ "$RUN_COMPOSER" == "1" ]]; then
    if ! command -v composer >/dev/null 2>&1; then
        echo "错误: 未找到 composer"
        exit 1
    fi
    echo "[2/4] 更新后端依赖..."
    composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
else
    echo "[2/4] 已跳过 composer install (RUN_COMPOSER=0)"
fi

echo "[3/4] 执行数据库迁移..."
php artisan migrate --force

echo "[4/4] 重启 Supervisor 管理进程..."
if ! command -v "$SUPERVISOR_CMD" >/dev/null 2>&1; then
    echo "错误: 未找到 ${SUPERVISOR_CMD}"
    exit 1
fi

"$SUPERVISOR_CMD" reread || true
"$SUPERVISOR_CMD" update || true

for program in $SUPERVISOR_PROGRAMS; do
    echo "重启进程: ${program}"
    if ! "$SUPERVISOR_CMD" restart "$program"; then
        echo "警告: 进程 ${program} 不存在或重启失败，已跳过。"
    fi
done

echo "===== Mosure 升级完成 ====="
