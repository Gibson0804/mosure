import React from 'react';
import { Card, Form, Input, Button, Space, Typography, Divider, Row, Col } from 'antd';
import { SecurityConfig as SecurityConfigType } from '../types';

const { Title } = Typography;

interface SecurityConfigProps {
  loading: boolean;
  form: any;
  config: SecurityConfigType;
  onSave: () => void;
  onReset: () => void;
}

export default function SecurityConfig({ loading, form, config, onSave, onReset }: SecurityConfigProps) {
  return (
    <div id="security" style={{ scrollMarginTop: 80 }}>
      <Card loading={loading}>
        <Title level={4} style={{ marginTop: 0 }}>安全设置</Title>
        <Divider />
        <Form form={form} layout="vertical">
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name={[ 'session_lifetime' ]} label="会话时长(分钟)">
                <Input type="number" />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name={[ 'password_min_length' ]} label="密码最小长度">
                <Input type="number" />
              </Form.Item>
            </Col>
          </Row>
          <Space>
            <Button onClick={onReset}>重置</Button>
            <Button type="primary" onClick={onSave}>保存</Button>
          </Space>
        </Form>
      </Card>
    </div>
  );
}
