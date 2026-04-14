import { Form, Rate } from 'antd';
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

const RateComp = {
    typeName: '评分',
    component: ({ child, form }: middleOneType) => {
        const childAny = child as any;
        
        let formOneProp: FormItemProps = {
            'label': clickLabel(child),
            'name': child.field,
            'rules': child.rules,
            'initialValue': child.curValue || childAny.default || 0
        }

        const count = childAny.count !== undefined ? childAny.count : 5;
        const allowHalf = childAny.allowHalf !== undefined ? childAny.allowHalf : false;

        return (
            <Form.Item {...formOneProp} >
                <Rate 
                    count={count} 
                    allowHalf={allowHalf}
                />
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        return value !== undefined ? value : 0
    }
};

export default RateComp;
