import React from 'react';
import { Button, Card, Form, Input, Typography, Row, Col, message } from 'antd';
import { LockOutlined, CodepenCircleOutlined } from '@ant-design/icons';
import { useForm } from '@inertiajs/react';
import LogoCenter from '../../components/Logo';

const { Title } = Typography;

interface ResetPasswordProps {
  token: string;
  email: string;
}

const ResetPassword: React.FC<ResetPasswordProps> = ({ token, email }) => {
  const { data, setData, post, processing, errors } = useForm({
    token: token,
    email: email,
    password: '',
    password_confirmation: '',
  });

  const handleSubmit = () => {
    post('/reset-password', {
      onSuccess: () => {
        message.success('密码已成功重置，您已登录');
      },
      onError: (errors) => {
        if (errors.email) {
          message.error(errors.email);
        }
        if (errors.password) {
          message.error(errors.password);
        }
        if (errors.token) {
          message.error(errors.token);
        }
      }
    });
  };

  return (
    <Row justify="center" align="middle" style={{ width: '100%', minHeight: '100vh' }}>
      <Col xs={22} sm={16} md={12} lg={8} xl={6} style={{ width: '100%', maxWidth: '450px' }}>
        <div style={{ textAlign: 'center', marginBottom: '30px' }}>
          <LogoCenter />
          <Typography.Text type="secondary">重置密码</Typography.Text>
        </div>
        
        <Card variant="outlined" style={{ boxShadow: '0 1px 2px -2px rgba(0, 0, 0, 0.16), 0 3px 6px 0 rgba(0, 0, 0, 0.12), 0 5px 12px 4px rgba(0, 0, 0, 0.09)' }}>
          <Form
            name="reset-password"
            onFinish={handleSubmit}
            size="large"
            layout="vertical"
          >
            <Form.Item
              name="password"
              validateStatus={errors.password ? 'error' : ''}
              help={errors.password}
              rules={[
                { required: true, message: '请输入新密码!' },
                { min: 8, message: '密码长度不能少于8个字符!' }
              ]}
            >
              <Input.Password 
                prefix={<LockOutlined />} 
                placeholder="新密码" 
                value={data.password}
                onChange={e => setData('password', e.target.value)}
              />
            </Form.Item>

            <Form.Item
              name="password_confirmation"
              validateStatus={errors.password_confirmation ? 'error' : ''}
              help={errors.password_confirmation}
              rules={[
                { required: true, message: '请确认新密码!' },
                ({ getFieldValue }) => ({
                  validator(_, value) {
                    if (!value || getFieldValue('password') === value) {
                      return Promise.resolve();
                    }
                    return Promise.reject(new Error('两次输入的密码不一致!'));
                  },
                }),
              ]}
            >
              <Input.Password 
                prefix={<LockOutlined />} 
                placeholder="确认新密码" 
                value={data.password_confirmation}
                onChange={e => setData('password_confirmation', e.target.value)}
              />
            </Form.Item>

            <Form.Item>
              <Button 
                type="primary" 
                htmlType="submit" 
                loading={processing}
                block
              >
                重置密码
              </Button>
            </Form.Item>
          </Form>
        </Card>
      </Col>
    </Row>
  );
};

export default ResetPassword;
