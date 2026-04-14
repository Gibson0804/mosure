import React, { useEffect, useMemo, useState } from 'react';
import { Table, Form, Input, Select, Button, Space, Tag, Modal, message, Switch, Drawer, Upload, Tabs, Descriptions } from 'antd';
import type { ColumnsType, TablePaginationConfig } from 'antd/es/table';
import { UploadOutlined, PlusOutlined, LinkOutlined, ReloadOutlined, RobotOutlined, LoadingOutlined, CopyOutlined, InfoCircleOutlined } from '@ant-design/icons';
import { Progress, Alert, Collapse, Typography, Tooltip } from 'antd';
import { usePage } from '@inertiajs/react';
import api from '../../util/Service';

interface PageItem {
  id: number;
  slug: string;
  title: string;
  description?: string | null;
  page_type: 'single' | 'spa';
  status: 'draft' | 'published';
  external_url?: string | null;
  access_url?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

interface PluginPageItem {
  plugin_id: string;
  name: string;
  version: string;
  description: string;
  access_url: string;
  source: 'plugin';
}

interface ListMeta { total: number; page: number; page_size: number; page_count: number; }

const PageHostingPage: React.FC = () => {
  const { props: pageProps } = usePage<any>();
  const [form] = Form.useForm();
  const [editForm] = Form.useForm();
  const [externalForm] = Form.useForm();
  const [zipForm] = Form.useForm();
  const [data, setData] = useState<PageItem[]>([]);
  const [meta, setMeta] = useState<ListMeta>({ total: 0, page: 1, page_size: 15, page_count: 0 });
  const [loading, setLoading] = useState(false);
  const [pluginPages, setPluginPages] = useState<PluginPageItem[]>([]);

  // 编辑弹窗
  const [editOpen, setEditOpen] = useState(false);
  const [editing, setEditing] = useState<PageItem | null>(null);
  const [editLoading, setEditLoading] = useState(false);
  const [externalOpen, setExternalOpen] = useState(false);
  const [externalEditing, setExternalEditing] = useState<PageItem | null>(null);
  const [externalLoading, setExternalLoading] = useState(false);

  // 详情抽屉
  const [detailOpen, setDetailOpen] = useState(false);
  const [detailData, setDetailData] = useState<any>(null);
  const [detailLoading, setDetailLoading] = useState(false);

  // ZIP 上传弹窗
  const [zipOpen, setZipOpen] = useState(false);
  const [zipLoading, setZipLoading] = useState(false);

  // 模型摘要（用于生成 AI 提示词）
  const [modelsSummary, setModelsSummary] = useState<any[]>([]);
  const [modelsLoaded, setModelsLoaded] = useState(false);

  // AI 生成
  const [aiOpen, setAiOpen] = useState(false);
  const [aiPrompt, setAiPrompt] = useState('');
  const [aiLoading, setAiLoading] = useState(false);
  const [aiTaskId, setAiTaskId] = useState<number | null>(null);
  const [aiStatus, setAiStatus] = useState<string>('');
  const [aiResult, setAiResult] = useState<string>('');
  const [aiError, setAiError] = useState<string>('');

  const columns: ColumnsType<PageItem> = useMemo(() => [
    { title: 'Slug', dataIndex: 'slug', key: 'slug', width: 160 },
    { title: '标题', dataIndex: 'title', key: 'title', width: 200 },
    { title: '类型', dataIndex: 'page_type', key: 'page_type', width: 100, render: (t: string) => t === 'single' ? '单页面' : 'SPA' },
    {
      title: '状态', dataIndex: 'status', key: 'status', width: 100,
      render: (s: string) => <Tag color={s === 'published' ? 'green' : 'orange'}>{s === 'published' ? '已发布' : '草稿'}</Tag>
    },
    { title: '描述', dataIndex: 'description', key: 'description', ellipsis: true },
    { title: '更新时间', dataIndex: 'updated_at', key: 'updated_at', width: 180 },
    {
      title: '操作', key: 'actions', fixed: 'right', width: 380, render: (_, rec) => (
        <Space>
          <Button size="small" onClick={() => onViewDetail(rec)}>查看</Button>
          <Button size="small" onClick={() => onEdit(rec)}>编辑</Button>
          <Button size="small" onClick={() => onToggle(rec)}>{rec.status === 'published' ? '下线' : '发布'}</Button>
          <Button size="small" onClick={() => onVisit(rec)} icon={<LinkOutlined />}>访问</Button>
          <Button size="small" danger onClick={() => onDelete(rec)}>删除</Button>
        </Space>
      )
    },
  ], []);

  const externalColumns: ColumnsType<PageItem> = useMemo(() => [
    { title: 'Slug', dataIndex: 'slug', key: 'slug', width: 160 },
    { title: '标题', dataIndex: 'title', key: 'title', width: 220 },
    {
      title: '外部链接',
      dataIndex: 'external_url',
      key: 'external_url',
      ellipsis: true,
      render: (url?: string | null) => url || '-',
    },
    {
      title: '状态', dataIndex: 'status', key: 'status', width: 100,
      render: (s: string) => <Tag color={s === 'published' ? 'green' : 'orange'}>{s === 'published' ? '已发布' : '草稿'}</Tag>
    },
    { title: '描述', dataIndex: 'description', key: 'description', ellipsis: true },
    { title: '更新时间', dataIndex: 'updated_at', key: 'updated_at', width: 180 },
    {
      title: '操作', key: 'actions', fixed: 'right', width: 380, render: (_, rec) => (
        <Space>
          <Button size="small" onClick={() => onViewDetail(rec)}>查看</Button>
          <Button size="small" onClick={() => onEditExternal(rec)}>编辑</Button>
          <Button size="small" onClick={() => onToggle(rec)}>{rec.status === 'published' ? '下线' : '发布'}</Button>
          <Button size="small" onClick={() => onVisit(rec)} icon={<LinkOutlined />}>访问</Button>
          <Button size="small" danger onClick={() => onDelete(rec)}>删除</Button>
        </Space>
      )
    },
  ], []);

  const pluginColumns: ColumnsType<PluginPageItem> = useMemo(() => [
    { title: '插件 ID', dataIndex: 'plugin_id', key: 'plugin_id', width: 160 },
    { title: '名称', dataIndex: 'name', key: 'name', width: 200 },
    { title: '版本', dataIndex: 'version', key: 'version', width: 100 },
    { title: '描述', dataIndex: 'description', key: 'description', ellipsis: true },
    {
      title: '操作', key: 'actions', width: 120, render: (_, rec) => (
        <Button size="small" icon={<LinkOutlined />} onClick={() => window.open(rec.access_url, '_blank')}>访问</Button>
      )
    },
  ], []);

  const fetchList = async (page?: number, pageSize?: number) => {
    setLoading(true);
    try {
      const params: any = { page: page ?? meta.page, page_size: pageSize ?? meta.page_size };
      const values = form.getFieldsValue();
      if (values.keyword) params['filter[keyword]'] = values.keyword;
      if (values.status) params['filter[status]'] = values.status;
      const res: any = await api.get('/manage/pages/list', { params });
      setData(res.data?.items ?? []);
      setMeta(res.data?.meta ?? { total: 0, page: 1, page_size: 15, page_count: 0 });
      setPluginPages(res.data?.plugin_pages ?? []);
    } catch (e: any) {
      message.error(e?.message || '加载失败');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchList(1); }, []);

  const hostedPages = useMemo(() => data.filter(item => !item.external_url), [data]);
  const externalPages = useMemo(() => data.filter(item => !!item.external_url), [data]);

  const onSearch = () => fetchList(1);
  const onReset = () => { form.resetFields(); fetchList(1); };

  const onTableChange = (pagination: TablePaginationConfig) => {
    fetchList(pagination.current, pagination.pageSize);
  };

  // 加载模型摘要
  const loadModelsSummary = async () => {
    if (modelsLoaded) return;
    try {
      const res: any = await api.get('/manage/pages/models-summary');
      setModelsSummary(res.data?.models ?? []);
      setModelsLoaded(true);
    } catch { /* ignore */ }
  };

  // 生成 AI 提示词
  const generateAiPrompt = (): string => {
    const prefix = pageProps?.project_info?.prefix || 'your_prefix';
    const appUrl = window.location.origin;

    const listModels = modelsSummary.filter(m => m.mold_type === 'list');
    const singleModels = modelsSummary.filter(m => m.mold_type === 'single');

    let modelsText = '当前项目没有内容模型。';
    if (modelsSummary.length > 0) {
      const formatModel = (m: any) => {
        const fieldsStr = m.fields?.map((f: any) => `  - ${f.field}（${f.label}，类型: ${f.type}）`).join('\n') || '  （无自定义字段）';
        return `模型名称: ${m.name}\n表名(table_name): ${m.table_name}\n类型: ${m.mold_type === 'list' ? '内容列表（list）' : '内容单页（single）'}\n字段:\n${fieldsStr}`;
      };
      let parts: string[] = [];
      if (listModels.length > 0) {
        parts.push('### 内容列表模型（list 类型，可包含多条数据）\n' + listModels.map(formatModel).join('\n\n'));
      }
      if (singleModels.length > 0) {
        parts.push('### 内容单页模型（single 类型，只有一条数据，用于配置页/关于页等）\n' + singleModels.map(formatModel).join('\n\n'));
      }
      modelsText = '当前项目的内容模型如下：\n\n' + parts.join('\n\n');
    }

    return `请帮我生成一个完整的单文件 HTML 页面，用于部署到 Mosure 前端托管系统。

## 项目信息
${modelsText}

## Mosure SDK（系统会自动注入到页面中，你不需要引入）
页面中可以通过 window.Mosure 对象与内容模型进行数据交互。根据模型类型不同，使用不同的方法：

### 一、内容列表模型（list 类型）的方法

1. Mosure.getList(tableName, params)
   - 获取内容列表
   - params 可选: { page: 1, page_size: 10 }
   - 返回: { code: 200, data: { items: [{id, field1, field2, ...}, ...], total: 100, page: 1, page_size: 15, page_count: 7, fields: [...] } }

2. Mosure.getItem(tableName, id)
   - 获取单条内容详情
   - 返回: { code: 200, data: { id, field1, field2, created_at, updated_at, ... } }
   - 如果不存在返回: { code: 404, message: '内容不存在' }

3. Mosure.createItem(tableName, data)
   - 创建内容，data 为字段键值对，如 { title: '标题', content: '内容' }
   - 返回: { code: 200, data: { message: '创建成功' } }

4. Mosure.updateItem(tableName, id, data)
   - 更新内容
   - 返回: { code: 200, data: { message: '更新成功' } }

5. Mosure.deleteItem(tableName, id)
   - 删除内容
   - 返回: { code: 200, data: { message: '删除成功' } }

### 二、内容单页模型（single 类型）的方法

6. Mosure.getPage(tableName)
   - 获取单页内容（整个页面只有一条数据，以键值对形式存储）
   - 返回: { code: 200, data: { field1: value1, field2: value2, ... } }
   - 如果无内容返回: { code: 200, data: {} }

7. Mosure.updatePage(tableName, data)
   - 更新单页内容，data 为字段键值对
   - 返回: { code: 200, data: [] }

### 通用说明
- 所有方法都是 async 的，需要 await 调用
- code=200 表示成功，其他值表示失败（message 字段含错误信息）
- tableName 参数使用上面模型中的 table_name 值
- list 类型模型使用 getList/getItem/createItem/updateItem/deleteItem
- single 类型模型使用 getPage/updatePage

## HTML 页面要求
1. 必须是完整的 HTML 文档（含 <!DOCTYPE html>、<html>、<head>、<body>）
2. 所有 CSS 使用内联 <style> 标签，所有 JS 使用内联 <script> 标签
3. 页面设计应简洁美观，使用现代化 UI 风格，适配移动端
4. 不要引入任何外部 CSS/JS 库（不要用 CDN）
5. 不要在 HTML 中包含 Mosure SDK 的 <script> 标签（系统会自动注入）
6. 如果页面需要与数据交互，使用 window.Mosure 的方法
7. 确保页面可以独立运行，所有功能完整可用

## 我的需求
请在这里描述你想要的页面功能...
`;
  };

  // 复制提示词
  const copyAiPrompt = () => {
    const text = generateAiPrompt();

    // 优先使用 Clipboard API，否则 fallback 到 document.execCommand
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(() => {
        message.success('提示词已复制到剪贴板');
      }).catch(() => {
        fallbackCopy(text);
      });
    } else {
      fallbackCopy(text);
    }
  };

