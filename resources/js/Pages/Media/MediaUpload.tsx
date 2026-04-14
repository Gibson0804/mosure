import React, { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { 
  Form, Button, Card, Typography, Breadcrumb, 
  Row, Col, Upload, Input, message, Select, TreeSelect, Tag
} from 'antd';
import { 
  UploadOutlined, InboxOutlined
} from '@ant-design/icons';
import { Link } from '@inertiajs/react';
import type { UploadFile, UploadProps } from 'antd/es/upload/interface';
import { MEDIA_FOLDER_ROUTES, MEDIA_ROUTES, MEDIA_TAG_ROUTES } from '../../Constants/routes';
import api from '../../util/Service';

const { Title } = Typography;
const { TextArea } = Input;
const { Option } = Select;
const { Dragger } = Upload;

const MediaUpload: React.FC = () => {
  const [form] = Form.useForm();
  const [fileList, setFileList] = useState<UploadFile[]>([]);
  const [uploading, setUploading] = useState(false);
  const [originalFilename, setOriginalFilename] = useState('');
  const [folderTree, setFolderTree] = useState<any[]>([]);
  const [allTags, setAllTags] = useState<Array<{ id: number; name: string; color: string }>>([]);

  // 处理文件选择
  const handleFileChange: UploadProps['onChange'] = ({ fileList: newFileList }) => {
    // 限制只能上传一个文件
    if (newFileList.length > 1) {
      message.warning('一次只能上传一个文件');
      setFileList(newFileList.slice(-1));
    } else {
      setFileList(newFileList);
      // 设置默认文件名为原始文件名（不含扩展名）
      if (newFileList.length > 0 && newFileList[0].name) {
        const nameWithoutExt = newFileList[0].name.replace(/\.[^/.]+$/, '');
        setOriginalFilename(nameWithoutExt);
        form.setFieldsValue({ filename: nameWithoutExt });
      }
    }
  };

  useEffect(() => {
    fetch(MEDIA_FOLDER_ROUTES.tree, { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((res) => {
        if (res && res.code === 0) {
          setFolderTree(res.data || []);
        }
      })
      .catch(() => {});
    
    // 加载标签列表
    api.get(MEDIA_TAG_ROUTES.list)
      .then((res) => {
        setAllTags(res.data || []);
      })
      .catch(() => {});
  }, []);

  // 处理文件上传
  const handleUpload = (values: any) => {
    if (fileList.length === 0) {
      message.error('请选择要上传的文件');
      return;
    }

    const formData = new FormData();
    fileList.forEach(file => {
      if (file.originFileObj) {
        formData.append('file', file.originFileObj);
      }
    });
    
    if (values.description) {
      formData.append('description', values.description);
    }

    // 添加自定义文件名
    if (values.filename) {
      formData.append('filename', values.filename);
    }
    
    setUploading(true);
    
    // 附加文件夹与标签
    if (values.folder_id) {
      formData.append('folder_id', String(values.folder_id));
    }
    if (values.tags && Array.isArray(values.tags) && values.tags.length) {
      formData.append('tags', values.tags.join(','));
    }

    router.post(MEDIA_ROUTES.create, formData, {
      onSuccess: () => {
        setFileList([]);
        form.resetFields();
        setUploading(false);
        message.success('媒体文件上传成功');
      },
      onError: (errors) => {
        setUploading(false);
        message.error('媒体文件上传失败');
      },
      forceFormData: true,
      preserveScroll: true
    });
  };

  // 上传前检查文件大小
  const beforeUpload = (file: File) => {
    const isLt100M = file.size / 1024 / 1024 < 100;
    if (!isLt100M) {
      message.error('文件大小不能超过100MB!');
      return Upload.LIST_IGNORE;
    }
    return false; // 阻止自动上传
  };

  return (
      <div style={{ padding: '0 24px', minHeight: 280 }}>
        <Breadcrumb 
          style={{ margin: '16px 0' }}
          items={[
            { title: '媒体资源', href: '/media' },
            { title: '上传媒体' }
          ]}
        />
        
        <Card>
          <Row justify="space-between" align="middle" style={{ marginBottom: 16 }}>
            <Col>
              <Title level={4}>上传媒体资源</Title>
            </Col>
            <Col>
              <Link href="/media">
                <Button type="default">
                  返回列表
                </Button>
              </Link>
            </Col>
          </Row>
          
          <Form
            form={form}
            layout="vertical"
            onFinish={handleUpload}
          >
            <Form.Item
              label="选择文件"
              name="file"
              rules={[{ required: true, message: '请选择要上传的文件' }]}
            >
              <Dragger
                name="file"
                fileList={fileList}
                onChange={handleFileChange}
                beforeUpload={beforeUpload}
                maxCount={1}
                multiple={false}
              >
                <p className="ant-upload-drag-icon">
                  <InboxOutlined />
                </p>
                <p className="ant-upload-text">点击或拖拽文件到此区域上传</p>
                <p className="ant-upload-hint">
                  支持单个文件上传，文件大小不超过100MB
                </p>
              </Dragger>
            </Form.Item>

            <Form.Item
              label="自定义文件名"
              name="filename"
              extra="不包含扩展名，留空则使用原文件名"
            >
              <Input placeholder="输入自定义文件名" />
            </Form.Item>
            
            <Form.Item label="所在文件夹" name="folder_id">
              <TreeSelect
                style={{ width: '100%' }}
                dropdownStyle={{ maxHeight: 400, overflow: 'auto' }}
                treeDefaultExpandAll
                allowClear
                placeholder="请选择文件夹（可选）"
                treeData={folderTree.map((n:any) => ({ value: n.id, title: n.name, children: (n.children||[]).map((c:any)=>({ value: c.id, title: c.name, children: (c.children||[]).map((cc:any)=>({ value: cc.id, title: cc.name })) })) }))}
              />
            </Form.Item>

            <Form.Item label="标签" name="tags" extra="可选择多个标签">
              <Select 
                mode="multiple" 
                style={{ width: '100%' }} 
                placeholder="请选择标签"
                options={allTags.map(tag => ({
                  label: <Tag color={tag.color}>{tag.name}</Tag>,
                  value: tag.name
                }))}
              />
            </Form.Item>

            <Form.Item
              label="描述"
              name="description"
            >
              <TextArea 
                rows={4} 
                placeholder="请输入文件描述（可选）"
                maxLength={500}
                showCount
              />
            </Form.Item>
            
            <Form.Item>
              <Button
                type="primary"
                htmlType="submit"
                loading={uploading}
                icon={<UploadOutlined />}
              >
                {uploading ? '上传中...' : '开始上传'}
              </Button>
            </Form.Item>
          </Form>
        </Card>
      </div>
  );
};

export default MediaUpload;
