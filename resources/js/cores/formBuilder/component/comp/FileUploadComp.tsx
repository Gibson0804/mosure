import { Upload, Form, Modal, message, Button } from 'antd';
import { UploadOutlined } from '@ant-design/icons';
import { FormItemProps } from 'antd';
import { middleOneType, getInitialFileList } from "../MiddleOne";
import { useState, useEffect } from 'react';
import type { UploadFile } from 'antd';
import { GetUploadResPath, UploadPath } from '../../utils/DefineUtil';
import React from 'react';

const FileUploadComp = {
    typeName: '文件上传',
    component: ({ child, form }: middleOneType) => {
        let formOneProp: FormItemProps = {
            'label': child.label,
            'name': child.field,
            'rules': child.rules,
        }
        // 单文件：初始值取第一个URL字符串用于表单值，数组用于 fileList 显示
        const initList = getInitialFileList(child.curValue).slice(0, 1);
        const initUrl = initList.length > 0 ? (initList[0]?.url || '') : '';
        if (child.curValue) {
            formOneProp.initialValue = initUrl;
        }

        const [fileList, setFileList] = useState<UploadFile[]>(initList);
        // 监听表单值变化（例如从历史版本加载到表单时），同步本地 fileList
        // 注意：在模型编辑页面 form 为 null，此时不需要监听
        const watched = form ? Form.useWatch(child.field, form) : undefined;
        useEffect(() => {
            if (watched !== undefined) {
                const next = getInitialFileList(watched).slice(0, 1);
                setFileList(next);
            }
        }, [watched]);

        const handleFileChange = (info: any) => {
            let newFileList = info.fileList.slice(-1); // 只保留最后一个

            if (info.file.status === 'done') {
                const resp = info.file.response;
                if (resp && resp.code !== 0) {
                    message.error(resp.message || '文件上传失败');
                    setFileList([]);
                    form && form.setFieldsValue({ [child.field]: '' });
                    return;
                }
            }

            if (info.file.status === 'done' || info.file.status === 'removed') {
                // 统一提取 URL，构建干净的 fileList
                newFileList = newFileList
                    .map((file: any, i: number) => {
                        const url = file.url ?? GetUploadResPath(file);
                        if (!url) return null;
                        return { uid: String(i), name: file.name || url.split('/').pop() || `file-${i}`, status: 'done' as const, url };
                    })
                    .filter(Boolean);

                // 单文件：表单值存字符串URL
                const url = newFileList.length > 0 ? (newFileList[0]?.url || '') : '';
                form && form.setFieldsValue({ [child.field]: url });
                if (info.file.status === 'done') message.success(`文件上传成功`);
            }
            setFileList(newFileList);
        }
        return (
            <Form.Item {...formOneProp} >
                <div>
                    <Upload
                        name='file'
                        action={UploadPath}
                        fileList={fileList}
                        maxCount={1}
                        onChange={handleFileChange}
                        withCredentials={true}
                        headers={{
                            'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                        }}
                    >
                        {fileList.length >= 1 ? null : <Button icon={<UploadOutlined />}>Upload</Button>}
                    </Upload>
                </div>
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        // 单文件：始终返回字符串URL
        if (!value) return '';
        if (typeof value === 'string') {
            const trimmed = value.trim();
            if (trimmed.startsWith('[')) {
                try {
                    const arr = JSON.parse(trimmed);
                    if (Array.isArray(arr) && arr.length > 0) return typeof arr[0] === 'string' ? arr[0] : (arr[0]?.url || '');
                } catch {}
            }
            return trimmed;
        }
        if (Array.isArray(value)) {
            const first = value[0];
            if (!first) return '';
            return typeof first === 'string' ? first : (first?.url || '');
        }
        return typeof value?.url === 'string' ? value.url : '';
    }
};

export default FileUploadComp;
