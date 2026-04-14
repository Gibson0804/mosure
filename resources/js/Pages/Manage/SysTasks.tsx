import React, { useEffect, useMemo, useState } from 'react';
import { Table, Form, Input, Select, Button, Space, Tag, Drawer, Tabs, Descriptions, message, Modal, DatePicker } from 'antd';
import type { ColumnsType, TablePaginationConfig } from 'antd/es/table';
import api from '../../util/Service';
import dayjs from 'dayjs';

interface ListMeta { total: number; page: number; page_size: number; page_count: number; }

interface SysTaskItem {
  id: number;
  project_prefix?: string | null;
  domain?: string | null;
  type: string;
  title?: string | null;
  status: string;
  stage?: string | null;
  progress_total?: number | null;
  progress_done?: number | null;
  progress_failed?: number | null;
  request_id?: string | null;
  error_message?: string | null;
  started_at?: string | null;
  finished_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  payload?: any;
  result?: any;
  canceled_at?: string | null;
  cancel_reason?: string | null;
  parent_id?: number | null;
  root_id?: number | null;
  sort_no?: number | null;
}

interface SysTaskStepItem {
  id: number;
  task_id: number;
  seq: number;
  title?: string | null;
  status: string;
  payload?: any;
  result?: any;
  error_message?: string | null;
  started_at?: string | null;
  finished_at?: string | null;
  created_at?: string | null;
}

const statusColor = (s: string) => {
  const v = String(s || '');
  if (v === 'success') return 'green';
  if (v === 'failed') return 'red';
  if (v === 'canceled') return 'orange';
  if (v === 'processing') return 'blue';
  return 'default';
};

const typeLabelMap: Record<string, string> = {
  'content_generation': '内容生成',
  'content_batch': '内容批量',
  'mold_suggest': '模型生成',
  'ai_agent_run': 'AI代理运行',
  'media_capture': '媒体采集',
  'chrome_capture_ai': 'Chrome采集AI',
  'rich_text_edit': '富文本编辑',
};

const statusLabelMap: Record<string, string> = {
  'pending': '待处理',
  'processing': '处理中',
  'success': '成功',
  'failed': '失败',
  'canceled': '已取消',
};

const typeLabel = (type: string) => typeLabelMap[type] || type;
const statusLabel = (status: string) => statusLabelMap[status] || status;

