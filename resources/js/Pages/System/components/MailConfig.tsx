import React from 'react';
import { Card, Form, Input, Button, Space, Typography, Divider, Row, Col, Select } from 'antd';
import { MailConfig as MailConfigType } from '../types';

const { Title, Text } = Typography;

interface MailConfigProps {
  loading: boolean;
  form: any;
  config: MailConfigType;
  onSave: () => void;
  onReset: () => void;
  onTest: () => void;
}

export default function MailConfig({ loading, form, config, onSave, onReset, onTest }: MailConfigProps) {
  return (
    <div id="mail" style={{ scrollMarginTop: 80 }}>
      <Card loading={loading}>
        <Title level={4} style={{ marginTop: 0 }}>邮件配置</Title>
        <Text type="secondary">配置系统邮件发送服务（仅系统级）。</Text>
        <Divider />
        <Form form={form} layout="vertical">
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name={[ 'mailer' ]} label="Mailer">
                <Select options={[{ value: 'smtp', label: 'smtp' }, { value: 'log', label: 'log' }, { value: 'sendmail', label: 'sendmail' }]} />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name={[ 'encryption' ]} label="加密">
                <Select options={[{ value: 'tls', label: 'tls' }, { value: 'ssl', label: 'ssl' }, { value: '', label: 'none' }]} />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name={[ 'host' ]} label="SMTP 主机">
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name={[ 'port' ]} label="端口">
                <Input type="number" />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name={[ 'username' ]} label="用户名">
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name={[ 'password' ]} label="密码">
                <Input.Password placeholder="****xxxx" />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name={[ 'from_address' ]} label="发信人地址">
                <Input />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name={[ 'from_name' ]} label="发信人名称">
                <Input />
              </Form.Item>
            </Col>
          </Row>
          <Space>
            <Button onClick={onReset}>重置</Button>
            <Button onClick={onTest}>测试连接</Button>
            <Button type="primary" onClick={onSave}>保存</Button>
          </Space>
        </Form>
      </Card>
    </div>
  );
}