  const fallbackCopy = (text: string) => {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    try {
      const successful = document.execCommand('copy');
      document.body.removeChild(ta);
      if (successful) {
        message.success('提示词已复制到剪贴板');
      } else {
        message.error('复制失败，请手动复制');
      }
    } catch (err) {
      document.body.removeChild(ta);
      message.error('复制失败，请手动复制');
    }
  };

  // 新建/编辑
  const onAdd = () => {
    setEditing(null);
    editForm.resetFields();
    editForm.setFieldsValue({ status: 'published' });
    setEditOpen(true);
    loadModelsSummary();
  };

  const onEdit = (rec: PageItem) => {
    setEditing(rec);
    setDetailLoading(true);
    api.get(`/manage/pages/get/${rec.slug}`).then((res: any) => {
      editForm.setFieldsValue(res.data);
      setEditOpen(true);
    }).catch((e: any) => {
      message.error(e?.message || '获取详情失败');
    }).finally(() => setDetailLoading(false));
  };

  const onAddExternal = () => {
    setExternalEditing(null);
    externalForm.resetFields();
    externalForm.setFieldsValue({ status: 'published' });
    setExternalOpen(true);
  };

  const onEditExternal = (rec: PageItem) => {
    setExternalEditing(rec);
    setDetailLoading(true);
    api.get(`/manage/pages/get/${rec.slug}`).then((res: any) => {
      externalForm.setFieldsValue(res.data);
      setExternalOpen(true);
    }).catch((e: any) => {
      message.error(e?.message || '获取详情失败');
    }).finally(() => setDetailLoading(false));
  };

