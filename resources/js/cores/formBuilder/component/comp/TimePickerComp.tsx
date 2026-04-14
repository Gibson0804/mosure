import { TimePicker, Form } from 'antd';
import { FormItemProps } from 'antd';
import { middleOneType } from "../MiddleOne";
import dayjs from 'dayjs';
import { TimeFormat } from '../../utils/DefineUtil';
import React from 'react';

const TimePickerComp = {
    typeName: '时间选择框',
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
                formOneProp.initialValue = dayjs(child.curValue, TimeFormat)
            }
        }
        return (
            <Form.Item {...formOneProp} >
                <TimePicker format={TimeFormat} />
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        return value.format(TimeFormat)
    }
};

export default TimePickerComp;
