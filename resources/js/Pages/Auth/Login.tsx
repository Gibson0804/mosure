import React, { useEffect } from 'react';
import { Button, Card, Checkbox, Form, Input, Typography, Row, Col, message } from 'antd';
import { UserOutlined, LockOutlined, CodepenCircleOutlined } from '@ant-design/icons';
import { useForm, Link, router } from '@inertiajs/react';
import { AUTH_ROUTES } from '../../Constants/routes';
import { useTranslate } from '../../util/useTranslate';
import LogoCenter from '../../components/Logo';

const { Title } = Typography;

const Login: React.FC = () => {

  const _t = useTranslate();
  const { data, setData, post, processing, errors } = useForm({
    email: '',
    password: '',
    remember: false,
  });

  const handleSubmit = () => {
    post(AUTH_ROUTES.doLogin);
  };

  return (
    <Row justify="center" align="middle" style={{ width: '100%', minHeight: '100vh' }}>
      <Col xs={22} sm={16} md={12} lg={8} xl={6} style={{ width: '100%', maxWidth: '450px' }}>
            <div style={{ textAlign: 'center', marginBottom: '30px' }}>
              <LogoCenter />
              <Typography.Text type="secondary">{_t('description')}</Typography.Text>
            </div>
            
            <Card variant="outlined" style={{ boxShadow: '0 1px 2px -2px rgba(0, 0, 0, 0.16), 0 3px 6px 0 rgba(0, 0, 0, 0.12), 0 5px 12px 4px rgba(0, 0, 0, 0.09)' }}>
              <Form
                name="login"
                initialValues={{ remember: true }}
                onFinish={handleSubmit}
                size="large"
                layout="vertical"
              >
                <Form.Item
                  name="email"
                  validateStatus={errors.email ? 'error' : ''}
                  help={errors.email}
                  rules={[{ required: true, message: '请输入您的邮箱!' }]}
                >
                  <Input 
                    prefix={<UserOutlined />} 
                    placeholder="邮箱" 
                    value={data.email}
                    onChange={e => setData('email', e.target.value)}
                  />
                </Form.Item>

                <Form.Item
                  name="password"
                  validateStatus={errors.password ? 'error' : ''}
                  help={errors.password}
                  rules={[{ required: true, message: '请输入您的密码!' }]}
                >
                  <Input.Password 
                    prefix={<LockOutlined />} 
                    placeholder="密码" 
                    value={data.password}
                    onChange={e => setData('password', e.target.value)}
                  />
                </Form.Item>

                <Form.Item>
                  <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                    <Form.Item name="remember" valuePropName="checked" noStyle>
                      <Checkbox 
                        checked={data.remember}
                        onChange={e => setData('remember', e.target.checked)}
                      >
                        记住我
                      </Checkbox>
                    </Form.Item>
                    <Link href="/forgot-password" style={{ float: 'right' }}>
                      忘记密码？
                    </Link>
                  </div>
                </Form.Item>

                <Form.Item>
                  <Button 
                    type="primary" 
                    htmlType="submit" 
                    loading={processing}
                    block
                  >
                    登录
                  </Button>
                </Form.Item>
              </Form>
            </Card>
          </Col>
        </Row>
  );
};

export default Login;
