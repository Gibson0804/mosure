import React, { useEffect, useMemo, useState } from 'react';
import CorsHelpModal from '../../components/CorsHelpModal';
import { Card, Form, Input, InputNumber, Switch, Select, Button, Space, Divider, message, Modal, Menu, Typography, Tag, Empty } from 'antd';
import { ExclamationCircleOutlined } from '@ant-design/icons';
import api from '../../util/Service';

const { TextArea } = Input;

interface ProjectConfigPayload {
  basic: {
    display_name: string;
    logo: string;
    primary_color: string;
    description: string;
  };
  api: {
    enable_ip_whitelist: boolean;
    ip_whitelist: string[];
    enable_cors: boolean;
    allowed_origins: string[];
    enable_audit: boolean;
    mask_fields: string[];
  };
  mcp: {
    enabled: boolean;
    token: string;
  };
  auth: {
    enabled: boolean;
    provider: string;
    allow_register: boolean;
    session_ttl_minutes: number;
    allowed_origins: string[];
    require_email_verify: boolean;
  };
}

const defaultConfig: ProjectConfigPayload = {
  basic: {
    display_name: '',
    logo: '',
    primary_color: '#1890ff',
    description: '',
  },
  api: {
    enable_ip_whitelist: false,
    ip_whitelist: [],
    enable_cors: true,
    allowed_origins: [],
    enable_audit: true,
    mask_fields: ['password', 'token', 'secret'],
  },
  mcp: {
    enabled: false,
    token: '',
  },
  auth: {
    enabled: false,
    provider: 'local',
    allow_register: false,
    session_ttl_minutes: 10080,
    allowed_origins: [],
    require_email_verify: false,
  },
};

const tagsSelectProps = {
  mode: 'tags' as const,
  tokenSeparators: [',', ' '],
  style: { width: '100%' },
  placeholder: '输入后回车可添加多条',
};

