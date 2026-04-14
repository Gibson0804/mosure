import React, { useMemo, useState } from 'react';
import { Table, Button, Space, Modal, Form, Input, InputNumber, DatePicker, Switch, message, Popconfirm, Tag, Checkbox, Typography, Radio, Alert } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';
import { router } from '@inertiajs/react';
import api from '../../util/Service';
import { API_KEY_ROUTES } from '../../Constants/routes';
import dayjs from 'dayjs';

const API_SCOPE_OPTIONS = [
    { label: '内容读取', value: 'content.read', group: '内容', risky: false },
    { label: '内容写入', value: 'content.write', group: '内容', risky: true },
    { label: '单页读取', value: 'page.read', group: '单页', risky: false },
    { label: '单页写入', value: 'page.write', group: '单页', risky: true },
    { label: '媒体读取', value: 'media.read', group: '媒体', risky: false },
    { label: '媒体写入', value: 'media.write', group: '媒体', risky: true },
    { label: '函数调用', value: 'function.invoke', group: '函数', risky: true },
];

const READONLY_SCOPES = ['content.read', 'page.read', 'media.read'];
const ALL_SCOPES = API_SCOPE_OPTIONS.map(item => item.value);
type PermissionMode = 'readonly' | 'all' | 'custom';

const sameScopes = (a: string[] = [], b: string[] = []) => {
    const left = [...a].sort().join(',');
    const right = [...b].sort().join(',');
    return left === right;
};

const inferPermissionMode = (scopes?: string[]): PermissionMode => {
    const list = Array.isArray(scopes) && scopes.length > 0 ? scopes : ALL_SCOPES;
    if (sameScopes(list, READONLY_SCOPES)) return 'readonly';
    if (sameScopes(list, ALL_SCOPES)) return 'all';
    return 'custom';
};

interface ApiKey {
    id: number;
    name: string;
    key: string;
    description?: string;
    rate_limit: number;
    expires_at?: string;
    is_active: boolean;
    scopes?: string[];
    last_used_at?: string;
    created_at: string;
}

interface Props {
    apiKeys: ApiKey[];
    total: number;
}

