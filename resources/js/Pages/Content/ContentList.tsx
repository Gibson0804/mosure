import React, { useEffect, useMemo, useState } from 'react';
import { Space, Button, Table, Tag, Modal, Checkbox, message, InputNumber, Dropdown, Drawer, Typography, Descriptions, Divider, Form, Input, Select, DatePicker, TimePicker } from 'antd';
import { Link, router } from '@inertiajs/react'
import { DownOutlined, ExclamationCircleFilled } from '@ant-design/icons';
import api from '../../util/Service';
import { CONTENT_ROUTES, MOLD_ROUTES } from '../../Constants/routes';
import AiGenerateModal, { AiButton } from '../../components/AiGenerateModal';
import dayjs from 'dayjs';

type DetailInfoData = {
    format_info: Array<any> | null,
    raw_info: Array<any> | Record<string, any> | null
}

type detailpropsType = {
    info: DetailInfoData | null
}

type FileObj = { url: string; name?: string };
type fileType = {
    type: string,
    content: Array<FileObj | string> | FileObj | string | null
}

type CheckboxValueType = string | number;


const resolveColorValue = (content: fileType['content']): string => {
    if (typeof content === 'string') return content;
    if (Array.isArray(content) && content.length) {
        return resolveColorValue(content[0] as fileType['content']);
    }
    if (content && typeof content === 'object' && 'color' in content) {
        return (content as Record<string, any>).color ?? '#ffffff';
    }
    return '#ffffff';
}

const colorItem = (fileValue : fileType) => {
    const color = resolveColorValue(fileValue.content);
    return (
        <>
            <div style={{display: 'inline-block',width: '20px', height: '20px', backgroundColor: color, border: '1px solid #d9d9d9', borderRadius: 4}}></div>
            <span style={{ display: 'inline-block', verticalAlign: 'top', height: '25px', lineHeight: '20px', marginLeft: '6px' }}> {color}</span>
        </> 
    )
}

const normalizeToArray = (content: any): Array<FileObj | string> => {
    if (content == null) return [];
    return Array.isArray(content) ? content : [content];
}

const getUrl = (item: FileObj | string): string => {
    return typeof item === 'string' ? item : (item.url ?? '');
}

const getName = (item: FileObj | string): string => {
    if (typeof item === 'string') {
        try {
            const parts = item.split('/');
            return parts[parts.length - 1] || item;
        } catch { return item; }
    }
    return item.name || item.url || '文件';
}

const fileItem = (fileValue : fileType) => {
    const items = normalizeToArray(fileValue.content);
    return (
        <div>
            {items.map((item, index) => {
                const url = getUrl(item);
                const name = getName(item);
                return (
                    <a
                        style={{display: 'block'}}
                        key={index}
                        href={url}
                        target="_blank"
                        rel="noreferrer"
                    >{name}</a>
                )
            })}
        </div>
    )
}

const imageItem = (fileValue : fileType) => {
    const items = normalizeToArray(fileValue.content);
    return (
        <div>
            {items.map((item, index) => {
                const url = getUrl(item);
                if (!url) return null;
                return (
                    <img
                        key={index}
                        src={url}
                        alt={`Image ${index + 1}`}
                        width={100}
                        height={100}
                        style={{ cursor: 'pointer', objectFit: 'cover', marginRight: 6 }}
                        onClick={() => window.open(url, '_blank')}
                    />
                )
            })}
        </div>
    )
}

const richTextItem = (fileValue : fileType) => {
    const html = typeof fileValue.content === 'string'
        ? fileValue.content
        : Array.isArray(fileValue.content)
            ? fileValue.content.join('')
            : '';
    return (
        <div dangerouslySetInnerHTML={{ __html: html || '' }} />
    )
}

//字段展示转换
const formatDataValue = (recordValue: object|string): React.ReactNode => {

    if(recordValue && recordValue instanceof Object) {
        const fileValue = recordValue as fileType
        switch (fileValue.type) {
            case 'color':
                return colorItem(fileValue)
            case 'file':
                return fileItem(fileValue)
            case 'image':
                return imageItem(fileValue)
            case 'imageGallery':
                return imageItem(fileValue)
            case 'richText':
                return richTextItem(fileValue)
            default:
                // Convert object to string representation for display
                return JSON.stringify(recordValue)
        }
    }
    return recordValue
}
interface DetailInfoProps extends detailpropsType {
    onOpenRichText: (payload: { title: string, content: string }) => void;
}

