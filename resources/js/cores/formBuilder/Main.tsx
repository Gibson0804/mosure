import React, { useState, useContext, useEffect, CSSProperties, Component, useRef } from 'react';
import { FormItem } from './component/FormItem';
import { Middle } from './component/Middle';
import { RightBar } from './component/RightBar';
import { SchemasProvider, useSchemasDispatch, SchemasType, SchemasChildType } from './context/SchemaContext';

export type SchemasTypeOut = SchemasType
export type SchemasChildTypeOut = SchemasChildType


// 常量定义
const RIGHT_BAR_WIDTH = 300; // 右侧栏宽度

// 容器样式
const containerStyle: CSSProperties = {
    display: "flex",
    position: "relative",
    minHeight: "calc(100vh - 120px)" // 减去顶部导航和其他元素的高度
};

// 主要内容区域样式
const contentStyle: CSSProperties = {
    flex: "1 1 auto",
    paddingRight: `${RIGHT_BAR_WIDTH + 20}px`, // 留出右侧栏的空间加上一点间距
    boxSizing: "border-box",
    width: "100%"
};

// 右侧栏样式 - 始终固定在右侧
const rightBarStyle: CSSProperties = {
    width: `${RIGHT_BAR_WIDTH}px`,
    position: "absolute",
    top: 0,
    right: 0,
    bottom: 0,
    border: "1px solid #ddd",
    borderRadius: "4px",
    background: "#fff",
    boxShadow: "0 2px 8px rgba(0,0,0,0.1)",
    overflow: "auto",
    padding: "16px",
    boxSizing: "border-box"
}

type props = {
    initSchemas: SchemasType | null,
    handleSaveFunc: Function,
    errors:any
}

export const FormBuilder = ({initSchemas, errors, handleSaveFunc}:props) => {
    // 使用简化的布局，不再需要滚动监听和复杂的状态管理

    return (
        <>
            <SchemasProvider initSchemas={initSchemas}>
                <FormItem />
                <div style={containerStyle}>
                    <div style={contentStyle}>
                        <Middle handleSaveFunc={handleSaveFunc} />
                    </div>

                    <div style={rightBarStyle}>
                        <RightBar errors={errors} />
                    </div>
                </div>
            </SchemasProvider>
        </>
    )
};


