import React from 'react';
import { Alert, Button, Card, Col, Divider, Form, Input, Row, Select, Space, Switch, Typography } from 'antd';
import { StorageConfig as StorageConfigType } from '../types';

const { Title, Paragraph } = Typography;

const S3_PROVIDER_OPTIONS = [
  { value: 'generic', label: '通用 S3 兼容存储' },
  { value: 'aliyun', label: '阿里云 OSS' },
  { value: 'cos', label: '腾讯云 COS' },
  { value: 'qiniu', label: '七牛云 Kodo' },
  { value: 'aws', label: 'AWS S3' },
];

const S3_PROVIDER_HINTS: Record<string, React.ReactNode> = {
  generic: '适用于任意 S3 兼容对象存储，请按服务商文档填写 Bucket、Region、Endpoint 等信息。',
  aliyun: '阿里云请按 OSS 的 S3 兼容接入方式填写 Endpoint、Bucket、Region 等信息。',
  cos: '请按服务商文档填写 Bucket、Region、Endpoint 等信息。',
  qiniu: '七牛云请按 Kodo 的 S3 兼容接入方式填写 Endpoint、Bucket、Region 等信息。',
  aws: 'AWS S3 通常可不填 Endpoint；若使用加速域名或自定义 CDN，可填写“公开访问域名”。',
};

interface StorageConfigProps {
  loading: boolean;
  form: any;
  config: StorageConfigType;
  onSave: () => void;
  onReset: () => void;
  onTest: () => void;
}

export default function StorageConfig({ loading, form, onSave, onReset, onTest }: StorageConfigProps) {
  const storageDefault = Form.useWatch('default', form);
  const s3Provider = Form.useWatch(['s3', 'provider'], form) || 'generic';

  return (
    <div id="storage" style={{ scrollMarginTop: 80 }}>
      <Card loading={loading} style={{ marginBottom: 16 }}>
        <Title level={4} style={{ marginTop: 0 }}>存储配置</Title>
        <Paragraph type="secondary" style={{ marginBottom: 0 }}>
          当前支持本地存储，以及任意兼容 S3 协议的对象存储。
        </Paragraph>
        <Divider />
        <Form form={form} layout="vertical" initialValues={{ default: 'local', s3: { provider: 'generic', use_path_style_endpoint: false } }}>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name={['default']} label="默认存储">
                <Select
                  options={[
                    { value: 'local', label: '本地存储 (local)' },
                    { value: 's3', label: 'S3 兼容对象存储' },
                  ]}
                />
              </Form.Item>
            </Col>
          </Row>

          {storageDefault === 's3' && (
            <>
              <Divider orientation="left" plain>S3 兼容对象存储配置</Divider>
              <Alert
                showIcon
                type="info"
                style={{ marginBottom: 16 }}
                message="S3 配置"
                description={S3_PROVIDER_HINTS[s3Provider] || S3_PROVIDER_HINTS.generic}
              />
              <Row gutter={16}>
                <Col span={12}>
                  <Form.Item name={['s3', 'provider']} label="服务商预设">
                    <Select options={S3_PROVIDER_OPTIONS} />
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item
                    name={['s3', 'bucket']}
                    label="Bucket / 存储桶"
                    tooltip="例如：my-bucket"
                  >
                    <Input placeholder="请输入 Bucket 名称" />
                  </Form.Item>
                </Col>
              </Row>

              <Row gutter={16}>
                <Col span={12}>
                  <Form.Item name={['s3', 'key']} label="Access Key / SecretId">
                    <Input.Password placeholder="请输入访问 Key" />
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item name={['s3', 'secret']} label="Secret Key / SecretKey">
                    <Input.Password placeholder="请输入密钥" />
                  </Form.Item>
                </Col>
              </Row>

              <Row gutter={16}>
                <Col span={12}>
                  <Form.Item
                    name={['s3', 'region']}
                    label="Region"
                    tooltip="如 ap-guangzhou、us-east-1、auto 等，具体以服务商要求为准"
                  >
                    <Input placeholder="请输入 Region" />
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item
                    name={['s3', 'endpoint']}
                    label="Endpoint"
                    tooltip="如 https://oss-cn-hangzhou.aliyuncs.com 或 https://cos.ap-guangzhou.myqcloud.com"
                  >
                    <Input placeholder="请输入 Endpoint，可带 https://" />
                  </Form.Item>
                </Col>
              </Row>

              <Row gutter={16}>
                <Col span={12}>
                  <Form.Item
                    name={['s3', 'url']}
                    label="公开访问域名 / CDN 域名（可选）"
                    tooltip="如配置了 CDN 或自定义公开域名，生成的文件 URL 将优先使用该值"
                  >
                    <Input placeholder="如 https://cdn.example.com" />
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item
                    name={['s3', 'use_path_style_endpoint']}
                    label="Path Style Endpoint"
                    valuePropName="checked"
                    tooltip="MinIO、部分私有部署或网关兼容层常需要开启"
                  >
                    <Switch checkedChildren="开启" unCheckedChildren="关闭" />
                  </Form.Item>
                </Col>
              </Row>

            </>
          )}

          <Space style={{ marginTop: 12 }}>
            <Button onClick={onReset}>重置</Button>
            <Button onClick={onTest}>测试连接</Button>
            <Button type="primary" onClick={onSave}>保存</Button>
          </Space>
        </Form>
      </Card>
    </div>
  );
}
