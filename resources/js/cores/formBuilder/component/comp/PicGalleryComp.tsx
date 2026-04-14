import { getInitialFileList, middleOneType } from "../MiddleOne";
import React, { useState, useEffect } from 'react'
import { Form, FormItemProps, UploadProps, Modal, message } from 'antd';
import { Upload } from 'antd';
import { PlusOutlined } from '@ant-design/icons';
import type { UploadFile } from 'antd';
import { GetUploadResPath, UploadPath } from '../../utils/DefineUtil';

const PicGalleryComp = {
    typeName: '图片集',
    component: ({ child, form }: middleOneType) => {
        let formOneProp: FormItemProps = {
            'label': child.label,
            'name': child.field,
            'rules': child.rules,
        }
        if (child.curValue) {
            formOneProp.initialValue = getInitialFileList(child.curValue)
        }

        const [previewOpen, setPreviewOpen] = useState(false);
        const [previewImage, setPreviewImage] = useState('');
        const [previewTitle, setPreviewTitle] = useState('');
        const [fileList, setFileList] = useState<UploadFile[]>(getInitialFileList(child.curValue));
        const watched = form ? Form.useWatch(child.field, form) : undefined;
        useEffect(() => {
            if (watched !== undefined) {
                const next = getInitialFileList(watched);
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
            let newFileList: UploadFile[] = info.fileList;

            if (info.file.status === 'done' || info.file.status === 'removed') {
                const cleaned: UploadFile[] = [];
                newFileList.forEach((file: any, i: number) => {
                    const url = file.url ?? GetUploadResPath(file);
                    if (url) cleaned.push({ uid: String(i), name: file.name || url.split('/').pop() || `file-${i}`, status: 'done' as const, url });
                });
                newFileList = cleaned;

                // 表单值存 URL 数组
                const urls = cleaned.map((f) => f.url).filter(Boolean);
                form && form.setFieldsValue({
                    [child.field]: urls
                });
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
                    onPreview={handlePreview}
                    onChange={handleChange}
                    withCredentials={true}
                    headers={{
                        'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                    }}
                >
                    {fileList.length >= 20 ? null : uploadButton}
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
        // 多图：始终返回 JSON 数组字符串
        if (!value) return '[]';
        if (Array.isArray(value)) {
            const urls = value.map((v: any) => (typeof v === 'string' ? v : v?.url || '')).filter(Boolean);
            return JSON.stringify(urls);
        }
        if (typeof value === 'string') {
            const trimmed = value.trim();
            // 已经是 JSON 数组
            if (trimmed.startsWith('[')) return trimmed;
            // 单个 URL 字符串，包装为数组
            if (trimmed) return JSON.stringify([trimmed]);
            return '[]';
        }
        return '[]';
    }
};

export default PicGalleryComp;
