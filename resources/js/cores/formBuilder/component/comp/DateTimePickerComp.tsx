import { DatePicker, Form } from 'antd';
import { FormItemProps } from 'antd';
import { middleOneType } from "../MiddleOne";
import dayjs from 'dayjs';
import { DataTimeFormat } from '../../utils/DefineUtil';
import React from 'react';

const DateTimePickerComp = {
    typeId: 'select',
    typeName: '日期时间',
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
                formOneProp.initialValue = dayjs(child.curValue, DataTimeFormat)
            }
        }
        return (
            <Form.Item {...formOneProp} >
                <DatePicker showTime format={DataTimeFormat} />
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        return value.format(DataTimeFormat)
    }
};

export default DateTimePickerComp;
