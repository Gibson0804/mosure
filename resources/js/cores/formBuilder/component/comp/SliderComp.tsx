import { Form, Slider } from 'antd';
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

const SliderComp = {
    typeName: '滑块',
    component: ({ child, form }: middleOneType) => {
        const childAny = child as any;
        
        let formOneProp: FormItemProps = {
            'label': clickLabel(child),
            'name': child.field,
            'rules': child.rules,
            'initialValue': child.curValue || childAny.default || 0
        }

        const min = childAny.min !== undefined ? childAny.min : 0;
        const max = childAny.max !== undefined ? childAny.max : 100;
        const step = childAny.step !== undefined ? childAny.step : 1;
        const marks = childAny.marks || undefined;

        return (
            <Form.Item {...formOneProp} >
                <Slider 
                    min={min} 
                    max={max} 
                    step={step}
                    marks={marks}
                />
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        return value !== undefined ? value : 0
    }
};

export default SliderComp;
