import React, { useEffect, useMemo, useState } from 'react';
import { Table, Form, Input, Select, Button, Space, Tag, Modal, message, Switch, DatePicker, Drawer } from 'antd';
import type { ColumnsType, TablePaginationConfig } from 'antd/es/table';
import api from '../../util/Service';
import dayjs from 'dayjs';

interface FnItem { id: number; name: string; slug?: string }
interface CronItem {
  id: number;
  name: string;
  enabled: boolean;
  function_id: number;
  schedule_type: 'once' | 'cron';
  run_at?: string | null;
  cron_expr?: string | null;
  timezone?: string | null;
  next_run_at?: string | null;
  remark?: string | null;
  payload?: any;
}
interface ListMeta { total: number; page: number; page_size: number; page_count: number; }
interface ExecItem { id: number; status: string; duration_ms: number; error?: string | null; created_at: string; payload?: any; result?: any }

const CloudCronsPage: React.FC = () => {
  const [form] = Form.useForm();
  const [data, setData] = useState<CronItem[]>([]);
  const [meta, setMeta] = useState<ListMeta>({ total: 0, page: 1, page_size: 15, page_count: 0 });
  const [loading, setLoading] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<CronItem | null>(null);
  const [hookFns, setHookFns] = useState<FnItem[]>([]);
  const [execOpen, setExecOpen] = useState(false);
  const [execLoading, setExecLoading] = useState(false);
  const [execData, setExecData] = useState<ExecItem[]>([]);
  const [execMeta, setExecMeta] = useState<ListMeta>({ total: 0, page: 1, page_size: 10, page_count: 0 });
  const [execForId, setExecForId] = useState<number | null>(null);
  const [selectedExec, setSelectedExec] = useState<ExecItem | null>(null);
  const [execDetailOpen, setExecDetailOpen] = useState(false);

  const columns: ColumnsType<CronItem> = useMemo(() => [
    { title: '名称', dataIndex: 'name', key: 'name', width: 200 },
    { title: '类型', dataIndex: 'schedule_type', key: 'schedule_type', width: 120, render: (t: string) => t === 'once' ? '一次性' : '周期' },
    { title: '表达式/时间', key: 'expr', width: 240, render: (_, rec) => rec.schedule_type === 'once' ? (rec.run_at ? dayjs(rec.run_at).format('YYYY-MM-DD HH:mm') : '-') : (rec.cron_expr || '-') },
    { title: '下次执行', dataIndex: 'next_run_at', key: 'next_run_at', width: 180, render: (v: any) => v ? dayjs(v).format('YYYY-MM-DD HH:mm') : '-' },
    { title: '启用', dataIndex: 'enabled', key: 'enabled', width: 90, render: (b: boolean) => <Tag color={b ? 'green' : 'red'}>{b ? '是' : '否'}</Tag> },
    { title: '备注', dataIndex: 'remark', key: 'remark', ellipsis: true },
    { title: '操作', key: 'actions', fixed: 'right', width: 420, render: (_, rec) => (
      <Space>
        <Button size="small" onClick={() => onEdit(rec)}>编辑</Button>
        <Button size="small" onClick={() => onToggle(rec)}>{rec.enabled ? '禁用' : '启用'}</Button>
        <Button size="small" onClick={() => onRunNow(rec)}>立即执行</Button>
        <Button size="small" onClick={() => openExec(rec)}>执行记录</Button>
        <Button size="small" danger onClick={() => onDelete(rec)}>删除</Button>
      </Space>
    ) },
  ], []);

  const fetchList = async (page?: number, pageSize?: number) => {
    const values = form.getFieldsValue();
    const params: any = {
      page: page ?? meta.page,
      page_size: pageSize ?? meta.page_size,
      filter: {
        keyword: values.keyword || undefined,
        enabled: typeof values.enabled === 'number' ? values.enabled : (typeof values.enabled === 'boolean' ? (values.enabled ? 1 : 0) : undefined),
      },
    };
    setLoading(true);
    try {
      const res = await api.get('/manage/crons/list', { params });
      const payload = res.data;
      setData(payload.data || []);
      setMeta(payload.meta || { total: 0, page: 1, page_size: 15, page_count: 0 });
    } catch (e: any) {
      message.error(e?.message || '获取定时任务列表失败');
    } finally { setLoading(false); }
  };

  const fetchHookFns = async () => {
    try {
      const res = await api.get('/manage/functions/list', { params: { page: 1, page_size: 200, filter: { type: 'hook', enabled: 1 } } });
      const payload = res.data;
      setHookFns((payload.data || []).map((x: any) => ({ id: x.id, name: x.name, slug: x.slug })));
    } catch (e: any) { /* ignore */ }
  };

  useEffect(() => { fetchList(1, meta.page_size); fetchHookFns(); /* eslint-disable-next-line */ }, []);

  const onCreate = () => { setEditing(null); setModalOpen(true); };
  const onEdit = (rec: CronItem) => { setEditing(rec); setModalOpen(true); };

  const onToggle = async (rec: CronItem) => { try { await api.post(`/manage/crons/toggle/${rec.id}`); message.success('已切换'); fetchList(); } catch (e: any) { message.error(e?.message || '操作失败'); } };
  const onRunNow = async (rec: CronItem) => { try { await api.post(`/manage/crons/run-now/${rec.id}`); message.success('已触发'); fetchList(); } catch (e: any) { message.error(e?.message || '操作失败'); } };
  const onDelete = (rec: CronItem) => { Modal.confirm({ title: `确认删除「${rec.name}」?`, onOk: async () => { try { await api.post(`/manage/crons/delete/${rec.id}`); message.success('已删除'); fetchList(); } catch (e: any) { message.error(e?.message || '删除失败'); } } }); };

  const openExec = (rec: CronItem) => { setExecForId(rec.id); setExecOpen(true); fetchExec(rec.id, 1, execMeta.page_size); };

  const fetchExec = async (cronId: number, page: number, pageSize: number) => {
    setExecLoading(true);
    try {
      const res = await api.get(`/manage/crons/executions/${cronId}`, { params: { page, page_size: pageSize } });
      const payload = res.data;
      setExecData(payload.data || []);
      setExecMeta(payload.meta || { total: 0, page: 1, page_size: 10, page_count: 0 });
    } catch (e: any) { message.error(e?.message || '获取执行记录失败'); }
    finally { setExecLoading(false); }
  };

  const CronModal: React.FC = () => {
    const [f] = Form.useForm<any>();
    const typeVal: 'once'|'cron' = Form.useWatch('schedule_type', f);
    useEffect(() => {
      if (!modalOpen) return;
      if (editing) {
        const e: any = editing as any;
        f.setFieldsValue({ name: e.name, enabled: !!e.enabled, function_id: e.function_id, schedule_type: e.schedule_type || 'once', run_at: e.run_at ? dayjs(e.run_at) : undefined, cron_expr: e.cron_expr || undefined, timezone: e.timezone || 'Asia/Shanghai', payload: e.payload ? (typeof e.payload === 'string' ? e.payload : JSON.stringify(e.payload, null, 2)) : '', remark: e.remark || '' });
      } else { f.resetFields(); f.setFieldsValue({ enabled: true, schedule_type: 'once', timezone: 'Asia/Shanghai' }); }
    }, [modalOpen, editing?.id]);

    const onSubmit = async () => {
      const values = await f.validateFields();
      const payload: any = { name: values.name, enabled: values.enabled ? 1 : 0, function_id: Number(values.function_id), schedule_type: values.schedule_type, run_at: values.schedule_type === 'once' && values.run_at ? (values.run_at as any).format('YYYY-MM-DD HH:mm:ss') : undefined, cron_expr: values.schedule_type === 'cron' ? (values.cron_expr || '') : undefined, timezone: values.timezone || undefined, payload: values.payload || undefined, remark: values.remark || undefined };
      try { let url = '/manage/crons/create'; if (editing) url = `/manage/crons/update/${editing.id}`; await api.post(url, payload); message.success('已保存'); setModalOpen(false); fetchList(); } catch (e: any) { message.error(e?.message || '保存失败'); }
    };

    return (
      <Modal open={modalOpen} title={editing ? '编辑定时任务' : '新建定时任务'} onOk={onSubmit} onCancel={() => setModalOpen(false)} width={820} destroyOnClose>
        <Form form={f} layout="vertical">
          <Space style={{ width: '100%' }} wrap>
            <Form.Item name="name" label="名称" rules={[{ required: true }]} style={{ width: 260 }}><Input /></Form.Item>
            <Form.Item name="function_id" label="绑定触发函数" rules={[{ required: true }]} style={{ width: 280 }}>
              <Select showSearch optionFilterProp="label" options={hookFns.map(fn => ({ value: fn.id, label: `${fn.name}${fn.slug ? ` (${fn.slug})` : ''}` }))} />
            </Form.Item>
            <Form.Item name="schedule_type" label="类型" rules={[{ required: true }]} style={{ width: 160 }}>
              <Select options={[{ label: '一次性', value: 'once' }, { label: '周期', value: 'cron' }]} />
            </Form.Item>
            <Form.Item name="enabled" label="启用" valuePropName="checked" style={{ width: 120 }}><Switch /></Form.Item>
            {typeVal === 'once' && (<Form.Item name="run_at" label="执行时间" rules={[{ required: true }]} style={{ width: 260 }}><DatePicker showTime format="YYYY-MM-DD HH:mm:ss" /></Form.Item>)}
            {typeVal === 'cron' && (<><Form.Item name="cron_expr" label="Cron 表达式" rules={[{ required: true }]} style={{ width: 280 }}><Input placeholder="* * * * *" /></Form.Item><Form.Item name="timezone" label="时区" style={{ width: 220 }}><Input placeholder="Asia/Shanghai" /></Form.Item></>)}
            <Form.Item name="remark" label="备注" style={{ width: 520 }}><Input /></Form.Item>
            <Form.Item name="payload" label="Payload(JSON)" style={{ width: '100%' }}><Input.TextArea rows={6} placeholder='{"key":"value"}' /></Form.Item>
          </Space>
        </Form>
      </Modal>
    );
  };

  return (
    <div style={{ padding: 24 }}>
      <Space style={{ marginBottom: 16 }}>
        <Form form={form} layout="inline" onFinish={() => fetchList(1, meta.page_size)}>
          <Form.Item name="keyword" label="关键词"><Input placeholder="名称" allowClear style={{ width: 220 }} /></Form.Item>
          <Form.Item name="enabled" label="启用"><Select allowClear style={{ width: 120 }} options={[{ label: '是', value: 1 }, { label: '否', value: 0 }]} /></Form.Item>
          <Form.Item><Space><Button type="primary" htmlType="submit">查询</Button><Button onClick={() => { form.resetFields(); fetchList(1, meta.page_size); }}>重置</Button></Space></Form.Item>
        </Form>
        <Button type="primary" onClick={() => onCreate()}>新建定时任务</Button>
      </Space>
      <Table columns={columns} dataSource={data} rowKey="id" loading={loading} onChange={(p: TablePaginationConfig) => fetchList(p.current || 1, p.pageSize || meta.page_size)} pagination={{ current: meta.page, pageSize: meta.page_size, total: meta.total, showSizeChanger: true, showQuickJumper: true, showTotal: (t, r) => `第 ${r[0]}-${r[1]} 条，共 ${t} 条` }} scroll={{ x: 'max-content' }} />
      <Drawer open={execOpen} width={720} title="执行记录" onClose={() => setExecOpen(false)}>
        <Table
          columns={[
            { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
            { title: '状态', dataIndex: 'status', key: 'status', width: 100, render: (s: string) => <Tag color={s === 'success' ? 'green' : 'red'}>{s}</Tag> },
            { title: '耗时(ms)', dataIndex: 'duration_ms', key: 'duration_ms', width: 110 },
            { title: '错误', dataIndex: 'error', key: 'error', ellipsis: true },
            { title: '时间', dataIndex: 'created_at', key: 'created_at', width: 180, render: (v: string) => dayjs(v).format('YYYY-MM-DD HH:mm:ss') },
            { title: '操作', key: 'actions', width: 80, render: (_, rec: ExecItem) => (
              <Button size="small" onClick={() => { setSelectedExec(rec); setExecDetailOpen(true); }}>详情</Button>
            )},
          ] as ColumnsType<ExecItem>}
          dataSource={execData}
          rowKey="id"
          loading={execLoading}
          onChange={(p: TablePaginationConfig) => { if (execForId) fetchExec(execForId, p.current || 1, p.pageSize || execMeta.page_size); }}
          pagination={{ current: execMeta.page, pageSize: execMeta.page_size, total: execMeta.total, showSizeChanger: true, showQuickJumper: true, showTotal: (t, r) => `第 ${r[0]}-${r[1]} 条，共 ${t} 条` }}
          scroll={{ x: 'max-content' }}
        />
      </Drawer>

      <Modal open={execDetailOpen} title="执行记录详情" onCancel={() => setExecDetailOpen(false)} footer={null} width={800}>
        {selectedExec && (
          <div>
            <div style={{ marginBottom: 12 }}>
              <strong>Payload（请求参数）</strong>
              <pre style={{ background: '#f5f5f5', padding: 12, borderRadius: 4, maxHeight: 300, overflow: 'auto' }}>
                {typeof selectedExec.payload === 'object' ? JSON.stringify(selectedExec.payload, null, 2) : JSON.stringify(selectedExec.payload)}
              </pre>
            </div>
            <div>
              <strong>Result（返回结果）</strong>
              <pre style={{ background: '#f5f5f5', padding: 12, borderRadius: 4, maxHeight: 300, overflow: 'auto' }}>
                {typeof selectedExec.result === 'object' ? JSON.stringify(selectedExec.result, null, 2) : JSON.stringify(selectedExec.result)}
              </pre>
            </div>
          </div>
        )}
      </Modal>
      <CronModal />
    </div>
  );
};

export default CloudCronsPage;