  const onEditSubmit = async () => {
    try {
      const values = await editForm.validateFields();
      setEditLoading(true);
      if (editing) {
        await api.post(`/manage/pages/update/${editing.slug}`, values);
        message.success('更新成功');
      } else {
        await api.post('/manage/pages/create', values);
        message.success('创建成功');
      }
      setEditOpen(false);
      fetchList();
    } catch (e: any) {
      if (e?.message) message.error(e.message);
    } finally {
      setEditLoading(false);
    }
  };

  const onExternalSubmit = async () => {
    try {
      const values = await externalForm.validateFields();
      setExternalLoading(true);
      const payload = {
        ...values,
        page_type: 'spa',
        html_content: null,
      };
      if (externalEditing) {
        await api.post(`/manage/pages/update/${externalEditing.slug}`, payload);
        message.success('更新成功');
      } else {
        await api.post('/manage/pages/create', payload);
        message.success('创建成功');
      }
      setExternalOpen(false);
      fetchList();
    } catch (e: any) {
      if (e?.message) message.error(e.message);
    } finally {
      setExternalLoading(false);
    }
  };

  const onToggle = async (rec: PageItem) => {
    try {
      await api.post(`/manage/pages/toggle/${rec.slug}`);
      message.success(rec.status === 'published' ? '已下线' : '已发布');
      fetchList();
    } catch (e: any) {
      message.error(e?.message || '操作失败');
    }
  };

