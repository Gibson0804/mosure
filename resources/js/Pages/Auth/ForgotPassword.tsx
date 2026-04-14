import React from 'react';
import { Button, Card, Form, Input, Typography, Row, Col, message } from 'antd';
import { MailOutlined, CodepenCircleOutlined } from '@ant-design/icons';
import { useForm, Link } from '@inertiajs/react';
import LogoCenter from '../../components/Logo';

const { Title } = Typography;

const ForgotPassword: React.FC = () => {
  const { data, setData, post, processing, errors, reset } = useForm({
    email: '',
  });

  const handleSubmit = () => {
    post('/forgot-password', {
      onSuccess: () => {
        message.success('密码重置链接已发送到您的邮箱');
        reset('email');
      },
      onError: (errors) => {
        if (errors.email) {
          message.error(errors.email);
        }
      }
    });
  };

  return (
    <Row justify="center" align="middle" style={{ width: '100%', minHeight: '100vh' }}>
      <Col xs={22} sm={16} md={12} lg={8} xl={6} style={{ width: '100%', maxWidth: '450px' }}>
        <div style={{ textAlign: 'center', marginBottom: '30px' }}>
          <LogoCenter />
          <Typography.Text type="secondary">找回密码</Typography.Text>
        </div>
        
        <Card variant="outlined" style={{ boxShadow: '0 1px 2px -2px rgba(0, 0, 0, 0.16), 0 3px 6px 0 rgba(0, 0, 0, 0.12), 0 5px 12px 4px rgba(0, 0, 0, 0.09)' }}>
          <Form
            name="forgot-password"
            onFinish={handleSubmit}
            size="large"
            layout="vertical"
          >
            <Typography.Paragraph style={{ marginBottom: '20px' }}>
              请输入您的邮箱地址，我们将向您发送密码重置链接。
            </Typography.Paragraph>

            <Form.Item
              name="email"
              validateStatus={errors.email ? 'error' : ''}
              help={errors.email}
              rules={[{ required: true, message: '请输入您的邮箱!' }, { type: 'email', message: '请输入有效的邮箱地址!' }]}
            >
              <Input 
                prefix={<MailOutlined />} 
                placeholder="邮箱" 
                value={data.email}
                onChange={e => setData('email', e.target.value)}
              />
            </Form.Item>

            <Form.Item>
              <Button 
                type="primary" 
                htmlType="submit" 
                loading={processing}
                block
              >
                发送重置链接
              </Button>
            </Form.Item>

            <div style={{ textAlign: 'center' }}>
              <Link href="/login">返回登录</Link>
            </div>
          </Form>
        </Card>
      </Col>
    </Row>
  );
};

export default ForgotPassword;
