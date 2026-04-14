import React, { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { 
  Button, Card, Typography, Breadcrumb, Form, Input, message, Image, Row, Col, Select, TreeSelect, Tag, Upload
} from 'antd';
import { 
  SaveOutlined, RollbackOutlined, FileOutlined, UploadOutlined
} from '@ant-design/icons';
import type { UploadFile } from 'antd/es/upload/interface';
import { Link } from '@inertiajs/react';
import { MEDIA_FOLDER_ROUTES, MEDIA_ROUTES, MEDIA_TAG_ROUTES } from '../../Constants/routes';
import api from '../../util/Service';

const { Title } = Typography;
const { TextArea } = Input;

type Media = {
  id: number;
  filename: string;
  original_filename: string;
  mime_type: string;
  extension: string;
  path: string;
  url: string;
  size: number;
  type: string;
  description: string;
  created_at: string;
  updated_at: string;
  readable_size: string;
  is_image: boolean;
  is_video: boolean;
  icon: string;
  folder_id?: number | null;
  folder?: { id: number; name: string; full_path?: string } | null;
  tags?: string[];
};

interface MediaTag {
  id: number;
  name: string;
  color: string;
}

const MediaEdit: React.FC = () => {
  const { media } = usePage<{ media: Media }>().props;
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(false);
  const [folderTree, setFolderTree] = useState<any[]>([]);
  const [allTags, setAllTags] = useState<MediaTag[]>([]);
  const [fileList, setFileList] = useState<UploadFile[]>([]);
  const [previewUrl, setPreviewUrl] = useState<string>(media.url);

  // 初始化表单值
  React.useEffect(() => {
    form.setFieldsValue({
      filename: media.original_filename,
      description: media.description,
      folder_id: media.folder_id || null,
      tags: media.tags || [],
    });
  }, [media]);

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

  // 处理文件选择
  const handleFileChange = ({ fileList: newFileList }: any) => {
    if (newFileList.length > 1) {
      message.warning('一次只能上传一个文件');
      setFileList(newFileList.slice(-1));
    } else {
      setFileList(newFileList);
      // 预览新文件
      if (newFileList.length > 0 && newFileList[0].originFileObj) {
        const reader = new FileReader();
        reader.onload = (e) => {
          setPreviewUrl(e.target?.result as string);
        };
        reader.readAsDataURL(newFileList[0].originFileObj);
      }
    }
  };

  // 处理表单提交
  const handleSubmit = (values: any) => {
    setLoading(true);
    
    const formData = new FormData();
    
    // 如果有新文件，添加到 formData
    if (fileList.length > 0 && fileList[0].originFileObj) {
      formData.append('file', fileList[0].originFileObj);
    }
    
    if (values.filename) {
      formData.append('filename', values.filename);
    }
    if (values.description) {
      formData.append('description', values.description);
    }
    if (values.folder_id) {
      formData.append('folder_id', String(values.folder_id));
    }
    if (values.tags && Array.isArray(values.tags) && values.tags.length) {
      formData.append('tags', values.tags.join(','));
    }
    
    router.post(MEDIA_ROUTES.edit(media.id), formData, {
      onSuccess: () => {
        setLoading(false);
        message.success('更新成功');
        setFileList([]);
      },
      onError: (error) => {
        setLoading(false);
        message.error('更新失败' + error.message);
      },
      forceFormData: true
    });
  };

  return (
    <div>
      <div style={{ padding: '0 24px', minHeight: 280 }}>
        <Breadcrumb 
          style={{ margin: '16px 0' }}
          items={[
            { title: '媒体资源', href: '/media' },
            { title: '编辑媒体' }
          ]}
        />
        
        <Card>
          <Row justify="space-between" align="middle" style={{ marginBottom: 16 }}>
            <Col>
              <Title level={4}>编辑媒体资源</Title>
            </Col>
            <Col>
              <Link href="/media">
                <Button icon={<RollbackOutlined />}>
                  返回列表
                </Button>
              </Link>
            </Col>
          </Row>
          
          <Row gutter={24}>
            <Col span={8}>
              <div style={{ marginBottom: 16 }}>
                <div style={{ fontWeight: 'bold', marginBottom: 8 }}>文件预览</div>
                {(media.is_image || (fileList.length > 0 && fileList[0].type?.startsWith('image/'))) ? (
                  <div>
                    <Image
                      src={previewUrl}
                      alt={media.original_filename}
                      style={{ width: '100%', maxHeight: '300px', objectFit: 'contain' }}
                      fallback="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Crect fill='%23f0f0f0' width='200' height='200'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23999'%3E加载中...%3C/text%3E%3C/svg%3E"
                    />
                  </div>
                ) : (media.is_video || (fileList.length > 0 && fileList[0].type?.startsWith('video/'))) ? (
                  <video 
                    controls 
                    style={{ width: '100%', maxHeight: '300px' }}
                    src={previewUrl}
                  >
                    您的浏览器不支持视频标签
                  </video>
                ) : (
                  <div style={{ 
                    width: '100%', 
                    height: '200px', 
                    display: 'flex', 
                    alignItems: 'center', 
                    justifyContent: 'center',
                    background: '#f5f5f5',
                    borderRadius: '4px'
                  }}>
                    <FileOutlined style={{ fontSize: 64, color: '#999' }} />
                    <div style={{ marginLeft: 12 }}>
                      <div>{media.original_filename}</div>
                      <div>{media.readable_size}</div>
                    </div>
                  </div>
                )}
              </div>
              
              <div style={{ marginBottom: 16 }}>
                <div style={{ fontWeight: 'bold', marginBottom: 8 }}>重新上传文件</div>
                <Upload
                  fileList={fileList}
                  onChange={handleFileChange}
                  beforeUpload={() => false}
                  maxCount={1}
                >
                  <Button icon={<UploadOutlined />} block>
                    选择新文件（可选）
                  </Button>
                </Upload>
                <div style={{ marginTop: 8, fontSize: 12, color: '#999' }}>
                  选择新文件将覆盖当前文件，ID保持不变
                </div>
              </div>
              
              <div>
                <div style={{ fontWeight: 'bold', marginBottom: 8 }}>当前文件信息</div>
                <p><strong>原始文件名:</strong> {media.original_filename}</p>
                <p><strong>MIME类型:</strong> {media.mime_type}</p>
                <p><strong>文件大小:</strong> {media.readable_size}</p>
                <p><strong>上传时间:</strong> {media.created_at}</p>
              </div>
            </Col>
            
            <Col span={16}>
              <Form
                form={form}
                layout="vertical"
                onFinish={handleSubmit}
              >
                <Form.Item
                  label="文件名"
                  name="filename"
                  rules={[{ required: true, message: '请输入文件名' }]}
                >
                  <Input placeholder="请输入文件名" />
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
                    loading={loading}
                    icon={<SaveOutlined />}
                  >
                    保存更改
                  </Button>
                </Form.Item>
              </Form>
            </Col>
          </Row>
        </Card>
      </div>
    </div>
  );
};

export default MediaEdit;
