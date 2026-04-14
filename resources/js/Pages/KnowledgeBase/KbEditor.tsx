import React, { useEffect, useState, useRef, useCallback, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Layout, Button, Input, Select, Space, Tag, Form, message,
    Typography, Spin, Drawer, Tooltip, Anchor
} from 'antd';
import {
    ArrowLeftOutlined, SaveOutlined,
    SettingOutlined, BookOutlined, UnorderedListOutlined
} from '@ant-design/icons';
import api from '../../util/Service';
import { KB_ROUTES } from '../../Constants/routes';
import MilkdownEditor from './components/MilkdownEditor';

interface KbEditorProps {
    articleId?: number;
}

export default function KbEditor({ articleId }: KbEditorProps) {
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [title, setTitle] = useState('');
    const [content, setContent] = useState('');
    const [categoryId, setCategoryId] = useState<number | null>(null);
    const [status, setStatus] = useState('private');
    const [tags, setTags] = useState<string[]>([]);
    const [summary, setSummary] = useState('');
    const [categories, setCategories] = useState<any[]>([]);
    const [settingsOpen, setSettingsOpen] = useState(false);
    const [tagInput, setTagInput] = useState('');
    const [currentId, setCurrentId] = useState<number | undefined>(articleId);

    // 从 URL 参数读取默认分类
    useEffect(() => {
        if (!articleId) {
            const params = new URLSearchParams(window.location.search);
            const catId = params.get('category_id');
            if (catId) setCategoryId(Number(catId));
        }
    }, [articleId]);

    // 加载分类
    useEffect(() => {
        api.get(KB_ROUTES.categoryTree).then((res: any) => {
            const tree = res.data?.tree || [];
            setCategories(flattenCategories(tree));
        }).catch(() => {});
    }, []);

    // 加载文章
    useEffect(() => {
        if (articleId) {
            setLoading(true);
            api.get(KB_ROUTES.articleDetail(articleId)).then((res: any) => {
                const data = res.data || {};
                setTitle(data.title || '');
                setContent(data.content || '');
                setCategoryId(data.category_id);
                setStatus(data.status || 'draft');
                setTags(data.tags || []);
                setSummary(data.summary || '');
                setCurrentId(data.id);
            }).catch((e: any) => {
                message.error(e.message || '加载文章失败');
            }).finally(() => setLoading(false));
        }
    }, [articleId]);

    const flattenCategories = (tree: any[], depth = 0): any[] => {
        const result: any[] = [];
        tree.forEach((item: any) => {
            result.push({
                label: '　'.repeat(depth) + item.title,
                value: item.id,
            });
            if (item.children?.length) {
                result.push(...flattenCategories(item.children, depth + 1));
            }
        });
        return result;
    };

    const handleSave = async () => {
        if (!title.trim()) {
            message.warning('请输入文章标题');
            return;
        }

        setSaving(true);
        try {
            const payload: any = {
                title: title.trim(),
                content,
                category_id: categoryId,
                tags,
                summary: summary.trim(),
                status,
            };

            let res: any;
            if (currentId) {
                res = await api.post(KB_ROUTES.updateArticle(currentId), payload);
                message.success('保存成功');
            } else {
                res = await api.post(KB_ROUTES.createArticle, payload);
                message.success('创建成功');
                const newId = res.data?.id;
                if (newId) {
                    setCurrentId(newId);
                    window.history.replaceState({}, '', KB_ROUTES.editor(newId));
                }
            }

            // 保存成功后跳转回列表页
            router.visit(KB_ROUTES.index);
        } catch (e: any) {
            message.error(e.message || '保存失败');
        } finally {
            setSaving(false);
        }
    };

    const handleAddTag = () => {
        const t = tagInput.trim();
        if (t && !tags.includes(t)) {
            setTags([...tags, t]);
        }
        setTagInput('');
    };

    const handleRemoveTag = (tag: string) => {
        setTags(tags.filter(t => t !== tag));
    };

    const handleContentChange = useCallback((markdown: string) => {
        setContent(markdown);
    }, []);

    // 从 markdown 内容提取标题生成目录
    const tocItems = useMemo(() => {
        if (!content) return [];
        const lines = content.split('\n');
        const items: { level: number; text: string; id: string }[] = [];
        let inCodeBlock = false;
        lines.forEach((line) => {
            if (line.trim().startsWith('```')) {
                inCodeBlock = !inCodeBlock;
                return;
            }
            if (inCodeBlock) return;
            const match = line.match(/^(#{1,3})\s+(.+)$/);
            if (match) {
                const level = match[1].length;
                const text = match[2].trim();
                const id = text.replace(/[^\w\u4e00-\u9fa5]/g, '-').toLowerCase();
                items.push({ level, text, id });
            }
        });
        return items;
    }, [content]);

    const [tocVisible, setTocVisible] = useState(true);

    const scrollToHeading = (text: string) => {
        const editor = document.querySelector('.milkdown-editor-wrapper');
        if (!editor) return;
        const headings = editor.querySelectorAll('h1, h2, h3');
        for (const h of headings) {
            if (h.textContent?.trim() === text) {
                h.scrollIntoView({ behavior: 'smooth', block: 'start' });
                break;
            }
        }
    };

    return (
        <>
            <Head title={currentId ? `编辑 - ${title}` : '新建文章'} />
            <Layout style={{ minHeight: '100vh', background: '#f5f5f5' }}>
                {/* 顶部导航 */}
                <Layout.Header style={{
                    background: '#fff', padding: '0 24px', display: 'flex',
                    alignItems: 'center', justifyContent: 'space-between',
                    borderBottom: '1px solid #f0f0f0', height: 56,
                }}>
                    <Space>
                        <Link href={KB_ROUTES.index}>
                            <Button type="text" icon={<ArrowLeftOutlined />} />
                        </Link>
                        <BookOutlined style={{ fontSize: 18, color: '#1890ff' }} />
                        <Typography.Text style={{ fontSize: 15 }}>
                            {currentId ? '编辑文章' : '新建文章'}
                        </Typography.Text>
                    </Space>
                    <Space>
                        <Tooltip title="文章设置">
                            <Button icon={<SettingOutlined />} onClick={() => setSettingsOpen(true)} />
                        </Tooltip>
                        <Button type="primary" icon={<SaveOutlined />} loading={saving} onClick={handleSave}>
                            保存
                        </Button>
                    </Space>
                </Layout.Header>

                {/* 编辑器主体 */}
                <Spin spinning={loading}>
                    <div style={{ display: 'flex', maxWidth: 1200, margin: '0 auto', padding: '24px 24px 80px', gap: 24 }}>
                        {/* 左侧目录 */}
                        {tocVisible && tocItems.length > 0 && (
                            <div style={{
                                width: 200, flexShrink: 0, position: 'sticky', top: 80,
                                alignSelf: 'flex-start', maxHeight: 'calc(100vh - 120px)', overflowY: 'auto',
                                background: '#fff', borderRadius: 8, border: '1px solid #f0f0f0', padding: '12px 0',
                            }}>
                                <div style={{ padding: '0 12px 8px', fontSize: 13, fontWeight: 600, color: '#1e293b', borderBottom: '1px solid #f0f0f0' }}>
                                    目录
                                </div>
                                <div style={{ padding: '8px 0' }}>
                                    {tocItems.map((item, i) => (
                                        <div
                                            key={i}
                                            onClick={() => scrollToHeading(item.text)}
                                            style={{
                                                padding: '4px 12px',
                                                paddingLeft: 12 + (item.level - 1) * 12,
                                                fontSize: 12,
                                                color: '#64748b',
                                                cursor: 'pointer',
                                                whiteSpace: 'nowrap',
                                                overflow: 'hidden',
                                                textOverflow: 'ellipsis',
                                                lineHeight: '24px',
                                            }}
                                            onMouseEnter={(e) => { (e.target as HTMLElement).style.color = '#2563eb'; (e.target as HTMLElement).style.background = '#f1f5f9'; }}
                                            onMouseLeave={(e) => { (e.target as HTMLElement).style.color = '#64748b'; (e.target as HTMLElement).style.background = 'transparent'; }}
                                        >
                                            {item.text}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* 编辑器区域 */}
                        <div style={{ flex: 1, minWidth: 0 }}>
                            {/* 标题输入 */}
                            <Input
                                value={title}
                                onChange={(e) => setTitle(e.target.value)}
                                placeholder="请输入文章标题..."
                                bordered={false}
                                style={{
                                    fontSize: 28, fontWeight: 700, padding: '8px 0',
                                    marginBottom: 16,
                                }}
                            />

                            {/* Milkdown 编辑器 */}
                            <div style={{
                                minHeight: 500,
                                background: '#fff',
                                borderRadius: 8,
                                border: '1px solid #f0f0f0',
                                position: 'relative',
                            }}>
                                {!loading && (
                                    <MilkdownEditor
                                        value={content}
                                        onChange={handleContentChange}
                                    />
                                )}
                            </div>
                        </div>
                    </div>
                </Spin>
            </Layout>

            {/* 文章设置抽屉 */}
            <Drawer
                title="文章设置"
                open={settingsOpen}
                onClose={() => setSettingsOpen(false)}
                width={360}
            >
                <Form layout="vertical">
                    <Form.Item label="分类">
                        <Select
                            value={categoryId}
                            onChange={(v) => setCategoryId(v)}
                            placeholder="选择分类"
                            allowClear
                            options={categories}
                            style={{ width: '100%' }}
                        />
                    </Form.Item>
                    <Form.Item label="摘要">
                        <Input.TextArea
                            value={summary}
                            onChange={(e) => setSummary(e.target.value)}
                            placeholder="文章摘要（可选）"
                            rows={3}
                        />
                    </Form.Item>
                    <Form.Item label="标签">
                        <Space wrap style={{ marginBottom: 8 }}>
                            {tags.map(t => (
                                <Tag key={t} closable onClose={() => handleRemoveTag(t)}>{t}</Tag>
                            ))}
                        </Space>
                        <Input
                            value={tagInput}
                            onChange={(e) => setTagInput(e.target.value)}
                            onPressEnter={handleAddTag}
                            placeholder="输入标签后按回车"
                            suffix={
                                <Button type="text" size="small" onClick={handleAddTag}>添加</Button>
                            }
                        />
                    </Form.Item>
                    <Form.Item label="状态">
                        <Select
                            value={status}
                            onChange={(v) => setStatus(v)}
                            options={[
                                { label: '私有', value: 'private' },
                                { label: '公开', value: 'public' },
                            ]}
                        />
                    </Form.Item>
                </Form>
            </Drawer>
        </>
    );
}
