import React, { useEffect, useMemo, useState, useRef } from 'react';
import { Table, Button, Space, Tag, message, Drawer, Form, Input, Select, Modal, Switch, Radio, Checkbox, Popconfirm } from 'antd';
import type { ColumnsType, TablePaginationConfig } from 'antd/es/table';
import api from '../../util/Service';
import dayjs from 'dayjs';

interface TriggerItem {
  id: number;
  name: string;
  enabled: boolean;
  trigger_type: 'content_model' | 'content_single' | 'function_exec';
  events?: string[] | null;
  created_at?: string;
}
interface ListMeta { total: number; page: number; page_size: number; page_count: number; }
interface ExecItem { id: number; status: string; duration_ms: number; error?: string | null; event?: string | null; created_at: string; payload?: any; result?: any }
interface ModelItem { id: number; name: string; table_name?: string; mold_type: string; fields?: Array<{ field: string; label: string; type: string }>; }
interface FnItem { id: number; name: string; slug?: string }

const contentEventOptions = [
  { label: '新增前', value: 'before_create' },
  { label: '新增后', value: 'after_create' },
  { label: '修改前', value: 'before_update' },
  { label: '修改后', value: 'after_update' },
  { label: '删除前', value: 'before_delete' },
  { label: '删除后', value: 'after_delete' },
];

const eventLabelMap: Record<string, string> = {
  'before_create': '新增前',
  'after_create': '新增后',
  'before_update': '修改前',
  'after_update': '修改后',
  'before_delete': '删除前',
  'after_delete': '删除后',
  'before_execute': '执行前',
  'after_execute': '执行后',
};

const funcEventOptions = [
  { label: '执行前', value: 'before_execute' },
  { label: '执行后', value: 'after_execute' },
];

