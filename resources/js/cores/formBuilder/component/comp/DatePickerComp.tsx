import { DatePicker, Form } from 'antd';
import { FormItemProps } from 'antd';
import { middleOneType } from "../MiddleOne";
import dayjs from 'dayjs';
import React from 'react';
import { DataFormat } from '../../utils/DefineUtil';

const DatePickerComp = {
    typeId: 'select',
    typeName: '日期选择框',
    component: ({ child, form }: middleOneType) => {
        let formOneProp: FormItemProps = {
            'label': child.label,
            'name': child.field,
            'rules': child.rules,
        }
        if (child.curValue != 'Invalid Date') {
            if(child.curValue == undefined) {
                formOneProp.initialValue = dayjs()
            } else {
                formOneProp.initialValue = dayjs(child.curValue, DataFormat)
            }
        }
        return (
            <Form.Item {...formOneProp} >
                <DatePicker format={DataFormat} />
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        return value.format(DataFormat)
    }
};

export default DatePickerComp;
