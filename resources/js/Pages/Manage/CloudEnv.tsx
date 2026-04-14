import React, { useEffect, useMemo, useState } from 'react';
import { Table, Form, Input, Button, Space, Modal, message, Tag } from 'antd';
import type { ColumnsType, TablePaginationConfig } from 'antd/es/table';
import api from '../../util/Service';

interface EnvItem { id: number; name: string; value: string; remark?: string | null; created_at?: string }
interface ListMeta { total: number; page: number; page_size: number; page_count: number; }

const CloudEnvPage: React.FC = () => {
  const [form] = Form.useForm();
  const [data, setData] = useState<EnvItem[]>([]);
  const [meta, setMeta] = useState<ListMeta>({ total: 0, page: 1, page_size: 15, page_count: 0 });
  const [loading, setLoading] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<EnvItem | null>(null);

  const columns: ColumnsType<EnvItem> = useMemo(() => [
    { title: '变量名', dataIndex: 'name', key: 'name', width: 220 },
    { title: '值', dataIndex: 'value', key: 'value', ellipsis: true, render: (v: string) => (
      <Space>
        <span style={{ maxWidth: 360, display: 'inline-block' }}>{v}</span>
        <Button size="small" onClick={() => navigator.clipboard.writeText(v).then(() => message.success('已复制'))}>复制</Button>
      </Space>
    ) },
    { title: '备注', dataIndex: 'remark', key: 'remark', ellipsis: true },
    { title: '操作', key: 'actions', width: 200, render: (_, rec) => (
      <Space>
        <Button size="small" onClick={() => onEdit(rec)}>修改</Button>
        <Button size="small" danger onClick={() => onDelete(rec)}>删除</Button>
      </Space>
    ) },
  ], []);

  const fetchList = async (page?: number, pageSize?: number) => {
    const values = form.getFieldsValue();
    const params: any = { page: page ?? meta.page, page_size: pageSize ?? meta.page_size, keyword: values.keyword || undefined };
    setLoading(true);
    try {
      const res = await api.get('/manage/cloud-env/list', { params });
      const payload = res.data;
      setData(payload.data || []);
      setMeta(payload.meta || { total: 0, page: 1, page_size: 15, page_count: 0 });
    } catch (e: any) {
      const msg = e?.response?.data?.errors?.message || e?.message || '获取环境变量失败';
      message.error(Array.isArray(msg) ? msg[0] : msg);
    }
    finally { setLoading(false); }
  };

  useEffect(() => { fetchList(1, meta.page_size); /* eslint-disable-next-line */ }, []);

  const onCreate = () => { setEditing(null); setModalOpen(true); };
  const onEdit = (rec: EnvItem) => { setEditing(rec); setModalOpen(true); };

  const onDelete = (rec: EnvItem) => {
    Modal.confirm({ title: `确认删除「${rec.name}」?`, onOk: async () => {
      try { await api.post(`/manage/cloud-env/delete/${rec.id}`); message.success('已删除'); fetchList(); }
      catch (e: any) { message.error(e?.message || '删除失败'); }
    }});
  };

  const EnvModal: React.FC = () => {
    const [f] = Form.useForm<any>();
    useEffect(() => {
      if (!modalOpen) return;
      if (editing) f.setFieldsValue(editing); else { f.resetFields(); }
    }, [modalOpen, editing?.id]);

    const onSubmit = async () => {
      const values = await f.validateFields();
        let url = '/manage/cloud-env/create';
        if (editing) url = `/manage/cloud-env/update/${editing.id}`;
        api.post(url, values)
          .then(() => {
            message.success('已保存');
            setModalOpen(false);
            fetchList();
          })
          .catch((e: any) => {
            if (e.message) {
              message.error(e.message);
            } else {
              message.error('保存失败');
            }
          });
    };

    return (
      <Modal open={modalOpen} title={editing ? '修改环境变量' : '新建环境变量'} onOk={onSubmit} onCancel={() => setModalOpen(false)} width={680} destroyOnClose>
        <Form form={f} layout="vertical">
          <Space style={{ width: '100%' }} wrap>
            <Form.Item name="name" label="变量名" rules={[{ required: true }]} style={{ width: 300 }}>
              <Input placeholder="例如：HMAC_SECRET" />
            </Form.Item>
            <Form.Item name="value" label="值" rules={[{ required: true }]} style={{ width: 640 }}>
              <Input />
            </Form.Item>
            <Form.Item name="remark" label="备注" style={{ width: 640 }}>
              <Input />
            </Form.Item>
          </Space>
        </Form>
      </Modal>
    );
  };

  return (
    <div style={{ padding: 24 }}>
      <Space style={{ marginBottom: 16 }}>
        <Form form={form} layout="inline" onFinish={() => fetchList(1, meta.page_size)}>
          <Form.Item name="keyword" label="关键词">
            <Input placeholder="变量名/备注" allowClear style={{ width: 260 }} />
          </Form.Item>
          <Form.Item>
            <Space>
              <Button type="primary" htmlType="submit">查询</Button>
              <Button onClick={() => { form.resetFields(); fetchList(1, meta.page_size); }}>重置</Button>
            </Space>
          </Form.Item>
        </Form>
        <Button type="primary" onClick={onCreate}>新建变量</Button>
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

      <EnvModal />
    </div>
  );
};

export default CloudEnvPage;
