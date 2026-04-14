import { Checkbox, Form } from 'antd';
import { FormItemProps } from 'antd';
import { middleOneType } from "../MiddleOne";
import React from 'react';
import api from '../../../../util/Service';
import { useModelContentOptions, normalizeStaticOptions } from './common/sharedOptions';
import OptionsSourcePanel from './common/OptionsSourcePanel';


const CheckboxComp = {
    typeId: 'select',
    typeName: '多选框',
    component: ({ child, form }: middleOneType) => {
        let formOneProp: FormItemProps = {
            'label': child.label,
            'name': child.field,
            'rules': child.rules,
        }
        if (child.curValue != '' && typeof child.curValue == 'string') {
            //按,分隔成数组
            formOneProp.initialValue = child.curValue.split(',')
        }
        const { options: dynamicOptions, isModelSource } = useModelContentOptions(child);
        const safeOptions = isModelSource ? (dynamicOptions ?? []) : normalizeStaticOptions((child as any).options);
        return (
            <Form.Item {...formOneProp} >
                <Checkbox.Group options={safeOptions} />
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        if (value == null) return '';
        return Array.isArray(value) ? value.join(',') : String(value);
    },
    canChangeParam: (pickChildren, schemasDispatch) => {
        return (<OptionsSourcePanel pickChildren={pickChildren} schemasDispatch={schemasDispatch} />)
    },
};

export default CheckboxComp;