const DetailInfo = ({ info, onOpenRichText }: DetailInfoProps) => {
    if (!info || !info.format_info || !info.raw_info) {
        return null;
    }

    const rawInfoMap = useMemo(() => {
        if (!info.raw_info) return {} as Record<string, any>;
        if (Array.isArray(info.raw_info)) {
            const map: Record<string, any> = {};
            info.raw_info.forEach((entry: any) => {
                if (entry && typeof entry === 'object') {
                    const key = entry.field ?? entry.key ?? entry.id;
                    if (key) {
                        map[key] = entry.value ?? entry.curValue ?? entry.content ?? '';
                    }
                }
            });
            return map;
        }
        if (typeof info.raw_info === 'object') {
            return info.raw_info as Record<string, any>;
        }
        return {} as Record<string, any>;
    }, [info.raw_info]);

    // 计算每项的 span：richText 占 2，普通占 1；若最后落单的普通项则扩为 2
    const computedItems = (() => {
        const res: Array<{ item: any; span: 1 | 2 }> = [];
        let lastSingleIndex: number | null = null;
        for (const it of info.format_info) {
            const isRT = it && it.curValue && typeof it.curValue === 'object' && it.curValue.type === 'richText';
            if (isRT) {
                // 之前若有落单普通项，先把它扩到 2
                if (lastSingleIndex != null) {
                    res[lastSingleIndex].span = 2;
                    lastSingleIndex = null;
                }
                res.push({ item: it, span: 2 });
            } else {
                if (lastSingleIndex == null) {
                    res.push({ item: it, span: 1 });
                    lastSingleIndex = res.length - 1;
                } else {
                    res.push({ item: it, span: 1 });
                    lastSingleIndex = null;
                }
            }
        }
        if (lastSingleIndex != null) {
            res[lastSingleIndex].span = 2;
        }
        return res;
    })();

    return (
        <div>
        <Descriptions
            size="small"
            column={2}
            bordered
            styles={{
                label: { color: '#666', fontWeight: 500, background: '#fafafa' },
                content: { whiteSpace: 'pre-wrap', background: '#fff' },
            }}
        >
            {computedItems.map(({ item, span }) => {
                const key = item.id ?? item.field ?? item.key ?? item.label;
                const isRichText = item.curValue && typeof item.curValue === 'object' && item.curValue.type === 'richText';
                if (isRichText) {
                    const rawHtml = typeof item.curValue.content === 'string' ? item.curValue.content : '';
                    const preview = (rawHtml.replace(/<[^>]+>/g, '') || '').slice(0, 120);
                    return (
                        <Descriptions.Item span={span} key={key} label={item.label}>
                            <Typography.Paragraph ellipsis={{ rows: 2 }} style={{ marginBottom: 4 }}>
                                {preview || '—'}
                            </Typography.Paragraph>
                            <Button size="small" type="link" onClick={() => onOpenRichText({ title: item.label, content: rawHtml })}>
                                查看详情
                            </Button>
                        </Descriptions.Item>
                    );
                }
                return (
                    <Descriptions.Item span={span} key={key} label={item.label}>
                        {formatDataValue(item.curValue)}
                    </Descriptions.Item>
                );
            })}
        </Descriptions>
        <Divider></Divider>
        <Descriptions
            size="small"
            column={2}
            bordered
            styles={{
                label: { width: 140, color: '#666', fontWeight: 500, background: '#fff' },
                content: { whiteSpace: 'pre-wrap', background: '#fff' },
            }}
        >
        {/* 'created_at', 'updated_at', 'deleted_at', 'updated_by', 'created_by', 'content_status' */}
            <Descriptions.Item key='created_at' label='创建时间'>
                {rawInfoMap.created_at || '—'}
            </Descriptions.Item>
            <Descriptions.Item key='created_by' label='创建人'>
                {rawInfoMap.created_by || '—'}
            </Descriptions.Item>
            <Descriptions.Item key='updated_at' label='修改时间'>
                {rawInfoMap.updated_at || '—'}
            </Descriptions.Item>
            <Descriptions.Item key='updated_by' label='修改人'>
                {rawInfoMap.updated_by || '—'}
            </Descriptions.Item>
            <Descriptions.Item key='content_status' label='状态'>
                <Tag color={statusColor(rawInfoMap.content_status)}>{rawInfoMap.content_status ? statusText(rawInfoMap.content_status) : '—'}</Tag>
            </Descriptions.Item>
        </Descriptions>
        </div>
    );
}

interface ColumnType {
    key: string,
    title: string,
    dataIndex?: string,
    fixed?: 'left' | 'right' | boolean,
    width?: number,
    render?: any,
    sorter?: boolean,
    sortDirections?: ('ascend' | 'descend' | null)[],
    sortOrder?: 'ascend' | 'descend' | null,
}

