import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Modal, Input, Divider, Space, Select, Switch, Typography, Steps, message, Button, Progress, Table, Tag } from 'antd';
import api from '../util/Service';

export type AiModelOption = { label: string; value: string };

export interface AiGeneratePayload {
  prompt: string;
  model: string;
  options: Record<string, boolean>;
}

export type AiGenerationStatus = 'pending' | 'processing' | 'assembling' | 'success' | 'failed';

export interface AiConfirmHelpers {
  updateStatus: (status: AiGenerationStatus) => void;
}

export interface AiGenerateModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title?: string;
  description?: string;
  promptPlaceholder?: string;
  models?: AiModelOption[];
  defaultModel?: string;
  hideModelSelect?: boolean;
  forceModel?: string;
  options?: { key: string; label: string; defaultValue?: boolean }[];
  okText?: string;
  cancelText?: string;
  // onConfirm 应返回创建的 taskId（number 或 { taskId }），组件将自动轮询 /task/ai/generate-status/{taskId}
  onConfirm: (payload: AiGeneratePayload, helpers?: AiConfirmHelpers) => Promise<number | { taskId: number } | void>;
  // 轮询成功后返回的结果与上下文（包含 payload）给调用方处理
  onResult?: (result: any, context: { payload: AiGeneratePayload }) => void | Promise<void>;
  extraContent?: React.ReactNode;
}

const { Text } = Typography;

type AiButtonProps = {
  onClick: () => void;
  text?: string;
  type?: 'link' | 'text' | 'default' | 'primary' | 'dashed';
  size?: 'small' | 'middle' | 'large';
  icon?: React.ReactNode;
  loading?: boolean;
  style?: React.CSSProperties;
};

export const AiButton: React.FC<AiButtonProps> = ({
  onClick,
  text = 'AI 生成',
  type = 'default',
  size = 'middle',
  icon,
  loading,
  style,
}) => (
  <Button
    onClick={onClick}
    type={type}
    size={size}
    icon={icon}
    loading={loading}
    style={style}
  >
    {text}
  </Button>
);