  const onDelete = (rec: PageItem) => {
    Modal.confirm({
      title: '确认删除',
      content: `确定要删除页面「${rec.title}」吗？`,
      onOk: async () => {
        try {
          await api.post(`/manage/pages/delete/${rec.slug}`);
          message.success('删除成功');
          fetchList();
        } catch (e: any) {
          message.error(e?.message || '删除失败');
        }
      }
    });
  };

  const onVisit = (rec: PageItem) => {
    const base = window.location.origin;
    const prefix = pageProps?.project_info?.prefix || '';
    const url = rec.external_url || rec.access_url || `${base}/sites/${prefix}/${rec.slug}`;
    window.open(url, '_blank');
  };

  const onViewDetail = async (rec: PageItem) => {
    setDetailLoading(true);
    setDetailOpen(true);
    try {
      const res: any = await api.get(`/manage/pages/get/${rec.slug}`);
      setDetailData(res.data);
    } catch (e: any) {
      message.error(e?.message || '获取详情失败');
    } finally {
      setDetailLoading(false);
    }
  };

  // AI 生成页面
  const onAiGenerate = () => {
    setAiPrompt('');
    setAiTaskId(null);
    setAiStatus('');
    setAiResult('');
    setAiError('');
    setAiOpen(true);
  };

  const onAiSubmit = async () => {
    if (!aiPrompt.trim()) { message.warning('请输入页面描述'); return; }
    setAiLoading(true);
    setAiStatus('submitting');
    setAiError('');
    setAiResult('');
    try {
      const res: any = await api.post('/manage/pages/ai-generate', { prompt: aiPrompt.trim() });
      const taskId = res.data?.task_id;
      if (!taskId) { setAiError('未获取到任务 ID'); setAiLoading(false); return; }
      setAiTaskId(taskId);
      setAiStatus('processing');
      pollAiTask(taskId);
    } catch (e: any) {
      setAiError(e?.message || '提交失败');
      setAiLoading(false);
      setAiStatus('');
    }
  };

