import { Form, InputNumber } from 'antd';
import { FormItemProps } from 'antd';
import { middleOneType } from "../MiddleOne";
import React from 'react';

const NumInputComp = {
    typeId: 'select',
    typeName: '数字输入框',
    component: ({ child, form }: middleOneType) => {
        let formOneProp: FormItemProps = {
            'label': child.label,
            'name': child.field,
            'rules': child.rules,
            'initialValue': child.curValue
        }
        return (
            <Form.Item {...formOneProp} >
                <InputNumber />
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        return value
    }
};

export default NumInputComp;
