#!/bin/bash

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PLUGINS_DIR="$PROJECT_ROOT/Plugins"

print_info() {
    echo -e "${BLUE}[信息]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[成功]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[警告]${NC} $1"
}

print_error() {
    echo -e "${RED}[错误]${NC} $1"
}

read_input() {
    local prompt="$1"
    local default="$2"
    local result

    if [ -n "$default" ]; then
        read -p "$prompt [$default]: " result
        echo "${result:-$default}"
    else
        read -p "$prompt: " result
        echo "$result"
    fi
}

read_confirm() {
    local prompt="$1"
    local response

    read -p "$prompt [y/N]: " response
    case "$response" in
        [yY][eE][sS]|[yY]) return 0 ;;
        *) return 1 ;;
    esac
}

validate_plugin_id() {
    local id="$1"
    if [[ ! $id =~ ^[a-z][a-z0-9]*$ ]]; then
        print_error "插件目录名必须以小写字母开头，只能包含小写字母和数字，不能包含连字符、下划线或大写字母"
        return 1
    fi
    return 0
}

validate_version() {
    local version="$1"
    if [[ ! $version =~ ^v[0-9]+$ ]]; then
        print_error "版本号格式必须为 v1、v2 这类形式"
        return 1
    fi
    return 0
}

create_plugin_structure() {
    local plugin_dir="$1"
    local has_frontend="$2"
    local has_src="$3"

    mkdir -p "$plugin_dir/models"
    mkdir -p "$plugin_dir/functions/endpoints"
    mkdir -p "$plugin_dir/functions/hooks"
    mkdir -p "$plugin_dir/menus"
    mkdir -p "$plugin_dir/data"

    if [ "$has_frontend" = "true" ]; then
        mkdir -p "$plugin_dir/frontend/dist/assets"
    fi

    if [ "$has_src" = "true" ]; then
        mkdir -p "$plugin_dir/src"
    fi
}

generate_plugin_json() {
    local plugin_dir="$1"
    local plugin_id="$2"
    local plugin_name="$3"
    local plugin_description="$4"
    local plugin_author="$5"
    local version="$6"
    local has_frontend="$7"
    local has_src="$8"

    cat > "$plugin_dir/plugin.json" << EOF
{
  "id": "${plugin_id}_${version}",
  "name": "$plugin_name",
  "description": "$plugin_description",
  "author": "$plugin_author",
  "version": "$version",
  "has_frontend": $has_frontend,
  "has_src": $has_src,
  "provides": {
    "models": [],
    "functions": {
      "endpoints": [],
      "hooks": [],
      "variables": false,
      "triggers": false,
      "schedules": false
    },
    "data": [],
    "menus": []
  }
}
EOF
}

generate_plugin_class() {
    local plugin_dir="$1"
    local plugin_id="$2"
    local version_dir="$3"
    local plugin_name="$4"

    cat > "$plugin_dir/$plugin_id.php" << EOF
<?php

namespace Plugins\\${plugin_id}\\${version_dir};

use Plugins\AbstractPlugin;
use Illuminate\Support\Facades\Log;

class ${plugin_id} extends AbstractPlugin
{
    public function install(string \$projectPrefix): bool
    {
        Log::info("${plugin_id}_${version_dir} installing for {\$projectPrefix}");
        return parent::install(\$projectPrefix);
    }

    public function uninstall(string \$projectPrefix): bool
    {
        Log::info("${plugin_id}_${version_dir} uninstalling for {\$projectPrefix}");
        return parent::uninstall(\$projectPrefix);
    }

    public function onAfterInstall(string \$projectPrefix): void
    {
        Log::info("${plugin_name} installed for {\$projectPrefix}");
    }
}
EOF
}

generate_function_configs() {
    local plugin_dir="$1"

    cat > "$plugin_dir/functions/variables.json" << EOF
[]
EOF

    cat > "$plugin_dir/functions/triggers.json" << EOF
[]
EOF

    cat > "$plugin_dir/functions/schedules.json" << EOF
[]
EOF
}