const TriggersPage: React.FC = () => {
  const [data, setData] = useState<TriggerItem[]>([]);
  const [meta, setMeta] = useState<ListMeta>({ total: 0, page: 1, page_size: 15, page_count: 0 });
  const [loading, setLoading] = useState(false);
  const [execOpen, setExecOpen] = useState(false);
  const [execLoading, setExecLoading] = useState(false);
  const [execData, setExecData] = useState<ExecItem[]>([]);
  const [execMeta, setExecMeta] = useState<ListMeta>({ total: 0, page: 1, page_size: 10, page_count: 0 });
  const [execForId, setExecForId] = useState<number | null>(null);
  const [selectedExec, setSelectedExec] = useState<ExecItem | null>(null);
  const [execDetailOpen, setExecDetailOpen] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<TriggerItem | null>(null);
  const [models, setModels] = useState<ModelItem[]>([]);
  const [endpointFns, setEndpointFns] = useState<FnItem[]>([]);
  const [hookPhpFns, setHookPhpFns] = useState<FnItem[]>([]);

  const contentModels = useMemo(() => models.filter(m => m.mold_type === 'list'), [models]);

  const columns: ColumnsType<TriggerItem> = useMemo(() => [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
    { title: '名称', dataIndex: 'name', key: 'name', width: 200 },
    { title: '类型', dataIndex: 'trigger_type', key: 'trigger_type', width: 200, render: (v) => ({
      'content_model': '内容列表变动'
      // , 'content_single': '内容单页变动', 'function_exec': '云函数执行'
    }[String(v)] || String(v)) },
    { title: '事件', dataIndex: 'events', key: 'events', width: 300, render: (arr: any, record: TriggerItem) => {
      try {
        // 字符串转json
        if (typeof arr === 'string') {
          arr = JSON.parse(arr);
        }
        if (!Array.isArray(arr) || arr.length === 0) return '-';
        const rec = record as any;
        let prefix = '';
        if (record.trigger_type === 'content_model' && rec.mold_name) {
          prefix = rec.mold_name + ' - ';
        } else if (record.trigger_type === 'function_exec' && rec.function_name) {
          prefix = rec.function_name + ' - ';
        }
        const eventText = arr.map(e => eventLabelMap[e] || e).join('、');
        return prefix + eventText;
      } catch (e) {
        return '-';
      }
    } },
    { title: '启用', dataIndex: 'enabled', key: 'enabled', width: 90, render: (b: boolean) => <Tag color={b ? 'green' : 'red'}>{b ? '是' : '否'}</Tag> },
    { title: '操作', key: 'actions', fixed: 'right', width: 320, render: (_, rec) => (
      <Space>
        <Button size="small" onClick={() => onEdit(rec)}>编辑</Button>
        <Button size="small" onClick={() => onToggle(rec)}>{rec.enabled ? '禁用' : '启用'}</Button>
        <Popconfirm
          title="确认删除"
          description={`确定要删除触发器「${rec.name}」吗？`}
          onConfirm={() => onDelete(rec)}
          okText="确定"
          cancelText="取消"
        >
          <Button size="small" danger>删除</Button>
        </Popconfirm>
        <Button size="small" onClick={() => openExec(rec)}>执行记录</Button>
      </Space>
    ) },
  ], []);

  const fetchList = (page?: number, pageSize?: number) => {
    setLoading(true);
    api.get('/manage/triggers/list', { params: { page: page ?? meta.page, page_size: pageSize ?? meta.page_size } })
      .then((res) => {
        const payload = res.data;
        setData(payload.data || []);
        setMeta(payload.meta || { total: 0, page: 1, page_size: 15, page_count: 0 });
      })
      .catch((e: any) => {
        message.error(e?.message || '获取触发器列表失败');
      })
      .finally(() => {
        setLoading(false);
      });
  };

  useEffect(() => { fetchList(1, meta.page_size); /* eslint-disable-next-line */ }, []);
  useEffect(() => {
    api.get('/mold/builder/models_and_fields')
      .then((r1) => {
        const m = (r1.data?.models || []) as any[];
        const ms = m.map((x: any) => ({ id: x.id, name: x.name, table_name: x.table_name, mold_type: x.mold_type, fields: (x.fields || []) }));
        setModels(ms);
      })
      .catch((e) => { /* ignore */ });
    
    api.get('/manage/functions/list', { params: { page: 1, page_size: 500, filter: { enabled: 1 } } })
      .then((r2) => {
        const arr = (r2.data?.data || []) as any[];
        setEndpointFns(arr.map((x: any) => ({ id: x.id, name: x.name, slug: x.slug }))); // 这里包含所有函数
      })
      .catch((e) => { /* ignore */ });
    
    api.get('/manage/functions/list', { params: { page: 1, page_size: 500, filter: { type: 'hook', enabled: 1 } } })
      .then((r3) => {
        const arr = (r3.data?.data || []) as any[];
        setHookPhpFns(arr.map((x: any) => ({ id: x.id, name: x.name })));
      })
      .catch((e) => { /* ignore */ });
  }, []);

  const onCreate = () => { setEditing(null); setModalOpen(true); };
  const onEdit = (rec: TriggerItem) => { setEditing(rec); setModalOpen(true); };

  const onToggle = (rec: TriggerItem) => {
    api.post(`/manage/triggers/toggle/${rec.id}`)
      .then(() => {
        message.success('已切换');
        fetchList();
      })
      .catch((e: any) => {
        message.error(e?.message || '操作失败');
      });
  };

  const onDelete = (rec: TriggerItem) => {
    api.post(`/manage/triggers/delete/${rec.id}`)
      .then(() => {
        message.success('已删除');
        fetchList();
      })
      .catch((e: any) => {
        message.error(e?.message || '删除失败');
      });
  };

  const openExec = (rec: TriggerItem) => { setExecForId(rec.id); setExecOpen(true); fetchExec(rec.id, 1, execMeta.page_size); };

  const fetchExec = (triggerId: number, page: number, pageSize: number) => {
    setExecLoading(true);
    api.get(`/manage/triggers/executions/${triggerId}`, { params: { page, page_size: pageSize } })
      .then((res) => {
        const payload = res.data;
        setExecData(payload.data || []);
        setExecMeta(payload.meta || { total: 0, page: 1, page_size: 10, page_count: 0 });
      })
      .catch((e: any) => {
        message.error(e?.message || '获取执行记录失败');
      })
      .finally(() => {
        setExecLoading(false);
      });
  };

  const TriggerModal: React.FC = () => {
    const [f] = Form.useForm<any>();
    const [contentOptions, setContentOptions] = useState<Array<{ value: string; label: string }>>([]);
    const [contentLoading, setContentLoading] = useState(false);
    const clearedOnceRef = useRef(false);
    useEffect(() => {
      if (!modalOpen) return;
      if (editing) {
        const e: any = editing as any;
        const patch: any = {
          ...e,
          enabled: !!e.enabled,
          events: Array.isArray(e.events) ? e.events : (typeof e.events === 'string' ? ((): string[] => { try { return JSON.parse(e.events); } catch { return []; } })() : []),
        };
        if (e.trigger_type === 'content_model') {
          patch.model_id = typeof e.mold_id === 'number' ? e.mold_id : (e.mold_id ? parseInt(String(e.mold_id), 10) : 0);
        }
        if (e.trigger_type === 'content_single') {
          patch.model_id = typeof e.mold_id === 'number' ? e.mold_id : (e.mold_id ? parseInt(String(e.mold_id), 10) : undefined);
          patch.content_id = e.content_id;
        }
        if (e.trigger_type === 'function_exec') {
          patch.watch_function_id = e.watch_function_id;
        }
        patch.input_schema = e.input_schema ? (typeof e.input_schema === 'string' ? e.input_schema : JSON.stringify(e.input_schema, null, 2)) : '';
        f.setFieldsValue(patch);
      } else {
        f.resetFields();
        f.setFieldsValue({ enabled: true, trigger_type: 'content_model', events: ['before_update','after_update'], model_id: 0 });
      }
    }, [modalOpen, editing?.id]);

    const triggerType = Form.useWatch('trigger_type', f);
    const modelId = Form.useWatch('model_id', f);
    const events = Form.useWatch('events', f);

    useEffect(() => {
      if (!modalOpen) return;
      if ((triggerType === 'content_model' || triggerType === 'content_single') && (modelId !== undefined && modelId !== null)) {
        try {
          let fields: string[] = [];
          if (String(modelId) === '0') {
            fields = [];
          } else {
            const m = models.find(x => String(x.id) === String(modelId));
            if (m) fields = (m.fields || []).map(x => x.field).filter(Boolean);
          }
          // 根据ContentService.php中dispatch的参数格式生成示例
          const randomId = Math.floor(Math.random() * 1000) + 1;
          const dataFields: any = {};
          fields.forEach(f => {
            dataFields[f] = `xxx`;
          });

          // 获取用户选择的触发时机
          const selectedEvents = f.getFieldValue('events') || ['before_update', 'after_update'];
          const firstEvent = Array.isArray(selectedEvents) ? selectedEvents[0] : selectedEvents;

          // 根据触发时机生成不同的示例
          let example: any = {
            mold_id: fields.length > 0 ? parseInt(String(modelId)) : 1,
            id: randomId,
          };

          if (firstEvent === 'before_create') {
            example.data = dataFields;
            example.before = {};
            example.after = {};
          } else if (firstEvent === 'after_create') {
            example.data = dataFields;
            example.before = {};
            example.after = dataFields;
          } else if (firstEvent === 'before_update') {
            example.data = dataFields;
            example.before = dataFields;
            example.after = {};
          } else if (firstEvent === 'after_update') {
            example.data = {};
            example.before = dataFields;
            example.after = dataFields;
          } else if (firstEvent === 'before_delete' || firstEvent === 'after_delete') {
            example.data = {};
            example.before = dataFields;
            example.after = {};
          } else {
            // 默认情况
            example.data = dataFields;
            example.before = {};
            example.after = {};
          }

          const cur = f.getFieldValue('input_schema');
          if (!editing || !cur) {
            setTimeout(() => {
              f.setFieldsValue({ input_schema: JSON.stringify(example, null, 2) });
            }, 0);
          }
        } catch {}
      }
    }, [triggerType, modelId, events, modalOpen]);

    // 切换到单条内容时，如当前 model_id 为 0（“全部”），异步清空一次以避免同步更新引起的闪烁
    useEffect(() => {
      if (!modalOpen) return;
      if (triggerType === 'content_single') {
        const curModel = f.getFieldValue('model_id');
        if (!clearedOnceRef.current && String(curModel) === '0') {
          clearedOnceRef.current = true;
          setTimeout(() => { f.setFieldsValue({ model_id: undefined, content_id: undefined }); }, 0);
        }
      }
    }, [triggerType, modalOpen]);

    // 当关闭弹窗或切换到非单条模式时，重置清空标记
    useEffect(() => {
      if (!modalOpen || triggerType !== 'content_single') {
        clearedOnceRef.current = false;
      }
    }, [modalOpen, triggerType]);

    // 单条内容：根据模型自动获取可选内容（用 title/name/首个文本字段 作为显示字段）
    useEffect(() => {
      if (!modalOpen) return;
      if (triggerType !== 'content_single') return;
      if (modelId === undefined || modelId === null || String(modelId) === '0') { setContentOptions([]); return; }
      const m = models.find(x => String(x.id) === String(modelId));
      if (!m) { setContentOptions([]); return; }
      const fields = m.fields || [];
      const names = new Set(fields.map(f => String(f.field || '')));
      let pick = 'title';
      if (!names.has('title')) {
        if (names.has('name')) pick = 'name';
        else {
          const tf = fields.find(f => ['input','string','text'].includes(String(f.type)) && f.field);
          pick = tf?.field || String(fields[0]?.field || 'title');
        }
      }
      setContentLoading(true);
      api.post(`/content/field-options/${modelId}`, { field: pick })
        .then((res) => {
          setContentOptions((res.data || []) as Array<{ value: string; label: string }>);
        })
        .catch(() => {
          setContentOptions([]);
        })
        .finally(() => {
          setContentLoading(false);
        });
    }, [modalOpen, triggerType, modelId]);

    const onSubmit = () => {
      f.validateFields()
        .then((values) => {
          const payload: any = {
            name: values.name,
            enabled: values.enabled ? 1 : 0,
            trigger_type: values.trigger_type,
            events: values.events || [],
            input_schema: ((): any => { try { return values.input_schema ? JSON.parse(values.input_schema) : undefined; } catch { return undefined; } })(),
            remark: values.remark || undefined,
          };
          if (values.trigger_type === 'content_model') {
            payload.mold_id = values.model_id !== undefined && values.model_id !== null ? parseInt(String(values.model_id), 10) : 0;
            payload.content_id = null;
            payload.watch_function_id = null;
          } else if (values.trigger_type === 'content_single') {
            payload.mold_id = parseInt(String(values.model_id), 10);
            payload.content_id = values.content_id ? parseInt(String(values.content_id), 10) : undefined;
            payload.watch_function_id = null;
          } else if (values.trigger_type === 'function_exec') {
            payload.mold_id = 0;
            payload.content_id = null;
            payload.watch_function_id = values.watch_function_id ? parseInt(String(values.watch_function_id), 10) : undefined;
          }
          payload.action_function_id = parseInt(String(values.action_function_id), 10);
          
          let url = '/manage/triggers/create';
          if (editing) url = `/manage/triggers/update/${editing.id}`;
          
          return api.post(url, payload);
        })
        .then(() => {
          message.success('已保存');
          setModalOpen(false);
          fetchList();
        })
        .catch((e: any) => {
          message.error(e?.message || '保存失败');
        });
    };

    return (
      <Modal open={modalOpen} title={editing ? '编辑触发器' : '新建触发器'} onOk={onSubmit} onCancel={() => setModalOpen(false)} width={980} maskClosable={false} keyboard={false} forceRender getContainer={false}>
        <Form form={f} layout="vertical" onSubmitCapture={(e) => e.preventDefault()}>
          <Space style={{ width: '100%' }} wrap>
            <Form.Item name="name" label="触发器名称" rules={[{ required: true }]} style={{ width: 300 }}>
              <Input />
            </Form.Item>
            <Form.Item name="trigger_type" label="触发条件" rules={[{ required: true }]} style={{ width: 220 }}>
              <Radio.Group options={[
                { label: '列表内容变动（按模型）', value: 'content_model' },
                // { label: '单条内容变动（按内容）', value: 'content_single' },
                // { label: '云函数执行前/后', value: 'function_exec' },
              ]} optionType="button" buttonStyle="solid" />
            </Form.Item>
            <Form.Item name="enabled" label="启用" valuePropName="checked" style={{ width: 120 }}>
              <Switch />
            </Form.Item>
          </Space>

          {(triggerType === 'content_model' || triggerType === 'content_single') && (
            <Space style={{ width: '100%' }} wrap>
              <Form.Item name="model_id" label="内容模型" rules={[{ required: true }]} style={{ width: 260 }}>
                <Select options={
                  triggerType === 'content_model'
                    ? ([{ label: '全部', value: 0 }] as any[]).concat(contentModels.map(m => ({ label: m.name, value: m.id })))
                    : contentModels.map(m => ({ label: m.name, value: m.id }))
                } />
              </Form.Item>
              {triggerType === 'content_single' && (
                <Form.Item name="content_id" label="选择内容" rules={[{ required: true }]} style={{ width: 420 }}>
                  <Select
                    showSearch
                    filterOption={(input, option) => ((option?.label as string) || '').toLowerCase().includes(input.toLowerCase())}
                    options={contentOptions}
                    loading={contentLoading}
                    placeholder="请选择内容"
                  />
                </Form.Item>
              )}
              <Form.Item name="events" label="触发时机" rules={[{ required: true }]} style={{ minWidth: 520 }}>
                <Checkbox.Group options={contentEventOptions} />
              </Form.Item>
            </Space>
          )}

          {triggerType === 'function_exec' && (
            <Space style={{ width: '100%' }} wrap>
              <Form.Item name="watch_function_id" label="监听函数" rules={[{ required: true }]} style={{ width: 360 }}>
                <Select placeholder="选择被监听的云函数" options={endpointFns.map(f => ({ label: `${f.name}${f.slug ? ` (${f.slug})` : ''}`, value: f.id }))} />
              </Form.Item>
              <Form.Item name="events" label="触发时机" rules={[{ required: true }]} style={{ minWidth: 360 }}>
                <Checkbox.Group options={funcEventOptions} />
              </Form.Item>
            </Space>
          )}

          <Space style={{ width: '100%' }} wrap>
            <Form.Item name="action_function_id" label="触发函数" rules={[{ required: true }]} style={{ width: 360 }}>
              <Select placeholder="选择触发函数（hook, php）" options={hookPhpFns.map(f => ({ label: f.name, value: f.id }))} />
            </Form.Item>
            <Form.Item name="remark" label="备注" style={{ width: 520 }}>
              <Input />
            </Form.Item>
          </Space>

          {(triggerType === 'content_model' || triggerType === 'content_single') && (
            <Form.Item name="input_schema" label="触发参数示例" tooltip="根据ContentService.php中dispatch格式生成，包含mold_id、id、data、before、after字段，使用随机字段值">
              <Input.TextArea autoSize={{ minRows: 6, maxRows: 12 }} placeholder={`{\n  "mold_id": 1,\n  "id": 123,\n  "data": {\n    "title": "示例标题",\n    "content": "示例内容"\n  },\n  "before": {},\n  "after": {}\n}`} />
            </Form.Item>
          )}
        </Form>
      </Modal>
    );
  };

  return (
    <div style={{ padding: 24 }}>
      <Space style={{ marginBottom: 16 }}>
        <Button type="primary" onClick={onCreate}>新建触发器</Button>
        <Button onClick={() => fetchList(1, meta.page_size)}>刷新</Button>
      </Space>

      <Table
        columns={columns}
        dataSource={data}
        rowKey="id"
        loading={loading}
        onChange={(p: TablePaginationConfig) => fetchList(p.current || 1, p.pageSize || meta.page_size)}
        pagination={{ current: meta.page, pageSize: meta.page_size, total: meta.total, showSizeChanger: true, showQuickJumper: true, showTotal: (t, r) => `第 ${r[0]}-${r[1]} 条，共 ${t} 条` }}
        scroll={{ x: 'max-content' }}
      />

      <Drawer open={execOpen} width={720} title="执行记录" onClose={() => setExecOpen(false)}>
        <Table
          columns={[
            { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
            { title: '状态', dataIndex: 'status', key: 'status', width: 100, render: (s: string) => <Tag color={s === 'success' ? 'green' : 'red'}>{s}</Tag> },
            { title: '事件', dataIndex: 'event', key: 'event', width: 200 },
            { title: '耗时(ms)', dataIndex: 'duration_ms', key: 'duration_ms', width: 110 },
            { title: '错误', dataIndex: 'error', key: 'error' },
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

      <TriggerModal />
    </div>
  );
};

export default TriggersPage;
