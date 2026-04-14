import { Form, Input, message, Radio, Select, Space, Tabs } from 'antd';
import { FormItemProps } from 'antd';
import { middleOneType } from "../MiddleOne";
import React from 'react';
import { useModelContentOptions, normalizeStaticOptions } from './common/sharedOptions';
import { fetchModelsAndFieldsOnce, loadModelFieldsFromCache } from './common/sharedModels';
import OptionsSourcePanel from './common/OptionsSourcePanel';

const RadioComp = {
    typeName: '单选框',
    component: ({ child, form }: middleOneType) => {
        const formOneProp = {
            'label': child.label,
            'name': child.field,
            'rules': child.rules,
            'initialValue': child.curValue
        }
        const { options: dynamicOptions, isModelSource } = useModelContentOptions(child);
        const safeOptions = isModelSource ? (dynamicOptions ?? []) : normalizeStaticOptions((child as any).options);
        return (
            <Form.Item {...formOneProp}>
                <Radio.Group options={safeOptions} />
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        return value
    },
    canChangeParam: (pickChildren, schemasDispatch) => {
        return (<OptionsSourcePanel pickChildren={pickChildren} schemasDispatch={schemasDispatch} />)
    }
};

export default RadioComp;
