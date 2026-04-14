import React, { useEffect, useMemo, useState } from 'react';
import { Card, Form, Input, Button, Space, Typography, Divider, Row, Col, Select, message } from 'antd';
import { AiProvidersConfig as AiProvidersConfigType } from '../types';

const { Title, Text, Paragraph } = Typography;

interface AiProvidersConfigProps {
  loading: boolean;
  form: any;
  config: AiProvidersConfigType;
  onSave: () => void;
  onReset: () => void;
  onTestProvider: () => void;
}

export default function AiProvidersConfig({
  loading,
  form,
  config,
  onSave,
  onReset,
  onTestProvider
}: AiProvidersConfigProps) {

  const [selectedProvider, setSelectedProvider] = useState<string | undefined>(() => {
    try {
      const v = form.getFieldsValue(true);
      return (v as any)?.active_provider;
    } catch {
      return undefined;
    }
  });

  useEffect(() => {
    try {
      const v = form.getFieldsValue(true);
      setSelectedProvider((v as any)?.active_provider);
    } catch {
      // ignore
    }
  }, [config, form]);

  const providerOptions = useMemo(() => {
    const providers: { label: string; value: string }[] = [];

    for (const key of Object.keys(config)) {
      if (key === 'active_provider') continue;
      const providerConfig = (config as any)[key] || {};
      const label = providerConfig.label || key;
      providers.push({ label, value: key });
    }

    return providers;
  }, [config]);

  const handleProviderSelect = (provider: string | undefined) => {
    const currentValues = form.getFieldsValue(true);
    const newValues = { ...currentValues, active_provider: provider };
    form.setFieldsValue(newValues);
    setSelectedProvider(provider);
  };

  const handleSave = () => {
    const values = form.getFieldsValue(true) as AiProvidersConfigType;

    const active = (values as any)?.active_provider;
    if (!active) {
      message.error('请选择一个提供商');
      return;
    }

    if (active === 'custom') {
      const customConfig = (values as any)?.custom;
      if (!customConfig?.completion_url) {
        message.error('请填写 API 地址');
        return;
      }
      if (!customConfig?.api_key) {
        message.error('请填写 API Key');
        return;
      }
      if (!customConfig?.model) {
        message.error('请填写模型名称');
        return;
      }
    }

    onSave();
  };

  const renderProviderFields = () => {
    if (!selectedProvider) return null;

    const isCustom = selectedProvider === 'custom';
    const providerConfig = (config as any)?.[selectedProvider] || {};
    const models = providerConfig.models || [];

    const completionUrlName = [selectedProvider, 'completion_url'] as any;
    const apiKeyName = [selectedProvider, 'api_key'] as any;
    const modelName = [selectedProvider, 'model'] as any;

    const modelSelectProps = {
      options: models.map((m: string) => ({ label: m, value: m })),
      placeholder: models.length > 0 ? '选择或输入模型' : '输入模型名称',
    };

    const providerLabel = providerConfig.label || selectedProvider;
    const urlPlaceholder = providerConfig.completion_url || 'https://api.example.com/v1';

    return (
      <Card size="small" style={{ marginBottom: 16 }}>
        <Title level={5} style={{ marginBottom: 16 }}>
          {providerLabel}
        </Title>

        {isCustom && (
          <Card type="inner" style={{ marginBottom: 16, background: '#f6ffed' }}>
            <Paragraph style={{ marginBottom: 8 }}>
              <strong>自定义 OpenAI 兼容 API</strong>
            </Paragraph>
            <Paragraph type="secondary" style={{ marginBottom: 0 }}>
              任何兼容 OpenAI API 格式的大模型服务都可以使用。只需填写 API 地址、密钥和模型名称即可。
            </Paragraph>
          </Card>
        )}

        <Row gutter={16}>
          <Col span={12}>
            <Form.Item
              name={completionUrlName}
              label="API 地址（Base URL）"
              rules={[{ required: true, message: '请填写 API 地址' }]}
            >
              <Input placeholder={urlPlaceholder} />
            </Form.Item>
          </Col>
          <Col span={12}>
            <Form.Item name={apiKeyName} label="API Key">
              <Input.Password placeholder="sk-xxxx..." />
            </Form.Item>
          </Col>
        </Row>

        {isCustom ? (
          <Form.Item
            name={modelName}
            label="模型名称"
            rules={[{ required: true, message: '请填写模型名称' }]}
          >
            <Input placeholder="例如：moonshot-v1-8k 或 gpt-4" />
          </Form.Item>
        ) : (
          <Form.Item
            name={modelName}
            label="模型（下拉或自定义）"
            getValueFromEvent={(val: any) => {
              if (Array.isArray(val) && val.length > 0) {
                return val[val.length - 1];
              }
              return val;
            }}
          >
            <Select
              mode="tags"
              options={modelSelectProps.options}
              placeholder={modelSelectProps.placeholder}
              maxTagCount={1}
            />
          </Form.Item>
        )}

        {isCustom && (
          <Paragraph type="secondary" style={{ fontSize: 12, marginBottom: 0 }}>
            💡 提示：模型名称需要填写服务商提供的完整模型 ID
          </Paragraph>
        )}

        <Space style={{ marginTop: 16 }}>
          <Button onClick={() => onTestProvider()}>测试连接</Button>
        </Space>
      </Card>
    );
  };

  return (
    <div id="ai" style={{ scrollMarginTop: 80 }}>
      <Card loading={loading} style={{ marginBottom: 16 }}>
        <Title level={4} style={{ marginTop: 0 }}>大模型提供商</Title>
        <Text type="secondary">选择一个AI提供商，填写密钥和模型配置。只能启用一个提供商。</Text>
        <Divider />
        <Form form={form} layout="vertical">
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item label="提供商" name={['active_provider']}>
                <Select
                  allowClear
                  options={providerOptions}
                  placeholder="请选择提供商"
                  onChange={(v) => handleProviderSelect(v)}
                />
              </Form.Item>
            </Col>
          </Row>

          {renderProviderFields()}

          <Space>
            <Button onClick={onReset}>重置</Button>
            <Button type="primary" onClick={handleSave}>保存</Button>
          </Space>
        </Form>
      </Card>
    </div>
  );
}
