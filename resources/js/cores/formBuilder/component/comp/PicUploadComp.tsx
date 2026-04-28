import { getInitialFileList, middleOneType } from "../MiddleOne";
import React, { useState, useEffect } from 'react'
import { Form, FormItemProps, UploadProps, Modal, message } from 'antd';
import { Upload } from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import type { UploadFile } from 'antd';
import { GetUploadResPath, UploadPath } from '../../utils/DefineUtil';

const PicUploadComp = {
    typeName: '图片上传',
    component: ({ child, form }: middleOneType) => {
        let formOneProp: FormItemProps = {
            'label': child.label,
            'name': child.field,
            'rules': child.rules,
        }
        // 单图：初始值取第一个URL字符串用于表单值，数组用于 fileList 显示
        const initList = getInitialFileList(child.curValue).slice(0, 1);
        const initUrl = initList.length > 0 ? (initList[0]?.url || '') : '';
        if (child.curValue) {
            formOneProp.initialValue = initUrl;
        }

        const [previewOpen, setPreviewOpen] = useState(false);
        const [previewImage, setPreviewImage] = useState('');
        const [previewTitle, setPreviewTitle] = useState('');
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

        const handleCancel = () => setPreviewOpen(false);

        const handlePreview = async (file: UploadFile) => {
            setPreviewImage(file.url as string);
            setPreviewOpen(true);
            setPreviewTitle(file.name || file.url!.substring(file.url!.lastIndexOf('/') + 1));
        };

        const handleChange: UploadProps['onChange'] = (info) => {
            let newFileList: UploadFile[] = info.fileList.slice(-1); // 只保留最后一个

            if (info.file.status === 'done') {
                const resp: any = (info.file as any).response;
                if (resp && resp.code !== 0) {
                    message.error(resp.message || '图片上传失败');
                    setFileList([]);
                    form && form.setFieldsValue({ [child.field]: '' });
                    return;
                }
            }

            if (info.file.status === 'done' || info.file.status === 'removed') {
                const cleaned: UploadFile[] = [];
                newFileList.forEach((file: any, i: number) => {
                    const url = file.url ?? GetUploadResPath(file);
                    if (url) cleaned.push({ uid: String(i), name: file.name || url.split('/').pop() || `file-${i}`, status: 'done' as const, url });
                });
                newFileList = cleaned;

                // 单图：表单值存字符串URL
                const url = cleaned.length > 0 ? (cleaned[0].url || '') : '';
                form && form.setFieldsValue({ [child.field]: url });
                if (info.file.status === 'done') message.success(`图片上传成功`);
            }
            setFileList(newFileList);
        }

        const uploadButton = (
            <button style={{ border: 0, background: 'none' }} type="button">
                <PlusOutlined />
                <div style={{ marginTop: 8 }}>Upload</div>
            </button>
        );

        let formOne = (
            <div>
                <Upload
                    name='file'
                    action={UploadPath}
                    listType="picture-card"
                    fileList={fileList}
                    maxCount={1}
                    onPreview={handlePreview}
                    onChange={handleChange}
                    withCredentials={true}
                    headers={{
                        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                    }}
                >
                    {fileList.length >= 1 ? null : uploadButton}
                </Upload>
                <Modal open={previewOpen} title={previewTitle} footer={null} onCancel={handleCancel}>
                    <img alt="example" style={{ width: '100%' }} src={previewImage} />
                </Modal>
            </div>
        )
        return (
            <Form.Item {...formOneProp} >
                {formOne}
            </Form.Item>
        )
    },
    formatValue: (value: any) => {
        // 单图：始终返回字符串URL
        if (!value) return '';
        if (typeof value === 'string') {
            const trimmed = value.trim();
            // 如果是 JSON 数组，取第一个
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

export default PicUploadComp;
