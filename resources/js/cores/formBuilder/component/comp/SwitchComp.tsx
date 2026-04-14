import { Form, Switch } from 'antd';
import { FormItemProps } from 'antd';
import { middleOneType } from "../MiddleOne";
import React from 'react';

const SwitchComp = {
    typeName: '开关',
    component: ({ child, form }: middleOneType) => {
        let formOneProp: FormItemProps = {
            'label': child.label,
            'name': child.field,
            'rules': child.rules,
            'initialValue': child.curValue == 1
        }
        return (
            <Form.Item {...formOneProp} >
                <Switch
                    checkedChildren="开启"
                    unCheckedChildren="关闭"
                    defaultChecked
                />
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        return value
    }
};

export default SwitchComp;