generate_frontend_files() {
    local plugin_dir="$1"
    local plugin_id="$2"
    local plugin_name="$3"
    local version="$4"

    cat > "$plugin_dir/frontend/manifest.json" << EOF
{
  "plugin_id": "$plugin_id",
  "version": "$version",
  "entry_point": "index.html",
  "spa_mode": true,
  "files": [
    "index.html",
    "assets/main.js",
    "assets/main.css"
  ]
}
EOF

    cat > "$plugin_dir/frontend/dist/config.js" << EOF
window.API_CONFIG = {
  domain: "",
  apiKey: ""
};
EOF

    cat > "$plugin_dir/frontend/dist/index.html" << EOF
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>$plugin_name</title>
  <link rel="stylesheet" href="assets/main.css">
</head>
<body>
  <div id="app">
    <h1>$plugin_name</h1>
    <p>插件前端页面占位文件。</p>
  </div>
  <script src="config.js"></script>
  <script src="assets/main.js"></script>
</body>
</html>
EOF

    cat > "$plugin_dir/frontend/dist/assets/main.css" << EOF
body {
  font-family: sans-serif;
  margin: 0;
  padding: 24px;
}
EOF

    cat > "$plugin_dir/frontend/dist/assets/main.js" << EOF
console.log('$plugin_name frontend loaded');
EOF
}

generate_readme() {
    local plugin_dir="$1"
    local plugin_id="$2"
    local plugin_name="$3"
    local version="$4"

    cat > "$plugin_dir/README.md" << EOF
# $plugin_name

- 插件目录：\`Plugins/$plugin_id/$version\`
- 插件 ID：\`${plugin_id}_${version}\`

## 目录结构

\`\`\`text
$plugin_id/$version/
├── plugin.json
├── $plugin_id.php
├── models/
├── functions/
│   ├── endpoints/
│   ├── hooks/
│   ├── variables.json
│   ├── triggers.json
│   └── schedules.json
├── menus/
├── data/
└── frontend/
\`\`\`

## 说明

- 当前 Mosure 安装器实际识别的是 \`functions/endpoints\` 和 \`functions/hooks\`
- \`provides\` 是声明信息，真实安装仍以目录扫描为主
- 若需要前端页面，请将构建产物放入 \`frontend/dist\`
EOF
}

main() {
    if [ ! -d "$PLUGINS_DIR" ]; then
        print_error "插件目录不存在: $PLUGINS_DIR"
        exit 1
    fi

    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}    Mosure 插件生成器${NC}"
    echo -e "${GREEN}========================================${NC}"

    while true; do
        plugin_id=$(read_input "插件目录名" "")
        if validate_plugin_id "$plugin_id"; then
            break
        fi
    done

    plugin_name=$(read_input "插件名称" "$plugin_id")
    plugin_description=$(read_input "插件描述" "")
    plugin_author=$(read_input "作者" "Mosure Team")
    version=$(read_input "版本号" "v1")
    while ! validate_version "$version"; do
        version=$(read_input "版本号" "v1")
    done

    has_frontend="false"
    if read_confirm "是否需要前端页面？"; then
        has_frontend="true"
    fi

    has_src="false"
    if read_confirm "是否需要 src 目录？"; then
        has_src="true"
    fi

    plugin_dir="$PLUGINS_DIR/$plugin_id/$version"

    if [ -d "$plugin_dir" ]; then
        print_error "插件目录已存在: $plugin_dir"
        if ! read_confirm "是否覆盖？"; then
            exit 0
        fi
        rm -rf "$plugin_dir"
    fi

    print_info "开始创建插件..."

    mkdir -p "$plugin_dir"
    create_plugin_structure "$plugin_dir" "$has_frontend" "$has_src"
    generate_plugin_json "$plugin_dir" "$plugin_id" "$plugin_name" "$plugin_description" "$plugin_author" "$version" "$has_frontend" "$has_src"
    generate_plugin_class "$plugin_dir" "$plugin_id" "$version" "$plugin_name"
    generate_function_configs "$plugin_dir"
    generate_readme "$plugin_dir" "$plugin_id" "$plugin_name" "$version"

    if [ "$has_frontend" = "true" ]; then
        generate_frontend_files "$plugin_dir" "$plugin_id" "$plugin_name" "$version"
    fi

    print_success "插件创建完成"
    print_info "插件目录: $plugin_dir"
    print_info "plugin.json id: ${plugin_id}_${version}"
}

main