const ProjectConfigPage: React.FC = () => {
  const [form] = Form.useForm<ProjectConfigPayload>();
  const [loading, setLoading] = useState(false);
  const [projectPrefix, setProjectPrefix] = useState<string>('');
  const [selectedCategory, setSelectedCategory] = useState<string>('basic');
  const [query, setQuery] = useState<string>('');
  const [initialConfig, setInitialConfig] = useState<ProjectConfigPayload>(defaultConfig);
  const [saving, setSaving] = useState(false);
  // 项目级 AI 模型配置已移除（改为系统级），删除相关状态
  const [mcpPreview, setMcpPreview] = useState<{ json: string; serverUrl: string; enabled: boolean; token: string } | null>(null);
  const [mcpPreviewLoading, setMcpPreviewLoading] = useState(false);
  const [corsHelpVisible, setCorsHelpVisible] = useState(false);

  const loadConfig = () => {
    setLoading(true);
    api.get('/manage/project-config/data')
      .then((res) => {
        const data = (res?.data?.data ?? res?.data) as any;
        const cfg: ProjectConfigPayload = (data?.config ?? defaultConfig) as ProjectConfigPayload;
        setProjectPrefix(data?.project_prefix || '');
        form.setFieldsValue(cfg);
        setInitialConfig(cfg);
      })
      .catch((err: any) => {
        message.error(err?.message || '获取配置失败');
        form.setFieldsValue(defaultConfig);
        setInitialConfig(defaultConfig);
      })
      .finally(() => {
        setLoading(false);
      });
  };

  useEffect(() => {
    loadConfig();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  type SettingType = 'text' | 'textarea' | 'number' | 'switch' | 'tags' | 'select' | 'info' | 'action';
  interface SettingSchemaItem {
    key: string;
    label: string;
    description?: string;
    type: SettingType;
    category: 'basic' | 'api' | 'mcp' | 'auth' | 'maintenance';
    formName?: (string | number)[];
    options?: { label: string; value: any }[];
    min?: number;
    max?: number;
    defaultValue?: any;
    danger?: boolean;
    confirmText?: string;
    actionApi?: string;
  }

  const settings = useMemo<SettingSchemaItem[]>(() => ([
    { key: 'basic.prefix', label: '项目缩写（前缀）', description: '', type: 'info', category: 'basic', defaultValue: '' },
    { key: 'basic.display_name', label: '项目展示名', description: '显示在导航或标题中的项目名称。', type: 'text', category: 'basic', formName: ['basic', 'display_name'], defaultValue: '' },
    { key: 'basic.description', label: '项目描述', description: '简要说明项目用途或备注信息。', type: 'textarea', category: 'basic', formName: ['basic', 'description'], defaultValue: '' },
    { key: 'api.enable_ip_whitelist', label: '启用 IP 白名单', description: '仅允许白名单中的 IP 访问 API。仅影响/open开头的接口。', type: 'switch', category: 'api', formName: ['api', 'enable_ip_whitelist'], defaultValue: false },
    { key: 'api.ip_whitelist', label: '允许的 IP', description: '指定允许访问API的IP地址，例如 192.168.1.100 或 192.168.1.0/24。', type: 'tags', category: 'api', formName: ['api', 'ip_whitelist'], defaultValue: [] },
    { key: 'api.enable_cors', label: '启用 CORS', description: '控制是否允许跨域请求访问API。仅影响/open开头的接口。', type: 'switch', category: 'api', formName: ['api', 'enable_cors'], defaultValue: true },
    { key: 'api.cors_help', label: 'CORS 帮助', description: '查看 CORS 跨域配置的详细说明和示例。', type: 'action', category: 'api', confirmText: '', actionApi: '' },
    { key: 'api.allowed_origins', label: '允许的 Origin', description: '指定允许访问API的域名，例如 http://example.com 或 https://*.example.com。留空表示允许所有域名访问。', type: 'tags', category: 'api', formName: ['api', 'allowed_origins'], defaultValue: [] },
    { key: 'api.enable_audit', label: '启用审计日志', type: 'switch', category: 'api', formName: ['api', 'enable_audit'], defaultValue: true },
    { key: 'api.mask_fields', label: '日志敏感字段屏蔽', type: 'tags', category: 'api', formName: ['api', 'mask_fields'], defaultValue: ['password', 'token', 'secret'] },
    // MCP
    { key: 'mcp.enabled', label: '启用 MCP 服务', description: '开启后，客户端可通过 MCP 协议访问本项目提供的服务。', type: 'switch', category: 'mcp', formName: ['mcp', 'enabled'], defaultValue: false },
    { key: 'mcp.token', label: 'MCP 访问令牌', description: '令牌=项目前缀+16位随机hex；用于客户端鉴权。', type: 'text', category: 'mcp', formName: ['mcp', 'token'], defaultValue: '' },
    { key: 'mcp.generate', label: '生成/重置令牌', description: '生成新的 MCP 访问令牌（将覆盖旧令牌）。', type: 'action', category: 'mcp', danger: false, confirmText: '确认生成新的 MCP 令牌？旧令牌将失效。', actionApi: '/manage/project-config/mcp/generate-token' },
    { key: 'auth.enabled', label: '启用项目用户认证', description: '开启后，当前项目可使用系统内置的前台用户登录、角色权限和 OpenAPI 直连鉴权。', type: 'switch', category: 'auth', formName: ['auth', 'enabled'], defaultValue: false },
    { key: 'auth.provider', label: '认证方式', description: '第一版使用本地账号；后续可扩展 OAuth/SSO。', type: 'select', category: 'auth', formName: ['auth', 'provider'], defaultValue: 'local', options: [{ label: '本地账号', value: 'local' }] },
    { key: 'auth.allow_register', label: '允许公开注册', description: '关闭时只能由管理员在“项目用户”中创建账号。', type: 'switch', category: 'auth', formName: ['auth', 'allow_register'], defaultValue: false },
    { key: 'auth.require_email_verify', label: '要求邮箱验证', description: '预留策略；启用前请先配置系统邮件服务。', type: 'switch', category: 'auth', formName: ['auth', 'require_email_verify'], defaultValue: false },
    { key: 'auth.session_ttl_minutes', label: '登录态有效期（分钟）', description: '项目用户 session 的默认有效期。', type: 'number', category: 'auth', formName: ['auth', 'session_ttl_minutes'], defaultValue: 10080, min: 5, max: 43200 },
    { key: 'auth.allowed_origins', label: '允许的前端 Origin', description: '用于项目用户登录和 OpenAPI 直连的前端来源辅助校验。', type: 'tags', category: 'auth', formName: ['auth', 'allowed_origins'], defaultValue: [] },
    { key: 'maintenance.repair', label: '修复表结构', description: '检查并创建缺失的表，补齐缺失字段。', type: 'action', category: 'maintenance', danger: false, confirmText: '确认修复当前项目的数据库表结构？', actionApi: '/manage/project-config/repair' },
    { key: 'maintenance.purge', label: '清空数据', description: '危险操作：清空当前项目的数据（空项目状态）。', type: 'action', category: 'maintenance', danger: true, confirmText: '确认清空当前项目数据？此操作不可撤销！', actionApi: '/manage/project-config/purge' },
  ]), []);

  const categories = useMemo(() => [
    { key: 'basic', label: '基础' },
    { key: 'api', label: 'API' },
    { key: 'mcp', label: 'MCP' },
    { key: 'auth', label: '项目用户' },
    { key: 'maintenance', label: '维护' },
  ], []);

  const matchQuery = (s: string) => (s || '').toLowerCase().includes(query.toLowerCase());
  const filteredSettings = useMemo(() => settings.filter(it => matchQuery(it.label) || matchQuery(it.description || '') || matchQuery(it.key)), [settings, query]);
  const countsByCategory = useMemo(() => {
    const res: Record<string, number> = {};
    filteredSettings.forEach(it => { res[it.category] = (res[it.category] || 0) + 1; });
    return res;
  }, [filteredSettings]);
  const menuItems = useMemo(() => categories.map(c => ({ key: c.key, label: `${c.label}${countsByCategory[c.key] ? ` (${countsByCategory[c.key]})` : ''}` })), [categories, countsByCategory]);

  const isEqual = (a: any, b: any) => JSON.stringify(a) === JSON.stringify(b);

  // 项目级 AI 模型列表加载逻辑已移除（统一由系统级管理）
  const renderSettingItem = (item: SettingSchemaItem) => {
    if (item.type === 'info') {
      return (
        <Card size="small" key={item.key} style={{ marginBottom: 12 }}>
          <Space direction="vertical" style={{ width: '100%' }}>
            <Typography.Text strong>{item.label}</Typography.Text>
            <Typography.Paragraph copyable style={{ marginBottom: 8 }}>{projectPrefix}</Typography.Paragraph>
            {item.description && <Typography.Text type="secondary">{item.description}</Typography.Text>}
          </Space>
        </Card>
      );
    }
    if (item.type === 'action') {
      // 特殊处理CORS帮助按钮
      if (item.key === 'api.cors_help') {
        const btn = (
          <Button key={item.key} type="link" onClick={() => setCorsHelpVisible(true)}>
            查看CORS帮助
          </Button>
        );
        return (
          <Card size="small" key={item.key} style={{ marginBottom: 12 }}>
            <Space align="start" style={{ width: '100%', justifyContent: 'space-between' }}>
              <div>
                <Typography.Text strong>{item.label}</Typography.Text>
                {item.description && <div><Typography.Text type="secondary">{item.description}</Typography.Text></div>}
              </div>
              {btn}
            </Space>
          </Card>
        );
      }
      
      const btn = (
        <Button key={item.key} danger={!!item.danger} onClick={() => confirmAction(item.confirmText || '确认操作？', () => api.post(item.actionApi!))}>
          {item.label}
        </Button>
      );
      return (
        <Card size="small" key={item.key} style={{ marginBottom: 12, borderColor: item.danger ? '#ffccc7' : undefined }}>
          <Space align="start" style={{ width: '100%', justifyContent: 'space-between' }}>
            <div>
              <Typography.Text strong>{item.label}</Typography.Text>
              {item.description && <div><Typography.Text type="secondary">{item.description}</Typography.Text></div>}
            </div>
            {btn}
          </Space>
        </Card>
      );
    }
    let disabled = false;
    if (item.key === 'api.ip_whitelist') {
      disabled = !form.getFieldValue(['api', 'enable_ip_whitelist']);
    }
    if (item.key === 'api.allowed_origins') {
      disabled = !form.getFieldValue(['api', 'enable_cors']);
    }
    if (item.key === 'mcp.token') {
      disabled = true;
    }
    if (item.key.startsWith('auth.') && item.key !== 'auth.enabled') {
      disabled = !form.getFieldValue(['auth', 'enabled']);
    }
    let control: React.ReactNode = null;
    switch (item.type) {
      case 'text':
        control = <Input placeholder="" disabled={disabled} />;
        break;
      case 'textarea':
        control = <TextArea rows={3} placeholder="" disabled={disabled} />;
        break;
      case 'number':
        control = <InputNumber min={item.min} max={item.max} style={{ width: '100%' }} disabled={disabled} />;
        break;
      case 'switch':
        control = <Switch onChange={(checked) => {
          // 手动触发表单更新，确保其他依赖此值的字段能正确响应
          if (item.formName) {
            form.setFieldValue(item.formName, checked);
            form.validateFields([item.formName] as any);
          }
        }} />;
        break;
      case 'tags':
        control = <Select {...tagsSelectProps} disabled={disabled} />;
        break;
      case 'select':
        control = <Select options={item.options} disabled={disabled} />;
        break;
    }
    // 如果是依赖其他字段的项，使用嵌套 Form.Item
    if (item.key === 'api.ip_whitelist' || item.key === 'api.allowed_origins' || (item.key.startsWith('auth.') && item.key !== 'auth.enabled')) {
      return (
        <div key={item.key} style={{ marginBottom: 12 }}>
          <Form.Item
            label={item.label}
            extra={item.description}
            shouldUpdate={(prevValues, currentValues) => {
              if (item.key === 'api.ip_whitelist') {
                return prevValues?.api?.enable_ip_whitelist !== currentValues?.api?.enable_ip_whitelist;
              }
              if (item.key === 'api.allowed_origins') {
                return prevValues?.api?.enable_cors !== currentValues?.api?.enable_cors;
              }
              if (item.key.startsWith('auth.')) {
                return prevValues?.auth?.enabled !== currentValues?.auth?.enabled;
              }
              return false;
            }}
          >
            {() => {
              let itemDisabled = disabled;
              if (item.key === 'api.ip_whitelist') {
                itemDisabled = !form.getFieldValue(['api', 'enable_ip_whitelist']);
              }
              if (item.key === 'api.allowed_origins') {
                itemDisabled = !form.getFieldValue(['api', 'enable_cors']);
              }
              if (item.key.startsWith('auth.') && item.key !== 'auth.enabled') {
                itemDisabled = !form.getFieldValue(['auth', 'enabled']);
              }
              
              let itemControl: React.ReactNode = null;
              switch (item.type) {
                case 'text':
                  itemControl = <Input placeholder="" disabled={itemDisabled} />;
                  break;
                case 'textarea':
                  itemControl = <TextArea rows={3} placeholder="" disabled={itemDisabled} />;
                  break;
                case 'number':
                  itemControl = <InputNumber min={item.min} max={item.max} style={{ width: '100%' }} disabled={itemDisabled} />;
                  break;
                case 'switch':
                  itemControl = <Switch disabled={itemDisabled} onChange={(checked) => {
                    if (item.formName) {
                      form.setFieldValue(item.formName, checked);
                      form.validateFields([item.formName] as any);
                    }
                  }} />;
                  break;
                case 'tags':
                  itemControl = <Select {...tagsSelectProps} disabled={itemDisabled} />;
                  break;
                case 'select':
                  itemControl = <Select options={item.options} disabled={itemDisabled} />;
                  break;
              }
              return (
                <Form.Item
                  name={item.formName as any}
                  valuePropName={item.type === 'switch' ? 'checked' : undefined}
                >
                  {itemControl}
                </Form.Item>
              );
            }}
          </Form.Item>
        </div>
      );
    }

    return (
      <div key={item.key} style={{ marginBottom: 12 }}>
        <Form.Item name={item.formName as any} label={item.label} valuePropName={item.type === 'switch' ? 'checked' : undefined} extra={item.description}>
          {control}
        </Form.Item>
      </div>
    );
  };

  const handleSaveCategory = () => {
    const cat = selectedCategory as keyof ProjectConfigPayload;
    const values = form.getFieldsValue(true) as ProjectConfigPayload;
    const currentPayload = values?.[cat];

    if (!currentPayload || typeof currentPayload !== 'object') {
      message.warning('当前分类没有可保存的内容');
      return;
    }

    setSaving(true);
    const payload: Partial<ProjectConfigPayload> = {
      [cat]: currentPayload,
    } as Partial<ProjectConfigPayload>;

    api.post('/manage/project-config/save', payload)
      .then(() => {
        message.success('已保存');
        loadConfig();
      })
      .catch((e: any) => {
        message.error(e?.message || '保存失败');
      })
      .finally(() => {
        setSaving(false);
      });
  };

  const handleResetCategory = () => {
    const cat = selectedCategory as keyof ProjectConfigPayload;
    const part = (initialConfig as any)?.[cat] ?? {};
    form.setFieldsValue({ [cat]: part });
    message.success('已重置未保存修改');
  };

  const confirmAction = (title: string, onOk: () => Promise<any>) => {
    Modal.confirm({
      title,
      icon: <ExclamationCircleOutlined />,
      okText: '确定',
      cancelText: '取消',
      onOk: () => {
        return onOk()
          .then(() => {
            message.success('操作成功');
            loadConfig();
          })
          .catch((e: any) => {
            message.error(e?.message || '操作失败');
          });
      },
    });
  };

  const handleFetchMcpClientConfig = () => {
    setMcpPreviewLoading(true);
    api.get('/manage/project-config/mcp/client-config')
      .then((res) => {
        const data = (res?.data?.data ?? res?.data) as any;
        setMcpPreview({
          json: data?.json || '',
          serverUrl: data?.serverUrl || '',
          enabled: !!data?.enabled,
          token: data?.token || '',
        });
      })
      .catch((e: any) => {
        message.error(e?.message || '获取客户端配置失败');
      })
      .finally(() => setMcpPreviewLoading(false));
  };

  const visible = filteredSettings.filter(s => s.category === selectedCategory);
  return (
    <div style={{ padding: 24 }}>
      <CorsHelpModal visible={corsHelpVisible} onClose={() => setCorsHelpVisible(false)} />
      <Card title="项目配置" extra={<Button onClick={loadConfig}>重载</Button>}>
        <div style={{ display: 'flex', gap: 16 }}>
          <div style={{ width: 280, position: 'sticky', top: 0, alignSelf: 'flex-start' }}>
            <Card size="small" style={{ marginBottom: 12 }}>
              <Input.Search placeholder="搜索设置..." allowClear value={query} onChange={(e) => setQuery(e.target.value)} />
            </Card>
            <Menu mode="inline" selectedKeys={[selectedCategory]} onClick={(info) => setSelectedCategory(info.key)} items={menuItems} />
          </div>
          <div style={{ flex: 1, minHeight: 360 }}>
            <Form form={form} layout="vertical" initialValues={defaultConfig}>
              <Typography.Title level={5} style={{ marginTop: 0 }}>{categories.find(c => c.key === selectedCategory)?.label} 设置</Typography.Title>
              <Divider style={{ margin: '8px 0 16px' }} />
              {visible.length === 0 ? (
                <Empty description="无匹配的设置" />
              ) : (
                visible.map(renderSettingItem)
              )}
              {selectedCategory === 'mcp' && (
                <>
                  <Divider style={{ margin: '8px 0 12px' }} />
                  <Card size="small" title="客户端配置预览" extra={<Button loading={mcpPreviewLoading} onClick={handleFetchMcpClientConfig}>获取配置</Button>} style={{ marginBottom: 12 }}>
                    {mcpPreview ? (
                      <Space direction="vertical" style={{ width: '100%' }}>
                        <Typography.Text type="secondary">Server URL</Typography.Text>
                        <Typography.Paragraph copyable style={{ marginBottom: 8 }}>{mcpPreview.serverUrl}</Typography.Paragraph>
                        <Typography.Text type="secondary">当前令牌</Typography.Text>
                        <Typography.Paragraph copyable style={{ marginBottom: 8 }}>{mcpPreview.token || '(未生成)'}</Typography.Paragraph>
                        <Typography.Text type="secondary">Windsurf mcp.json 片段</Typography.Text>
                        <Input.TextArea rows={8} value={mcpPreview.json} readOnly />
                      </Space>
                    ) : (
                      <Typography.Text type="secondary">点击“获取配置”预览可直接用于客户端（如 Windsurf）的 MCP 配置。</Typography.Text>
                    )}
                  </Card>
                </>
              )}
              <Divider style={{ margin: '8px 0 16px' }} />
              <Space>
                <Button onClick={handleResetCategory} disabled={loading || saving}>重置</Button>
                <Button type="primary" onClick={handleSaveCategory} loading={saving} disabled={loading}>保存</Button>
              </Space>
            </Form>
          </div>
        </div>
      </Card>
    </div>
  );
}

export default ProjectConfigPage;
