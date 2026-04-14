import { Form, Cascader } from 'antd';
import { FormItemProps } from 'antd';
import { middleOneType } from "../MiddleOne";
import React from 'react';
import { SchemasChildType } from '../../context/SchemaContext';
import { useModelContentOptions } from './common/sharedOptions';
import CascaderOptionsPanel from './common/CascaderOptionsPanel';
import { chinaRegions } from './common/chinaRegions';

const clickLabel = (child: SchemasChildType) => {
    return (
        <span>
            {child.label}
        </span>
    )
}

const CascaderComp = {
    typeId: 'cascader',
    typeName: '级联选择',
    component: ({ child, form }: middleOneType) => {
        const childAny = child as any;
        
        // 处理初始值：支持 JSON 字符串或数组格式
        let initialValue = child.curValue;
        if (typeof initialValue === 'string' && initialValue) {
            try {
                initialValue = JSON.parse(initialValue);
            } catch (e) {
                initialValue = undefined;
            }
        }
        
        let formOneProp: FormItemProps = {
            'label': clickLabel(child),
            'name': child.field,
            'rules': child.rules,
            'initialValue': initialValue
        }

        // 支持三种数据源：预设选项、模型字段、自定义配置
        const { options: dynamicOptions, isModelSource } = useModelContentOptions(child);
        
        let options = chinaRegions; // 默认使用中国省市区数据
        
        if (isModelSource && dynamicOptions) {
            // 模型字段返回的数据需要是树形结构
            options = dynamicOptions;
        } else if (childAny.options && childAny.options.length > 0) {
            // 如果配置了自定义选项，使用自定义选项
            options = childAny.options;
        }

        const placeholder = childAny.placeholder || '请选择';
        const changeOnSelect = childAny.changeOnSelect !== undefined ? childAny.changeOnSelect : false;

        return (
            <Form.Item {...formOneProp} >
                <Cascader 
                    options={options}
                    placeholder={placeholder}
                    changeOnSelect={changeOnSelect}
                    style={{ width: '100%' }}
                />
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        // 将数组转为 JSON 字符串存储
        if (Array.isArray(value) && value.length > 0) {
            return JSON.stringify(value);
        }
        return ''
    },
    canChangeParam: (pickChildren: any, schemasDispatch: any) => {
        return (<CascaderOptionsPanel pickChildren={pickChildren} schemasDispatch={schemasDispatch} />)
    }
};

export default CascaderComp;