type SubjectPageReturnType = {
    allListTitle: Array<ColumnType>,
    dataSource: Array<ColumnType>,
    columns: Array<ColumnType>,
    moldId: string,
    listShowFields: any,
    filterShowFields?: any,
    schema?: any[],
    filters?: Record<string, any>,
    pagination?: {
        total: number;
        page: number;
        page_size: number;
        page_count: number;
    };
}


    // 状态标签颜色与文案
    const statusText = (s: string) => {
        switch (s) {
            case 'published': return '已发布';
            case 'disabled': return '已下线';
            case 'pending':
            default: return '待发布';
        }
    };
    const statusTextAction = (s: string) => {
        switch (s) {
            case 'published': return '发布';
            case 'disabled': return '下线';
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
    const statusTransitions = (s: string) => {
        if (s === 'published') return ['disabled'];
        if (s === 'pending') return ['published', 'disabled'];
        if (s === 'disabled') return ['published'];
        return [];
    };

const App: React.FC<SubjectPageReturnType> = ({ allListTitle, dataSource, columns, moldId, listShowFields, filterShowFields, schema, filters, pagination }) => {

    const [isShowFieldOpen, setIsShowFieldOpen] = useState(false);
    const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);
    const [richDrawer, setRichDrawer] = useState<{ open: boolean, title: string, content: string }>({ open: false, title: '', content: '' });

    const [aiModalOpen, setAiModalOpen] = useState(false);
    const [aiCount, setAiCount] = useState<number>(5);
    const [tableData, setTableData] = useState(dataSource);
    const [verDrawer, setVerDrawer] = useState<{ open: boolean, id: string | number | null }>({ open: false, id: null });
    const [verList, setVerList] = useState<any[]>([]);
    const [verLoading, setVerLoading] = useState(false);
    const [verDiffModal, setVerDiffModal] = useState<{ open: boolean, items: any[] }>({ open: false, items: [] });
    const latestVersion = verList && verList.length ? verList[0].version : null;
    const [moldFieldTypeMap, setMoldFieldTypeMap] = useState<Record<string, string>>({});

    // 排序状态管理
    const [sortField, setSortField] = useState<string | null>(null);
    const [sortOrder, setSortOrder] = useState<'ascend' | 'descend' | null>(null);

    const [filterForm] = Form.useForm();
    const [filterModalOpen, setFilterModalOpen] = useState(false);
    const [visibleFilterKeys, setVisibleFilterKeys] = useState<string[]>([]);
    const [dynamicOptionsMap, setDynamicOptionsMap] = useState<Record<string, Array<{ label: string; value: string }>>>({});

    // 从 URL 中读取排序状态
    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const sortParam = urlParams.get('sort');
        if (sortParam) {
            if (sortParam.startsWith('-')) {
                setSortField(sortParam.substring(1));
                setSortOrder('descend');
            } else {
                setSortField(sortParam);
                setSortOrder('ascend');
            }
        } else {
            setSortField(null);
            setSortOrder(null);
        }
    }, []);

    useEffect(() => {
        setTableData(dataSource);
    }, [dataSource]);

    const schemaArr = useMemo(() => Array.isArray(schema) ? schema : [], [schema]);

    const listShowFieldsArr = useMemo(() => {
        try {
            return listShowFields ? JSON.parse(listShowFields) : [];
        } catch {
            return [];
        }
    }, [listShowFields]);

    const filterShowFieldsArr = useMemo(() => {
        try {
            return filterShowFields ? JSON.parse(filterShowFields) : [];
        } catch {
            return [];
        }
    }, [filterShowFields]);

    const filterDefs = useMemo(() => {
        const defs: Array<{ key: string; label: string; type: string; raw: any; supported: boolean; defaultVisible: boolean; mode: 'text'|'single'|'multi'|'date_range'|'time_range'|'number' }> = [];

        const unsupportedTypes = new Set(['dividingLine', 'richText', 'picUpload', 'picGallery', 'fileUpload', 'colorPicker', 'cascader', 'dateRangePicker']);
        const defaultHiddenTypes = new Set(['textarea', 'slider', 'rate', 'tags']);

        const pushDef = (key: string, label: string, type: string, raw: any) => {
            const supported = !unsupportedTypes.has(type);
            let mode: any = 'text';
            if (type === 'input' || type === 'textarea') mode = 'text';
            else if (type === 'radio') mode = 'single';
            else if (type === 'select' || type === 'checkbox') mode = 'multi';
            else if (type === 'datePicker' || type === 'dateTimePicker' || type === 'dateRangePicker') mode = 'date_range';
            else if (type === 'timePicker') mode = 'time_range';
            else if (type === 'numInput' || type === 'slider' || type === 'rate') mode = 'number';
            else if (type === 'switch') mode = 'single';

            const defaultVisible = supported && !defaultHiddenTypes.has(type) && (listShowFieldsArr.length ? listShowFieldsArr.includes(key) : true);
            defs.push({ key, label, type, raw, supported, defaultVisible, mode });
        };

        schemaArr.forEach((f: any) => {
            const key = f?.field;
            const label = f?.label || f?.name || f?.field;
            const type = f?.type || 'input';
            if (!key) return;
            pushDef(String(key), String(label), String(type), f);
        });

        pushDef('id', 'ID', 'numInput', null);
        pushDef('content_status', '状态', 'radio', { options: [
            { label: '待发布', value: 'pending' },
            { label: '已发布', value: 'published' },
            { label: '已下线', value: 'disabled' },
        ] });
        pushDef('created_at', '创建时间', 'dateTimePicker', null);
        pushDef('updated_at', '修改时间', 'dateTimePicker', null);

        return defs;
    }, [schemaArr, listShowFieldsArr]);

    const filterOptionsForModal = useMemo(() => {
        return filterDefs
            .filter(d => d.supported)
            .map(d => ({ label: d.label, value: d.key }));
    }, [filterDefs]);

    useEffect(() => {
        let keys: string[] = [];

        // 1) 优先使用数据库配置
        if (Array.isArray(filterShowFieldsArr) && filterShowFieldsArr.length) {
            keys = filterShowFieldsArr.map(String);
        }

        // 2) 无配置则用默认展示规则
        if (!keys.length) {
            keys = filterDefs.filter(d => d.defaultVisible).map(d => d.key);
        }

        const activeFilterKeys = filters && typeof filters === 'object' ? Object.keys(filters) : [];
        if (activeFilterKeys.length) {
            const set = new Set([...keys, ...activeFilterKeys]);
            keys = Array.from(set);
        }

        setVisibleFilterKeys(keys);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [moldId, filterDefs.length, filters, filterShowFieldsArr]);

    const normalizeOptions = (raw: any): Array<{ label: string; value: string }> => {
        try {
            if (Array.isArray(raw)) {
                return raw.map((o: any) => {
                    if (o == null) return null;
                    if (typeof o === 'string' || typeof o === 'number' || typeof o === 'boolean') {
                        const s = String(o);
                        return { label: s, value: s };
                    }
                    if (typeof o === 'object') {
                        const label = o.label != null ? String(o.label) : (o.value != null ? String(o.value) : '');
                        const value = o.value != null ? String(o.value) : label;
                        if (!label) return null;
                        return { label, value };
                    }
                    return null;
                }).filter(Boolean) as any;
            }
            if (typeof raw === 'string' && raw.trim()) {
                return raw.split(',').map((s) => s.trim()).filter(Boolean).map((v) => ({ label: v, value: v }));
            }
            if (raw && typeof raw === 'object') {
                return Object.entries(raw).map(([k, v]) => ({ label: v != null ? String(v) : String(k), value: String(k) }));
            }
        } catch {}
        return [];
    };

    useEffect(() => {
        const rawFilters = (filters && typeof filters === 'object') ? filters : {};
        const init: Record<string, any> = {};

        Object.entries(rawFilters).forEach(([k, v]) => {
            if (v && typeof v === 'object' && 'op' in v) {
                const op = String((v as any).op || '');
                const val = (v as any).value;
                if (op === 'like' || op === 'like_prefix' || op === 'like_suffix') {
                    init[k] = typeof val === 'string' ? val.replace(/%/g, '') : '';
                } else if (op === 'in' || op === 'contains_any' || op === 'contains_all') {
                    if (Array.isArray(val)) init[k] = val;
                    else if (typeof val === 'string') init[k] = val.split(',').map((s: string) => s.trim()).filter(Boolean);
                } else if (op === 'between' || op === 'range') {
                    if (Array.isArray(val) && val.length >= 2) {
                        const a = val[0] ? dayjs(val[0]) : null;
                        const b = val[1] ? dayjs(val[1]) : null;
                        if (a && a.isValid() && b && b.isValid()) init[k] = [a, b];
                    }
                } else {
                    init[k] = val;
                }
            } else {
                init[k] = v;
            }
        });

        // 对 switch 类型的值进行转换，将布尔值或字符串转换为数字
        filterDefs.forEach((def) => {
            if (def.type === 'switch' && init[def.key] !== undefined) {
                const val = init[def.key];
                if (val === true || val === '1' || val === 1) {
                    init[def.key] = 1;
                } else if (val === false || val === '0' || val === 0) {
                    init[def.key] = 0;
                } else if (val === null || val === undefined || val === '') {
                    init[def.key] = '';
                }
            }
        });

        filterForm.setFieldsValue(init);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [moldId, filters]);

    useEffect(() => {
        const visibleSet = new Set(visibleFilterKeys);
        const need = filterDefs.filter(d => visibleSet.has(d.key) && d.supported && d.raw && d.raw.optionsSource === 'model' && d.raw.sourceModelId && d.raw.sourceFieldName);
        if (!need.length) return;

        need.forEach((d) => {
            if (dynamicOptionsMap[d.key]) return;
            api.post(CONTENT_ROUTES.fieldOptions(d.raw.sourceModelId), { field: d.raw.sourceFieldName })
                .then((res) => {
                    const payload = (res?.data && (res.data.data ?? res.data)) || [];
                    const arr = Array.isArray(payload) ? payload : [];
                    const opts = arr.map((it: any) => ({ label: String(it.label ?? it.value ?? ''), value: String(it.value ?? '') })).filter((x: any) => x.value);
                    setDynamicOptionsMap(prev => ({ ...prev, [d.key]: opts }));
                })
                .catch(() => {});
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [visibleFilterKeys, filterDefs]);

    const applyFilters = () => {
        const values = filterForm.getFieldsValue(true) as Record<string, any>;
        const next: Record<string, any> = {};
        const defMap: Record<string, any> = {};
        filterDefs.forEach((d) => { defMap[d.key] = d; });

        visibleFilterKeys.forEach((key) => {
            const def = defMap[key];
            if (!def || !def.supported) return;
            const v = values[key];
            if (v == null) return;
            if (typeof v === 'string' && v.trim() === '') return;
            if (Array.isArray(v) && v.length === 0) return;

            if (def.mode === 'text') {
                next[key] = { op: 'like_prefix', value: String(v) };
            } else if (def.mode === 'single' || def.mode === 'switch') {
                next[key] = { op: 'eq', value: v };
            } else if (def.mode === 'multi') {
                next[key] = { op: 'contains_any', value: Array.isArray(v) ? v : [v] };
            } else if (def.mode === 'date_range') {
                const a = Array.isArray(v) ? v[0] : null;
                const b = Array.isArray(v) ? v[1] : null;
                if (a && b) {
                    const fmt = def.type === 'datePicker' ? 'YYYY-MM-DD' : 'YYYY-MM-DD HH:mm:ss';
                    next[key] = { op: 'between', value: [dayjs(a).format(fmt), dayjs(b).format(fmt)] };
                }
            } else if (def.mode === 'time_range') {
                const a = Array.isArray(v) ? v[0] : null;
                const b = Array.isArray(v) ? v[1] : null;
                if (a && b) {
                    next[key] = { op: 'between', value: [dayjs(a).format('HH:mm:ss'), dayjs(b).format('HH:mm:ss')] };
                }
            } else if (def.mode === 'number') {
                next[key] = { op: 'eq', value: v };
            }
        });

        router.visit(CONTENT_ROUTES.list(moldId), {
            method: 'get',
            data: { filters: next },
            preserveState: true,
            preserveScroll: true,
        });
    };

    const resetFilters = () => {
        filterForm.resetFields();
        router.visit(CONTENT_ROUTES.list(moldId), {
            method: 'get',
            data: { filters: {} },
            preserveState: true,
            preserveScroll: true,
        });
    };

    const saveVisibleFilters = (keys: string[]) => {
        setVisibleFilterKeys(keys);
        api.post(MOLD_ROUTES.update(moldId), { filter_show_fields: JSON.stringify(keys) })
            .then(() => {
                message.success('保存成功');
            })
            .catch((e) => {
                message.error(e?.message || '保存失败');
            });
    };

    // 移除项目级 AI 模型读取，后端将按系统级默认模型处理

    useEffect(() => {
        api.get(`/mold/builder/${moldId}/fields`)
            .then((res) => {
                const payload = (res?.data?.fields ?? res?.data?.data?.fields) || [];
                const map: Record<string, string> = {};
                (payload as any[]).forEach((f: any) => { if (f && f.field) map[f.field] = f.type || 'input'; });
                setMoldFieldTypeMap(map);
            })
            .catch(() => {});
    }, [moldId]);

    const renderDiffValue = (field: string, val: any) => {
        const t = (moldFieldTypeMap as any)[field];
        try {
            if (t === 'picUpload') {
                // 单图：字符串URL
                const url = typeof val === 'string' ? val : '';
                if (url) return <img src={url} alt="img" width={60} height={60} style={{ objectFit: 'cover', borderRadius: 4, border: '1px solid #f0f0f0' }} />;
                return <span>（空）</span>;
            }
            if (t === 'picGallery') {
                // 多图：后端返回 {type: "imageGallery", content: [...]}
                let items: any[] = [];
                if (val && typeof val === 'object' && val.type === 'imageGallery') {
                    items = Array.isArray(val.content) ? val.content : [];
                } else if (Array.isArray(val)) {
                    items = val;
                } else if (typeof val === 'string' && val) {
                    try { const p = JSON.parse(val); items = Array.isArray(p) ? p : []; } catch { items = []; }
                }
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

    let checkedField = listShowFieldsArr
    const handleShowFieldsCheck = (value: Array<CheckboxValueType>) => {
        checkedField = value
    }

    const handleShowFieldsChange = () => {
        setIsShowFieldOpen(true)

        api.post(MOLD_ROUTES.update(moldId), { list_show_fields: JSON.stringify(checkedField) })
            .then(function (response) {
                message.success('操作成功', 1, onclose = () => window.location.reload())
            })
            .catch(function (error) {
                message.error('操作失败' + error);
            });

    }

    const values = allListTitle.map((item) => {
        let res = {
            'value': item.dataIndex as string,
            'label': item.title as string,
        }
        return res
    })

    const renderFilterItem = (key: string) => {
        const def = filterDefs.find((d) => d.key === key);
        if (!def || !def.supported) return null;

        const commonStyle = { width: 220 } as any;

        if (def.mode === 'text') {
            return (
                <Form.Item key={key} name={key} label={def.label}>
                    <Input allowClear placeholder="请输入" style={commonStyle} />
                </Form.Item>
            );
        }

        if (def.mode === 'number') {
            return (
                <Form.Item key={key} name={key} label={def.label}>
                    <InputNumber style={commonStyle} />
                </Form.Item>
            );
        }

        if (def.mode === 'date_range') {
            const showTime = def.type === 'dateTimePicker';
            return (
                <Form.Item key={key} name={key} label={def.label}>
                    <DatePicker.RangePicker showTime={showTime} style={commonStyle} />
                </Form.Item>
            );
        }

        if (def.mode === 'time_range') {
            const RangePicker = (TimePicker as any).RangePicker;
            return (
                <Form.Item key={key} name={key} label={def.label}>
                    {RangePicker ? <RangePicker style={commonStyle} /> : <TimePicker style={commonStyle} />}
                </Form.Item>
            );
        }

        // switch 类型使用特殊的下拉框，包含三个选项：不限、是、否
        if (def.type === 'switch') {
            const switchOptions = [
                { label: '不限', value: '' },
                { label: '是', value: 1 },
                { label: '否', value: 0 },
            ];
            return (
                <Form.Item key={key} name={key} label={def.label}>
                    <Select
                        allowClear
                        options={switchOptions}
                        style={commonStyle}
                        placeholder="请选择"
                    />
                </Form.Item>
            );
        }

        const staticOptions = normalizeOptions(def.raw?.options);
        const options = (def.raw && def.raw.optionsSource === 'model' && dynamicOptionsMap[def.key])
            ? dynamicOptionsMap[def.key]
            : staticOptions;

        if (def.mode === 'multi') {
            return (
                <Form.Item key={key} name={key} label={def.label}>
                    <Select
                        mode="multiple"
                        allowClear
                        showSearch
                        optionFilterProp="label"
                        options={options}
                        style={commonStyle}
                        placeholder="请选择"
                    />
                </Form.Item>
            );
        }

        return (
            <Form.Item key={key} name={key} label={def.label}>
                <Select
                    allowClear
                    showSearch
                    optionFilterProp="label"
                    options={options}
                    style={commonStyle}
                    placeholder="请选择"
                />
            </Form.Item>
        );
    };

    const handelDelete = (id: string | number) => {

        Modal.confirm({
            title: '确定删除？',
            icon: <ExclamationCircleFilled />,
            okText: '确定',
            okType: 'danger',
            cancelText: '取消',
            maskClosable: true,
            onOk() {
                router.post(CONTENT_ROUTES.delete(moldId, id), {}, {
                    onSuccess: () => {
                        message.success('删除成功', 1)
                    },
                    onError: (errors) => {
                        message.error('删除失败' + errors.message);
                    }
                })

            }
        })
    }


    const [detailList, setDetailList] = useState<DetailInfoData | null>(null);

    const handelDetail = (id: string | number) => {

        api.post(CONTENT_ROUTES.detail(moldId, id))
            .then(function (response) {
                setDetailList(response.data)
                setIsDetailModalOpen(true);
            })

            .catch(function (error) {
                message.error('操作失败' + error);
            });
    }

    const openVersions = (id: string | number) => {
        setVerDrawer({ open: true, id });
        setVerLoading(true);
        api.get(`/content/versions/${moldId}/${id}`)
            .then((res) => {
                const rows = (res?.data?.data ?? res?.data) || [];
                setVerList(Array.isArray(rows) ? rows : []);
            })
            .catch((e: any) => {
                message.error(e?.message || '加载版本失败');
            })
            .finally(() => {
                setVerLoading(false);
            });
    };

    const doRollback = (version: number) => {
        if (!verDrawer.id) return;
        Modal.confirm({
            title: `回滚到版本 ${version}？`,
            onOk: () => {
                return api.post(`/content/versions/rollback/${moldId}/${verDrawer.id}/${version}`, {})
                    .then((resp) => {
                        const respMsg = (resp as any)?.message ?? (resp as any)?.msg;
                        message.success(respMsg || '回滚成功');
                        return api.get(`/content/versions/${moldId}/${verDrawer.id}`);
                    })
                    .then((res) => {
                        const rows = (res?.data?.data ?? res?.data) || [];
                        setVerList(Array.isArray(rows) ? rows : []);
                    })
                    .catch((e: any) => {
                        message.error(e?.message || '回滚失败');
                    });
            }
        });
    };

    const makeDiffAgainstLatest = (version: number) => {
        if (!verDrawer.id || !version) return;
        if (!latestVersion) {
            message.warning('暂无最新版本');
            return;
        }
        if (version === latestVersion) {
            message.info('已是最新版本');
            return;
        }
        api.get(`/content/versions/diff`, { params: { mold_id: moldId, id: verDrawer.id, v1: version, v2: latestVersion } })
            .then((res) => {
                const items = (res?.data?.data ?? res?.data) || [];
                setVerDiffModal({ open: true, items: Array.isArray(items) ? items : [] });
            })
            .catch((e: any) => {
                message.error(e?.message || '对比失败');
            });
    };

    const updateStatus = (id: string | number, next: string) => {
        let url = '';
        if (next === 'published') url = `/content/publish/${moldId}/${id}`;
        else if (next === 'disabled') url = `/content/unpublish/${moldId}/${id}`;
        else return;

        api.post(url, {})
            .then(() => {
                setTableData((prev: any[]) => prev.map((row) => {
                    if (String(row.key) === String(id)) {
                        return { ...row, content_status: next };
                    }
                    return row;
                }));
                message.success('状态已更新');
            })
            .catch((e: any) => {
                message.error(e?.message || '状态更新失败');
            });
    };
    let renderCol = JSON.parse(JSON.stringify(columns))

    renderCol = renderCol.map((item: ColumnType) => {
        item.width = 150
        
        // 为所有可排序的列添加 sorter 属性
        if (item.dataIndex && item.dataIndex !== 'action' && item.dataIndex !== 'content_status') {
            item.sorter = true;
            item.sortDirections = ['ascend', 'descend', null]; // 支持升序、降序、取消排序
            
            // 设置当前列的排序状态
            if (sortField === item.dataIndex) {
                item.sortOrder = sortOrder;
            } else {
                item.sortOrder = null;
            }
        }
        
        if (item.dataIndex == 'id') {
            item.width = 50
            item.fixed = "left"
        }
        if (item.dataIndex == 'content_status') {
            item.width = 100
            item.fixed = "right"
            item.title = '状态'
            item.render = (recordValue: string, row: any) => {
                const items = statusTransitions(recordValue).map((key) => ({ key, label: statusTextAction(key) }));
                return (
                    <Dropdown
                        menu={{ items, onClick: ({ key }) => updateStatus(row.key, key as string) }}
                        disabled={items.length === 0}
                        trigger={["click"]}
                    >
                        <a onClick={(e) => e.preventDefault()} style={{ textDecoration: 'none' }}>
                            <Tag color={statusColor(recordValue)} style={{ cursor: items.length ? 'pointer' : 'default' }}>
                                {statusText(recordValue)}<DownOutlined style={{ fontSize: 10, marginLeft: 4 }} />
                            </Tag>
                        </a>
                    </Dropdown>
                )
            }
            return item
        }

        // switch 类型的字段特殊处理：1显示为"是"，0显示为"否"
        // 从 schema 中查找字段类型
        const fieldSchema = schemaArr.find((f: any) => f.field === item.dataIndex);
        if (fieldSchema && fieldSchema.type === 'switch') {
            item.render = (recordValue: any) => {
                if (recordValue === true || recordValue === '1' || recordValue === 1) {
                    return '是';
                } else if (recordValue === false || recordValue === '0' || recordValue === 0) {
                    return '否';
                }
                return formatDataValue(recordValue);
            }
        } else {
            item.render = (recordValue:Object|string) => {
                return formatDataValue(recordValue)
            }
        }

        return item
    })


    renderCol = renderCol.concat([
        {
            title: 'Action',
            key: 'action',
            fixed: 'right',
            width: 200,
            render: (_: any, record: any) => {
                const cur = (record.content_status as string) || 'pending';
                return (
                    <Space size="middle">
                        {/* 状态显示固定在左侧 */}
                        <Link href={CONTENT_ROUTES.edit(moldId, record.key)}>
                            修改
                        </Link>
                        <a onClick={() => handelDetail(record.key)}>详情</a>
                        <a onClick={() => openVersions(record.key)}>历史</a>
                        <a style={{ color: 'red' }} onClick={() => handelDelete(record.key)}>删除</a>
                    </Space>
                );
            },
        },
    ])


    // 选择与批量操作
    const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);
    const rowSelection = {
        selectedRowKeys,
        onChange: (keys: React.Key[]) => setSelectedRowKeys(keys),
    };

    const handleTableChange = (pagination: any, tableFilters: any, sorter: any) => {
        const params: any = {
            page: pagination.current,
            page_size: pagination.pageSize,
        };
        
        // 保留 filters 参数
        if (filters && Object.keys(filters).length > 0) {
            params.filters = filters;
        }
        
        // 保留 sort 参数
        if (sorter && (sorter.field || sorter.column?.dataIndex)) {
            const field = sorter.field || sorter.column?.dataIndex;
            // 只有在有排序顺序时才添加 sort 参数
            if (sorter.order) {
                const sortOrder = sorter.order === 'descend' ? '-' : '';
                params.sort = sortOrder + field;
                // 更新排序状态
                setSortField(field);
                setSortOrder(sorter.order);
            } else {
                // 如果没有排序顺序（取消排序），不添加 sort 参数
                // 清除排序状态
                setSortField(null);
                setSortOrder(null);
            }
        }
        
        router.visit(CONTENT_ROUTES.list(moldId), {
            method: 'get',
            data: params,
            preserveScroll: true,
        });
    };

    const confirmBatch = (title: string, onOk: () => void) => {
        Modal.confirm({
            title,
            icon: <ExclamationCircleFilled />,
            okText: '确定',
            cancelText: '取消',
            onOk,
        });
    };

    const batchPublish = async () => {
        if (!selectedRowKeys.length) return;
        const selectedKeySet = new Set(selectedRowKeys.map((key) => String(key)));
        try {
            api.post(`/content/publish-batch/${moldId}`, { ids: selectedRowKeys })
            .then(function (response) {
                let failed_ids = response.data.failed_ids || [];
                setTableData((prev: any[]) => prev.map(
                    (row) => (selectedKeySet.has(String(row.key))&& !failed_ids.includes((row.key)))
                    ? { ...row, content_status: 'published' }
                    : row
                ));
                setSelectedRowKeys([]);
                if (failed_ids.length > 0) {
                    message.error(`有${failed_ids.length}条记录上架失败，ID:${failed_ids.join(',')}`);
                } else {
                    message.success('批量上架完成');
                }
            })
        } catch (e: any) {
            message.error(e?.message || '批量上架失败');
        }
    };

    const batchUnpublish = async () => {
        if (!selectedRowKeys.length) return;
        const selectedKeySet = new Set(selectedRowKeys.map((key) => String(key)));
        try {
            api.post(`/content/unpublish-batch/${moldId}`, { ids: selectedRowKeys })
            .then(function (response) {
                let failed_ids = response.data.failed_ids || [];
                setTableData((prev: any[]) => prev.map(
                    (row) => (selectedKeySet.has(String(row.key))&& !failed_ids.includes((row.key)))
                    ? { ...row, content_status: 'disabled' }
                    : row
                ));
                setSelectedRowKeys([]);
                if (failed_ids.length > 0) {
                    message.error(`有${failed_ids.length}条记录下架失败，ID:${failed_ids.join(',')}`);
                } else {
                    message.success('批量下架完成');
                }
            })
        } catch (e: any) {
            message.error(e?.message || '批量下架失败');
        }
    };

    const batchDelete = async () => {
        if (!selectedRowKeys.length) return;
        const selectedKeySet = new Set(selectedRowKeys.map((key) => String(key)));
        try {
            api.post(`/content/delete-batch/${moldId}`, { ids: selectedRowKeys })
            .then(function (response) {
                let failed_ids = response.data.failed_ids || [];
                setTableData((prev: any[]) => prev.filter(
                    (row) => !selectedKeySet.has(String(row.key)) || failed_ids.includes((row.key))
                ));
                setSelectedRowKeys([]);
                if (failed_ids.length > 0) {
                    message.error(`有${failed_ids.length}条记录删除失败，ID:${failed_ids.join(',')}`);
                } else {
                    message.success('批量删除完成');
                }
            })
        } catch (e: any) {
            message.error(e?.message || '批量删除失败');
        }
    };

    return (
   <>
            {/* 按钮区域 */}
            <div style={{ marginBottom: 20 }}>
                <Link href={CONTENT_ROUTES.add(moldId)}>
                    <Button type="primary">添加</Button>
                </Link>
                <Button style={{ marginLeft: 20 }} type="primary" onClick={() => setIsShowFieldOpen(true)}>
                    列表展示字段
                </Button>
                <Button style={{ marginLeft: 20 }} onClick={() => setFilterModalOpen(true)}>
                    筛选项展示字段
                </Button>

                <AiButton
                    onClick={() => setAiModalOpen(true)}
                    style={{ marginLeft: 20 }}
                    text="AI 批量生成"
                />

                <Button
                    style={{ marginLeft: 20 }}
                    disabled={!selectedRowKeys.length}
                    onClick={() => confirmBatch('确定批量上架选中的内容？', batchPublish)}
                >
                    批量上架
                </Button>

                <Button
                    style={{ marginLeft: 12 }}
                    danger
                    disabled={!selectedRowKeys.length}
                    onClick={() => confirmBatch('确定批量下架选中的内容？', batchUnpublish)}
                >
                    批量下架
                </Button>
                <Button
                    style={{ marginLeft: 12 }}
                    danger
                    disabled={!selectedRowKeys.length}
                    onClick={() => confirmBatch('确定批量删除选中的内容？', batchDelete)}
                >
                    批量删除
                </Button>
            </div>

            <div style={{ marginBottom: 16, padding: 12, background: '#fafafa', borderRadius: 6 }}>
                <Form form={filterForm} layout="inline">
                    <Space wrap>
                        {visibleFilterKeys.map((k) => renderFilterItem(k))}
                        <Space>
                            <Button type="primary" onClick={applyFilters}>筛选</Button>
                            <Button onClick={resetFilters}>重置</Button>
                        </Space>
                    </Space>
                </Form>
            </div>

            <AiGenerateModal
                open={aiModalOpen}
                onOpenChange={setAiModalOpen}
                title="AI 批量生成内容"
                description="输入提示词和生成数量，系统将自动生成多条内容数据。当前为模拟数据。"
                promptPlaceholder="例如：生成5条关于新品发布的内容"
                hideModelSelect={true}
                extraContent={(
                    <div style={{ marginBottom: 12 }}>
                        <div style={{ marginBottom: 6, fontWeight: 500 }}>生成数量</div>
                        <InputNumber
                            min={1}
                            max={20}
                            value={aiCount}
                            onChange={(val) => setAiCount(Number(val) || 1)}
                        />
                        <div style={{ marginTop: 4, color: '#999' }}>最多支持一次生成 20 条内容。</div>
                    </div>
                )}
                onConfirm={({ prompt, model }) => {
                    const count = Math.min(Math.max(aiCount || 1, 1), 20);
                    return api.post(`/content/ai/generate-batch-start/${moldId}`, {
                        prompt,
                        count,
                    })
                        .then((res) => {
                            const info = res?.data ?? res;
                            const taskId = info?.task_id ?? info?.taskId ?? info?.data?.task_id;
                            if (!taskId) throw new Error('任务创建失败');
                            return taskId;
                        });
                }}
                onResult={(result) => {
                    const total = result?.total ?? 0;
                    const done = result?.done ?? 0;
                    const failed = result?.failed ?? 0;
                    message.success(`批量生成完成：成功 ${done}/${total}，失败 ${failed}`);
                    setTimeout(() => window.location.reload(), 600);
                }}
                okText="开始生成"
            />


            <Modal
                title="列表展示字段"
                centered
                open={isShowFieldOpen}
                onOk={() => handleShowFieldsChange()}
                onCancel={() => setIsShowFieldOpen(false)}
            >

                <Checkbox.Group onChange={handleShowFieldsCheck} defaultValue={listShowFieldsArr} options={values} />
            </Modal>

            <Modal
                title="筛选项展示字段"
                centered
                open={filterModalOpen}
                onOk={() => setFilterModalOpen(false)}
                onCancel={() => setFilterModalOpen(false)}
            >
                <Checkbox.Group
                    value={visibleFilterKeys}
                    options={filterOptionsForModal}
                    onChange={(vals) => saveVisibleFilters((vals as any[]).map(String))}
                />
            </Modal>


            <Modal
                open={isDetailModalOpen}
                onOk={() => setIsDetailModalOpen(false)}
                onCancel={() => setIsDetailModalOpen(false)}
                footer={null}
                width={720}
            >
                <DetailInfo
                    info={detailList}
                    onOpenRichText={({ title, content }) => setRichDrawer({ open: true, title, content })}
                />
            </Modal>

            <Table
                rowSelection={{
                    ...rowSelection,
                }}
                pagination={{
                    pageSize: pagination?.page_size || 15,
                    total: pagination?.total || dataSource.length,
                    current: pagination?.page || 1,
                    showSizeChanger: true,
                    showQuickJumper: true,
                    showTotal: (total) => `共 ${total} 条`,
                }}
                onChange={handleTableChange}
                columns={renderCol}
                dataSource={tableData}
                scroll={{ x: 1020 }}
            />

            <Drawer
                title={richDrawer.title || '富文本详情'}
                placement="right"
                open={richDrawer.open}
                onClose={() => setRichDrawer({ open: false, title: '', content: '' })}
                width={720}
                destroyOnClose
                styles={{ body: { padding: 0 } }}
            >
                <div style={{ padding: 24, height: '100%', overflow: 'auto' }}>
                    {richDrawer.content
                        ? <div dangerouslySetInnerHTML={{ __html: richDrawer.content }} />
                        : <Typography.Paragraph type="secondary">暂无内容</Typography.Paragraph>
                    }
                </div>
            </Drawer>

            <Drawer
                title="版本历史"
                placement="right"
                open={verDrawer.open}
                onClose={() => { setVerDrawer({ open: false, id: null }); }}
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
                            title: '操作', key: 'op', width: 200,
                            render: (_: any, r: any) => (
                                <Space>
                                    {latestVersion && r.version === latestVersion ? (
                                        <Typography.Text type="secondary">已是最新版本</Typography.Text>
                                    ) : (
                                        <a onClick={() => makeDiffAgainstLatest(r.version)}>对比</a>
                                    )}
                                    <a onClick={() => doRollback(r.version)} style={{ color: 'red' }}>回滚</a>
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
        </>
    )
}


export default App;