const ApiKeyList: React.FC<Props> = ({ apiKeys, total: initialTotal }) => {
    const [modalVisible, setModalVisible] = useState(false);
    const [editingKey, setEditingKey] = useState<ApiKey | null>(null);
    const [permissionMode, setPermissionMode] = useState<PermissionMode>('readonly');
    const [form] = Form.useForm();
    const watchedScopes = Form.useWatch('scopes', form) as string[] | undefined;

    const groupedScopeOptions = useMemo(() => {
        return API_SCOPE_OPTIONS.reduce<Record<string, typeof API_SCOPE_OPTIONS>>((acc, option) => {
            acc[option.group] = acc[option.group] || [];
            acc[option.group].push(option);
            return acc;
        }, {});
    }, []);

    const hasRiskyScope = (watchedScopes || []).some(scope => API_SCOPE_OPTIONS.find(item => item.value === scope)?.risky);

    const applyPermissionMode = (mode: PermissionMode) => {
        setPermissionMode(mode);
        if (mode === 'readonly') {
            form.setFieldsValue({ scopes: READONLY_SCOPES });
        } else if (mode === 'all') {
            form.setFieldsValue({ scopes: ALL_SCOPES });
        }
    };

    const handleCreate = () => {
        setEditingKey(null);
        form.resetFields();
        setPermissionMode('readonly');
        form.setFieldsValue({
            rate_limit: 1000,
            is_active: true,
            permission_mode: 'readonly',
            scopes: READONLY_SCOPES,
        });
        setModalVisible(true);
    };

    const handleEdit = (record: ApiKey) => {
        const scopes = record.scopes && record.scopes.length > 0 ? record.scopes : ALL_SCOPES;
        const mode = inferPermissionMode(scopes);
        setEditingKey(record);
        setPermissionMode(mode);
        form.setFieldsValue({
            name: record.name,
            api_key: record.key,
            description: record.description,
            rate_limit: record.rate_limit,
            expires_at: record.expires_at ? dayjs(record.expires_at) : undefined,
            is_active: record.is_active,
            permission_mode: mode,
            scopes,
        });
        setModalVisible(true);
    };

    const handleSubmit = async () => {
        try {
            const values = await form.validateFields();
            const formData = {
                ...values,
                expires_at: values.expires_at ? values.expires_at.toISOString() : null,
            };
            delete (formData as any).permission_mode;
            if (!editingKey) {
                router.post(API_KEY_ROUTES.create, formData, {
                    onSuccess: () => setModalVisible(false),
                    onError: (error) => message.error('创建失败: ' + JSON.stringify(error)),
                });
            } else {
                router.post(API_KEY_ROUTES.edit(editingKey.id), formData, {
                    onSuccess: () => {
                        setModalVisible(false);
                        message.success('更新成功');
                    },
                    onError: (error) => message.error('更新失败: ' + JSON.stringify(error)),
                });
            }
        } catch (error) {
            message.error('操作失败: ' + error);
        }
    };

    const handleDelete = async (id: number) => {
        router.post(API_KEY_ROUTES.delete(id), {}, {
            onSuccess: () => message.success('删除成功'),
            onError: (error: any) => message.error('删除失败: ' + (error?.message || JSON.stringify(error))),
        });
    };

    const handleToggle = async (id: number, currentStatus: boolean) => {
        router.post(`/api-key/toggle/${id}`, {}, {
            onSuccess: () => message.success(currentStatus ? '已禁用' : '已启用'),
            onError: (error: any) => message.error('操作失败: ' + (error?.message || JSON.stringify(error))),
        });
    };

    const handleGenerate = async () => {
        api.post(API_KEY_ROUTES.generate).then(response => {
            message.success('密钥生成成功');
            form.setFieldsValue({ api_key: response.data.api_key });
        }).catch(error => message.error('生成失败: ' + error));
    };

    const columns = [
        { title: 'ID', dataIndex: 'id', key: 'id', width: 80, fixed: 'left' as const },
        { title: '名称', dataIndex: 'name', key: 'name', width: 150 },
        { title: '描述', dataIndex: 'description', key: 'description', ellipsis: true },
        { title: '请求限制', dataIndex: 'rate_limit', key: 'rate_limit', width: 100, render: (rateLimit: number) => `${rateLimit}/分钟` },
        {
            title: '权限',
            dataIndex: 'scopes',
            key: 'scopes',
            width: 320,
            render: (scopes: string[] | undefined) => {
                const list = Array.isArray(scopes) && scopes.length > 0 ? scopes : ALL_SCOPES;
                const mode = inferPermissionMode(list);
                const modeLabel = mode === 'readonly' ? '只读' : mode === 'all' ? '全部' : '自定义';
                const color = mode === 'readonly' ? 'green' : mode === 'all' ? 'red' : 'blue';
                return (
                    <Space size={[4, 4]} wrap>
                        <Tag color={color}>{modeLabel}</Tag>
                        {list.map(scope => {
                            const option = API_SCOPE_OPTIONS.find(item => item.value === scope);
                            return <Tag key={scope}>{option?.label || scope}</Tag>;
                        })}
                    </Space>
                );
            },
        },
        { title: '状态', dataIndex: 'is_active', key: 'is_active', width: 80, render: (isActive: boolean) => <Tag color={isActive ? 'green' : 'red'}>{isActive ? '启用' : '禁用'}</Tag> },
        { title: '过期时间', dataIndex: 'expires_at', key: 'expires_at', width: 160, render: (expiresAt: string) => expiresAt ? dayjs(expiresAt).format('YYYY-MM-DD HH:mm:ss') : '永不过期' },
        { title: '最后使用', dataIndex: 'last_used_at', key: 'last_used_at', width: 160, render: (lastUsedAt: string) => lastUsedAt ? dayjs(lastUsedAt).format('YYYY-MM-DD HH:mm:ss') : '从未使用' },
        { title: '创建时间', dataIndex: 'created_at', key: 'created_at', width: 160, render: (createdAt: string) => dayjs(createdAt).format('YYYY-MM-DD HH:mm:ss') },
        {
            title: '操作', key: 'action', width: 200, fixed: 'right' as const, render: (_: any, record: ApiKey) => (
                <Space size="small">
                    <Button type="link" icon={<EditOutlined />} onClick={() => handleEdit(record)} size="small">编辑</Button>
                    <Popconfirm title={`确定要${record.is_active ? '禁用' : '启用'}这个API密钥吗？`} onConfirm={() => handleToggle(record.id, record.is_active)} okText="确定" cancelText="取消">
                        <Button type="link" style={{ color: record.is_active ? '#ff4d4f' : '#52c41a' }} size="small">{record.is_active ? '禁用' : '启用'}</Button>
                    </Popconfirm>
                    <Popconfirm title="确定要删除这个API密钥吗？" onConfirm={() => handleDelete(record.id)} okText="确定" cancelText="取消">
                        <Button type="link" danger icon={<DeleteOutlined />} size="small">删除</Button>
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    return (
        <div style={{ padding: 24 }}>
            <div style={{ marginBottom: 16 }}>
                <Button type="primary" icon={<PlusOutlined />} onClick={handleCreate}>创建API密钥</Button>
            </div>

            <Table columns={columns} scroll={{ x: 'max-content' }} dataSource={apiKeys} rowKey="id" pagination={{ total: initialTotal, showSizeChanger: true, showQuickJumper: true, showTotal: (total, range) => `第 ${range[0]}-${range[1]} 条，共 ${total} 条` }} />

            <Modal title={editingKey ? '编辑API密钥' : '创建API密钥'} open={modalVisible} onOk={handleSubmit} onCancel={() => setModalVisible(false)} width={680} footer={[<Button key="cancel" onClick={() => setModalVisible(false)}>取消</Button>, <Button key="submit" type="primary" onClick={handleSubmit}>{editingKey ? '更新' : '创建'}</Button>]}>
                <Form form={form} layout="vertical" initialValues={{ rate_limit: 1000, is_active: true, permission_mode: 'readonly', scopes: READONLY_SCOPES }}>
                    <Form.Item name="name" label="API密钥名称" rules={[{ required: true, message: '请输入API密钥名称' }]}><Input placeholder="请输入API密钥名称" /></Form.Item>

                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <Form.Item style={{ flex: 1 }} name="api_key" label="API密钥" rules={[{ required: true, message: '请输入API密钥' }]}><Input placeholder="请输入API密钥" disabled={!!editingKey} /></Form.Item>
                        {!editingKey && <Button style={{ marginTop: 5 }} type="primary" onClick={handleGenerate}>自动生成</Button>}
                    </div>

                    <Form.Item name="description" label="描述"><Input.TextArea placeholder="请输入API密钥描述" rows={3} /></Form.Item>
                    <Form.Item name="rate_limit" label="请求限制（次/分钟）" rules={[{ required: true, message: '请输入请求限制' }]}><InputNumber min={1} max={10000} placeholder="请输入请求限制" style={{ width: '100%' }} /></Form.Item>
                    <Form.Item name="expires_at" label="过期时间"><DatePicker style={{ width: '100%' }} placeholder="选择过期时间，不选择则永不过期" showTime /></Form.Item>

                    <Form.Item name="permission_mode" label="权限模式" required>
                        <Radio.Group onChange={(e) => applyPermissionMode(e.target.value)} value={permissionMode}>
                            <Radio.Button value="readonly">只读权限</Radio.Button>
                            <Radio.Button value="all">全部权限</Radio.Button>
                            <Radio.Button value="custom">自定义权限</Radio.Button>
                        </Radio.Group>
                    </Form.Item>

                    {permissionMode === 'all' && <Alert type="warning" showIcon style={{ marginBottom: 12 }} message="全部权限 API Key 仅应放在服务端/BFF 使用，不要暴露到浏览器前端、移动端包或公开仓库。" />}
                    {permissionMode === 'readonly' && <Alert type="success" showIcon style={{ marginBottom: 12 }} message="只读权限适合公开前端页面，仅允许读取内容、单页和媒体资源。" />}
                    {permissionMode === 'custom' && hasRiskyScope && <Alert type="warning" showIcon style={{ marginBottom: 12 }} message="当前自定义权限包含写入或函数调用能力。若用于前端直连，请确认该写入是低风险场景，并叠加限流、验证码、字段校验或审核策略；更安全的方式是使用 BFF 或项目用户登录态。" />}

                    <Form.Item name="scopes" label="接口权限" rules={[{ validator: (_, value) => Array.isArray(value) && value.length > 0 ? Promise.resolve() : Promise.reject(new Error('请至少选择一个接口权限')) }]}>
                        <Checkbox.Group style={{ width: '100%' }} disabled={permissionMode !== 'custom'}>
                            <Space direction="vertical" style={{ width: '100%' }}>
                                {Object.entries(groupedScopeOptions).map(([group, options]) => (
                                    <div key={group}>
                                        <Typography.Text strong>{group}</Typography.Text>
                                        <div style={{ marginTop: 6 }}>
                                            <Space wrap>
                                                {options.map(option => <Checkbox key={option.value} value={option.value}>{option.label}</Checkbox>)}
                                            </Space>
                                        </div>
                                    </div>
                                ))}
                            </Space>
                        </Checkbox.Group>
                    </Form.Item>

                    <Typography.Paragraph type="secondary" style={{ marginTop: -8 }}>API Key 权限用于限制最大能力。前端写接口较多时，不建议暴露长期写权限 Key，应使用 BFF 或项目用户登录态。</Typography.Paragraph>
                    <Form.Item name="is_active" label="是否启用" valuePropName="checked"><Switch /></Form.Item>
                </Form>
            </Modal>
        </div>
    );
};

export default ApiKeyList;
