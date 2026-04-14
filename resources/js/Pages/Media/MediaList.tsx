import React, { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import api from '../../util/Service';
import { 
  Table, Button, Space, Popconfirm, message, 
  Card, Typography, Breadcrumb, Row, Col, Input, Select, Tag, Image, Tree, Modal, TreeSelect, Dropdown, Radio, Tooltip
} from 'antd';
import { 
  PlusOutlined, EditOutlined, DeleteOutlined, 
  SearchOutlined, DownloadOutlined, EyeOutlined, 
  FileOutlined, MoreOutlined, DownOutlined, RightOutlined
} from '@ant-design/icons';
import { Link } from '@inertiajs/react';
import MEDIA_TYPES, { getMediaTypeConfig } from './mediaTypes';
import { formatSize } from '../../util/StringUtil';
import { MEDIA_ROUTES, MEDIA_FOLDER_ROUTES, MEDIA_TAG_ROUTES } from '../../Constants/routes';

const { Title } = Typography;
const { Search } = Input;
const { Option } = Select;

interface Media {
  id: number;
  filename: string;
  original_filename: string;
  mime_type: string;
  extension: string;
  path: string;
  url: string;
  size: number;
  type: string;
  description: string;
  created_at: string;
  updated_at: string;
  readable_size: string;
  icon: string;
  folder_id?: number | null;
  folder?: { id: number; name: string } | null;
  tags?: string[];
}

interface MediaTag {
  id: number;
  name: string;
  color: string;
  sort: number;
}

interface FolderNode {
  id: number;
  name: string;
  parent_id?: number | null;
  children?: FolderNode[];
  is_system?: boolean;
}

const MediaList = () => {
  const { media, filters = { type: null, search: '', folder_id: null, tag: null } } = usePage<any>().props;
  const [loading, setLoading] = useState(false);
  const [searchValue, setSearchValue] = useState(filters.search || '');
  const [typeFilter, setTypeFilter] = useState(filters.type || null);
  const [selectedRowKeys, setSelectedRowKeys] = useState<React.Key[]>([]);
  const [folderTree, setFolderTree] = useState<FolderNode[]>([]);
  const [selectedFolderId, setSelectedFolderId] = useState<number | null>(filters.folder_id ? Number(filters.folder_id) : null);
  const [moveModalOpen, setMoveModalOpen] = useState(false);
  const [moveToFolderId, setMoveToFolderId] = useState<number | null>(null);

  // 文件夹管理相关状态
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [createName, setCreateName] = useState('');
  const [createParentId, setCreateParentId] = useState<number | null>(null);

  const [renameModalOpen, setRenameModalOpen] = useState(false);
  const [renameName, setRenameName] = useState('');
  const [renameId, setRenameId] = useState<number | null>(null);

  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [deleteStrategy, setDeleteStrategy] = useState<'keep' | 'move'>('keep');
  const [deleteTargetFolderId, setDeleteTargetFolderId] = useState<number | null>(null);

  // 树展开/折叠状态
  const [expandedKeys, setExpandedKeys] = useState<React.Key[]>([]);
  const [autoExpandParent, setAutoExpandParent] = useState(true);

  // 标签相关状态
  const [allTags, setAllTags] = useState<MediaTag[]>([]);
  const [selectedTag, setSelectedTag] = useState<string | null>(filters.tag || null);
  const [tagModalOpen, setTagModalOpen] = useState(false);
  const [tagFormData, setTagFormData] = useState<{ id?: number; name: string; color: string }>({ name: '', color: '#1890ff' });

  // 获取所有文件夹ID（用于展开全部）
  const getAllFolderKeys = (nodes: FolderNode[]): React.Key[] => {
    const keys: React.Key[] = [];
    const walk = (items: FolderNode[]) => {
      items.forEach(item => {
        keys.push(item.id);
        if (item.children && item.children.length) {
          walk(item.children);
        }
      });
    };
    walk(nodes);
    return keys;
  };

  // 展开全部
  const expandAll = () => {
    setExpandedKeys(getAllFolderKeys(folderTree));
    setAutoExpandParent(true);
  };

  // 折叠全部
  const collapseAll = () => {
    setExpandedKeys([]);
    setAutoExpandParent(false);
  };

  // 获取文件夹及其所有子孙文件夹的ID列表
  const getFolderAndDescendantIds = (folderId: number, nodes: FolderNode[]): number[] => {
    const ids: number[] = [folderId];
    const findNode = (id: number, items: FolderNode[]): FolderNode | null => {
      for (const item of items) {
        if (item.id === id) return item;
        if (item.children) {
          const found = findNode(id, item.children);
          if (found) return found;
        }
      }
      return null;
    };
    const collectDescendants = (node: FolderNode) => {
      if (node.children) {
        node.children.forEach(child => {
          ids.push(child.id);
          collectDescendants(child);
        });
      }
    };
    const node = findNode(folderId, nodes);
    if (node) {
      collectDescendants(node);
    }
    return ids;
  };

  

  // 渲染树节点标题（带操作按钮）
  const renderFolderTitle = (node: any) => (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%', paddingRight: 8 }}>
      <span>{node.name}</span>
      <Dropdown
        menu={{
          items: [
            { key: 'create', label: '新建子文件夹', icon: <PlusOutlined /> },
            { type: 'divider' as const },
            { key: 'rename', label: '重命名', icon: <EditOutlined />, disabled: !!node.is_system },
            { key: 'delete', label: '删除', icon: <DeleteOutlined />, danger: true, disabled: !!node.is_system },
          ],
          onClick: ({ key }) => {
            if (key === 'create') {
              setCreateParentId(node.id);
              setCreateName('');
              setCreateModalOpen(true);
            } else if (key === 'rename') {
              setRenameId(node.id);
              setRenameName(node.name);
              setRenameModalOpen(true);
            } else if (key === 'delete') {
              setDeleteId(node.id);
              setDeleteStrategy('keep');
              setDeleteTargetFolderId(null);
              setDeleteModalOpen(true);
            }
          }
        }}
        trigger={['click']}
      >
        <Button 
          type="text" 
          size="small" 
          icon={<MoreOutlined />}
          onClick={(e) => e.stopPropagation()}
          style={{ marginLeft: 4 }}
        />
      </Dropdown>
    </div>
  );

  // 构建 antd Tree 所需结构
  const buildTreeData = (nodes: FolderNode[]): any[] => {
    return (nodes || []).map((n) => ({
      key: n.id,
      title: renderFolderTitle(n as any),
      is_system: (n as any).is_system,
      children: buildTreeData(n.children || [])
    }));
  };


  // 提交：新建文件夹
  const submitCreateFolder = () => {
    if (!createName.trim()) {
      message.warning('请输入文件夹名称');
      return;
    }
    setLoading(true);
    api.post(MEDIA_FOLDER_ROUTES.create, { name: createName.trim(), parent_id: createParentId })
      .then(() => {
        setCreateModalOpen(false);
        message.success('创建成功');
        // 重新加载文件夹树
        api.get(MEDIA_FOLDER_ROUTES.tree).then((res) => {
          setFolderTree(res.data || []);
        });
      })
      .catch((error) => {
        message.error('创建失败: ' + (error.message || ''));
      })
      .finally(() => {
        setLoading(false);
      });
  };

  // 提交：重命名
  const submitRenameFolder = () => {
    if (!renameId) return;
    if (!renameName.trim()) {
      message.warning('请输入文件夹名称');
      return;
    }
    setLoading(true);
    api.post(MEDIA_FOLDER_ROUTES.rename(renameId), { name: renameName.trim() })
      .then(() => {
        setRenameModalOpen(false);
        message.success('重命名成功');
        // 重新加载文件夹树
        api.get(MEDIA_FOLDER_ROUTES.tree).then((res) => {
          setFolderTree(res.data || []);
        });
      })
      .catch((error) => {
        message.error('重命名失败: ' + (error.message || ''));
      })
      .finally(() => {
        setLoading(false);
      });
  };

  // 提交：删除
  const submitDeleteFolder = () => {
    if (!deleteId) return;
    if (deleteStrategy === 'move' && !deleteTargetFolderId) {
      message.warning('请选择目标文件夹');
      return;
    }
    setLoading(true);
    api.post(MEDIA_FOLDER_ROUTES.delete(deleteId), {
      strategy: deleteStrategy,
      target_folder_id: deleteStrategy === 'move' ? deleteTargetFolderId : undefined,
    })
      .then(() => {
        setDeleteModalOpen(false);
        message.success('删除成功');
        // 如果删除的是当前选中文件夹，清空选择并刷新列表
        if (selectedFolderId === deleteId) {
          setSelectedFolderId(null);
          refreshList();
        }
        // 重新加载文件夹树
        api.get(MEDIA_FOLDER_ROUTES.tree).then((res) => {
          setFolderTree(res.data || []);
        });
      })
      .catch((error) => {
        message.error('删除失败: ' + (error.message || ''));
      })
      .finally(() => {
        setLoading(false);
      });
  };


  // 刷新文件夹树
  const refreshFolderTree = () => {
    fetch(MEDIA_FOLDER_ROUTES.tree, { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((res) => {
        if (res && res.code === 0) {
          setFolderTree(res.data || []);
        }
      })
      .catch(() => {});
  };

  // 刷新媒体列表（保持当前筛选）
  const refreshList = () => {
    let folderIds = '';
    if (selectedFolderId !== null) {
      const ids = getFolderAndDescendantIds(selectedFolderId, folderTree);
      folderIds = ids.join(',');
    }
    router.get(MEDIA_ROUTES.index, {
      search: searchValue,
      type: typeFilter,
      folder_id: folderIds,
      tag: selectedTag ?? ''
    }, {
      preserveState: true,
      replace: true
    });
  };

  // 加载标签列表
  const loadTags = () => {
    api.get(MEDIA_TAG_ROUTES.list)
      .then((res) => {
        setAllTags(res.data || []);
      })
      .catch(() => {});
  };

  useEffect(() => {
    refreshFolderTree();
    loadTags();
  }, []);

  // 初始化时展开所有节点
  useEffect(() => {
    if (folderTree.length > 0 && expandedKeys.length === 0) {
      setExpandedKeys(getAllFolderKeys(folderTree));
    }
  }, [folderTree]);

  // 处理搜索
  const handleSearch = (value: string) => {
    let folderIds = '';
    if (selectedFolderId !== null) {
      const ids = getFolderAndDescendantIds(selectedFolderId, folderTree);
      folderIds = ids.join(',');
    }
    router.get(MEDIA_ROUTES.index, { 
      search: value, 
      type: typeFilter,
      folder_id: folderIds,
      tag: selectedTag ?? ''
    }, {
      preserveState: true,
      replace: true
    });
  };

  // 处理类型筛选
  const handleTypeChange = (value: string | null) => {
    setTypeFilter(value);
    let folderIds = '';
    if (selectedFolderId !== null) {
      const ids = getFolderAndDescendantIds(selectedFolderId, folderTree);
      folderIds = ids.join(',');
    }
    router.get(MEDIA_ROUTES.index, { 
      search: searchValue, 
      type: value,
      folder_id: folderIds,
      tag: selectedTag ?? ''
    }, {
      preserveState: true,
      replace: true
    });
  };

  const handleFolderSelect = (keys: React.Key[]) => {
    const id = keys.length ? Number(keys[0]) : null;
    setSelectedFolderId(id);
    
    // 如果选中了文件夹，获取该文件夹及其所有子孙文件夹的ID
    let folderIds = '';
    if (id !== null) {
      const ids = getFolderAndDescendantIds(id, folderTree);
      folderIds = ids.join(',');
    }
    
    router.get(MEDIA_ROUTES.index, {
      search: searchValue,
      type: typeFilter,
      folder_id: folderIds,
      tag: selectedTag ?? ''
    }, {
      preserveState: true,
      replace: true
    });
  };

  // 处理标签筛选
  const handleTagChange = (value: string | null) => {
    setSelectedTag(value);
    let folderIds = '';
    if (selectedFolderId !== null) {
      const ids = getFolderAndDescendantIds(selectedFolderId, folderTree);
      folderIds = ids.join(',');
    }
    router.get(MEDIA_ROUTES.index, {
      search: searchValue,
      type: typeFilter,
      folder_id: folderIds,
      tag: value ?? ''
    }, {
      preserveState: true,
      replace: true
    });
  };

  // 标签管理：打开新建/编辑弹窗
  const openTagModal = (tag?: MediaTag) => {
    if (tag) {
      setTagFormData({ id: tag.id, name: tag.name, color: tag.color });
    } else {
      setTagFormData({ name: '', color: '#1890ff' });
    }
    setTagModalOpen(true);
  };

  // 标签管理：提交
  const submitTag = () => {
    if (!tagFormData.name.trim()) {
      message.warning('请输入标签名称');
      return;
    }
    setLoading(true);
    const request = tagFormData.id
      ? api.post(MEDIA_TAG_ROUTES.update(tagFormData.id), { name: tagFormData.name, color: tagFormData.color })
      : api.post(MEDIA_TAG_ROUTES.create, { name: tagFormData.name, color: tagFormData.color });
    
    request
      .then(() => {
        message.success(tagFormData.id ? '更新成功' : '创建成功');
        setTagModalOpen(false);
        loadTags();
      })
      .catch((error) => {
        message.error((tagFormData.id ? '更新失败: ' : '创建失败: ') + (error.message || ''));
      })
      .finally(() => {
        setLoading(false);
      });
  };

  // 标签管理：删除
  const deleteTag = (id: number) => {
    Modal.confirm({
      title: '确认删除该标签？',
      content: '删除后，已使用该标签的媒体将不受影响',
      onOk: () => {
        return api.post(MEDIA_TAG_ROUTES.delete(id))
          .then(() => {
            message.success('删除成功');
            loadTags();
            if (selectedTag === allTags.find(t => t.id === id)?.name) {
              setSelectedTag(null);
              handleTagChange(null);
            }
          })
          .catch((error) => {
            message.error('删除失败: ' + (error.message || ''));
          });
      }
    });
  };

  // 删除媒体资源
  const handleDelete = (id: number) => {
    setLoading(true);
    router.delete(MEDIA_ROUTES.delete(id), {
      onSuccess: () => {
        setLoading(false);
      },
      onError: () => {
        setLoading(false);
        message.error('删除媒体资源失败');
      }
    });
  };

  // 批量删除媒体资源（基础版）
  const handleBatchDelete = () => {
    if (selectedRowKeys.length === 0) {
      message.warning('请先选择要删除的媒体资源');
      return;
    }
    setLoading(true);
    const ids: number[] = selectedRowKeys.map((k) => Number(k));
    router.post(MEDIA_ROUTES.batchDelete, { ids }, {
      onSuccess: () => {
        setSelectedRowKeys([]);
        setLoading(false);
        message.success('批量删除成功');
      },
      onError: () => {
        setLoading(false);
        message.error('批量删除失败');
      }
    });
  };

  // 批量移动：弹出选择文件夹对话框
  const handleBatchMove = () => {
    if (selectedRowKeys.length === 0) {
      message.warning('请先选择要移动的媒体资源');
      return;
    }
    setMoveModalOpen(true);
  };

  // 批量移动：提交
  const doBatchMove = () => {
    if (!moveToFolderId) {
      message.warning('请选择目标文件夹');
      return;
    }
    const ids: number[] = selectedRowKeys.map((k) => Number(k));
    setLoading(true);
    api.post(MEDIA_ROUTES.batchMove, { ids, to_folder_id: moveToFolderId })
    .then(() => {
      setLoading(false);
      setMoveModalOpen(false);
      setSelectedRowKeys([]);
      message.success('移动成功');
      router.get(MEDIA_ROUTES.index, {
          search: searchValue,
          type: typeFilter,
          folder_id: selectedFolderId ?? ''
        }, {
          preserveState: true,
          replace: true,
        });
      })
      .catch((error) => {
        setLoading(false);
        message.error('移动失败: ' + (error.message || ''));
      });
  };

  const rowSelection = {
    selectedRowKeys,
    onChange: (keys: React.Key[]) => setSelectedRowKeys(keys),
  };

  // 表格列定义
  const columns = [
    {
      title: '预览',
      key: 'preview',
      width: 100,
      fixed: 'left' as const,
      render: (_: any, record: Media) => (
        record.type === 'image' ? (
          <div style={{ width: 80, height: 80, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <Image 
              src={record.url} 
              alt={record.original_filename}
              width={80}
              height={80}
              style={{ objectFit: 'cover' }}
              preview={{ src: record.url }}
              onError={(e) => {
                const target = e.target as HTMLImageElement;
                target.style.display = 'none';
              }}
            />
            <FileOutlined style={{ display: 'none', fontSize: 24, color: '#999', position: 'absolute' }} className="fallback-icon" />
          </div>
        ) : (
          <div style={{ 
            width: 80, 
            height: 80, 
            display: 'flex', 
            alignItems: 'center', 
            justifyContent: 'center',
            background: '#f5f5f5',
            borderRadius: '4px'
          }}>
            <FileOutlined style={{ fontSize: 24, color: '#999' }} />
          </div>
        )
      )
    },
    {
      title: 'ID.文件名',
      dataIndex: 'original_filename',
      key: 'original_filename',
      ellipsis: true,
      width: 150,
      render: (_: any, record: Media) => (
        <div>
          <div>{record.original_filename}</div>
          {record.tags && record.tags.length > 0 && (
            <div style={{ marginTop: 4 }}>
              {record.tags.map((tagName, idx) => {
                const tag = allTags.find(t => t.name === tagName);
                return (
                  <Tag key={idx} color={tag?.color || '#1890ff'} style={{ marginRight: 4, marginBottom: 2 }}>
                    {tagName}
                  </Tag>
                );
              })}
            </div>
          )}
        </div>
      )
    },
    {
      title: '所在文件夹',
      dataIndex: 'folder',
      key: 'folder',
      width: 180,
      render: (folder: { id: number; name: string; full_path?: string } | null) => (
        folder ? <span style={{ color: '#666' }}>{folder.full_path || folder.name}</span> : <span style={{ color: '#999' }}>-</span>
      )
    },
    {
      title: '类型',
      dataIndex: 'type',
      key: 'type',
      width: 100,
      render: (type: string) => {
        const config = getMediaTypeConfig(type);
        return <Tag color={config.color}>{config.label}</Tag>;
      }
    },
    {
      title: '大小',
      dataIndex: 'size',
      key: 'size',
      width: 100,
      render: (size: number) => (
        // size转换 单位 b kb mb 等
        formatSize(size)
      )
    },
    {
      title: '上传时间',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 170,
    },
    {
      title: '操作',
      key: 'action',
      fixed: 'right' as const,
      width: 250,
      render: (_: any, record: Media) => (
        <Space size="small">
          <Link href={`/media/show/${record.id}`}>
            <Button type="primary" icon={<EyeOutlined />} size="small">
              查看
            </Button>
          </Link>
          <Link href={`/media/edit/${record.id}`}>
            <Button type="default" icon={<EditOutlined />} size="small">
              编辑
            </Button>
          </Link>
          <Button 
            type="default" 
            icon={<DownloadOutlined />} 
            size="small"
            onClick={() => window.open(record.url, '_blank')}
          >
            下载
          </Button>
          <Popconfirm
            title="确定要删除这个媒体资源吗?"
            onConfirm={() => handleDelete(record.id)}
            okText="确定"
            cancelText="取消"
          >
            <Button type="primary" danger icon={<DeleteOutlined />} size="small">
              删除
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div className="py-6">
      <Head title="媒体资源管理" />
      <div style={{ minHeight: 280 }}>
         <Card
          title={(
            <span>
              媒体资源管理
            </span>
          )}
          extra={(
                  <Space>
                    <Popconfirm
                      title={`确定要删除已选择的 ${selectedRowKeys.length} 个媒体资源吗?`}
                      onConfirm={handleBatchDelete}
                      okText="确定"
                      cancelText="取消"
                      disabled={selectedRowKeys.length === 0}
                    >
                      <Button danger disabled={selectedRowKeys.length === 0} loading={loading} icon={<DeleteOutlined />}>批量删除</Button>
                    </Popconfirm>
                    <Button onClick={handleBatchMove} disabled={selectedRowKeys.length === 0}>批量移动</Button>
                    <Dropdown
                      menu={{
                        items: allTags.map((tag) => ({
                          key: tag.id,
                          label: (
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', minWidth: 150 }}>
                              <Tag color={tag.color}>{tag.name}</Tag>
                              <Space size="small">
                                <Button type="text" size="small" icon={<EditOutlined />} onClick={(e) => { e.stopPropagation(); openTagModal(tag); }} />
                                <Button type="text" size="small" danger icon={<DeleteOutlined />} onClick={(e) => { e.stopPropagation(); deleteTag(tag.id); }} />
                              </Space>
                            </div>
                          )
                        })).concat([
                          { type: 'divider' as const },
                          { key: 'add', label: <><PlusOutlined /> 新建标签</>, onClick: () => openTagModal() }
                        ])
                      }}
                    >
                      <Button>标签管理</Button>
                    </Dropdown>
                    <Link href="/media/create">
                      <Button type="primary" icon={<PlusOutlined />}>
                        上传媒体
                      </Button>
                    </Link>
                  </Space>
          )}
        >
        <Row gutter={16}>
          <Col span={6}>
            <Card 
              title="文件夹" 
              size="small"
              style={{ minHeight: 580 }}
              bodyStyle={{ paddingLeft: 0, paddingRight: 0 }}
              extra={
                <Space size="small">
                  <Tooltip title={expandedKeys.length > 0 ? '折叠' : '展开'}>
                    <Button 
                      size="small" 
                      type="text"
                      icon={expandedKeys.length > 0 ? <DownOutlined /> : <RightOutlined />} 
                      onClick={() => expandedKeys.length > 0 ? collapseAll() : expandAll()}
                    />
                  </Tooltip>
                  <Tooltip title="新建文件夹">
                    <Button 
                      size="small" 
                      type="text"
                      icon={<PlusOutlined />} 
                      onClick={() => { setCreateParentId(null); setCreateName(''); setCreateModalOpen(true); }}
                    />
                  </Tooltip>
                </Space>
              }
            >
              <div
                onClick={() => handleFolderSelect([])}
                style={{
                  padding: '8px 16px',
                  cursor: 'pointer',
                  background: selectedFolderId === null ? '#e6f7ff' : 'transparent',
                  borderBottom: '1px solid #f0f0f0'
                }}
              >
                <strong>全部</strong>
              </div>
              <Tree
                blockNode
                treeData={buildTreeData(folderTree)}
                selectedKeys={selectedFolderId ? [selectedFolderId] : []}
                expandedKeys={expandedKeys}
                onExpand={(keys) => {
                  setExpandedKeys(keys);
                  setAutoExpandParent(false);
                }}
                autoExpandParent={autoExpandParent}
                onSelect={handleFolderSelect}
              />
            </Card>
          </Col>
          <Col span={18}>
            <Card>
              <Row gutter={16} style={{ marginBottom: 16 }}>
                <Col span={12}>
                  <Search
                    placeholder="搜索文件名或描述"
                    allowClear
                    enterButton={<><SearchOutlined /> 搜索</>}
                    size="middle"
                    value={searchValue}
                    onChange={(e) => setSearchValue(e.target.value)}
                    onSearch={handleSearch}
                  />
                </Col>
                <Col span={6}>
                  <Select
                    placeholder="按类型筛选"
                    style={{ width: '100%' }}
                    value={typeFilter}
                    onChange={handleTypeChange}
                    allowClear
                  >
                    {Object.entries(MEDIA_TYPES)
                      .filter(([key]) => key !== 'default')
                      .map(([key, config]) => (
                        <Option key={key} value={key}>
                          {config.label}
                        </Option>
                      ))
                    }
                  </Select>
                </Col>
                <Col span={6}>
                  <Select
                    placeholder="按标签筛选"
                    style={{ width: '100%' }}
                    value={selectedTag}
                    onChange={handleTagChange}
                    allowClear
                    dropdownRender={(menu) => (
                      <>
                        {menu}
                        <div style={{ padding: '8px', borderTop: '1px solid #f0f0f0' }}>
                          <Button type="link" size="small" onClick={() => openTagModal()} block>
                            <PlusOutlined /> 新建标签
                          </Button>
                        </div>
                      </>
                    )}
                  >
                    {allTags.map((tag) => (
                      <Option key={tag.id} value={tag.name}>
                        <Tag color={tag.color}>{tag.name}</Tag>
                      </Option>
                    ))}
                  </Select>
                </Col>
              </Row>
              
              <Table 
                columns={columns} 
                dataSource={media.data} 
                rowKey="id" 
                rowSelection={rowSelection}
                loading={loading}
                scroll={{ x: 1000 }}
                pagination={{
                  current: media.current_page,
                  pageSize: media.per_page,
                  total: media.total,
                  onChange: (page) => {
                    router.get(MEDIA_ROUTES.index, {
                      page,
                      search: searchValue,
                      type: typeFilter,
                      folder_id: selectedFolderId ?? ''
                    }, {
                      preserveState: true,
                      replace: true
                    });
                  }
                }}
              />
            </Card>
          </Col>
        </Row>

</Card>
        <Modal
          title="选择目标文件夹"
          open={moveModalOpen}
          onCancel={() => setMoveModalOpen(false)}
          onOk={doBatchMove}
        >
          <TreeSelect
            style={{ width: '100%'}}
            value={moveToFolderId as any}
            dropdownStyle={{ maxHeight: 400, overflow: 'auto' }}
            treeDefaultExpandAll
            allowClear
            placeholder="请选择目标文件夹"
            onChange={(v) => setMoveToFolderId(v as number)}
            treeData={folderTree.map((n) => ({ value: n.id, title: n.name, children: (n.children||[]).map((c)=>({ value: c.id, title: c.name, children: (c.children||[]).map((cc)=>({ value: cc.id, title: cc.name })) })) }))}
          />
        </Modal>

        <Modal
          title="新建文件夹"
          open={createModalOpen}
          onCancel={() => setCreateModalOpen(false)}
          onOk={submitCreateFolder}
          okButtonProps={{ disabled: !createName.trim() }}
        >
          <Input
            placeholder="请输入文件夹名称"
            value={createName}
            onChange={(e) => setCreateName(e.target.value)}
          />
        </Modal>

        <Modal
          title="重命名文件夹"
          open={renameModalOpen}
          onCancel={() => setRenameModalOpen(false)}
          onOk={submitRenameFolder}
          okButtonProps={{ disabled: !renameName.trim() }}
        >
          <Input
            placeholder="请输入新名称"
            value={renameName}
            onChange={(e) => setRenameName(e.target.value)}
          />
        </Modal>

        <Modal
          title="删除文件夹"
          open={deleteModalOpen}
          onCancel={() => setDeleteModalOpen(false)}
          onOk={submitDeleteFolder}
          okButtonProps={{ danger: true }}
        >
          <div style={{ marginBottom: 12 }}>删除后该文件夹内媒体将如何处理？</div>
          <Radio.Group 
            value={deleteStrategy}
            onChange={(e) => setDeleteStrategy(e.target.value as 'keep' | 'move')}
          >
            <Radio value="keep">移动到“未分类”</Radio>
            <Radio value="move">移动到其他文件夹</Radio>
          </Radio.Group>
          {deleteStrategy === 'move' && (
            <div style={{ marginTop: 12 }}>
              <TreeSelect
                style={{ width: '100%' }}
                value={deleteTargetFolderId as any}
                dropdownStyle={{ maxHeight: 400, overflow: 'auto' }}
                treeDefaultExpandAll
                allowClear
                placeholder="请选择目标文件夹"
                onChange={(v) => setDeleteTargetFolderId(v as number)}
                treeData={folderTree.map((n) => ({ value: n.id, title: n.name, disabled: n.id === deleteId, children: (n.children||[]).map((c)=>({ value: c.id, title: c.name, disabled: c.id === deleteId, children: (c.children||[]).map((cc)=>({ value: cc.id, title: cc.name, disabled: cc.id === deleteId })) })) }))}
              />
            </div>
          )}
        </Modal>

        <Modal
          title={tagFormData.id ? '编辑标签' : '新建标签'}
          open={tagModalOpen}
          onCancel={() => setTagModalOpen(false)}
          onOk={submitTag}
          okButtonProps={{ disabled: !tagFormData.name.trim() }}
        >
          <div style={{ marginBottom: 12 }}>
            <div style={{ marginBottom: 6 }}>标签名称</div>
            <Input
              placeholder="请输入标签名称"
              value={tagFormData.name}
              onChange={(e) => setTagFormData({ ...tagFormData, name: e.target.value })}
            />
          </div>
          <div>
            <div style={{ marginBottom: 6 }}>标签颜色</div>
            <Space wrap>
              {['#1890ff', '#52c41a', '#faad14', '#f5222d', '#722ed1', '#13c2c2', '#eb2f96', '#fa8c16', '#2f54eb', '#a0d911'].map((color) => (
                <div
                  key={color}
                  onClick={() => setTagFormData({ ...tagFormData, color })}
                  style={{
                    width: 32,
                    height: 32,
                    backgroundColor: color,
                    borderRadius: 4,
                    cursor: 'pointer',
                    border: tagFormData.color === color ? '3px solid #000' : '1px solid #d9d9d9'
                  }}
                />
              ))}
            </Space>
          </div>
        </Modal>
      </div>
    </div>
  );
};

export default function Page() {
  return <MediaList />;
}
