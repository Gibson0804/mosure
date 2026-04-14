#!/bin/bash

# 插件 plugin.json 自动生成脚本
# 用法: ./generate-plugin-json.sh <插件目录路径>

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 检查参数
if [ $# -eq 0 ]; then
    echo -e "${RED}错误: 请提供插件目录路径${NC}"
    echo "用法: $0 <插件目录路径>"
    echo "示例: $0 ./Plugins/todolist/v1_0_0"
    exit 1
fi

PLUGIN_DIR="$1"

# 检查目录是否存在
if [ ! -d "$PLUGIN_DIR" ]; then
    echo -e "${RED}错误: 目录不存在: $PLUGIN_DIR${NC}"
    exit 1
fi

PLUGIN_DIR=$(cd "$PLUGIN_DIR" && pwd)
PLUGIN_JSON="$PLUGIN_DIR/plugin.json"

echo -e "${GREEN}正在扫描插件目录: $PLUGIN_DIR${NC}"

# 初始化变量
PLUGIN_ID=""
PLUGIN_NAME=""
PLUGIN_DESCRIPTION=""
PLUGIN_AUTHOR=""
PLUGIN_VERSION=""
HAS_FRONTEND=false
HAS_SRC=false

# 检查是否存在 plugin.json
if [ -f "$PLUGIN_JSON" ]; then
    echo -e "${YELLOW}发现已存在的 plugin.json，将读取其基本信息${NC}"
    PLUGIN_ID=$(jq -r '.id // empty' "$PLUGIN_JSON" 2>/dev/null || echo "")
    PLUGIN_NAME=$(jq -r '.name // empty' "$PLUGIN_JSON" 2>/dev/null || echo "")
    PLUGIN_DESCRIPTION=$(jq -r '.description // empty' "$PLUGIN_JSON" 2>/dev/null || echo "")
    PLUGIN_AUTHOR=$(jq -r '.author // empty' "$PLUGIN_JSON" 2>/dev/null || echo "")
    PLUGIN_VERSION=$(jq -r '.version // empty' "$PLUGIN_JSON" 2>/dev/null || echo "")
fi

# 如果没有从 plugin.json 读取到基本信息，尝试从目录名推断
if [ -z "$PLUGIN_NAME" ]; then
    PLUGIN_NAME=$(basename "$PLUGIN_DIR")
    echo -e "${YELLOW}从目录名推断插件名称: $PLUGIN_NAME${NC}"
fi

if [ -z "$PLUGIN_VERSION" ]; then
    PLUGIN_VERSION="v1"
    echo -e "${YELLOW}使用默认版本: $PLUGIN_VERSION${NC}"
fi

if [ -z "$PLUGIN_ID" ]; then
    PLUGIN_ID="${PLUGIN_NAME}@${PLUGIN_VERSION}"
    echo -e "${YELLOW}生成插件ID: $PLUGIN_ID${NC}"
fi

# 扫描 models
echo -e "${GREEN}扫描 models 目录...${NC}"
MODELS_JSON="[]"
if [ -d "$PLUGIN_DIR/models" ]; then
    MODELS=$(find "$PLUGIN_DIR/models" -maxdepth 1 -name "*.json" -type f 2>/dev/null | sort)
    if [ -n "$MODELS" ]; then
        MODELS_ARRAY="["
        FIRST=true
        for model_file in $MODELS; do
            MODEL_NAME=$(basename "$model_file" .json)
            if [ "$FIRST" = true ]; then
                FIRST=false
            else
                MODELS_ARRAY+=","
            fi
            MODELS_ARRAY+="\"$MODEL_NAME\""
        done
        MODELS_ARRAY+="]"
        MODELS_JSON="$MODELS_ARRAY"
        echo -e "  找到模型: $(echo $MODELS | wc -w) 个"
    fi
fi

# 扫描 functions
echo -e "${GREEN}扫描 functions 目录...${NC}"
FUNCTIONS_ENDPOINTS="[]"
FUNCTIONS_HOOKS="[]"
FUNCTIONS_VARIABLES=false
FUNCTIONS_TRIGGERS=false
FUNCTIONS_SCHEDULES=false

if [ -d "$PLUGIN_DIR/functions" ]; then
    # 扫描 endpoints 函数
    if [ -d "$PLUGIN_DIR/functions/endpoints" ]; then
        ENDPOINT_FUNCS=$(find "$PLUGIN_DIR/functions/endpoints" -name "*.json" -type f 2>/dev/null | sort)
        if [ -n "$ENDPOINT_FUNCS" ]; then
            ENDPOINT_ARRAY="["
            FIRST=true
            for func_file in $ENDPOINT_FUNCS; do
                FUNC_NAME=$(basename "$func_file" .json)
                if [ "$FIRST" = true ]; then
                    FIRST=false
                else
                    ENDPOINT_ARRAY+=","
                fi
                ENDPOINT_ARRAY+="\"$FUNC_NAME\""
            done
            ENDPOINT_ARRAY+="]"
            FUNCTIONS_ENDPOINTS="$ENDPOINT_ARRAY"
            echo -e "  找到 endpoints 函数: $(echo $ENDPOINT_FUNCS | wc -w) 个"
        fi
    fi

    # 扫描 hooks 函数
    if [ -d "$PLUGIN_DIR/functions/hooks" ]; then
        HOOK_FUNCS=$(find "$PLUGIN_DIR/functions/hooks" -name "*.json" -type f 2>/dev/null | sort)
        if [ -n "$HOOK_FUNCS" ]; then
            HOOK_ARRAY="["
            FIRST=true
            for func_file in $HOOK_FUNCS; do
                FUNC_NAME=$(basename "$func_file" .json)
                if [ "$FIRST" = true ]; then
                    FIRST=false
                else
                    HOOK_ARRAY+=","
                fi
                HOOK_ARRAY+="\"$FUNC_NAME\""
            done
            HOOK_ARRAY+="]"
            FUNCTIONS_HOOKS="$HOOK_ARRAY"
            echo -e "  找到 hooks 函数: $(echo $HOOK_FUNCS | wc -w) 个"
        fi
    fi

    # 检查 variables.json
    if [ -f "$PLUGIN_DIR/functions/variables.json" ]; then
        FUNCTIONS_VARIABLES=true
        echo -e "  找到 variables.json"
    fi

    # 检查 triggers.json
    if [ -f "$PLUGIN_DIR/functions/triggers.json" ]; then
        FUNCTIONS_TRIGGERS=true
        echo -e "  找到 triggers.json"
    fi

    # 检查 schedules.json
    if [ -f "$PLUGIN_DIR/functions/schedules.json" ]; then
        FUNCTIONS_SCHEDULES=true
        echo -e "  找到 schedules.json"
    fi
fi

# 扫描 data
echo -e "${GREEN}扫描 data 目录...${NC}"
DATA_JSON="[]"
if [ -d "$PLUGIN_DIR/data" ]; then
    DATA_MODELS=$(find "$PLUGIN_DIR/data" -maxdepth 1 -mindepth 1 -type d 2>/dev/null | sort)
    if [ -n "$DATA_MODELS" ]; then
        DATA_ARRAY="["
        FIRST=true
        for data_dir in $DATA_MODELS; do
            MODEL_NAME=$(basename "$data_dir")
            # 检查目录下是否有json文件
            JSON_FILES=$(find "$data_dir" -name "*.json" -type f 2>/dev/null | wc -l)
            if [ "$JSON_FILES" -gt 0 ]; then
                if [ "$FIRST" = true ]; then
                    FIRST=false
                else
                    DATA_ARRAY+=","
                fi
                DATA_ARRAY+="\"$MODEL_NAME\""
            fi
        done
        DATA_ARRAY+="]"
        DATA_JSON="$DATA_ARRAY"
        echo -e "  找到数据模型: $(echo $DATA_JSON | jq 'length') 个"
    fi
fi

# 扫描 menus
echo -e "${GREEN}扫描 menus 目录...${NC}"
MENUS_JSON="[]"
if [ -d "$PLUGIN_DIR/menus" ]; then
    MENUS=$(find "$PLUGIN_DIR/menus" -name "*.json" -type f 2>/dev/null | sort)
    if [ -n "$MENUS" ]; then
        MENUS_ARRAY="["
        FIRST=true
        for menu_file in $MENUS; do
            MENU_NAME=$(basename "$menu_file" .json)
            if [ "$FIRST" = true ]; then
                FIRST=false
            else
                MENUS_ARRAY+=","
            fi
            MENUS_ARRAY+="\"$MENU_NAME\""
        done
        MENUS_ARRAY+="]"
        MENUS_JSON="$MENUS_ARRAY"
        echo -e "  找到菜单: $(echo $MENUS | wc -w) 个"
    fi
fi

# 检查是否有前端
if [ -d "$PLUGIN_DIR/frontend" ] || [ -d "$PLUGIN_DIR/src" ]; then
    HAS_FRONTEND=true
    echo -e "${GREEN}发现前端资源${NC}"
fi

if [ -d "$PLUGIN_DIR/src" ]; then
    HAS_SRC=true
fi

# 生成 plugin.json
echo -e "${GREEN}生成 plugin.json 文件...${NC}"

cat > "$PLUGIN_JSON" << EOF
{
  "id": "$PLUGIN_ID",
  "name": "$PLUGIN_NAME",
  "description": "$PLUGIN_DESCRIPTION",
  "author": "$PLUGIN_AUTHOR",
  "version": "$PLUGIN_VERSION",
  "has_frontend": $HAS_FRONTEND,
  "has_src": $HAS_SRC,
  "provides": {
    "models": $MODELS_JSON,
    "functions": {
      "endpoints": $FUNCTIONS_ENDPOINTS,
      "hooks": $FUNCTIONS_HOOKS,
      "variables": $FUNCTIONS_VARIABLES,
      "triggers": $FUNCTIONS_TRIGGERS,
      "schedules": $FUNCTIONS_SCHEDULES
    },
    "data": $DATA_JSON,
    "menus": $MENUS_JSON
  }
}
EOF

# 格式化 JSON
if command -v jq &> /dev/null; then
    jq '.' "$PLUGIN_JSON" > "${PLUGIN_JSON}.tmp"
    mv "${PLUGIN_JSON}.tmp" "$PLUGIN_JSON"
fi

echo -e "${GREEN}✓ plugin.json 已生成/更新: $PLUGIN_JSON${NC}"
echo ""
echo "生成的文件内容:"
echo "----------------------------------------"
cat "$PLUGIN_JSON"
echo "----------------------------------------"