  const pollAiTask = (taskId: number) => {
    let attempts = 0;
    const maxAttempts = 60;
    const interval = setInterval(async () => {
      attempts++;
      try {
        const res: any = await api.get(`/manage/sys-tasks/detail/${taskId}`);
        const task = res.data;
        if (!task) return;

        if (task.status === 'success') {
          clearInterval(interval);
          setAiLoading(false);
          setAiStatus('success');
          const summary = task.result?.summary_md || '页面生成完成';
          setAiResult(summary);
          message.success('AI 页面生成成功');
          fetchList();
        } else if (task.status === 'failed' || task.status === 'canceled') {
          clearInterval(interval);
          setAiLoading(false);
          setAiStatus('failed');
          setAiError(task.error_message || '任务失败');
        } else if (attempts >= maxAttempts) {
          clearInterval(interval);
          setAiLoading(false);
          setAiStatus('timeout');
          setAiError('任务超时，请在任务中心查看详情');
        }
      } catch {
        // 网络错误继续重试
      }
    }, 3000);
  };

  // ZIP 上传
  const onZipUpload = () => {
    zipForm.resetFields();
    setZipOpen(true);
  };

  const onZipSubmit = async () => {
    try {
      const values = await zipForm.validateFields();
      const formData = new FormData();
      formData.append('slug', values.slug);
      formData.append('title', values.title);
      if (values.description) formData.append('description', values.description);
      formData.append('file', values.file.file);
      setZipLoading(true);
      await api.post('/manage/pages/deploy-zip', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      message.success('部署成功');
      setZipOpen(false);
      fetchList();
    } catch (e: any) {
      if (e?.message) message.error(e.message);
    } finally {
      setZipLoading(false);
    }
  };

  return (
    <div style={{ padding: 24 }}>
      <h2 style={{ marginBottom: 16 }}>前端托管</h2>

      <Tabs defaultActiveKey="hosted" items={[
        {
          key: 'hosted',
          label: `托管页面 (${hostedPages.length})`,
          children: (
            <>
              <Form form={form} layout="inline" style={{ marginBottom: 16 }}>
                <Form.Item style={{ marginLeft: 'auto' }}>
                  <Space>
                    {/* <Button type="primary" icon={<RobotOutlined />} onClick={onAiGenerate}>AI 生成</Button> */}
                    <Button icon={<PlusOutlined />} onClick={onAdd}>新建页面</Button>
                    <Button icon={<UploadOutlined />} onClick={onZipUpload}>ZIP 部署</Button>
                  </Space>
                </Form.Item>
              </Form>

              <Table<PageItem>
                rowKey="id"
                columns={columns}
                dataSource={hostedPages}
                loading={loading}
                scroll={{ x: 1200 }}
                pagination={{
                  current: meta.page,
                  pageSize: meta.page_size,
                  total: meta.total,
                  showSizeChanger: true,
                  showTotal: (t) => `共 ${t} 条`,
                }}
                onChange={onTableChange}
              />
            </>
          ),
        },
        {
          key: 'external',
          label: `外部链接 (${externalPages.length})`,
          children: (
            <>
              <p style={{ marginBottom: 16, color: '#666' }}>登记托管在外部平台的前端应用链接，client 端会把它们作为项目入口展示。</p>
              <div style={{ marginBottom: 16 }}>
                <Button icon={<PlusOutlined />} onClick={onAddExternal}>新增外部链接</Button>
              </div>
              <Table<PageItem>
                rowKey="id"
                columns={externalColumns}
                dataSource={externalPages}
                loading={loading}
                scroll={{ x: 1400 }}
                pagination={false}
              />
            </>
          ),
        },
        {
          key: 'plugin',
          label: `插件前端 (${pluginPages.length})`,
          children: (
            <>
              <p style={{ marginBottom: 16, color: '#666' }}>以下是通过插件提供的前端页面，仅供查看，不可编辑或删除。</p>
              <Table<PluginPageItem>
                rowKey="plugin_id"
                columns={pluginColumns}
                dataSource={pluginPages}
                pagination={false}
              />
            </>
          ),
        }
      ]} />

      {/* 编辑弹窗 */}
      <Modal
        title={editing ? `编辑页面: ${editing.slug}` : '新建托管页面'}
        open={editOpen}
        onOk={onEditSubmit}
        onCancel={() => setEditOpen(false)}
        confirmLoading={editLoading}
        width={800}
        destroyOnClose
      >
        <Form form={editForm} layout="vertical">
          {!editing && (
            <Form.Item name="slug" label="Slug (URL标识)" rules={[
              { required: true, message: '请输入 slug' },
              { pattern: /^[a-z0-9][a-z0-9-]*$/, message: '仅限小写字母、数字、短横线' }
            ]}>
              <Input placeholder="如 todolist" />
            </Form.Item>
          )}
          <Form.Item name="title" label="标题" rules={[{ required: true, message: '请输入标题' }]}>
            <Input placeholder="页面标题" />
          </Form.Item>
          <Form.Item name="description" label="描述">
            <Input.TextArea rows={2} placeholder="页面描述（可选）" />
          </Form.Item>
          <Form.Item name="status" label="状态">
            <Select>
              <Select.Option value="published">发布</Select.Option>
              <Select.Option value="draft">草稿</Select.Option>
            </Select>
          </Form.Item>
          <Form.Item
            name="html_content"
            label="HTML 内容"
            extra="粘贴完整的单文件 HTML 页面代码。多页面应用请使用 ZIP 部署。"
          >
            <Input.TextArea rows={12} placeholder="<!DOCTYPE html>..." style={{ fontFamily: 'monospace', fontSize: 12 }} />
          </Form.Item>

          {!editing && (
            <Collapse
              size="small"
              style={{ marginTop: -8, marginBottom: 16 }}
              items={[{
                key: 'ai-prompt',
                label: (
                  <span>
                    <InfoCircleOutlined style={{ marginRight: 6 }} />
                    AI 生成提示词（复制给 DeepSeek / 豆包 / ChatGPT 等工具使用）
                  </span>
                ),
                children: (
                  <div>
                    <div style={{ marginBottom: 8, color: '#666', fontSize: 13 }}>
                      将以下提示词复制给任意 AI 工具，即可生成符合本系统要求的单页前端页面代码，然后将生成的 HTML 粘贴到上方输入框中。
                    </div>
                    <div style={{ position: 'relative' }}>
                      <pre style={{
                        background: '#f5f5f5', padding: '12px 40px 12px 12px', borderRadius: 6, fontSize: 12,
                        maxHeight: 360, overflow: 'auto', whiteSpace: 'pre-wrap', wordBreak: 'break-word',
                        lineHeight: 1.6, border: '1px solid #e8e8e8',
                      }}>
                        {generateAiPrompt()}
                      </pre>
                      <Tooltip title="复制提示词">
                        <Button
                          type="text"
                          icon={<CopyOutlined />}
                          onClick={copyAiPrompt}
                          style={{ position: 'absolute', top: 8, right: 8 }}
                        />
                      </Tooltip>
                    </div>
                  </div>
                ),
              }]}
            />
          )}
        </Form>
      </Modal>

      <Modal
        title={externalEditing ? `编辑外部链接: ${externalEditing.slug}` : '新增外部链接'}
        open={externalOpen}
        onOk={onExternalSubmit}
        onCancel={() => setExternalOpen(false)}
        confirmLoading={externalLoading}
        width={720}
        destroyOnClose
      >
        <Form form={externalForm} layout="vertical">
          {!externalEditing && (
            <Form.Item name="slug" label="Slug (URL标识)" rules={[
              { required: true, message: '请输入 slug' },
              { pattern: /^[a-z0-9][a-z0-9-]*$/, message: '仅限小写字母、数字、短横线' }
            ]}>
              <Input placeholder="如 my-external-app" />
            </Form.Item>
          )}
          <Form.Item name="title" label="标题" rules={[{ required: true, message: '请输入标题' }]}>
            <Input placeholder="外部应用标题" />
          </Form.Item>
          <Form.Item
            name="external_url"
            label="外部托管链接"
            rules={[
              { required: true, message: '请输入外部托管链接' },
              { type: 'url', message: '请输入合法的 URL' }
            ]}
          >
            <Input placeholder="如 https://example.com/my-app" />
          </Form.Item>
          <Form.Item name="description" label="描述">
            <Input.TextArea rows={2} placeholder="描述（可选）" />
          </Form.Item>
          <Form.Item name="status" label="状态">
            <Select>
              <Select.Option value="published">发布</Select.Option>
              <Select.Option value="draft">草稿</Select.Option>
            </Select>
          </Form.Item>
        </Form>
      </Modal>

      {/* 详情抽屉 */}
      <Drawer
        title="页面详情"
        open={detailOpen}
        onClose={() => { setDetailOpen(false); setDetailData(null); }}
        width={700}
      >
        {detailLoading ? <p>加载中...</p> : detailData && (
          <>
            <Descriptions bordered column={1} size="small">
              <Descriptions.Item label="Slug">{detailData.slug}</Descriptions.Item>
              <Descriptions.Item label="标题">{detailData.title}</Descriptions.Item>
              <Descriptions.Item label="类型">{detailData.page_type === 'single' ? '单页面' : 'SPA'}</Descriptions.Item>
              <Descriptions.Item label="状态">
                <Tag color={detailData.status === 'published' ? 'green' : 'orange'}>
                  {detailData.status === 'published' ? '已发布' : '草稿'}
                </Tag>
              </Descriptions.Item>
              <Descriptions.Item label="描述">{detailData.description || '-'}</Descriptions.Item>
              <Descriptions.Item label="访问地址">{detailData.access_url || '-'}</Descriptions.Item>
              <Descriptions.Item label="外部托管链接">{detailData.external_url || '-'}</Descriptions.Item>
              <Descriptions.Item label="创建时间">{detailData.created_at || '-'}</Descriptions.Item>
              <Descriptions.Item label="更新时间">{detailData.updated_at || '-'}</Descriptions.Item>
            </Descriptions>
            {detailData.html_content && (
              <div style={{ marginTop: 16 }}>
                <h4>HTML 内容</h4>
                <pre style={{
                  background: '#f5f5f5', padding: 12, borderRadius: 4, fontSize: 12,
                  maxHeight: 400, overflow: 'auto', whiteSpace: 'pre-wrap', wordBreak: 'break-all'
                }}>
                  {detailData.html_content}
                </pre>
              </div>
            )}
          </>
        )}
      </Drawer>

      {/* ZIP 部署弹窗 */}
      <Modal
        title="ZIP 部署 SPA 页面"
        open={zipOpen}
        onOk={onZipSubmit}
        onCancel={() => setZipOpen(false)}
        confirmLoading={zipLoading}
        destroyOnClose
      >
        <Form form={zipForm} layout="vertical">
          <Form.Item name="slug" label="Slug" rules={[
            { required: true, message: '请输入 slug' },
            { pattern: /^[a-z0-9][a-z0-9-]*$/, message: '仅限小写字母、数字、短横线' }
          ]}>
            <Input placeholder="如 my-app" />
          </Form.Item>
          <Form.Item name="title" label="标题" rules={[{ required: true, message: '请输入标题' }]}>
            <Input placeholder="页面标题" />
          </Form.Item>
          <Form.Item name="description" label="描述">
            <Input.TextArea rows={2} placeholder="描述（可选）" />
          </Form.Item>
          <Form.Item name="file" label="ZIP 文件" rules={[{ required: true, message: '请上传 ZIP 文件' }]}>
            <Upload beforeUpload={() => false} maxCount={1} accept=".zip">
              <Button icon={<UploadOutlined />}>选择 ZIP 文件</Button>
            </Upload>
          </Form.Item>
        </Form>
      </Modal>
      {/* AI 生成弹窗 */}
      <Modal
        title={<><RobotOutlined style={{ marginRight: 8 }} />AI 生成前端页面</>}
        open={aiOpen}
        onCancel={() => { if (!aiLoading) setAiOpen(false); }}
        footer={aiStatus === 'success' ? [
          <Button key="close" onClick={() => setAiOpen(false)}>关闭</Button>,
        ] : [
          <Button key="cancel" onClick={() => setAiOpen(false)} disabled={aiLoading}>取消</Button>,
          <Button key="submit" type="primary" onClick={onAiSubmit} loading={aiLoading}
            disabled={aiStatus === 'processing'}>
            {aiLoading ? '生成中...' : '开始生成'}
          </Button>,
        ]}
        width={600}
        destroyOnClose
        maskClosable={!aiLoading}
      >
        <div style={{ marginBottom: 16 }}>
          <p style={{ color: '#666', marginBottom: 8 }}>描述你想要的页面，AI 将自动生成并部署。例如：</p>
          <ul style={{ color: '#999', fontSize: 12, paddingLeft: 20, marginBottom: 12 }}>
            <li>创建一个待办事项管理页面，可以添加、完成、删除任务</li>
            <li>生成一个简洁的产品展示页面，包含标题、图片和描述</li>
            <li>做一个博客文章列表页，展示 article 模型的数据</li>
          </ul>
          <Input.TextArea
            rows={4}
            value={aiPrompt}
            onChange={(e) => setAiPrompt(e.target.value)}
            placeholder="描述你想要生成的页面..."
            disabled={aiLoading}
            maxLength={2000}
            showCount
          />
        </div>

        {aiStatus === 'processing' && (
          <div style={{ textAlign: 'center', padding: '16px 0' }}>
            <LoadingOutlined style={{ fontSize: 24, color: '#1890ff', marginBottom: 8 }} />
            <p style={{ color: '#666' }}>AI 正在生成页面，请稍候...</p>
            <p style={{ color: '#999', fontSize: 12 }}>通常需要 10-30 秒</p>
          </div>
        )}

        {aiStatus === 'success' && aiResult && (
          <Alert type="success" message="生成成功" description={aiResult} showIcon style={{ marginTop: 8 }} />
        )}

        {aiError && (
          <Alert type="error" message="生成失败" description={aiError} showIcon style={{ marginTop: 8 }} />
        )}
      </Modal>
    </div>
  );
};

export default PageHostingPage;
