import { Form, Select } from 'antd';
import { FormItemProps } from 'antd';
import { middleOneType } from "../MiddleOne";
import React from 'react';
import { SchemasChildType } from '../../context/SchemaContext';

const clickLabel = (child: SchemasChildType) => {
    return (
        <span>
            {child.label}
        </span>
    )
}

const TagsComp = {
    typeName: '标签',
    component: ({ child, form }: middleOneType) => {
        const childAny = child as any;
        
        // 处理初始值：支持 JSON 字符串或数组格式
        let initialValue = child.curValue || [];
        if (typeof initialValue === 'string' && initialValue) {
            try {
                initialValue = JSON.parse(initialValue);
            } catch (e) {
                initialValue = [];
            }
        }
        
        let formOneProp: FormItemProps = {
            'label': clickLabel(child),
            'name': child.field,
            'rules': child.rules,
            'initialValue': initialValue
        }

        const placeholder = childAny.placeholder || '输入后回车添加标签';
        const maxTags = childAny.maxTags || undefined;

        return (
            <Form.Item {...formOneProp} >
                <Select
                    mode="tags"
                    placeholder={placeholder}
                    maxCount={maxTags}
                    style={{ width: '100%' }}
                    tokenSeparators={[',']}
                />
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        // 将数组转为 JSON 字符串存储
        if (Array.isArray(value)) {
            return JSON.stringify(value);
        }
        return ''
    }
};

export default TagsComp;
