import { Form, DatePicker } from 'antd';
import { FormItemProps } from 'antd';
import { middleOneType } from "../MiddleOne";
import React from 'react';
import { SchemasChildType } from '../../context/SchemaContext';
import dayjs from 'dayjs';

const { RangePicker } = DatePicker;

const clickLabel = (child: SchemasChildType) => {
    return (
        <span>
            {child.label}
        </span>
    )
}

const DateRangePickerComp = {
    typeName: '日期区间',
    component: ({ child, form }: middleOneType) => {
        const childAny = child as any;
        
        // 处理初始值：支持 JSON 字符串或数组格式
        let initialValue = child.curValue;
        if (initialValue) {
            // 如果是 JSON 字符串，先解析
            if (typeof initialValue === 'string') {
                try {
                    initialValue = JSON.parse(initialValue);
                } catch (e) {
                    initialValue = null;
                }
            }
            // 转换为 dayjs 对象
            if (initialValue && Array.isArray(initialValue) && initialValue.length === 2) {
                initialValue = [
                    initialValue[0] ? dayjs(initialValue[0]) : null,
                    initialValue[1] ? dayjs(initialValue[1]) : null
                ];
            }
        }
        
        let formOneProp: FormItemProps = {
            'label': clickLabel(child),
            'name': child.field,
            'rules': child.rules,
            'initialValue': initialValue
        }

        const format = childAny.format || 'YYYY-MM-DD';
        const placeholder = childAny.placeholder || ['开始日期', '结束日期'];

        return (
            <Form.Item {...formOneProp} >
                <RangePicker 
                    format={format}
                    placeholder={placeholder}
                    style={{ width: '100%' }}
                />
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        // 将 dayjs 对象数组转换为 JSON 字符串存储
        if (value && Array.isArray(value) && value.length === 2) {
            const dateArray = [
                value[0] ? value[0].format('YYYY-MM-DD') : null,
                value[1] ? value[1].format('YYYY-MM-DD') : null
            ];
            return JSON.stringify(dateArray);
        }
        return ''
    }
};

export default DateRangePickerComp;
