import { Form, Input } from 'antd';
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
const InputComp = {
    typeName: '单行文本',
    component: ({ child, form }: middleOneType) => {

        let formOneProp: FormItemProps = {
            'label': clickLabel(child),
            'name': child.field,
            'rules': child.rules,
            'initialValue': child.curValue
        }

        return (
            <Form.Item {...formOneProp} >
                <Input />
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        return value
    }
};

export default InputComp;
