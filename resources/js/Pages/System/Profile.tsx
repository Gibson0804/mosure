import React, { useMemo, useState, useEffect } from 'react';
import { Button, Card, Form, Input, Typography, Row, Col, message, Upload, Avatar, Space, QRCode, Divider } from 'antd';
import { UserOutlined, UploadOutlined, QrcodeOutlined } from '@ant-design/icons';
import { useForm, usePage, Link } from '@inertiajs/react';
import { UploadPath } from '../../cores/formBuilder/utils/DefineUtil';
import type { UploadFile } from 'antd/es/upload/interface';

const { Title } = Typography;

interface PageProps {
  user: {
    id: number;
    name: string;
    email: string;
    avatar?: string;
  };
  qr_login_token?: string;
}

const Profile: React.FC = () => {
  const page = usePage<{ props: PageProps }>() as any;
  const { user, qr_login_token } = (page?.props ?? {}) as PageProps;

  // 构建二维码内容为 JSON 格式
  const serverUrl = window.location.origin;
  const qrCodeContent = JSON.stringify({
    domain: serverUrl,
    token: qr_login_token
  });

  const { data, setData, post, processing, errors, reset } = useForm({
    name: user?.name || '',
    email: user?.email || '',
    avatar: user?.avatar || '',
  });

  const [uploadList, setUploadList] = useState<UploadFile[]>([]);

  const handleUploadChange = ({ file, fileList }: any) => {
    setUploadList(fileList); // 跟踪进度/状态
    if (file.status === 'done') {
      const resp = file.response || {};
      const url = resp?.data?.url || resp?.url || file.url || '';
      if (url) {
        setData('avatar', url); // 用于 Avatar 和最终提交
        message.success('头像上传成功');
      }
    } else if (file.status === 'error') {
      message.error('上传失败');
    }
  };

  const handleSubmit = () => {
    post('/profile', {
      onSuccess: () => {
        message.success('资料已更新');
      },
      onError: (errs: any) => {
        if (errs.name) message.error(errs.name);
        if (errs.email) message.error(errs.email);
      },
    });
  };

  return (
    <Row justify="center" style={{ marginTop: 40 }}>
        <Col xs={24} sm={20} md={16} lg={12} xl={10} xxl={8}>
          <Card title="用户信息" variant="outlined"
           extra={(
              <Space>
              <Button type="primary" onClick={() => window.history.back()}>返回</Button>
              <Link href="/project"><Button>返回项目列表</Button></Link>
              </Space>
            )}
          >
          <Form layout="vertical" onFinish={handleSubmit}>
            <Form.Item label="头像">
              <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <Avatar size={64} src={data.avatar} icon={<UserOutlined />} />
                <Upload
                  name="file"
                  action={UploadPath}
                  listType="picture"
                  fileList={uploadList}
                  onChange={handleUploadChange}
                  withCredentials={true}
                  headers={{
                      'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                  }}
                  maxCount={1}
                >
                  <Button icon={<UploadOutlined />}>上传头像</Button>
                </Upload>
              </div>
            </Form.Item>

            <Form.Item label="昵称" validateStatus={errors.name ? 'error' : ''} help={errors.name}>
              <Input
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                placeholder="请输入昵称"
              />
            </Form.Item>

            <Form.Item label="邮箱" validateStatus={errors.email ? 'error' : ''} help={errors.email}>
              <Input
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                placeholder="请输入邮箱"
              />
            </Form.Item>

            <Form.Item>
              <Button type="primary" htmlType="submit" loading={processing}>
                保存
              </Button>
            </Form.Item>
          </Form>

          <Divider />

          <div style={{ marginTop: 24 }}>
            <Title level={5} style={{ marginBottom: 16 }}>
              <QrcodeOutlined /> 移动端扫码登录
            </Title>
            <div style={{ textAlign: 'center', padding: '16px', background: '#f5f5f5', borderRadius: '8px' }}>
              {qr_login_token ? (
                <div>
                  <QRCode 
                    value={qrCodeContent}
                    size={200}
                    style={{ marginBottom: 12 }}
                  />
                  <div style={{ fontSize: '12px', color: '#666' }}>
                    使用 Mosure 客户端扫描二维码登录
                  </div>
                </div>
              ) : (
                <div style={{ color: '#999' }}>二维码加载中...</div>
              )}
            </div>
          </div>
        </Card>
      </Col>
    </Row>
  );
};

export default Profile;
