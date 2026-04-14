import React, { useEffect, useMemo, useState } from 'react';
import { Alert, Button, Card, Checkbox, Form, Input, Modal, Popconfirm, Select, Space, Table, Tabs, Tag, Typography, message } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';
import api from '../../util/Service';

const PERMISSION_OPTIONS = [
  { label: '内容读取', value: 'content.read' },
  { label: '内容创建', value: 'content.create' },
  { label: '内容更新', value: 'content.update' },
  { label: '内容删除', value: 'content.delete' },
  { label: '单页读取', value: 'page.read' },
  { label: '单页更新', value: 'page.update' },
  { label: '媒体读取', value: 'media.read' },
  { label: '媒体创建', value: 'media.create' },
  { label: '媒体更新', value: 'media.update' },
  { label: '媒体删除', value: 'media.delete' },
  { label: '函数调用', value: 'function.invoke' },
];

interface ProjectRole { id: number; code: string; name: string; description?: string; permissions?: string[]; is_system?: boolean; }
interface ProjectUser { id: number; email?: string; username?: string; name?: string; status: string; role_ids?: number[]; permissions?: string[]; last_login_at?: string; created_at?: string; }

const ProjectAuthUsers: React.FC = () => {
  const [users, setUsers] = useState<ProjectUser[]>([]);
  const [roles, setRoles] = useState<ProjectRole[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [userModalOpen, setUserModalOpen] = useState(false);
  const [roleModalOpen, setRoleModalOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<ProjectUser | null>(null);
  const [editingRole, setEditingRole] = useState<ProjectRole | null>(null);
  const [userForm] = Form.useForm();
  const [roleForm] = Form.useForm();

  const roleOptions = useMemo(() => roles.map(role => ({ label: role.name, value: role.id })), [roles]);

  const loadAll = () => {
    setLoading(true);
    Promise.all([
      api.get('/manage/project-auth/users'),
      api.get('/manage/project-auth/roles'),
    ]).then(([userRes, roleRes]) => {
      const userData = userRes?.data ?? {};
      setUsers(userData?.data ?? []);
      setTotal(userData?.total ?? 0);
      setRoles(roleRes?.data ?? []);
    }).catch((error) => message.error(error?.message || '加载项目用户失败'))
      .finally(() => setLoading(false));
  };

  useEffect(() => { loadAll(); }, []);

  const openCreateUser = () => {
    setEditingUser(null);
    userForm.resetFields();
    const member = roles.find(role => role.code === 'member');
    userForm.setFieldsValue({ status: 'active', role_ids: member ? [member.id] : [] });
    setUserModalOpen(true);
  };

  const openEditUser = (user: ProjectUser) => {
    setEditingUser(user);
    userForm.resetFields();
    userForm.setFieldsValue({ ...user, password: '', role_ids: user.role_ids ?? [] });
    setUserModalOpen(true);
  };

  const saveUser = async () => {
    const values = await userForm.validateFields();
    const url = editingUser ? `/manage/project-auth/users/${editingUser.id}` : '/manage/project-auth/users';
    api.post(url, values).then(() => {
      message.success(editingUser ? '已更新项目用户' : '已创建项目用户');
      setUserModalOpen(false);
      loadAll();
    }).catch((error) => message.error(error?.response?.data?.message || error?.message || '保存失败'));
  };

  const openCreateRole = () => {
    setEditingRole(null);
    roleForm.resetFields();
    roleForm.setFieldsValue({ permissions: ['content.read', 'page.read', 'media.read'] });
    setRoleModalOpen(true);
  };

  const openEditRole = (role: ProjectRole) => {
    setEditingRole(role);
    roleForm.resetFields();
    roleForm.setFieldsValue({ ...role, permissions: role.permissions ?? [] });
    setRoleModalOpen(true);
  };

  const saveRole = async () => {
    const values = await roleForm.validateFields();
    const url = editingRole ? `/manage/project-auth/roles/${editingRole.id}` : '/manage/project-auth/roles';
    api.post(url, values).then(() => {
      message.success(editingRole ? '已更新项目角色' : '已创建项目角色');
      setRoleModalOpen(false);
      loadAll();
    }).catch((error) => message.error(error?.response?.data?.message || error?.message || '保存失败'));
  };

  return (
    <div style={{ padding: 24 }}>
      <Card title="项目用户认证" extra={<Button onClick={loadAll}>刷新</Button>}>
        <Alert type="info" showIcon style={{ marginBottom: 16 }} message="项目用户认证是系统内置可选模块。请先在“项目配置 -> 项目用户”启用后，再让前端使用项目用户登录态访问 OpenAPI。" />
        <Tabs items={[
          {
            key: 'users', label: '项目用户', children: <>
              <div style={{ marginBottom: 12 }}><Button type="primary" icon={<PlusOutlined />} onClick={openCreateUser}>创建用户</Button></div>
              <Table loading={loading} rowKey="id" dataSource={users} pagination={{ total }} scroll={{ x: 'max-content' }} columns={[
                { title: 'ID', dataIndex: 'id', width: 80, fixed: 'left' as const },
                { title: '用户', width: 240, render: (_: any, record: ProjectUser) => (
                  <Space direction="vertical" size={0}>
                    <Typography.Text>{record.name || '-'}</Typography.Text>
                    <Typography.Text type="secondary">{record.email || record.username || '-'}</Typography.Text>
                  </Space>
                ) },
                { title: '状态', dataIndex: 'status', width: 90, render: (status: string) => <Tag color={status === 'active' ? 'green' : 'red'}>{status === 'active' ? '启用' : '禁用'}</Tag> },
                { title: '角色', dataIndex: 'role_ids', width: 240, render: (roleIds: number[] = []) => {
                  const userRoles = roles.filter(role => roleIds.includes(role.id));
                  return userRoles.length > 0
                    ? <Space wrap>{userRoles.map(role => <Tag key={role.id}>{role.name}（{role.code}）</Tag>)}</Space>
                    : <Typography.Text type="secondary">未分配</Typography.Text>;
                } },
                { title: '权限', dataIndex: 'permissions', width: 520, render: (permissions: string[] = []) => <Space wrap>{permissions.map(p => <Tag key={p}>{p}</Tag>)}</Space> },
                { title: '最后登录', dataIndex: 'last_login_at', width: 180, render: (v: string) => v || '-' },
                { title: '操作', width: 180, render: (_: any, record: ProjectUser) => <Space>
                  <Button type="link" icon={<EditOutlined />} onClick={() => openEditUser(record)}>编辑</Button>
                  <Popconfirm title="确定删除该项目用户？" onConfirm={() => api.post(`/manage/project-auth/users/${record.id}/delete`).then(loadAll)}><Button type="link" danger icon={<DeleteOutlined />}>删除</Button></Popconfirm>
                </Space> },
              ]} />
            </>
          },
          {
            key: 'roles', label: '项目角色', children: <>
              <div style={{ marginBottom: 12 }}><Button type="primary" icon={<PlusOutlined />} onClick={openCreateRole}>创建角色</Button></div>
              <Table loading={loading} rowKey="id" dataSource={roles} pagination={false} columns={[
                { title: '编码', dataIndex: 'code' },
                { title: '名称', dataIndex: 'name' },
                { title: '说明', dataIndex: 'description', render: (description: string | undefined, record: ProjectRole) => (
                  <Space wrap>
                    {record.code === 'member' && <Tag color="green">公开注册用户默认角色</Tag>}
                    <Typography.Text type="secondary">{description || '-'}</Typography.Text>
                  </Space>
                ) },
                { title: '系统角色', dataIndex: 'is_system', render: (v: boolean) => v ? <Tag color="blue">系统</Tag> : <Tag>自定义</Tag> },
                { title: '权限', dataIndex: 'permissions', render: (permissions: string[] = []) => <Space wrap>{permissions.map(p => <Tag key={p}>{p}</Tag>)}</Space> },
                { title: '操作', width: 180, render: (_: any, record: ProjectRole) => <Space>
                  <Button type="link" icon={<EditOutlined />} onClick={() => openEditRole(record)}>编辑</Button>
                  <Popconfirm title="确定删除该项目角色？" onConfirm={() => api.post(`/manage/project-auth/roles/${record.id}/delete`).then(loadAll)} disabled={!!record.is_system}><Button type="link" danger disabled={!!record.is_system} icon={<DeleteOutlined />}>删除</Button></Popconfirm>
                </Space> },
              ]} />
            </>
          }
        ]} />
      </Card>

      <Modal title={editingUser ? '编辑项目用户' : '创建项目用户'} open={userModalOpen} onOk={saveUser} onCancel={() => setUserModalOpen(false)}>
        <Form form={userForm} layout="vertical">
          <Form.Item name="email" label="邮箱"><Input /></Form.Item>
          <Form.Item name="username" label="用户名"><Input /></Form.Item>
          <Form.Item name="name" label="显示名称"><Input /></Form.Item>
          <Form.Item name="password" label={editingUser ? '新密码（留空不修改）' : '密码'} rules={editingUser ? [] : [{ required: true, message: '请输入密码' }]}><Input.Password /></Form.Item>
          <Form.Item name="status" label="状态"><Select options={[{ label: '启用', value: 'active' }, { label: '禁用', value: 'disabled' }]} /></Form.Item>
          <Form.Item name="role_ids" label="角色"><Select mode="multiple" options={roleOptions} /></Form.Item>
        </Form>
      </Modal>

      <Modal title={editingRole ? '编辑项目角色' : '创建项目角色'} open={roleModalOpen} onOk={saveRole} onCancel={() => setRoleModalOpen(false)} width={720}>
        <Form form={roleForm} layout="vertical">
          <Form.Item name="code" label="角色编码" rules={[{ required: true, message: '请输入角色编码' }]}><Input disabled={!!editingRole?.is_system} /></Form.Item>
          <Form.Item name="name" label="角色名称" rules={[{ required: true, message: '请输入角色名称' }]}><Input /></Form.Item>
          <Form.Item name="description" label="描述"><Input.TextArea rows={2} /></Form.Item>
          <Form.Item name="permissions" label="权限"><Checkbox.Group options={PERMISSION_OPTIONS} /></Form.Item>
          <Typography.Paragraph type="secondary">角色权限决定项目用户登录态可访问哪些 OpenAPI。服务端会按每次请求做权限校验。</Typography.Paragraph>
        </Form>
      </Modal>
    </div>
  );
};

export default ProjectAuthUsers;
