import { Form, Select } from 'antd';
import { FormItemProps } from 'antd';
import { middleOneType } from "../MiddleOne";
import React from 'react';
import { useModelContentOptions, normalizeStaticOptions } from './common/sharedOptions';
import OptionsSourcePanel from './common/OptionsSourcePanel';

// 参数编辑面板改为复用 OptionsSourcePanel

const SelectComp = {
    typeId: 'select',
    typeName: '下拉选择',
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
        // 如果来源为模型，根据模型与字段动态拉取内容选项
        const { options: dynamicOptions, isModelSource } = useModelContentOptions(child);

        const safeOptions = isModelSource ? (dynamicOptions ?? []) : normalizeStaticOptions((child as any).options);

        return (
            <Form.Item {...formOneProp} >
                <Select
                    options={safeOptions}
                    mode="multiple"
                />
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

export default SelectComp;
