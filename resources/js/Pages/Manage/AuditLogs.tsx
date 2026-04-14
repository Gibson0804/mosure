import React, { useEffect, useMemo, useState } from 'react';
import { Table, Form, Input, Select, Button, DatePicker, Space, Tag, message, Modal, Descriptions } from 'antd';
import type { ColumnsType, TablePaginationConfig, TableProps } from 'antd/es/table';
import type { SorterResult } from 'antd/es/table/interface';
import api from '../../util/Service';
import dayjs from 'dayjs';

interface AuditLogItem {
  id: number;
  actor_type?: string | null;
  actor_id?: number | null;
  actor_name?: string | null;
  api_key?: string | null;
  meta?: any;
  before_data?: any;
  after_data?: any;
  diff?: any;
  action: string;
  module?: string | null;
  resource_type?: string | null;
  resource_table?: string | null;
  resource_id?: number | null;
  request_method?: string | null;
  request_path?: string | null;
  request_ip?: string | null;
  user_agent?: string | null;
  request_id?: string | null;
  status?: string | null;
  error_message?: string | null;
  created_at: string;
}

interface ListMeta {
  total: number;
  page: number;
  page_size: number;
  page_count: number;
}

const { RangePicker } = DatePicker;

const AuditLogs: React.FC = () => {
  const [form] = Form.useForm();
  const [data, setData] = useState<AuditLogItem[]>([]);
  const [meta, setMeta] = useState<ListMeta>({ total: 0, page: 1, page_size: 15, page_count: 0 });
  const [loading, setLoading] = useState(false);
  const [sorter, setSorter] = useState<{ field?: string; order?: 'ascend' | 'descend' }>();
  const [detailVisible, setDetailVisible] = useState(false);
  const [selectedLog, setSelectedLog] = useState<AuditLogItem | null>(null);

  const columns: ColumnsType<AuditLogItem> = useMemo(() => [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 80, sorter: true },
    { title: '动作', dataIndex: 'action', key: 'action', width: 110, sorter: true },
    { title: '模块', dataIndex: 'module', key: 'module', width: 120, ellipsis: true },
    { title: '资源表', dataIndex: 'resource_table', key: 'resource_table', width: 180, ellipsis: true },
    { title: '资源ID', dataIndex: 'resource_id', key: 'resource_id', width: 100 },
    { title: '方法', dataIndex: 'request_method', key: 'request_method', width: 90 },
    { title: '路径', dataIndex: 'request_path', key: 'request_path', width: 260, ellipsis: true },
    { title: 'API Key ID', dataIndex: ['meta','api_key_id'], key: 'api_key_id', width: 140, render: (_: any, rec) => rec?.meta?.api_key_id ?? '-' },
    { title: '状态', dataIndex: 'status', key: 'status', width: 90, render: (s?: string) => (
      <Tag color={s === 'success' ? 'green' : 'red'}>{s || '-'}</Tag>
    ) },
    { title: '创建时间', dataIndex: 'created_at', key: 'created_at', width: 170, sorter: true, render: (v: string) => dayjs(v).format('YYYY-MM-DD HH:mm:ss') },
    {
      title: '操作',
      key: 'action',
      width: 80,
      fixed: 'right',
      render: (_: any, record: AuditLogItem) => (
        <Button type="link" size="small" onClick={() => showDetail(record)}>
          详情
        </Button>
      ),
    },
  ], []);

  const fetchList = async (page?: number, pageSize?: number) => {
    const values = form.getFieldsValue();
    const params: any = {
      page: page ?? meta.page,
      page_size: pageSize ?? meta.page_size,
    };

    // sort
    if (sorter?.field) {
      const prefix = sorter.order === 'descend' ? '-' : '';
      params.sort = `${prefix}${sorter.field}`;
    } else {
      params.sort = '-created_at';
    }

    // filters
    const filter: any = {};
    if (values.action) filter.action = values.action;
    if (values.module) filter.module = values.module;
    if (values.status) filter.status = values.status;
    if (values.api_key_id) filter.api_key_id = values.api_key_id;
    if (values.resource_table) filter.resource_table = values.resource_table;
    if (values.request_path) filter.request_path = { op: 'like', value: values.request_path };
    if (values.created_at && values.created_at.length === 2) {
      filter.created_at = { op: 'between', value: [values.created_at[0].toISOString(), values.created_at[1].toISOString()] };
    }
    params.filter = filter;

    setLoading(true);
    try {
      const res = await api.get('/manage/audit-logs/list', { params });
      const payload = res.data;
      setData(payload.data || []);
      setMeta(payload.meta || { total: 0, page: 1, page_size: 15, page_count: 0 });
    } catch (e: any) {
      message.error(e?.message || '获取审计日志失败');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchList(1, meta.page_size);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const onTableChange: TableProps<AuditLogItem>['onChange'] = (pagination, _filters, sorter) => {
    const s = Array.isArray(sorter) ? sorter[0] : sorter;
    setSorter({ field: (s?.field as string) || undefined, order: s?.order || undefined });
    fetchList(pagination.current || 1, pagination.pageSize || meta.page_size);
  };

  const reset = () => {
    form.resetFields();
    setSorter(undefined);
    fetchList(1, meta.page_size);
  };

  const showDetail = (record: AuditLogItem) => {
    setSelectedLog(record);
    setDetailVisible(true);
  };

  const renderMetaContent = (meta: any) => {
    if (!meta || typeof meta !== 'object') {
      return <span style={{ color: '#999' }}>无变动内容</span>;
    }

    const items: any[] = [];
    Object.keys(meta).forEach((key) => {
      const value = meta[key];
      let displayValue: any = value;

      // 特殊处理 diff 格式：[before, after]
      if (Array.isArray(value) && value.length === 2) {
        const [before, after] = value;
        if (before === null && after !== null) {
          displayValue = (
            <p>
              <span style={{ fontWeight: 500 }}>{key}</span>
              <span style={{ color: '#999', margin: '0 8px' }}>设置为</span>
              {renderValue(after)}
            </p>
          );
        } else if (before !== null && after === null) {
          displayValue = (
            <p>
              <span style={{ fontWeight: 500 }}>{key}</span>
              <span style={{ color: '#999', margin: '0 8px' }}>从</span>
              {renderValue(before)}
              <span style={{ color: '#999', margin: '0 8px' }}>删除</span>
            </p>
          );
        } else if (before !== after) {
          displayValue = (
            <p>
              <span style={{ fontWeight: 500 }}>{key}</span>
              <span style={{ color: '#999', margin: '0 8px' }}>从</span>
              {renderValue(before)}
              <span style={{ color: '#999', margin: '0 8px' }}>变成</span>
              {renderValue(after)}
            </p>
          );
        }
      } else if (value === null || value === undefined) {
        displayValue = <span style={{ color: '#999' }}>null</span>;
      } else if (typeof value === 'boolean') {
        displayValue = (
          <Tag color={value ? 'green' : 'red'}>
            {value ? 'true' : 'false'}
          </Tag>
        );
      } else if (typeof value === 'number') {
        displayValue = <span style={{ fontFamily: 'monospace' }}>{value}</span>;
      } else if (Array.isArray(value)) {
        displayValue = (
          <div style={{ maxHeight: '200px', overflow: 'auto' }}>
            {value.map((item, idx) => (
              <div key={idx} style={{ padding: '4px 0', borderBottom: '1px solid #f0f0f0' }}>
                <span style={{ color: '#999', marginRight: '8px' }}>[{idx}]</span>
                {typeof item === 'object' ? (
                  <pre style={{ margin: 0, whiteSpace: 'pre-wrap', wordBreak: 'break-word', fontSize: '12px', background: '#fafafa', padding: '4px', borderRadius: '2px' }}>
                    {JSON.stringify(item, null, 2)}
                  </pre>
                ) : (
                  <span style={{ fontFamily: 'monospace' }}>{String(item)}</span>
                )}
              </div>
            ))}
          </div>
        );
      } else if (typeof value === 'string') {
        try {
          const parsed = JSON.parse(value);
          if (typeof parsed === 'object' && parsed !== null) {
            displayValue = (
              <pre style={{ margin: 0, whiteSpace: 'pre-wrap', wordBreak: 'break-word', fontSize: '12px', background: '#f5f5f5', padding: '8px', borderRadius: '4px' }}>
                {JSON.stringify(parsed, null, 2)}
              </pre>
            );
          } else {
            displayValue = <span style={{ fontFamily: 'monospace' }}>{value}</span>;
          }
        } catch {
          if (value.length > 200) {
            displayValue = (
              <pre style={{ margin: 0, whiteSpace: 'pre-wrap', wordBreak: 'break-word', fontSize: '12px', maxHeight: '150px', overflow: 'auto', background: '#f5f5f5', padding: '8px', borderRadius: '4px' }}>
                {value}
              </pre>
            );
          } else {
            displayValue = <span style={{ fontFamily: 'monospace' }}>{value}</span>;
          }
        }
      } else if (typeof value === 'object') {
        displayValue = (
          <pre style={{ margin: 0, whiteSpace: 'pre-wrap', wordBreak: 'break-word', fontSize: '12px', background: '#f5f5f5', padding: '8px', borderRadius: '4px', maxHeight: '300px', overflow: 'auto' }}>
            {JSON.stringify(value, null, 2)}
          </pre>
        );
      }

      items.push(
        <Descriptions.Item key={key} label={<span style={{ fontWeight: 500 }}>{key}</span>} span={typeof value === 'object' && value !== null && !Array.isArray(value) ? 3 : 1}>
          {displayValue}
        </Descriptions.Item>
      );
    });

    return items.length > 0 ? items : <Descriptions.Item label="变动内容"><span style={{ color: '#999' }}>无</span></Descriptions.Item>;
  };

  const renderValue = (value: any): any => {
    if (value === null || value === undefined) {
      return <span style={{ color: '#999' }}>null</span>;
    } else if (typeof value === 'boolean') {
      return (
        <Tag color={value ? 'green' : 'red'}>
          {value ? 'true' : 'false'}
        </Tag>
      );
    } else if (typeof value === 'number') {
      return <span style={{ fontFamily: 'monospace' }}>{value}</span>;
    } else if (typeof value === 'string') {
      if (value.length > 100) {
        return (
          <pre style={{ margin: 0, whiteSpace: 'pre-wrap', wordBreak: 'break-word', fontSize: '12px', background: '#fafafa', padding: '4px', borderRadius: '2px', display: 'inline-block' }}>
            {value}
          </pre>
        );
      }
      return <span style={{ fontFamily: 'monospace' }}>{value}</span>;
    } else if (typeof value === 'object') {
      return (
        <pre style={{ margin: 0, whiteSpace: 'pre-wrap', wordBreak: 'break-word', fontSize: '12px', background: '#fafafa', padding: '4px', borderRadius: '2px', display: 'inline-block' }}>
          {JSON.stringify(value, null, 2)}
        </pre>
      );
    }
    return String(value);
  };

  return (
    <div style={{ padding: 24 }}>
      <Form form={form} layout="inline" onFinish={() => fetchList(1, meta.page_size)} style={{ marginBottom: 16, gap: 12, rowGap: 12, columnGap: 12 }}>
        <Form.Item name="action" label="动作">
          <Select allowClear style={{ width: 140 }} options={[
            { label: 'create', value: 'create' },
            { label: 'update', value: 'update' },
            { label: 'delete', value: 'delete' },
          ]} />
        </Form.Item>
        <Form.Item name="module" label="模块"><Input placeholder="content/subject/..." allowClear style={{ width: 160 }} /></Form.Item>
        <Form.Item name="status" label="状态"><Select allowClear style={{ width: 120 }} options={[{ label: 'success', value: 'success' }, { label: 'fail', value: 'fail' }]} /></Form.Item>
        <Form.Item name="api_key_id" label="API Key ID"><Input allowClear style={{ width: 160 }} /></Form.Item>
        <Form.Item name="resource_table" label="资源表"><Input allowClear style={{ width: 200 }} /></Form.Item>
        <Form.Item name="request_path" label="路径"><Input allowClear style={{ width: 220 }} /></Form.Item>
        <Form.Item name="created_at" label="时间"><RangePicker showTime /></Form.Item>
        <Form.Item>
          <Space>
            <Button type="primary" htmlType="submit">查询</Button>
            <Button onClick={reset}>重置</Button>
          </Space>
        </Form.Item>
      </Form>

      <Table
        columns={columns}
        dataSource={data}
        rowKey="id"
        loading={loading}
        onChange={onTableChange}
        pagination={{
          current: meta.page,
          pageSize: meta.page_size,
          total: meta.total,
          showSizeChanger: true,
          showQuickJumper: true,
          showTotal: (total, range) => `第 ${range[0]}-${range[1]} 条，共 ${total} 条`,
        }}
        scroll={{ x: 'max-content' }}
      />

      <Modal
        title="审计日志详情"
        open={detailVisible}
        onCancel={() => setDetailVisible(false)}
        footer={[
          <Button key="close" onClick={() => setDetailVisible(false)}>
            关闭
          </Button>,
        ]}
        width={800}
      >
        {selectedLog && (
          <Descriptions column={3} bordered size="small">
            <Descriptions.Item label="ID">{selectedLog.id}</Descriptions.Item>
            <Descriptions.Item label="动作">{selectedLog.action}</Descriptions.Item>
            <Descriptions.Item label="模块">{selectedLog.module || '-'}</Descriptions.Item>
            <Descriptions.Item label="资源表">{selectedLog.resource_table || '-'}</Descriptions.Item>
            <Descriptions.Item label="资源ID">{selectedLog.resource_id || '-'}</Descriptions.Item>
            <Descriptions.Item label="状态">
              <Tag color={selectedLog.status === 'success' ? 'green' : 'red'}>
                {selectedLog.status || '-'}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="请求方法">{selectedLog.request_method || '-'}</Descriptions.Item>
            <Descriptions.Item label="请求路径" span={2}>{selectedLog.request_path || '-'}</Descriptions.Item>
            <Descriptions.Item label="请求IP">{selectedLog.request_ip || '-'}</Descriptions.Item>
            <Descriptions.Item label="操作者类型">{selectedLog.actor_type || '-'}</Descriptions.Item>
            <Descriptions.Item label="操作者ID">{selectedLog.actor_id || '-'}</Descriptions.Item>
            <Descriptions.Item label="操作者名称">{selectedLog.actor_name || '-'}</Descriptions.Item>
            <Descriptions.Item label="创建时间" span={2}>
              {dayjs(selectedLog.created_at).format('YYYY-MM-DD HH:mm:ss')}
            </Descriptions.Item>
            {selectedLog.error_message && (
              <Descriptions.Item label="错误信息" span={3}>
                <span style={{ color: 'red' }}>{selectedLog.error_message}</span>
              </Descriptions.Item>
            )}
            {selectedLog.diff && Object.keys(selectedLog.diff).length > 0 && (
              <Descriptions.Item label="变动内容" span={3}>
                {renderMetaContent(selectedLog.diff)}
              </Descriptions.Item>
            )}
          </Descriptions>
        )}
      </Modal>
    </div>
  );
};

export default AuditLogs;