export default function AiGenerateModal({
  open,
  onOpenChange,
  title = 'AI 生成',
  description,
  promptPlaceholder = '请输入提示文案...',
  models,
  defaultModel,
  hideModelSelect = false,
  forceModel,
  options = [],
  okText = '确定',
  cancelText = '取消',
  onConfirm,
  onResult,
  extraContent,
}: AiGenerateModalProps) {
  const [prompt, setPrompt] = useState('');
  const [modelOptions, setModelOptions] = useState<AiModelOption[]>(models ?? []);
  const [model, setModel] = useState<string>(() => defaultModel ?? models?.[0]?.value ?? '');
  const [optValues, setOptValues] = useState<Record<string, boolean>>(() => {
    const ret: Record<string, boolean> = {};
    options.forEach(o => { ret[o.key] = !!o.defaultValue });
    return ret;
  });
  const [submitting, setSubmitting] = useState(false);
  const [progressStatus, setProgressStatus] = useState<AiGenerationStatus | 'idle'>('idle');
  const [latestTaskInfo, setLatestTaskInfo] = useState<any>(null);
  const [loadingModels, setLoadingModels] = useState(false);
  const fetchedRef = useRef(false);

  const modelsProvided = Array.isArray(models) && models.length > 0;

  useEffect(() => {
    if (modelsProvided) {
      setModelOptions(models);
    }
  }, [modelsProvided, models]);

  useEffect(() => {
    if (hideModelSelect) {
      if (forceModel) {
        setModel(forceModel);
      }
      return;
    }
    if (modelsProvided) {
      return;
    }
    if (fetchedRef.current) {
      return;
    }
    fetchedRef.current = true;
    setLoadingModels(true);
    (async () => {
      try {
        const res = await api.post('/gpt/list_models', {});
        const list = Array.isArray(res?.data) ? res.data : [];
        const mapped: AiModelOption[] = list
          .map((item: any) => {
            if (item && typeof item === 'object') {
              const value = item.value ?? item.label ?? '';
              const label = item.label ?? item.value ?? value;
              return value ? { label, value } : null;
            }
            if (typeof item === 'string') {
              return { label: item, value: item };
            }
            return null;
          })
          .filter(Boolean) as AiModelOption[];
        setModelOptions(mapped);
        if (mapped.length > 0) {
          const initial = defaultModel && mapped.some(opt => opt.value === defaultModel)
            ? defaultModel
            : mapped[0].value;
          setModel(initial);
        }
      } catch (err: any) {
        fetchedRef.current = false; // allow retry on next open
        message.error(err?.message || '模型列表获取失败');
      } finally {
        setLoadingModels(false);
      }
    })();
  }, [modelsProvided, defaultModel, hideModelSelect, forceModel]);

  useEffect(() => {
    if (hideModelSelect) return;
    if (!defaultModel) {
      return;
    }
    if (modelOptions.some(opt => opt.value === defaultModel)) {
      setModel(prev => (prev && modelOptions.some(opt => opt.value === prev) ? prev : defaultModel));
    }
  }, [defaultModel, modelOptions, hideModelSelect]);

  useEffect(() => {
    if (hideModelSelect) return;
    if (model && modelOptions.some(opt => opt.value === model)) {
      return;
    }
    const fallback = modelOptions[0]?.value ?? '';
    if (fallback) {
      setModel(fallback);
    }
  }, [modelOptions, hideModelSelect]);

  // reset when open changes to true
  useEffect(() => {
    if (open) {
      setPrompt('');
      const initialModel = (() => {
        if (hideModelSelect) {
          return forceModel ?? model;
        }
        if (defaultModel && modelOptions.some(opt => opt.value === defaultModel)) {
          return defaultModel;
        }
        return modelOptions[0]?.value ?? model;
      })();
      setModel(initialModel ?? '');
      const ret: Record<string, boolean> = {};
      options.forEach(o => { ret[o.key] = !!o.defaultValue });
      setOptValues(ret);
      setSubmitting(false);
      setProgressStatus('idle');
      setLatestTaskInfo(null);
    } else {
      setProgressStatus('idle');
      setLatestTaskInfo(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, hideModelSelect, forceModel]);

  const statusColor = (s: string) => {
    const v = String(s || '');
    if (v === 'success') return 'green';
    if (v === 'failed') return 'red';
    if (v === 'canceled') return 'orange';
    if (v === 'processing') return 'blue';
    if (v === 'pending') return 'default';
    return 'default';
  };

  const stepsItems = useMemo(() => {
    const statuses: ('wait' | 'process' | 'finish' | 'error')[] = ['wait', 'wait', 'wait'];
    switch (progressStatus) {
      case 'pending':
        statuses[0] = 'process';
        break;
      case 'processing':
        statuses[0] = 'finish';
        statuses[1] = 'process';
        break;
      case 'assembling':
        statuses[0] = 'finish';
        statuses[1] = 'finish';
        statuses[2] = 'process';
        break;
      case 'success':
        statuses[0] = 'finish';
        statuses[1] = 'finish';
        statuses[2] = 'finish';
        break;
      case 'failed':
        statuses[0] = 'finish';
        statuses[1] = 'error';
        statuses[2] = 'wait';
        break;
      default:
        break;
    }
    return [
      { title: '分析请求', status: statuses[0] as any },
      { title: '请求大模型', status: statuses[1] as any },
      { title: '组装数据', status: statuses[2] as any },
    ];
  }, [progressStatus]);

  const handleOk = async () => {
    try {
      // 当隐藏模型选择时，允许不传模型，由后端按系统级默认模型处理
      if (!model && !hideModelSelect) {
        message.warning('请先选择模型');
        return;
      }
      setSubmitting(true);
      setProgressStatus('pending');

      const helpers: AiConfirmHelpers = {
        updateStatus: (status) => {
          setProgressStatus(prev => {
            if (prev === 'failed') {
              return prev;
            }
            return status;
          });
        },
      };

      const requestPayload = { prompt, model, options: optValues } as AiGeneratePayload;
      const ret = await onConfirm(requestPayload, helpers);

      // 兼容返回 number 或 { taskId }
      const taskId = typeof ret === 'number' ? ret : (ret && (ret as any).taskId);

      if (taskId) {
        // 轮询后端状态，驱动 Steps
        const maxAttempts = 60; // ~ 120s
        const interval = 2000;
        for (let attempt = 0; attempt < maxAttempts; attempt++) {
          const res = await api.post(`/task/ai/generate-status/${taskId}`, {});
          const info = (res as any)?.data ?? res;
          setLatestTaskInfo(info);
          const status = info?.status as AiGenerationStatus | undefined;

          if (status === 'failed') {
            setProgressStatus('failed');
            throw new Error(info?.error_message || '生成失败，请稍后重试');
          }

          if (status === 'success') {
            setProgressStatus('success');
            const result = info?.result ?? [];
            if (typeof onResult === 'function') {
              await onResult(result, { payload: requestPayload });
            }
            onOpenChange(false);
            return;
          }

          if (status === 'pending' || status === 'processing') {
            setProgressStatus(status);
          }

          await new Promise(resolve => setTimeout(resolve, interval));
        }

        throw new Error('AI 生成超时，请稍后重试');
      } else {
        // 非任务型流程：视为已成功完成，由外部 onConfirm 处理结果
        setProgressStatus(prev => (prev === 'failed' ? prev : 'assembling'));
        setTimeout(() => {
          setProgressStatus(prev => (prev === 'failed' ? prev : 'success'));
          onOpenChange(false);
        }, 200);
      }
    } catch (err: any) {
      setProgressStatus('failed');
      message.error(err?.message || '生成失败');
    } finally {
      setSubmitting(false);
    }
  };

  const handleCancel = () => onOpenChange(false);

  return (
    <Modal
      title={title}
      open={open}
      onCancel={handleCancel}
      width={720}
      footer={
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
          {!hideModelSelect && (
            <Space align="center">
              <Text strong>选择模型</Text>
              <Select
                options={modelOptions}
                value={model}
                onChange={setModel}
                loading={loadingModels}
                placeholder={loadingModels ? '模型加载中...' : '请选择模型'}
                disabled={loadingModels && modelOptions.length === 0}
              />
            </Space>
          )}
          <Space>
            <Button onClick={handleCancel} disabled={submitting}>
              {cancelText}
            </Button>
            <Button type="primary" loading={submitting} onClick={handleOk}>
              {okText}
            </Button>
          </Space>
        </div>
      }
    >
      {description && (
        <div style={{ marginBottom: 8 }}>
          <Text type="secondary">{description}</Text>
        </div>
      )}

      <div style={{ marginBottom: 12 }}>
        <Text strong>提示文案</Text>
        <Divider style={{ margin: '8px 0' }} />
        <Input.TextArea
          value={prompt}
          onChange={(e) => setPrompt(e.target.value)}
          placeholder={promptPlaceholder}
          rows={5}
        />
      </div>

      {options.length > 0 && (
        <div style={{ marginBottom: 12 }}>
          <Text strong>选项</Text>
          <Divider style={{ margin: '8px 0' }} />
          <Space size={24} wrap>
            {options.map(opt => (
              <Space key={opt.key}>
                <Switch
                  checked={!!optValues[opt.key]}
                  onChange={(v) => setOptValues(prev => ({ ...prev, [opt.key]: v }))}
                />
                <span>{opt.label}</span>
              </Space>
            ))}
          </Space>
        </div>
      )}

      {extraContent}

      {progressStatus !== 'idle' && (
        <div style={{ marginTop: 8 }}>
          <Text strong>进度</Text>
          <Divider style={{ margin: '8px 0' }} />
          <Steps size="small" items={stepsItems as any} />
          <div style={{ marginTop: 8, color: '#999' }}>
            {progressStatus === 'failed'
              ? '生成失败，请稍后重试。'
              : progressStatus === 'success'
                ? '生成完成。'
                : '正在生成，请稍候...'}
          </div>

          {latestTaskInfo?.result && typeof latestTaskInfo?.result === 'object' && (
            <div style={{ marginTop: 12 }}>
              <Divider style={{ margin: '8px 0' }} />
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}>
                <Text strong>整体进度</Text>
                <Text type="secondary">
                  {Number(latestTaskInfo?.result?.done ?? 0)}/{Number(latestTaskInfo?.result?.total ?? 0)}
                  {Number(latestTaskInfo?.result?.failed ?? 0) ? `，失败 ${Number(latestTaskInfo?.result?.failed ?? 0)}` : ''}
                </Text>
              </div>
              <Progress
                percent={Number(latestTaskInfo?.result?.percent ?? 0)}
                status={progressStatus === 'failed' ? 'exception' : (progressStatus === 'success' ? 'success' : 'active')}
              />

              {Array.isArray(latestTaskInfo?.result?.child_tasks) && latestTaskInfo?.result?.child_tasks?.length > 0 && (
                <div style={{ marginTop: 12 }}>
                  <Text strong>子任务</Text>
                  <Divider style={{ margin: '8px 0' }} />
                  <Table
                    size="small"
                    rowKey={(r: any) => String(r?.task_id ?? r?.id ?? Math.random())}
                    dataSource={latestTaskInfo?.result?.child_tasks || []}
                    pagination={false}
                    scroll={{ y: 240 }}
                    columns={[
                      {
                        title: '序号',
                        dataIndex: 'index',
                        width: 70,
                        render: (v: any) => (v === null || v === undefined ? '-' : (Number(v) + 1)),
                      },
                      {
                        title: '主题/名称',
                        dataIndex: 'topic',
                        ellipsis: true,
                        render: (v: any) => v || '-',
                      },
                      {
                        title: '状态',
                        dataIndex: 'status',
                        width: 120,
                        render: (v: any) => <Tag color={statusColor(String(v))}>{String(v || '-')}</Tag>,
                      },
                      {
                        title: '任务ID',
                        dataIndex: 'task_id',
                        width: 110,
                        render: (v: any) => v || '-',
                      },
                    ] as any}
                  />
                </div>
              )}
            </div>
          )}
        </div>
      )}
        <Divider style={{ margin: '8px 0' }} />

    </Modal>
    
  );
}
