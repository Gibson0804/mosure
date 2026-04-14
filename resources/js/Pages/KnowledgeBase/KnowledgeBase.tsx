import React, { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Layout, Card, Table, Button, Input, Select, Space, Tag, Tree, Modal,
    Form, message, Dropdown, Popconfirm, Typography, Empty, Tooltip
} from 'antd';
import {
    PlusOutlined, EditOutlined, DeleteOutlined, SearchOutlined,
    FolderOutlined, FolderAddOutlined, FileTextOutlined,
    EyeOutlined, ArrowLeftOutlined, BookOutlined, LinkOutlined
} from '@ant-design/icons';
import api from '../../util/Service';
import { KB_ROUTES } from '../../Constants/routes';

const { Sider, Content } = Layout;
const { Search } = Input;

interface Category {
    id: number;
    title: string;
    parent_id: number | null;
    children?: Category[];
}

interface Article {
    id: number;
    category_id: number | null;
    title: string;
    slug: string;
    summary: string;
    tags: string[];
    status: string;
    view_count: number;
    created_at: string;
    updated_at: string;
}

export default function KnowledgeBase() {
    const [categories, setCategories] = useState<Category[]>([]);
    const [articles, setArticles] = useState<Article[]>([]);
    const [total, setTotal] = useState(0);
    const [loading, setLoading] = useState(false);
    const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
    const [keyword, setKeyword] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('');
    const [tagFilter, setTagFilter] = useState<string>('');
    const [allTags, setAllTags] = useState<string[]>([]);
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(20);

    // 分类弹窗
    const [categoryModalOpen, setCategoryModalOpen] = useState(false);
    const [editingCategory, setEditingCategory] = useState<Category | null>(null);
    const [categoryForm] = Form.useForm();

    // 加载分类树
    const loadCategories = async () => {
        try {
            const res: any = await api.get(KB_ROUTES.categoryTree);
            setCategories(res.data?.tree || []);
        } catch (e: any) {
            message.error(e.message || '加载分类失败');
        }
    };

    // 加载文章列表
    const loadArticles = async (p?: number, overrides?: { keyword?: string; tag?: string }) => {
        setLoading(true);
        try {
            const params: any = {
                page: p || page,
                page_size: pageSize,
            };
            if (selectedCategoryId) params.category_id = selectedCategoryId;
            const kw = overrides?.keyword !== undefined ? overrides.keyword : keyword;
            if (kw) params.keyword = kw;
            if (statusFilter) params.status = statusFilter;
            const tag = overrides?.tag !== undefined ? overrides.tag : tagFilter;
            if (tag) params.tag = tag;

            const res: any = await api.get(KB_ROUTES.articleList, { params });
            const items = res.data?.items || [];
            setArticles(items);
            setTotal(res.data?.total || 0);

            // 收集所有标签用于筛选
            const tags = new Set<string>(allTags);
            items.forEach((a: Article) => a.tags?.forEach((t: string) => tags.add(t)));
            setAllTags(Array.from(tags));
        } catch (e: any) {
            message.error(e.message || '加载文章失败');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadCategories();
    }, []);

    useEffect(() => {
        loadArticles(1);
        setPage(1);
    }, [selectedCategoryId, statusFilter, tagFilter]);

    // 分类树数据转换
    const buildTreeData = (cats: Category[]): any[] => {
        return cats.map(c => ({
            key: c.id,
            title: (
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%' }}>
                    <span style={{ flex: 1, overflow: 'hidden', textOverflow: 'ellipsis' }}>{c.title}</span>
                    <Space size={0} style={{ marginLeft: 4 }}>
                        <Tooltip title="编辑">
                            <Button type="text" size="small" icon={<EditOutlined />}
                                onClick={(e) => { e.stopPropagation(); handleEditCategory(c); }}
                            />
                        </Tooltip>
                        <Popconfirm title="确定删除此分类？" onConfirm={() => handleDeleteCategory(c.id)}
                            onCancel={(e) => e?.stopPropagation()}>
                            <Button type="text" size="small" danger icon={<DeleteOutlined />}
                                onClick={(e) => e.stopPropagation()}
                            />
                        </Popconfirm>
                    </Space>
                </div>
            ),
            children: c.children && c.children.length > 0 ? buildTreeData(c.children) : undefined,
        }));
    };

    // 分类操作
    const handleAddCategory = () => {
        setEditingCategory(null);
        categoryForm.resetFields();
        if (selectedCategoryId) {
            categoryForm.setFieldsValue({ parent_id: selectedCategoryId });
        }
        setCategoryModalOpen(true);
    };

    const handleEditCategory = (cat: Category) => {
        setEditingCategory(cat);
        categoryForm.setFieldsValue({ title: cat.title, parent_id: cat.parent_id });
        setCategoryModalOpen(true);
    };

    const handleDeleteCategory = async (id: number) => {
        try {
            await api.post(KB_ROUTES.deleteCategory(id));
            message.success('删除成功');
            if (selectedCategoryId === id) setSelectedCategoryId(null);
            loadCategories();
        } catch (e: any) {
            message.error(e.message || '删除失败');
        }
    };

    const handleCategorySubmit = async () => {
        try {
            const values = await categoryForm.validateFields();
            if (editingCategory) {
                await api.post(KB_ROUTES.updateCategory(editingCategory.id), values);
                message.success('更新成功');
            } else {
                await api.post(KB_ROUTES.createCategory, values);
                message.success('创建成功');
            }
            setCategoryModalOpen(false);
            loadCategories();
        } catch (e: any) {
            if (e.message) message.error(e.message);
        }
    };

    // 文章操作
    const handleDeleteArticle = async (id: number) => {
        try {
            await api.post(KB_ROUTES.deleteArticle(id));
            message.success('删除成功');
            loadArticles();
        } catch (e: any) {
            message.error(e.message || '删除失败');
        }
    };

    const handleCopyShareLink = (slug: string) => {
        const url = `${window.location.origin}${KB_ROUTES.publicView(slug)}`;

        // 降级方案：优先用 clipboard API，失败则用 execCommand
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(() => {
                message.success('分享链接已复制');
            }).catch(() => {
                fallbackCopy(url);
            });
        } else {
            fallbackCopy(url);
        }
    };

    const fallbackCopy = (text: string) => {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            message.success('分享链接已复制');
        } catch (e) {
            message.error('复制失败，请手动复制');
        } finally {
            document.body.removeChild(textarea);
        }
    };

    const columns = [
        {
            title: '标题',
            dataIndex: 'title',
            key: 'title',
            render: (text: string, record: Article) => (
                <a href={KB_ROUTES.detail(record.id)} target="_blank" rel="noopener noreferrer" style={{ fontWeight: 500 }}>
                    <FileTextOutlined style={{ marginRight: 6 }} />{text}
                </a>
            ),
        },
        {
            title: '状态',
            dataIndex: 'status',
            key: 'status',
            width: 90,
            render: (status: string) => (
                <Tag color={status === 'public' ? 'green' : 'default'}>
                    {status === 'public' ? '公开' : '私有'}
                </Tag>
            ),
        },
        {
            title: '标签',
            dataIndex: 'tags',
            key: 'tags',
            width: 200,
            render: (tags: string[]) => tags?.map((t, i) => {
                const TAG_COLORS = ['blue', 'green', 'orange', 'purple', 'cyan', 'magenta', 'red', 'geekblue', 'volcano', 'gold', 'lime'];
                const colorIndex = t.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0) % TAG_COLORS.length;
                return <Tag key={i} color={TAG_COLORS[colorIndex]}>{t}</Tag>;
            }),
        },
        {
            title: '更新时间',
            dataIndex: 'updated_at',
            key: 'updated_at',
            width: 170,
        },
        {
            title: '操作',
            key: 'action',
            width: 180,
            render: (_: any, record: Article) => (
                <Space size="small">
                    <Link href={KB_ROUTES.editor(record.id)}>
                        <Button type="link" size="small" icon={<EditOutlined />}>编辑</Button>
                    </Link>
                    {record.status === 'public' && (
                        <Tooltip title="复制分享链接">
                            <Button type="link" size="small" icon={<LinkOutlined />}
                                onClick={() => handleCopyShareLink(record.slug)}>分享</Button>
                        </Tooltip>
                    )}
                    <Popconfirm title="确定删除此文章？" onConfirm={() => handleDeleteArticle(record.id)}>
                        <Button type="link" size="small" danger icon={<DeleteOutlined />} />
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    return (
        <>
            <Head title="知识库" />
            <Layout style={{ minHeight: '100vh', background: '#f5f5f5' }}>
                {/* 顶部导航栏 */}
                <Layout.Header style={{
                    background: '#fff', padding: '0 24px', display: 'flex',
                    alignItems: 'center', justifyContent: 'space-between',
                    borderBottom: '1px solid #f0f0f0', height: 56,
                }}>
                    <Space>
                        <Link href="/project">
                            <Button type="text" icon={<ArrowLeftOutlined />} />
                        </Link>
                        <BookOutlined style={{ fontSize: 20, color: '#1890ff' }} />
                        <Typography.Title level={4} style={{ margin: 0 }}>知识库</Typography.Title>
                    </Space>
                    <Link href={selectedCategoryId ? `${KB_ROUTES.editor()}?category_id=${selectedCategoryId}` : KB_ROUTES.editor()}>
                        <Button type="primary" icon={<PlusOutlined />}>新建文章</Button>
                    </Link>
                </Layout.Header>

                <Layout style={{ background: '#f5f5f5' }}>
                    {/* 左侧分类树 */}
                    <Sider width={260} style={{
                        background: '#fff', borderRight: '1px solid #f0f0f0',
                        padding: '16px 0', overflow: 'auto',
                    }}>
                        <div style={{ padding: '0 16px', marginBottom: 12, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <Typography.Text strong>分类目录</Typography.Text>
                            <Tooltip title="新建分类">
                                <Button type="text" size="small" icon={<FolderAddOutlined />} onClick={handleAddCategory} />
                            </Tooltip>
                        </div>

                        {/* 全部文章 */}
                        <div
                            style={{
                                padding: '6px 16px', cursor: 'pointer',
                                background: selectedCategoryId === null ? '#e6f4ff' : 'transparent',
                                fontWeight: selectedCategoryId === null ? 600 : 400,
                            }}
                            onClick={() => setSelectedCategoryId(null)}
                        >
                            <FolderOutlined style={{ marginRight: 8 }} />全部文章
                        </div>

                        {categories.length > 0 && (
                            <Tree
                                treeData={buildTreeData(categories)}
                                selectedKeys={selectedCategoryId ? [selectedCategoryId] : []}
                                onSelect={(keys) => {
                                    if (keys.length > 0) {
                                        setSelectedCategoryId(Number(keys[0]));
                                    }
                                }}
                                defaultExpandAll
                                blockNode
                                style={{ padding: '4px 8px' }}
                            />
                        )}
                    </Sider>

                    {/* 右侧文章列表 */}
                    <Content style={{ padding: 24 }}>
                        <Card size="small" style={{ marginBottom: 16 }}>
                            <Space wrap>
                                <Search
                                    placeholder="搜索文章标题、内容..."
                                    allowClear
                                    onSearch={(v) => { setKeyword(v); loadArticles(1, { keyword: v }); }}
                                    style={{ width: 280 }}
                                />
                                <Select
                                    placeholder="状态筛选"
                                    allowClear
                                    style={{ width: 120 }}
                                    value={statusFilter || undefined}
                                    onChange={(v) => setStatusFilter(v || '')}
                                    options={[
                                        { label: '私有', value: 'private' },
                                        { label: '公开', value: 'public' },
                                    ]}
                                />
                                <Select
                                    placeholder="标签筛选"
                                    allowClear
                                    style={{ width: 150 }}
                                    value={tagFilter || undefined}
                                    onChange={(v) => { setTagFilter(v || ''); loadArticles(1, { tag: v || '' }); }}
                                    options={allTags.map(t => ({ label: t, value: t }))}
                                />
                            </Space>
                        </Card>

                        <Card size="small">
                            <Table
                                dataSource={articles}
                                columns={columns}
                                rowKey="id"
                                loading={loading}
                                pagination={{
                                    current: page,
                                    pageSize,
                                    total,
                                    showSizeChanger: true,
                                    showTotal: (t) => `共 ${t} 篇`,
                                    onChange: (p, ps) => {
                                        setPage(p);
                                        setPageSize(ps);
                                        loadArticles(p);
                                    },
                                }}
                                locale={{ emptyText: <Empty description="暂无文章" /> }}
                            />
                        </Card>
                    </Content>
                </Layout>
            </Layout>

            {/* 分类编辑弹窗 */}
            <Modal
                title={editingCategory ? '编辑分类' : '新建分类'}
                open={categoryModalOpen}
                onOk={handleCategorySubmit}
                onCancel={() => setCategoryModalOpen(false)}
                destroyOnClose
            >
                <Form form={categoryForm} layout="vertical">
                    <Form.Item name="title" label="分类名称" rules={[{ required: true, message: '请输入分类名称' }]}>
                        <Input placeholder="如：项目设计文档" />
                    </Form.Item>
                    <Form.Item name="parent_id" label="父分类" hidden>
                        <Input />
                    </Form.Item>
                </Form>
            </Modal>
        </>
    );
}
