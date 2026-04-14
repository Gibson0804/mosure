import React, { useEffect, useMemo, useState } from 'react';
import { Table, Form, Input, Select, Button, Space, Tag, Modal, Drawer, message, Switch, Divider } from 'antd';
import { PlusOutlined, MinusCircleOutlined } from '@ant-design/icons';
import type { ColumnsType, TablePaginationConfig } from 'antd/es/table';
import api from '../../util/Service';
import dayjs from 'dayjs';

interface FnItem {
  id: number;
  name: string;
  slug: string;
  type?: 'endpoint' | 'hook' | 'cron';
  enabled: boolean;
  http_method?: string;
  rate_limit?: number | null;
  timeout_ms?: number | null;
  max_mem_mb?: number | null;
  input_schema?: any;
  output_schema?: any;
  code?: string | null;
  remark?: string | null;
  created_at?: string;
}

interface ParamField {
  field: string;
  type: string;
  comment: string;
}

interface ListMeta { total: number; page: number; page_size: number; page_count: number; }
interface ExecItem { id: number; status: string; duration_ms: number; error?: string | null; payload?: any; result?: any; api_key_id?: number | null; created_at: string; }

const WebFunctionsPage: React.FC = () => {
  const [form] = Form.useForm();
  const [data, setData] = useState<FnItem[]>([]);
  const [meta, setMeta] = useState<ListMeta>({ total: 0, page: 1, page_size: 15, page_count: 0 });
  const [loading, setLoading] = useState(false);
  const [execOpen, setExecOpen] = useState(false);
  const [execLoading, setExecLoading] = useState(false);
  const [execData, setExecData] = useState<ExecItem[]>([]);
  const [execMeta, setExecMeta] = useState<ListMeta>({ total: 0, page: 1, page_size: 10, page_count: 0 });
  const [execForId, setExecForId] = useState<number | null>(null);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<FnItem | null>(null);
  const [execDetailOpen, setExecDetailOpen] = useState(false);
  const [selectedExec, setSelectedExec] = useState<ExecItem | null>(null);

  const columns: ColumnsType<FnItem> = useMemo(() => [
    { title: '名称', dataIndex: 'name', key: 'name', width: 180 },
    { title: 'Slug', dataIndex: 'slug', key: 'slug', width: 200 },
    { title: 'HTTP方法', dataIndex: 'http_method', key: 'http_method', width: 100 },
    { title: '启用', dataIndex: 'enabled', key: 'enabled', width: 90, render: (b: boolean) => <Tag color={b ? 'green' : 'red'}>{b ? '是' : '否'}</Tag> },
    { title: '备注', dataIndex: 'remark', key: 'remark', ellipsis: true },
    { title: '操作', key: 'actions', fixed: 'right', width: 340, render: (_, rec) => (
      <Space>
        <Button size="small" onClick={() => onEdit(rec)}>编辑</Button>
        <Button size="small" onClick={() => onToggle(rec)}>{rec.enabled ? '禁用' : '启用'}</Button>
        <Button size="small" danger onClick={() => onDelete(rec)}>删除</Button>
        <Button size="small" onClick={() => openExec(rec)}>执行记录</Button>
        <Button size="small" type="dashed" onClick={() => {
          window.open(`/manage/functions/code/${rec.id}?type=endpoint`, '_blank');
        }}>函数代码</Button>
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
        type: 'endpoint', // 只查询 endpoint 类型
      },
    };
    setLoading(true);
    try {
      const res = await api.get('/manage/functions/list', { params });
      const payload = res.data;
      setData(payload.data || []);
      setMeta(payload.meta || { total: 0, page: 1, page_size: 15, page_count: 0 });
    } catch (e: any) {
      message.error(e?.message || '获取函数列表失败');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchList(1, meta.page_size); /* eslint-disable-next-line */ }, []);

  const onCreate = () => { setEditing(null); setModalOpen(true); };
  const onEdit = (rec: FnItem) => { setEditing(rec); setModalOpen(true); };

  const onToggle = async (rec: FnItem) => {
    try {
      await api.post(`/manage/functions/toggle/${rec.id}`);
      message.success('已切换');
      fetchList();
    } catch (e: any) {
      message.error(e?.message || '操作失败');
    }
  };

  const onDelete = async (rec: FnItem) => {
    try {
      const checkRes = await api.get(`/manage/functions/check-bindings/${rec.id}`);
      const bindings = checkRes.data || {};
      const hasCrons = bindings.crons && bindings.crons.length > 0;
      const hasTriggers = bindings.triggers && bindings.triggers.length > 0;
      
      let content = <div>确定要删除Web函数「{rec.name}」吗？</div>;
      if (hasCrons || hasTriggers) {
        content = (
          <div>
            <p>Web函数「{rec.name}」有以下绑定：</p>
            {hasCrons && (
              <div style={{ marginBottom: 8 }}>
                <strong>定时任务：</strong>
                <ul style={{ margin: '4px 0', paddingLeft: 20 }}>
                  {bindings.crons.map((c: any) => (
                    <li key={c.id}>{c.name}</li>
                  ))}
                </ul>
              </div>
            )}
            {hasTriggers && (
              <div style={{ marginBottom: 8 }}>
                <strong>触发器：</strong>
                <ul style={{ margin: '4px 0', paddingLeft: 20 }}>
                  {bindings.triggers.map((t: any) => (
                    <li key={t.id}>{t.name}</li>
                  ))}
                </ul>
              </div>
            )}
            <p style={{ marginTop: 12, color: '#ff4d4f' }}>删除后将自动删除这些绑定的定时任务和触发器，是否继续？</p>
          </div>
        );
      }
      
      Modal.confirm({
        title: '确认删除',
        content,
        width: 500,
        onOk: async () => {
          try {
            await api.post(`/manage/functions/delete/${rec.id}`);
            message.success('已删除');
            fetchList();
          } catch (e: any) { 
            message.error(e?.message || '删除失败'); 
          }
        }
      });
    } catch (e: any) {
      Modal.confirm({
        title: `确认删除「${rec.name}」?`,
        onOk: async () => {
          try {
            await api.post(`/manage/functions/delete/${rec.id}`);
            message.success('已删除');
            fetchList();
          } catch (e: any) { message.error(e?.message || '删除失败'); }
        }
      });
    }
  };

  const openExec = (rec: FnItem) => { setExecForId(rec.id); setExecOpen(true); fetchExec(rec.id, 1, execMeta.page_size); };

  const fetchExec = async (functionId: number, page: number, pageSize: number) => {
    setExecLoading(true);
    try {
      const res = await api.get(`/manage/functions/executions/${functionId}`, { params: { page, page_size: pageSize, type: 'endpoint' } });
      const payload = res.data;
      setExecData(payload.data || []);
      setExecMeta(payload.meta || { total: 0, page: 1, page_size: 10, page_count: 0 });
    } catch (e: any) { message.error(e?.message || '获取执行记录失败'); }
    finally { setExecLoading(false); }
  };

  const FunctionModal: React.FC = () => {
    const [f] = Form.useForm<any>();
    const [inputParams, setInputParams] = useState<ParamField[]>([]);
    const [outputParams, setOutputParams] = useState<ParamField[]>([]);

    useEffect(() => {
      if (!modalOpen) return;
      if (editing) {
        const e: any = editing as any;
        f.setFieldsValue({
          name: e.name,
          slug: e.slug,
          enabled: !!e.enabled,
          http_method: e.http_method || 'POST',
          remark: e.remark || '',
        });
        
        // 解析 input_schema 到表格
        if (e.input_schema && e.input_schema.properties) {
          const fields: ParamField[] = [];
          const required = e.input_schema.required || [];
          Object.keys(e.input_schema.properties).forEach(key => {
            const prop = e.input_schema.properties[key];
            fields.push({
              field: key,
              type: prop.type || 'string',
              comment: prop.description || '',
            });
          });
          setInputParams(fields);
        } else {
          setInputParams([]);
        }
        
        // 解析 output_schema 到表格
        if (e.output_schema && e.output_schema.properties) {
          const fields: ParamField[] = [];
          Object.keys(e.output_schema.properties).forEach(key => {
            const prop = e.output_schema.properties[key];
            fields.push({
              field: key,
              type: prop.type || 'string',
              comment: prop.description || '',
            });
          });
          setOutputParams(fields);
        } else {
          setOutputParams([]);
        }
      } else {
        f.resetFields();
        f.setFieldsValue({ http_method: 'POST', enabled: true });
        setInputParams([]);
        setOutputParams([]);
      }
    }, [modalOpen, editing?.id]);

    const onSubmit = async () => {
      const values = await f.validateFields();
      
      // 将表格数据转换为 JSON Schema
      let inputSchema = null;
      let outputSchema = null;
      
      if (inputParams.length > 0) {
        const properties: any = {};
        const required: string[] = [];
        inputParams.forEach(param => {
          if (param.field && param.field.trim()) {
            properties[param.field] = {
              type: param.type || 'string',
              description: param.comment || '',
            };
            // 暂时不支持必填标记，后续可扩展
          }
        });
        inputSchema = {
          type: 'object',
          properties,
          required,
        };
      }
      
      if (outputParams.length > 0) {
        const properties: any = {};
        outputParams.forEach(param => {
          if (param.field && param.field.trim()) {
            properties[param.field] = {
              type: param.type || 'string',
              description: param.comment || '',
            };
          }
        });
        outputSchema = {
          type: 'object',
          properties,
        };
      }
      
      const payload: any = {
        name: values.name,
        slug: values.slug,
        enabled: values.enabled ? 1 : 0,
        type: 'endpoint', // 固定为 endpoint
        remark: values.remark || undefined,
        http_method: values.http_method || 'POST',
        input_schema: inputSchema,
        output_schema: outputSchema,
      };
      
      try {
        let url = '/manage/functions/create';
        if (editing) url = `/manage/functions/update/${editing.id}`;
        await api.post(url, payload);
        message.success('已保存');
        setModalOpen(false);
        fetchList();
      } catch (e: any) { message.error(e?.message || '保存失败'); }
    };

    const addInputParam = () => {
      setInputParams([...inputParams, { field: '', type: 'string', comment: '' }]);
    };

    const removeInputParam = (index: number) => {
      setInputParams(inputParams.filter((_, i) => i !== index));
    };

    const updateInputParam = (index: number, key: keyof ParamField, value: string) => {
      const newParams = [...inputParams];
      newParams[index][key] = value;
      setInputParams(newParams);
    };

    const addOutputParam = () => {
      setOutputParams([...outputParams, { field: '', type: 'string', comment: '' }]);
    };

    const removeOutputParam = (index: number) => {
      setOutputParams(outputParams.filter((_, i) => i !== index));
    };

    const updateOutputParam = (index: number, key: keyof ParamField, value: string) => {
      const newParams = [...outputParams];
      newParams[index][key] = value;
      setOutputParams(newParams);
    };

    return (
      <Modal open={modalOpen} title={editing ? '编辑Web函数' : '新建Web函数'} onOk={onSubmit} onCancel={() => setModalOpen(false)} width={900} destroyOnClose>
        <Form form={f} layout="vertical">
          <Space style={{ width: '100%' }} wrap>
            <Form.Item name="name" label="名称" rules={[{ required: true }]} style={{ width: 260 }}>
              <Input />
            </Form.Item>
            <Form.Item name="slug" label="Slug" rules={[{ required: true }]} style={{ width: 240 }}>
              <Input placeholder="用于 /open/{prefix}/func/{slug}" />
            </Form.Item>
            <Form.Item name="http_method" label="HTTP 方法" style={{ width: 180 }}>
              <Select options={[{ value: 'GET', label: 'GET' }, { value: 'POST', label: 'POST' }, { value: 'PUT', label: 'PUT' }, { value: 'DELETE', label: 'DELETE' }]} />
            </Form.Item>
            <Form.Item name="enabled" label="启用" valuePropName="checked" style={{ width: 120 }}>
              <Switch />
            </Form.Item>
            <Form.Item name="remark" label="备注" style={{ width: 520 }}>
              <Input />
            </Form.Item>
          </Space>
          
          <Divider orientation="left">输入参数定义（选填）</Divider>
          <div style={{ marginBottom: 16 }}>
            <Button type="dashed" onClick={addInputParam} icon={<PlusOutlined />} block>
              添加输入参数
            </Button>
          </div>
          {inputParams.map((param, index) => (
            <Space key={index} style={{ width: '100%', marginBottom: 8 }} align="start">
              <Input
                placeholder="字段名"
                value={param.field}
                onChange={(e) => updateInputParam(index, 'field', e.target.value)}
                style={{ width: 200 }}
              />
              <Select
                value={param.type}
                onChange={(value) => updateInputParam(index, 'type', value)}
                style={{ width: 150 }}
                options={[
                  { value: 'string', label: 'string' },
                  { value: 'number', label: 'number' },
                  { value: 'integer', label: 'integer' },
                  { value: 'boolean', label: 'boolean' },
                  { value: 'array', label: 'array' },
                  { value: 'object', label: 'object' },
                ]}
              />
              <Input
                placeholder="备注说明"
                value={param.comment}
                onChange={(e) => updateInputParam(index, 'comment', e.target.value)}
                style={{ flex: 1 }}
              />
              <Button
                type="text"
                danger
                icon={<MinusCircleOutlined />}
                onClick={() => removeInputParam(index)}
              />
            </Space>
          ))}
          
          <Divider orientation="left">输出参数定义（选填）</Divider>
          <div style={{ marginBottom: 16 }}>
            <Button type="dashed" onClick={addOutputParam} icon={<PlusOutlined />} block>
              添加输出参数
            </Button>
          </div>
          {outputParams.map((param, index) => (
            <Space key={index} style={{ width: '100%', marginBottom: 8 }} align="start">
              <Input
                placeholder="字段名"
                value={param.field}
                onChange={(e) => updateOutputParam(index, 'field', e.target.value)}
                style={{ width: 200 }}
              />
              <Select
                value={param.type}
                onChange={(value) => updateOutputParam(index, 'type', value)}
                style={{ width: 150 }}
                options={[
                  { value: 'string', label: 'string' },
                  { value: 'number', label: 'number' },
                  { value: 'integer', label: 'integer' },
                  { value: 'boolean', label: 'boolean' },
                  { value: 'array', label: 'array' },
                  { value: 'object', label: 'object' },
                ]}
              />
              <Input
                placeholder="备注说明"
                value={param.comment}
                onChange={(e) => updateOutputParam(index, 'comment', e.target.value)}
                style={{ flex: 1 }}
              />
              <Button
                type="text"
                danger
                icon={<MinusCircleOutlined />}
                onClick={() => removeOutputParam(index)}
              />
            </Space>
          ))}
        </Form>
      </Modal>
    );
  };

  return (
    <div style={{ padding: 24 }}>
      <Space style={{ marginBottom: 16 }}>
        <Form form={form} layout="inline" onFinish={() => fetchList(1, meta.page_size)}>
          <Form.Item name="keyword" label="关键词">
            <Input placeholder="名称/slug" allowClear style={{ width: 220 }} />
          </Form.Item>
          <Form.Item name="enabled" label="启用">
            <Select allowClear style={{ width: 120 }} options={[{ label: '是', value: 1 }, { label: '否', value: 0 }]} />
          </Form.Item>
          <Form.Item>
            <Space>
              <Button type="primary" htmlType="submit">查询</Button>
              <Button onClick={() => { form.resetFields(); fetchList(1, meta.page_size); }}>重置</Button>
            </Space>
          </Form.Item>
        </Form>
        <Button type="primary" onClick={onCreate}>新建Web函数</Button>
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
            { title: '耗时(ms)', dataIndex: 'duration_ms', key: 'duration_ms', width: 110 },
            { title: '错误', dataIndex: 'error', key: 'error', ellipsis: true },
            { title: 'API Key ID', dataIndex: 'api_key_id', key: 'api_key_id', width: 120, render: (v: any) => v ?? '-' },
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
            <Divider orientation="left">Payload（请求参数）</Divider>
            <pre style={{ background: '#f5f5f5', padding: 12, borderRadius: 4, maxHeight: 300, overflow: 'auto' }}>
              {typeof selectedExec.payload === 'object' ? JSON.stringify(selectedExec.payload, null, 2) : JSON.stringify(selectedExec.payload)}
            </pre>
            <Divider orientation="left">Result（返回结果）</Divider>
            <pre style={{ background: '#f5f5f5', padding: 12, borderRadius: 4, maxHeight: 300, overflow: 'auto' }}>
              {typeof selectedExec.result === 'object' ? JSON.stringify(selectedExec.result, null, 2) : JSON.stringify(selectedExec.result)}
            </pre>
          </div>
        )}
      </Modal>

      <FunctionModal />
    </div>
  );
};

export default WebFunctionsPage;
