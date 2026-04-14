import React, { useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Card, Form, Space, message, Row, Col, Anchor, Button } from 'antd';
import api from '../../util/Service';
import { SYSTEM_CONFIG_ROUTES } from '../../Constants/routes';
import { SystemConfigData, AiProvidersConfig, MailConfig, StorageConfig, SecurityConfig } from './types';
import StorageConfigComponent from './components/StorageConfig';
import AiProvidersConfigComponent from './components/AiProvidersConfig';
import MailConfigComponent from './components/MailConfig';
import SecurityConfigComponent from './components/SecurityConfig';

export default function SystemConfig() {
  const [loading, setLoading] = useState(false);
  const [cfg, setCfg] = useState<SystemConfigData>({});
  const [formStorage] = Form.useForm();
  const [formAI] = Form.useForm();
  const [formMail] = Form.useForm();
  const [formSecurity] = Form.useForm();

  useEffect(() => {
    setLoading(true);
    api.get(SYSTEM_CONFIG_ROUTES.data)
      .then((res: any) => {
        const data = res.data || {};
        const conf = data.config || {};
        setCfg(conf);
        formStorage.setFieldsValue(conf.storage || {});
        formAI.setFieldsValue(conf.ai_providers || {});
        formMail.setFieldsValue(conf.mail || {});
        formSecurity.setFieldsValue(conf.security || {});
      })
      .catch((err: any) => {
        message.error('加载系统配置失败: ' + (err.message || ''));
      })
      .finally(() => setLoading(false));
  }, [formStorage, formAI, formMail, formSecurity]);

  const saveAI = () => {
    const values = formAI.getFieldsValue(true) as AiProvidersConfig;
    setLoading(true);
    api.post(SYSTEM_CONFIG_ROUTES.save, { ai_providers: values })
      .then(() => {
        message.success('AI 提供商已保存');
      })
      .catch((err: any) => {
        message.error('保存失败: ' + (err.message || ''));
      })
      .finally(() => setLoading(false));
  };

  const saveMail = () => {
    const values = formMail.getFieldsValue(true) as MailConfig;
    setLoading(true);
    api.post(SYSTEM_CONFIG_ROUTES.save, { mail: values })
      .then(() => {
        message.success('邮件配置已保存');
      })
      .catch((err: any) => {
        message.error('保存失败: ' + (err.message || ''));
      })
      .finally(() => setLoading(false));
  };

  const resetAI = () => {
    formAI.setFieldsValue(cfg.ai_providers || {});
    message.success('已重置 AI 提供商配置');
  };

  const resetMail = () => {
    formMail.setFieldsValue(cfg.mail || {});
    message.success('已重置邮件配置');
  };

  

  const saveStorage = () => {
    const values = formStorage.getFieldsValue(true) as StorageConfig;
    setLoading(true);
    api.post(SYSTEM_CONFIG_ROUTES.save, { storage: values })
      .then(() => message.success('存储设置已保存'))
      .catch((err: any) => message.error('保存失败: ' + (err.message || '')))
      .finally(() => setLoading(false));
  };

  const resetStorage = () => {
    formStorage.setFieldsValue(cfg.storage || {});
    message.success('已重置存储设置');
  };

  const testStorage = () => {
    const values = formStorage.getFieldsValue(true) as StorageConfig;
    setLoading(true);
    api.post(SYSTEM_CONFIG_ROUTES.testStorage, { storage: values })
      .then((res: any) => {
        message.success(res.message || '连接成功');
      })
      .catch((err: any) => {
        message.error('连接失败: ' + (err.message || ''));
      })
      .finally(() => setLoading(false));
  };

  const saveSecurity = () => {
    const values = formSecurity.getFieldsValue(true) as SecurityConfig;
    setLoading(true);
    api.post(SYSTEM_CONFIG_ROUTES.save, { security: values })
      .then(() => message.success('安全设置已保存'))
      .catch((err: any) => message.error('保存失败: ' + (err.message || '')))
      .finally(() => setLoading(false));
  };

  const resetSecurity = () => {
    formSecurity.setFieldsValue(cfg.security || {});
    message.success('已重置安全设置');
  };

  const testProvider = () => {
    const values = formAI.getFieldsValue(true) as AiProvidersConfig;
    const provider = (values as any)?.active_provider as ('zhipu' | 'deepseek' | 'tencent' | 'alibaba' | 'kimi' | 'custom' | undefined);
    if (!provider) {
      message.error('请先选择一个提供商');
      return;
    }
    const payload = (values as any)[provider] || {};
    setLoading(true);
    api.post(SYSTEM_CONFIG_ROUTES.testProvider, { provider, config: payload })
      .then((res: any) => {
        message.success(res.message || '连接成功');
      })
      .catch((err: any) => {
        message.error('连接失败: ' + (err.message || ''));
      })
      .finally(() => setLoading(false));
  };

  const testMail = () => {
    const values = formMail.getFieldsValue(true) as MailConfig;
    setLoading(true);
    api.post(SYSTEM_CONFIG_ROUTES.testMail, { mail: values })
      .then((res: any) => {
        message.success(res.message || '连接成功');
      })
      .catch((err: any) => {
        message.error('连接失败: ' + (err.message || ''));
      })
      .finally(() => setLoading(false));
  };

  return (
    <div style={{ maxWidth: 1200, margin: '0 auto', padding: 24 }}>
      <Head title="系统设置" />
      <Card
        title="系统设置"
        variant="outlined"
        extra={(
          <Space>
            <Button type="primary" onClick={() => window.history.back()}>返回</Button>
            <Link href="/project"><Button>返回项目列表</Button></Link>
          </Space>
        )}
      >
        <Row gutter={16}>
          <Col xs={24} sm={6} md={6} lg={6}>
            <Card size="small" style={{ position: 'sticky', top: 12 }}>
              <Anchor
                items={[
                  { key: 'storage', href: '#storage', title: '存储配置' },
                  { key: 'ai', href: '#ai', title: '大模型提供商' },
                  { key: 'mail', href: '#mail', title: '邮件配置' },
                  { key: 'security', href: '#security', title: '安全设置' },
                ]}
              />
            </Card>
          </Col>
          <Col xs={24} sm={18} md={18} lg={18}>
            <StorageConfigComponent
              loading={loading}
              form={formStorage}
              config={cfg.storage || {}}
              onSave={saveStorage}
              onReset={resetStorage}
              onTest={testStorage}
            />
            <AiProvidersConfigComponent
              loading={loading}
              form={formAI}
              config={cfg.ai_providers || {}}
              onSave={saveAI}
              onReset={resetAI}
              onTestProvider={testProvider}
            />

            <MailConfigComponent
              loading={loading}
              form={formMail}
              config={cfg.mail || {}}
              onSave={saveMail}
              onReset={resetMail}
              onTest={testMail}
            />

            <SecurityConfigComponent
              loading={loading}
              form={formSecurity}
              config={cfg.security || {}}
              onSave={saveSecurity}
              onReset={resetSecurity}
            />
          </Col>
        </Row>
      </Card>
    </div>
  );
}