const SysTasksPage: React.FC = () => {
  const [form] = Form.useForm();
  const [data, setData] = useState<SysTaskItem[]>([]);
  const [meta, setMeta] = useState<ListMeta>({ total: 0, page: 1, page_size: 20, page_count: 0 });
  const [loading, setLoading] = useState(false);

  const [drawerOpen, setDrawerOpen] = useState(false);
  const [currentId, setCurrentId] = useState<number | null>(null);
  const [detail, setDetail] = useState<SysTaskItem | null>(null);
  const [children, setChildren] = useState<SysTaskItem[]>([]);
  const [steps, setSteps] = useState<SysTaskStepItem[]>([]);
  const [detailLoading, setDetailLoading] = useState(false);

  const columns: ColumnsType<SysTaskItem> = useMemo(() => [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 90 },
    { title: '类型', dataIndex: 'type', key: 'type', width: 180, render: (v: any) => typeLabel(v) },
    { title: '标题', dataIndex: 'title', key: 'title', width: 240, ellipsis: true, render: (v: any) => v || '-' },
    { title: '状态', dataIndex: 'status', key: 'status', width: 110, render: (s: any) => <Tag color={statusColor(String(s))}>{statusLabel(String(s))}</Tag> },
    { title: '进度', key: 'progress', width: 140, render: (_, r) => {
      const total = Number(r.progress_total || 0);
      const done = Number(r.progress_done || 0);
      const failed = Number(r.progress_failed || 0);
      if (!total) return '-';
      return `${done + failed}/${total}`;
    }},
    { title: '错误', dataIndex: 'error_message', key: 'error_message', ellipsis: true, width: 220, render: (v: any) => v || '-' },
    { title: '创建时间', dataIndex: 'created_at', key: 'created_at', width: 170, render: (v: any) => v ? dayjs(v).format('YYYY-MM-DD HH:mm:ss') : '-' },
    { title: '操作', key: 'actions', fixed: 'right', width: 220, render: (_, rec) => (
      <Space>
        <Button size="small" onClick={() => openDetail(rec.id)}>详情</Button>
        <Button size="small" onClick={() => onRetry(rec)}>重试</Button>
        <Button size="small" danger onClick={() => onCancel(rec)}>取消</Button>
      </Space>
    ) },
  ], []);

  const fetchList = (page?: number, pageSize?: number) => {
    const values = form.getFieldsValue();
    const params: any = {
      page: page ?? meta.page,
      per_page: pageSize ?? meta.page_size,
      status: values.status || undefined,
      type: values.type || undefined,
      keyword: values.keyword || undefined,
    };

    // 处理创建时间范围
    if (values.created_at_start && Array.isArray(values.created_at_start) && values.created_at_start.length === 2) {
      params.created_at_start = values.created_at_start[0].format('YYYY-MM-DD HH:mm:ss');
      params.created_at_end = values.created_at_start[1].format('YYYY-MM-DD HH:mm:ss');
    }

    setLoading(true);
    api.get('/manage/sys-tasks/list', { params }).then((res: any) => {
      const d = res?.data || {};
      setData(d.items || []);
      setMeta({
        total: Number(d.total || 0),
        page: Number(d.page || 1),
        page_size: Number(d.page_size || params.per_page || 20),
        page_count: Number(d.page_count || 0),
      });
    }).catch((e: any) => {
      message.error('获取任务列表失败: ' + (e?.message || ''));
    }).finally(() => setLoading(false));
  };

  const openDetail = (id: number) => {
    setDrawerOpen(true);
    setCurrentId(id);
    setDetail(null);
    setChildren([]);
    setSteps([]);
    fetchDetailAll(id);
  };

  const fetchDetailAll = (id: number) => {
    setDetailLoading(true);
    Promise.all([
      api.get(`/manage/sys-tasks/detail/${id}`),
      api.get(`/manage/sys-tasks/children/${id}`),
      api.get(`/manage/sys-tasks/steps/${id}`),
    ]).then(([d1, d2, d3]: any[]) => {
      const p1 = d1?.data || null;
      const p2 = d2?.data || {};
      const p3 = d3?.data || {};
      setDetail(p1);
      setChildren(Array.isArray(p2.items) ? p2.items : []);
      setSteps(Array.isArray(p3.items) ? p3.items : []);
    }).catch((e: any) => {
      message.error('加载任务详情失败: ' + (e?.message || ''));
    }).finally(() => setDetailLoading(false));
  };

  const onRetry = (rec: SysTaskItem) => {
    Modal.confirm({
      title: `确认重试任务 #${rec.id} 吗？`,
      onOk: () => {
        return api.post(`/manage/sys-tasks/retry/${rec.id}`).then(() => {
          message.success('已触发重试');
          fetchList();
          if (currentId === rec.id) fetchDetailAll(rec.id);
        }).catch((e: any) => {
          message.error('重试失败: ' + (e?.message || ''));
        });
      }
    });
  };

  const onCancel = (rec: SysTaskItem) => {
    Modal.confirm({
      title: `确认取消任务 #${rec.id} 吗？`,
      onOk: () => {
        return api.post(`/manage/sys-tasks/cancel/${rec.id}`, { reason: '手动取消' }).then(() => {
          message.success('已取消');
          fetchList();
          if (currentId === rec.id) fetchDetailAll(rec.id);
        }).catch((e: any) => {
          message.error('取消失败: ' + (e?.message || ''));
        });
      }
    });
  };

  useEffect(() => {
    fetchList(1, meta.page_size);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const DetailBlock = () => {

    if (!detail) return <div style={{ padding: 12 }}>{detailLoading ? '加载中...' : '暂无数据'}</div>;

    const p = detail.payload ? JSON.stringify(detail.payload, null, 2) : '';
    const r = detail.result ? JSON.stringify(detail.result, null, 2) : '';

    return (
      <div style={{ padding: 12 }}>
        <Descriptions bordered size="small" column={2} style={{ marginBottom: 12 }}>
          <Descriptions.Item label="ID">{detail.id}</Descriptions.Item>
          <Descriptions.Item label="状态"><Tag color={statusColor(detail.status)}>{statusLabel(detail.status)}</Tag></Descriptions.Item>
          <Descriptions.Item label="类型">{typeLabel(detail.type)}</Descriptions.Item>
          <Descriptions.Item label="标题" span={2}>{detail.title || '-'}</Descriptions.Item>
          <Descriptions.Item label="进度">{`${Number(detail.progress_done || 0) + Number(detail.progress_failed || 0)}/${Number(detail.progress_total || 0) || '-'}`}</Descriptions.Item>
          <Descriptions.Item label="错误">{detail.error_message || '-'}</Descriptions.Item>
          <Descriptions.Item label="开始时间">{detail.started_at ? dayjs(detail.started_at).format('YYYY-MM-DD HH:mm:ss') : '-'}</Descriptions.Item>
          <Descriptions.Item label="结束时间">{detail.finished_at ? dayjs(detail.finished_at).format('YYYY-MM-DD HH:mm:ss') : '-'}</Descriptions.Item>
          <Descriptions.Item label="取消时间">{detail.canceled_at ? dayjs(detail.canceled_at).format('YYYY-MM-DD HH:mm:ss') : '-'}</Descriptions.Item>
          <Descriptions.Item label="取消原因">{detail.cancel_reason || '-'}</Descriptions.Item>
          <Descriptions.Item label="Request ID" span={2}>{detail.request_id || '-'}</Descriptions.Item>
        </Descriptions>

        <Tabs
          items={[
            {
              key: 'payload',
              label: 'Payload',
              children: <pre style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>{p || '-'}</pre>
            },
            {
              key: 'result',
              label: 'Result',
              children: <pre style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>{r || '-'}</pre>
            }
          ]}
        />
      </div>
    );
  };

  return (
    <div style={{ padding: 24 }}>
      <Form form={form} layout="inline" onFinish={() => fetchList(1, meta.page_size)} style={{ marginBottom: 16, gap: 12, rowGap: 12, columnGap: 12 }}>
        <Form.Item name="keyword" label="关键词"><Input allowClear placeholder="标题/RequestID/错误" style={{ width: 240 }} /></Form.Item>
        <Form.Item name="type" label="类型">
          <Select allowClear style={{ width: 180 }} options={[
            { label: '内容生成', value: 'content_generation' },
            { label: '内容批量', value: 'content_batch' },
            { label: '模型生成', value: 'mold_suggest' },
            { label: 'AI代理运行', value: 'ai_agent_run' },
            { label: '媒体采集', value: 'media_capture' },
            { label: 'Chrome采集AI', value: 'chrome_capture_ai' },
            { label: '富文本编辑', value: 'rich_text_edit' },
          ]} />
        </Form.Item>
        <Form.Item name="status" label="状态">
          <Select allowClear style={{ width: 140 }} options={[
            { label: '待处理', value: 'pending' },
            { label: '处理中', value: 'processing' },
            { label: '成功', value: 'success' },
            { label: '失败', value: 'failed' },
            { label: '已取消', value: 'canceled' },
          ]} />
        </Form.Item>
        <Form.Item name="created_at_start" label="创建时间">
          <DatePicker.RangePicker style={{ width: 280 }} />
        </Form.Item>
        <Form.Item>
          <Space>
            <Button type="primary" htmlType="submit">查询</Button>
            <Button onClick={() => { form.resetFields(); fetchList(1, meta.page_size); }}>重置</Button>
          </Space>
        </Form.Item>
      </Form>

      <Table
        columns={columns}
        dataSource={data}
        rowKey="id"
        loading={loading}
        onChange={(p: TablePaginationConfig) => fetchList(p.current || 1, p.pageSize || meta.page_size)}
        pagination={{
          current: meta.page,
          pageSize: meta.page_size,
          total: meta.total,
          showSizeChanger: true,
          showQuickJumper: true,
          showTotal: (t, r) => `第 ${r[0]}-${r[1]} 条，共 ${t} 条`,
        }}
        scroll={{ x: 'max-content' }}
      />

      <Drawer
        open={drawerOpen}
        width={980}
        title={currentId ? `任务详情 #${currentId}` : '任务详情'}
        onClose={() => { setDrawerOpen(false); setCurrentId(null); }}
        extra={currentId ? (
          <Space>
            <Button onClick={() => fetchDetailAll(currentId)} loading={detailLoading}>刷新</Button>
            <Button onClick={() => detail && onRetry(detail)} disabled={!detail}>重试</Button>
            <Button danger onClick={() => detail && onCancel(detail)} disabled={!detail}>取消</Button>
          </Space>
        ) : null}
      >
        <Tabs
          items={[
            { key: 'base', label: '基本信息', children: <DetailBlock /> },
            {
              key: 'children',
              label: `子任务(${children.length})`,
              children: (
                <Table
                  size="small"
                  rowKey="id"
                  dataSource={children}
                  columns={[
                    { title: 'sort', dataIndex: 'sort_no', key: 'sort_no', width: 80 },
                    { title: 'ID', dataIndex: 'id', key: 'id', width: 90 },
                    { title: '标题', dataIndex: 'title', key: 'title', width: 260, ellipsis: true },
                    { title: '状态', dataIndex: 'status', key: 'status', width: 120, render: (s: any) => <Tag color={statusColor(String(s))}>{String(s)}</Tag> },
                    { title: '错误', dataIndex: 'error_message', key: 'error_message', ellipsis: true },
                    { title: '操作', key: 'actions', width: 120, render: (_: any, r: any) => <Button size="small" onClick={() => openDetail(Number(r.id))}>详情</Button> },
                  ] as ColumnsType<SysTaskItem>}
                  pagination={false}
                  loading={detailLoading}
                  scroll={{ x: 'max-content' }}
                />
              )
            },
            {
              key: 'steps',
              label: `Steps(${steps.length})`,
              children: (
                <Table
                  size="small"
                  rowKey="id"
                  dataSource={steps}
                  columns={[
                    { title: 'seq', dataIndex: 'seq', key: 'seq', width: 80 },
                    { title: '标题', dataIndex: 'title', key: 'title', width: 260, ellipsis: true },
                    { title: '状态', dataIndex: 'status', key: 'status', width: 120, render: (s: any) => <Tag color={statusColor(String(s))}>{String(s)}</Tag> },
                    { title: '错误', dataIndex: 'error_message', key: 'error_message', ellipsis: true },
                    { title: '开始', dataIndex: 'started_at', key: 'started_at', width: 170, render: (v: any) => v ? dayjs(v).format('YYYY-MM-DD HH:mm:ss') : '-' },
                    { title: '结束', dataIndex: 'finished_at', key: 'finished_at', width: 170, render: (v: any) => v ? dayjs(v).format('YYYY-MM-DD HH:mm:ss') : '-' },
                  ] as ColumnsType<SysTaskStepItem>}
                  expandable={{
                    expandedRowRender: (r: SysTaskStepItem) => (
                      <div style={{ padding: 12 }}>
                        <div style={{ marginBottom: 8, fontWeight: 600 }}>Payload</div>
                        <pre style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>{r.payload ? JSON.stringify(r.payload, null, 2) : '-'}</pre>
                        <div style={{ margin: '12px 0 8px', fontWeight: 600 }}>Result</div>
                        <pre style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>{r.result ? JSON.stringify(r.result, null, 2) : '-'}</pre>
                      </div>
                    ),
                    rowExpandable: (r: SysTaskStepItem) => !!r.payload || !!r.result || !!r.error_message,
                  }}
                  pagination={false}
                  loading={detailLoading}
                  scroll={{ x: 'max-content' }}
                />
              )
            },
          ]}
        />
      </Drawer>
    </div>
  );
};

export default SysTasksPage;
