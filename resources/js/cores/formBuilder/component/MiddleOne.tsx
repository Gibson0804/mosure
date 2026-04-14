import type { UploadFile } from 'antd';
import { type SchemasChildType } from '../context/SchemaContext'
import PicUploadComp from './comp/PicUploadComp';
import InputComp from './comp/InputComp';
import TextAreaComp from './comp/TextAreaComp';
import RadioComp from './comp/RadioComp';
import SwitchComp from './comp/SwitchComp';
import CheckboxComp from './comp/CheckboxComp';
import SelectComp from './comp/SelectComp';
import NumInputComp from './comp/NumInputComp';
import ColorPickerComp from './comp/ColorPickerComp';
import DateTimePickerComp from './comp/DateTimePickerComp';
import DatePickerComp from './comp/DatePickerComp';
import TimePickerComp from './comp/TimePickerComp';
import FileUploadComp from './comp/FileUploadComp';
import RichTextComp from './comp/RichTextComp';
import DividingLineComp from './comp/DividingLineComp';
import SliderComp from './comp/SliderComp';
import RateComp from './comp/RateComp';
import CascaderComp from './comp/CascaderComp';
import DateRangePickerComp from './comp/DateRangePickerComp';
import TagsComp from './comp/TagsComp';
import PicGalleryComp from './comp/PicGalleryComp';

export type middleOneType = {
    child: SchemasChildType,
    form: any
}

export type itemCompType = {
    typeName: string,
    component: (props: middleOneType) => JSX.Element
    formatValue: any
    canChangeParam?: any
}

// 从任意格式的文件值中提取 URL
const extractUrl = (item: any): string => {
    if (typeof item === 'string') return item;
    if (item && typeof item === 'object') return item.url || item.response?.data?.url || '';
    return '';
};

// 安全地解析curValue（兼容新格式URL字符串/数组 和 旧格式对象数组）
export const getInitialFileList = (fileValue: any): UploadFile[] => {
    if (!fileValue) return [];

    try {
        // 如果已经是数组，提取URL
        if (Array.isArray(fileValue)) {
            return fileValue.map((item: any, i: number) => {
                const url = extractUrl(item);
                if (!url) return null;
                return { uid: String(i), name: url.split('/').pop() || `file-${i}`, status: 'done' as const, url };
            }).filter(Boolean) as UploadFile[];
        }

        if (typeof fileValue === 'string') {
            const trimmed = fileValue.trim();
            if (!trimmed) return [];

            // JSON 数组或对象
            if (trimmed.startsWith('[') || trimmed.startsWith('{')) {
                const parsed = JSON.parse(trimmed);
                const arr = Array.isArray(parsed) ? parsed : [parsed];
                return arr.map((item: any, i: number) => {
                    const url = extractUrl(item);
                    if (!url) return null;
                    return { uid: String(i), name: url.split('/').pop() || `file-${i}`, status: 'done' as const, url };
                }).filter(Boolean) as UploadFile[];
            }

            // 纯 URL 字符串（新格式：单文件）
            if (trimmed.startsWith('http') || trimmed.startsWith('/')) {
                return [{ uid: '0', name: trimmed.split('/').pop() || 'file', status: 'done' as const, url: trimmed }];
            }

            return [];
        }

        return [];
    } catch (e) {
        console.error('解析文件列表失败:', e);
        return [];
    }
};



//把ComponentMap改写成map类型
export const CompMap = new Map<string, itemCompType>(
    [
        ['input', InputComp],
        ['textarea', TextAreaComp],
        ['radio', RadioComp],
        ['switch', SwitchComp],
        ['checkbox', CheckboxComp],
        ['select', SelectComp],
        ['numInput', NumInputComp],
        ['colorPicker', ColorPickerComp],
        ['dateTimePicker', DateTimePickerComp],
        ['datePicker', DatePickerComp],
        ['timePicker', TimePickerComp],
        ['fileUpload', FileUploadComp],
        ['picUpload', PicUploadComp],
        ['picGallery', PicGalleryComp],
        ['richText', RichTextComp],
        ['dividingLine', DividingLineComp],
        ['slider', SliderComp],
        ['rate', RateComp],
        ['cascader', CascaderComp],
        ['dateRangePicker', DateRangePickerComp],
        ['tags', TagsComp],
    ]
)


export function MiddleOne({ child, form }: middleOneType) {
    if (!child.type) {
        return null
    }

    return CompMap.get(child.type)?.component({ child, form })
}

type middleFormatValue = {
    type: string,
    value: any
}

export function GetMiddleFormatValue({ type, value }: middleFormatValue) {
    try {
        const formatter = CompMap.get(type)?.formatValue;
        if (typeof formatter === 'function') {
            const o = formatter(value);
            return o;
        }
        return value;
    } catch (err) {
        console.error('GetMiddleFormatValue error:', type, err);
        return value;
    }
}

export function GetMiddleCanChangeParam(type: string, pickChildren: any, schemasDispatch: any) {

    let o = CompMap.get(type)
    let res = null
    if (o && o.canChangeParam != undefined) {
        res = o.canChangeParam(pickChildren, schemasDispatch)
    }

    return res

}
