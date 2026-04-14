import React, { useState, useEffect, useRef } from 'react'
import { Form, Col, Flex, message, Space, Drawer, Table, Tag, Modal, Typography, Divider } from 'antd';
import { Button } from 'antd';
import { GetMiddleFormatValue, MiddleOne } from './component/MiddleOne';
import { SchemasChildType } from './context/SchemaContext';
import api from '../../util/Service';
import AiGenerateModal from '../../components/AiGenerateModal';

const middleBox = {
    marginLeft: 20
};

interface SubjectPageReturnPropsType {
    schema: Array<SchemasChildType>,
    pageId: string,
    pageName: string,
    tableName: string,
    moldId?: string | number,
    onFinishHandlerFunc: Function,
    onGetAiOpenHandler?: (open: () => void) => void,
}
export function EditContent(page: SubjectPageReturnPropsType) {

    // const myPage = usePage() as SubjectPageReturnType
    let { schema, pageId, pageName, tableName, moldId, onFinishHandlerFunc, onGetAiOpenHandler } = page

    const [form] = Form.useForm();
    const [submitting, setSubmitting] = useState(false);
    const isSubmittingRef = useRef(false);

    // AI 助手状态
    const [aiModalOpen, setAiModalOpen] = useState(false);
    const [verDrawer, setVerDrawer] = useState<{ open: boolean }>(() => ({ open: false }));
    const [verList, setVerList] = useState<any[]>([]);
    const [verLoading, setVerLoading] = useState(false);
    const [verDiffModal, setVerDiffModal] = useState<{ open: boolean, items: any[] }>({ open: false, items: [] });
    const latestVersion = verList && verList.length ? verList[0].version : null;
    const isSubjectPage = String(pageId) === String(moldId);
    const verContentId = isSubjectPage ? 0 : pageId;

    const statusText = (s: string) => {
        switch (s) {
            case 'published': return '已发布';
            case 'disabled': return '已下线';
            case 'pending':
            default: return '待发布';
        }
    };
    const statusColor = (s: string) => {
        switch (s) {
            case 'published': return 'green';
            case 'disabled': return 'default';
            case 'pending':
            default: return 'gold';
        }
    };
    useEffect(() => {
        if (onGetAiOpenHandler) {
            onGetAiOpenHandler(() => setAiModalOpen(true));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // 移除项目级 AI 模型读取，后端将按系统级默认模型处理


    const schemaTypeMap = schema.reduce((acc, cur) => {
        acc[cur.field] = cur.type;
        return acc;
    }, {} as Record<string, string>);

    const schemaIdToFieldMap = schema.reduce((acc, cur) => {
        if (cur.id) {
            acc[cur.id] = cur.field;
        }
        return acc;
    }, {} as Record<string, string>);

    // 渲染对比值（支持图片/文件）
    const getUrl = (item: any): string => {
        if (typeof item === 'string') return item;
        if (item && typeof item === 'object') return item.url || '';
        return '';
    };
    const getName = (item: any): string => {
        if (typeof item === 'string') {
            try {
                const parts = item.split('/');
                return parts[parts.length - 1] || item;
            } catch { return item; }
        }
        if (item && typeof item === 'object') return item.name || item.url || '文件';
        return '';
    };
    const parseDiffItems = (val: any): any[] => {
        if (!val) return [];
        if (Array.isArray(val)) return val;
        if (typeof val === 'string') {
            const trimmed = val.trim();
            if (trimmed.startsWith('[') || trimmed.startsWith('{')) {
                try {
                    const parsed = JSON.parse(trimmed);
                    return Array.isArray(parsed) ? parsed : [parsed];
                } catch { return [trimmed]; }
            }
            // 纯 URL 字符串
            if (trimmed) return [trimmed];
        }
        return [];
    };
    const renderDiffValue = (field: string, val: any) => {
        const t = (schemaTypeMap as any)[field];
        try {
            if (t === 'picUpload') {
                // 单图：字符串URL
                const url = typeof val === 'string' ? val : '';
                if (url) return <img src={url} alt="img" width={60} height={60} style={{ objectFit: 'cover', borderRadius: 4, border: '1px solid #f0f0f0' }} />;
                return <span>（空）</span>;
            }
            if (t === 'picGallery') {
                // 多图：JSON数组
                const items = parseDiffItems(val);
                return (
                    <div>
                        {items.map((it: any, i: number) => {
                            const url = typeof it === 'string' ? it : (it?.url || '');
                            if (!url) return null;
                            return <img key={i} src={url} alt={`img-${i}`} width={60} height={60} style={{ objectFit: 'cover', marginRight: 6, borderRadius: 4, border: '1px solid #f0f0f0' }} />
                        })}
                    </div>
                );
            }
            if (t === 'fileUpload') {
                // 单文件：字符串URL
                const url = typeof val === 'string' ? val : '';
                const name = getName(val);
                if (url) return <a href={url} target="_blank" rel="noreferrer">{name}</a>;
                return <span>（空）</span>;
            }
        } catch {}
        return <span>{String(val ?? '')}</span>;
    };

    const onFinish = async (values: any) => {
        if (isSubmittingRef.current) {
            return;
        }
        
        isSubmittingRef.current = true;
        setSubmitting(true);
        
        try {
            for (let key in values) {
                if(values[key] == undefined) {
                    values[key] = ''
                    continue
                }
                values[key] = GetMiddleFormatValue({ type: schemaTypeMap[key], value: values[key] })
            }
            await onFinishHandlerFunc(values, pageId)
        } catch (error) {
            console.error('[防重复提交] 提交失败:', error);
        } finally {
            isSubmittingRef.current = false;
            setSubmitting(false);
        }
    }

    const children = schema;

    const savaBtnStye = {
        float: 'right',
        marginRight: '50'
    }

    const openVersions = async () => {
        if (!moldId || !pageId) return;
        try {
            setVerDrawer({ open: true });
            setVerLoading(true);
            const res = await api.get(`/content/versions/${moldId}/${verContentId}`);
            const rows = (res?.data?.data ?? res?.data) || [];
            setVerList(Array.isArray(rows) ? rows : []);
        } catch (e: any) {
            message.error(e?.message || '加载版本失败');
        } finally {
            setVerLoading(false);
        }
    };

    const makeDiffAgainstLatest = async (version: number) => {
        if (!moldId || !pageId || !version) return;
        if (!latestVersion) {
            message.warning('暂无最新版本');
            return;
        }
        if (version === latestVersion) {
            message.info('已是最新版本');
            return;
        }
        try {
            const res = await api.get(`/content/versions/diff`, { params: { mold_id: moldId, id: verContentId, v1: version, v2: latestVersion } });
            const items = (res?.data?.data ?? res?.data) || [];
            setVerDiffModal({ open: true, items: Array.isArray(items) ? items : [] });
        } catch (e: any) {
            message.error(e?.message || '对比失败');
        }
    };

    const doRollback = (version: number) => {
        if (!moldId || !pageId) return;
        if (isSubjectPage) {
            message.info('单页暂不支持一键回滚，请使用“加载到表单”后保存');
            return;
        }
        Modal.confirm({
            title: `回滚到版本 ${version}？`,
            onOk: async () => {
                try {
                    const resp = await api.post(`/content/versions/rollback/${moldId}/${verContentId}/${version}`, {});
                    const respMsg = (resp as any)?.message ?? (resp as any)?.msg;
                    message.success(respMsg || '回滚成功');
                    const res = await api.get(`/content/versions/${moldId}/${verContentId}`);
                    const rows = (res?.data?.data ?? res?.data) || [];
                    setVerList(Array.isArray(rows) ? rows : []);
                    setTimeout(() => window.location.reload(), 600);
                } catch (e: any) {
                    message.error(e?.message || '回滚失败');
                }
            }
        });
    };

    const loadVersionToForm = async (version: number) => {
        if (!moldId || !pageId) return;
        try {
            const res = await api.get(`/content/versions/${moldId}/${verContentId}/${version}`);
            const ver = (res?.data?.data ?? res?.data) || null;
            const data = ver?.data_json || {};
            const updates: Record<string, any> = {};
            const typeMap = schemaTypeMap;
            Object.keys(data || {}).forEach((field) => {
                const t = (typeMap as any)[field];
                let v = data[field];
                if (t === 'checkbox' || t === 'select') {
                    if (Array.isArray(v)) updates[field] = v;
                    else if (typeof v === 'string' && v) updates[field] = v.split(',').map(s => s.trim()).filter(Boolean);
                    else updates[field] = [];
                } else if (t === 'numInput' || t === 'number') {
                    const n = Number(v);
                    updates[field] = isNaN(n) ? undefined : n;
                } else if (t === 'picUpload' || t === 'fileUpload') {
                    // 单图/单文件：字符串URL
                    updates[field] = typeof v === 'string' ? v : '';
                } else if (t === 'picGallery') {
                    // 多图：JSON数组
                    try {
                        if (typeof v === 'string' && v) {
                            const parsed = JSON.parse(v);
                            updates[field] = Array.isArray(parsed) ? parsed : [parsed];
                        } else if (Array.isArray(v)) {
                            updates[field] = v;
                        } else {
                            updates[field] = [];
                        }
                    } catch {
                        updates[field] = v ? [v] : [];
                    }
                } else {
                    updates[field] = v ?? '';
                }
            });
            form.setFieldsValue(updates);
            message.success(`已加载版本 ${version} 到表单`);
        } catch (e: any) {
            message.error(e?.message || '加载失败');
        }
    };

    return (
        <div style={middleBox}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                {/* 顶部操作区由父组件控制（AI 按钮、返回按钮等） */}
            </div>
            <div style={{ marginTop: 12 }}>

                <Form
                    form={form}
                    layout={'horizontal'}
                    name="dynamic_form_nest_item"
                    onFinish={onFinish}
                    style={{ minWidth: 600 }}
                    autoComplete="off"
                >
                    <Flex wrap="wrap" gap="small">
                        {/* {childrenList} */}
                        {children.map((child) => {
                            let colLength = 11
                            if (child.type === 'dividing_line') {
                                colLength = 23
                            }
                            if (child.length) {
                                colLength = child.length - 1
                            }

                            return <Col key={child.id} span={colLength}>
                                <MiddleOne child={child} form={form} />
                            </Col>

                        })}
                    </Flex>

                    <Form.Item>
                        <Button 
                            type="primary" 
                            htmlType="submit" 
                            loading={submitting} 
                            disabled={submitting}
                        >
                            保存
                        </Button>
                        <Button style={{ marginLeft: 12 }} onClick={openVersions} disabled={submitting}>
                            版本历史
                        </Button>
                    </Form.Item>
                </Form>
                <AiGenerateModal
                    open={aiModalOpen}
                    onOpenChange={setAiModalOpen}
                    title="AI 帮助生成内容"
                    description="填写提示词并选择模型，系统将自动生成并填充表单内容。"
                    promptPlaceholder="例如：生成一篇关于 TypeScript 最佳实践的文章概要和正文开头"
                    hideModelSelect={true}
                    options={[{ key: 'onlyEmpty', label: '仅填充空字段', defaultValue: true }]}
                    onConfirm={async ({ prompt, model, options }) => {
                        if (!moldId) {
                            throw new Error('缺少模型ID，无法生成');
                        }
                        const payload = {
                            prompt,
                            only_empty: !!options.onlyEmpty,
                            current_values: form.getFieldsValue(true),
                        };
                        const res = await api.post(`/content/ai/generate/${moldId}`, payload);
                        const taskInfo = res?.data ?? res;
                        const taskId = taskInfo?.task_id ?? taskInfo?.id;
                        if (!taskId) {
                            throw new Error('任务创建失败，请稍后重试');
                        }
                        return taskId; // 交由 AiGenerateModal 内部轮询并驱动进度
                    }}
                    onResult={async (resultItems, { payload }) => {
                        if (!Array.isArray(resultItems) || resultItems.length === 0) {
                            throw new Error('未生成有效结果');
                        }

                        const current = form.getFieldsValue(true) as Record<string, any>;
                        const updates: Record<string, any> = {};
                        const onlyEmpty = !!payload.options.onlyEmpty;
                        const labelToFieldMap = schema.reduce((acc, cur) => {
                            if (cur.label && cur.field) acc[cur.label] = cur.field;
                            return acc;
                        }, {} as Record<string, string>);

                        resultItems.forEach((item: any) => {
                            if (!item || typeof item !== 'object') return;

                            const candidateKeys = [
                                item?.field,
                                item?.id ? schemaIdToFieldMap[item.id] : undefined,
                                item?.id,
                                item?.label ? labelToFieldMap[item.label] : undefined,
                            ];

                            const fieldKey = candidateKeys.find((key) => key && schemaTypeMap[key!]) as string | undefined;
                            if (!fieldKey || !(fieldKey in schemaTypeMap)) return;

                            const t = (schemaTypeMap as any)[fieldKey];
                            const curVal = current[fieldKey];
                            let isEmpty = curVal === undefined || curVal === null || curVal === '' || (Array.isArray(curVal) && curVal.length === 0);
                            if (t === 'richText' && typeof curVal === 'string') {
                                const plain = curVal.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, '').trim();
                                isEmpty = isEmpty || plain.length === 0;
                            }
                            if (!onlyEmpty || isEmpty) {
                                let v = item?.value;
                                if (t === 'select' || t === 'checkbox') {
                                    if (Array.isArray(v)) updates[fieldKey] = v;
                                    else if (v === undefined || v === null || v === '') updates[fieldKey] = [];
                                    else updates[fieldKey] = [v];
                                } else {
                                    updates[fieldKey] = v ?? '';
                                }
                            }
                        });
                        form.setFieldsValue(updates);
                        message.success('已生成并填充内容');
                    }}
                />
                <Drawer
                    title="版本历史"
                    placement="right"
                    open={verDrawer.open}
                    onClose={() => { setVerDrawer({ open: false }); }}
                    width={720}
                    destroyOnClose
                >
                    <Table
                        size="small"
                        loading={verLoading}
                        rowKey={(r) => String(r.version)}
                        dataSource={verList}
                        pagination={{ pageSize: 10 }}
                        columns={[
                            { title: '版本', dataIndex: 'version', width: 80 },
                            { title: '状态', dataIndex: 'content_status', width: 140, render: (v: string) => <Tag color={statusColor(v)}>{statusText(v)}</Tag> },
                            { title: '操作人', dataIndex: 'created_by', width: 100 },
                            { title: '时间', dataIndex: 'created_at', width: 180 },
                            {
                                title: '操作', key: 'op', width: 260,
                                render: (_: any, r: any) => (
                                    <Space>
                                        {latestVersion && r.version === latestVersion ? (
                                            <Typography.Text type="secondary">已是最新版本</Typography.Text>
                                        ) : (
                                            <a onClick={() => makeDiffAgainstLatest(r.version)}>对比</a>
                                        )}
                                        <a onClick={() => loadVersionToForm(r.version)}>加载到表单</a>
                                        {!isSubjectPage && (
                                            <a onClick={() => doRollback(r.version)} style={{ color: 'red' }}>回滚</a>
                                        )}
                                    </Space>
                                )
                            },
                        ]}
                    />
                </Drawer>
                <Modal
                    title="版本对比"
                    open={verDiffModal.open}
                    onCancel={() => setVerDiffModal({ open: false, items: [] })}
                    footer={<Button onClick={() => setVerDiffModal({ open: false, items: [] })}>关闭</Button>}
                    width={720}
                >
                    {verDiffModal.items && verDiffModal.items.length ? (
                        <div style={{ maxHeight: 420, overflow: 'auto' }}>
                            {verDiffModal.items.map((it: any, idx: number) => (
                                <div key={idx} style={{ marginBottom: 8 }}>
                                    <div style={{ fontWeight: 500 }}>{it.label || it.field}</div>
                                    <div style={{ color: '#999' }}>旧值：{renderDiffValue(it.field, it.old)}</div>
                                    <div>新值：{renderDiffValue(it.field, it.new)}</div>
                                    <Divider style={{ margin: '8px 0' }} />
                                </div>
                            ))}
                        </div>
                    ) : (
                        <Typography.Paragraph type="secondary">无差异</Typography.Paragraph>
                    )}
                </Modal>
            </div>
        </div>
    )
}