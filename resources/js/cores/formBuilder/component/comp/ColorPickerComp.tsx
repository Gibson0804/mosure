import { ColorPicker, Form } from 'antd';
import { FormItemProps } from 'antd';
import { middleOneType } from "../MiddleOne";
import type { Color } from 'antd/es/color-picker';
import React from 'react';

const ColorPickerComp = {
    typeId: 'select',
    typeName: '颜色选择器',
    component: ({ child, form }: middleOneType) => {
        const formOneProp: FormItemProps = {
            label: child.label,
            name: child.field,
            rules: child.rules,
            required: child.rules?.some((rule: any) => rule.required)
        };

        if (child.curValue) {
            formOneProp.initialValue = child.curValue;
        }

        const formOne = (
            <ColorPicker
                showText
                style={{ width: '100%' }}
                presets={[
                    {
                        label: '推荐',
                        colors: [
                            '#000000',
                            '#ffffff',
                            '#ff4d4f',
                            '#faad14',
                            '#52c41a',
                            '#1890ff',
                            '#722ed1',
                            '#eb2f96',
                        ],
                    },
                ]}
            />
        );

        return <Form.Item {...formOneProp}>{formOne}</Form.Item>;
    },
    formatValue: (value: any) => {
        if (typeof value === 'string') return value;
        if (value.toHexString) return value.toHexString();
        return '';
    }
};

export default ColorPickerComp;


    // typeId: 'select',
    // typeName: '颜色选择器',
    // component: ({ child, form }: middleOneType) => {
    //     let formOneProp: FormItemProps = {
    //         'label': child.label,
    //         'name': child.field,
    //         'rules': child.rules,
    //         'initialValue': child.curValue
    //     }
    //     return (
    //         <Form.Item {...formOneProp} >
    //             <ColorPicker showText />
    //         </Form.Item>
    //     )
    // },
    // formatValue: (value: any) => {
    //     if (typeof value == 'object') {
    //         return value.toHexString()
    //     }
    //     return value
    // }