import { Form, Input } from 'antd';
import { FormItemProps } from 'antd';
import { middleOneType } from "../MiddleOne";
import React from 'react';

const { TextArea } = Input;

const TextAreaComp = {
    typeName: '多行文本',
    component: ({ child, form }: middleOneType) => {
        const formOneProp = {
            'label': child.label,
            'name': child.field,
            'rules': child.rules,
            'initialValue': child.curValue
        }
        return (
            <Form.Item {...formOneProp} >
                <TextArea
                    rows={4}
                    showCount={{ formatter: ({ count }) => `${count} ` }}
                />
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        return value
    }
};

export default TextAreaComp;
