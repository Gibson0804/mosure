import React from 'react';
import { Button, Card, Form, Input, Typography, Row, Col, message, Space } from 'antd';
import { LockOutlined } from '@ant-design/icons';
import { Link, useForm } from '@inertiajs/react';

const { Title } = Typography;

const ChangePassword: React.FC = () => {
  const { data, setData, post, processing, errors, reset } = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  const handleSubmit = () => {
    post('/change-password', {
      onSuccess: () => {
        message.success('密码已成功更新');
        reset();
      },
      onError: (errors) => {
        if (errors.current_password) {
          message.error(errors.current_password);
        }
        if (errors.password) {
          message.error(errors.password);
        }
      }
    });
  };

  return (
    <Row justify="center" style={{ marginTop: 40 }}>
      <Col xs={24} sm={20} md={16} lg={12} xl={8}>
        <Card title="修改密码" variant="outlined" 
            extra={(
              <Space>
              <Button type="primary" onClick={() => window.history.back()}>返回</Button>
              <Link href="/project"><Button>返回项目列表</Button></Link>
              </Space>
            )}
          >
          <Form
            name="change-password"
            onFinish={handleSubmit}
            layout="vertical"
          >
            <Form.Item
              name="current_password"
              label="当前密码"
              validateStatus={errors.current_password ? 'error' : ''}
              help={errors.current_password}
              rules={[{ required: true, message: '请输入当前密码!' }]}
            >
              <Input.Password 
                prefix={<LockOutlined />} 
                placeholder="当前密码" 
                value={data.current_password}
                onChange={e => setData('current_password', e.target.value)}
              />
            </Form.Item>

            <Form.Item
              name="password"
              label="新密码"
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
              label="确认新密码"
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
              >
                更新密码
              </Button>
            </Form.Item>
          </Form>
        </Card>
      </Col>
    </Row>
  );
};


export default ChangePassword;
