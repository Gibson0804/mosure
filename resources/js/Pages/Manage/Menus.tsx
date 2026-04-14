import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { Button, Card, Col, Form, Input, InputNumber, Modal, Row, Select, Switch, Tree, Typography, Checkbox, message } from 'antd';
import {
  FileOutlined,
  UnorderedListOutlined,
  SaveOutlined,
  PlusOutlined,
  HomeOutlined,
  FileTextOutlined,
  FolderOutlined,
  BookOutlined,
  TagsOutlined,
  ShoppingOutlined,
  ShoppingCartOutlined,
  TeamOutlined,
  UserOutlined,
  SettingOutlined,
  ToolOutlined,
  BellOutlined,
  MessageOutlined,
  CalendarOutlined,
  ClockCircleOutlined,
  BarChartOutlined,
  LineChartOutlined,
  PieChartOutlined,
  StarOutlined,
  HeartOutlined,
} from '@ant-design/icons';
import api from '../../util/Service';

const { Title } = Typography;

interface MenuNode {
  id?: number;
  parent_id?: number | null;
  title?: string;
  label?: string;
  key: string;
  icon?: string | null;
  target_type?: 'group' | 'mold_list' | 'mold_single' | 'function' | 'route' | 'url' | 'shortcut';
  target_payload?: any;
  order?: number;
  visible?: boolean;
  permission_key?: string | null;
  area?: string;
  plugin_id?: string | null;
  editable?: boolean;
  children?: MenuNode[];
}

interface ModelItem {
  id: number;
  name: string;
  mold_type: 'list' | 'single';
  table_name: string;
  disabled?: boolean;
}

const MenusPage: React.FC = () => {
  const [tree, setTree] = useState<MenuNode[]>([]);
  const [loading, setLoading] = useState(false);
  const [selected, setSelected] = useState<MenuNode | null>(null);
  const [form] = Form.useForm();
  const [addModalVisible, setAddModalVisible] = useState(false);
  const [addForm] = Form.useForm();
  const [editModels, setEditModels] = useState<ModelItem[]>([]);
  const [addModels, setAddModels] = useState<ModelItem[]>([]);
  const [editSelectedModels, setEditSelectedModels] = useState<number[]>([]);
  const [addSelectedModels, setAddSelectedModels] = useState<number[]>([]);

  const loadTree = async () => {
    setLoading(true);
    try {
      const res = await api.get('/manage/menus/tree');
      setTree(res.data || []);
    } catch (e: any) {
      message.error('加载菜单树失败：' + (e.msg || e.message || '未知错误'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadTree();
    loadModels();
  }, []);

  const loadModels = async (excludeParentId?: number, isAdd: boolean = false) => {
    try {
      const params: any = {};
      if (excludeParentId !== undefined) {
        params.exclude_parent_id = excludeParentId;
      }
      const res = await api.get('/manage/menus/models', { params });
      const modelsData = res.data || [];
      if (isAdd) {
        setAddModels(modelsData);
      } else {
        setEditModels(modelsData);
      }
    } catch (e: any) {
      message.error('加载模型列表失败：' + (e.msg || e.message || '未知错误'));
    }
  };

  useEffect(() => {
    if (selected) {
      form.setFieldsValue({
        title: selected.title,
        order: selected.order ?? 0,
        visible: selected.visible !== false,
        icon: selected.icon || '',
      });
      // 加载当前菜单下的二级菜单（模型），排除当前菜单的二级菜单
      if (selected.target_type === 'group') {
        loadModels(selected.id, false);
        // 从 target_payload 中获取当前菜单的二级菜单模型ID
        const currentModelIds: number[] = [];
        if (selected.children && selected.children.length > 0) {
          selected.children.forEach(child => {
            if (child.target_payload?.mold_id) {
              currentModelIds.push(child.target_payload.mold_id);
            }
          });
        }
        setEditSelectedModels(currentModelIds);
      } else {
        loadModels(undefined, false);
        setEditSelectedModels([]);
      }
    } else {
      form.resetFields();
      loadModels(undefined, false);
      setEditSelectedModels([]);
    }
  }, [selected]);

  const antTreeData = useMemo(() => {
    const convert = (nodes: MenuNode[]): any[] =>
      nodes.map((n) => {
        const editable = n.editable !== false && !!n.id; // 仅 DB 菜单可编辑
        const nodeKey = n.id ? `db:${n.id}` : `core:${n.key}`;
        const text = n.title || n.label || '';
        const isHidden = n.visible === false;
        const color = isHidden ? '#ff9800' : (editable ? undefined : '#999'); // 隐藏=橙色，固定=灰色
        return {
          key: nodeKey,
          title: <span style={{ color }}>{text}{isHidden ? ' (隐藏)' : ''}</span>,
          selectable: editable,
          disabled: !editable,
          children: [],
        };
      });
    return convert(tree);
  }, [tree]);

  const onSelect = (keys: React.Key[]) => {
    const k = String(keys[0] || '');
    if (k.startsWith('db:')) {
      const id = parseInt(k.slice(3), 10);
      const node = (tree || []).find(n => n.id === id) || null;
      setSelected(node);
    } else {
      setSelected(null);
    }
  };

  const save = async () => {
    if (!selected || !selected.id) return;
    try {
      const values = await form.validateFields();
      await api.post(`/manage/menus/${selected.id}`, {
        title: values.title,
        order: values.order,
        visible: values.visible,
        icon: values.icon || null,
        target_payload: {
          models: editSelectedModels,
        },
      });
      message.success('保存成功');
      loadTree();
    } catch (e: any) {
      if (!e?.errorFields) {
        message.error('保存失败：' + (e.msg || e.message || '未知错误'));
      }
    }
  };

  const handleAddMenu = async () => {
    try {
      const values = await addForm.validateFields();
      await api.post('/manage/menus', {
        title: values.title,
        icon: values.icon || null,
        target_type: 'group',
        order: values.order || 0,
        visible: values.visible !== false,
        target_payload: {
          models: addSelectedModels,
        },
      });
      message.success('新增成功');
      setAddModalVisible(false);
      addForm.resetFields();
      setAddSelectedModels([]);
      loadTree();
    } catch (e: any) {
      if (!e?.errorFields) {
        message.error('新增失败：' + (e.msg || e.message || '未知错误'));
      }
    }
  };

  // 顶级菜单拖拽排序（DB 菜单可拖到任意位置，包括固定菜单中间）
  const onDrop = async (info: any) => {
    const dragKey: string = String(info.dragNode?.key || '');
    const dropKey: string = String(info.node?.key || '');
    
    if (!dragKey.startsWith('db:')) {
      message.warning('仅可拖动自定义菜单');
      return;
    }

    const dragId = parseInt(dragKey.slice(3), 10);
    const dragNode = (tree || []).find(n => n.id === dragId);
    if (!dragNode) return;

    // 找到目标节点在整个树中的位置
    const dropIdx = (tree || []).findIndex(n => 
      (n.id ? `db:${n.id}` : `core:${n.key}`) === dropKey
    );
    if (dropIdx < 0) return;

    const dropNode = tree[dropIdx];
    const before = info.dropPosition < 0;

    // 计算新的 order：在目标节点前后插入
    let newOrder: number;
    if (before) {
      const prevOrder = dropIdx > 0 ? (tree[dropIdx - 1].order || 0) : 0;
      const currOrder = dropNode.order || 0;
      newOrder = Math.floor((prevOrder + currOrder) / 2);
      if (newOrder === prevOrder || newOrder === currOrder) {
        newOrder = prevOrder + 5; // 避免重复，留出间隙
      }
    } else {
      const currOrder = dropNode.order || 0;
      const nextOrder = dropIdx < tree.length - 1 ? (tree[dropIdx + 1].order || 1000) : 1000;
      newOrder = Math.floor((currOrder + nextOrder) / 2);
      if (newOrder === currOrder || newOrder === nextOrder) {
        newOrder = currOrder + 5;
      }
    }

    try {
      await api.post(`/manage/menus/${dragId}/move`, { parent_id: null, order: newOrder });
      // 重新加载树以获取最新排序
      await loadTree();
      message.success('已更新排序');
    } catch (e: any) {
      message.error('更新排序失败：' + (e.msg || e.message || '未知错误'));
    }
  };

  // 无新增/删除/核心覆盖功能
  return (
    <div>
      <Head title="菜单管理" />
      <div style={{ padding: 24 }}>
        <Title level={2}>菜单管理</Title>
        <Row gutter={16}>
          <Col span={10}>
            <Card 
              title="菜单树" 
              loading={loading}
              extra={<Button type="primary" icon={<PlusOutlined />} onClick={() => setAddModalVisible(true)} size="small">
                新增菜单
              </Button>}
            >
              <Tree
                treeData={antTreeData}
                onSelect={onSelect}
                defaultExpandAll
                draggable={{ nodeDraggable: (node: any) => String(node?.key || '').startsWith('db:') }}
                allowDrop={() => true}
                onDrop={onDrop}
              />
            </Card>
          </Col>
          <Col span={14}>
            <Card title="属性" extra={<Button type="primary" icon={<SaveOutlined />} onClick={save} disabled={!selected}>保存</Button>}>
              <Form form={form} labelCol={{ span: 5 }} wrapperCol={{ span: 16 }}>
                <Form.Item label="标题" name="title" rules={[{ required: true, message: '请输入标题' }]}> 
                  <Input placeholder="标题" disabled={!selected} />
                </Form.Item>
                <Form.Item label="图标" name="icon">
                  <Select placeholder="选择图标" disabled={!selected} allowClear>
                    <Select.Option value="HomeOutlined"><HomeOutlined /> HomeOutlined</Select.Option>
                    <Select.Option value="FileTextOutlined"><FileTextOutlined /> FileTextOutlined</Select.Option>
                    <Select.Option value="FolderOutlined"><FolderOutlined /> FolderOutlined</Select.Option>
                    <Select.Option value="BookOutlined"><BookOutlined /> BookOutlined</Select.Option>
                    <Select.Option value="TagsOutlined"><TagsOutlined /> TagsOutlined</Select.Option>
                    <Select.Option value="ShoppingOutlined"><ShoppingOutlined /> ShoppingOutlined</Select.Option>
                    <Select.Option value="ShoppingCartOutlined"><ShoppingCartOutlined /> ShoppingCartOutlined</Select.Option>
                    <Select.Option value="TeamOutlined"><TeamOutlined /> TeamOutlined</Select.Option>
                    <Select.Option value="UserOutlined"><UserOutlined /> UserOutlined</Select.Option>
                    <Select.Option value="SettingOutlined"><SettingOutlined /> SettingOutlined</Select.Option>
                    <Select.Option value="ToolOutlined"><ToolOutlined /> ToolOutlined</Select.Option>
                    <Select.Option value="BellOutlined"><BellOutlined /> BellOutlined</Select.Option>
                    <Select.Option value="MessageOutlined"><MessageOutlined /> MessageOutlined</Select.Option>
                    <Select.Option value="CalendarOutlined"><CalendarOutlined /> CalendarOutlined</Select.Option>
                    <Select.Option value="ClockCircleOutlined"><ClockCircleOutlined /> ClockCircleOutlined</Select.Option>
                    <Select.Option value="BarChartOutlined"><BarChartOutlined /> BarChartOutlined</Select.Option>
                    <Select.Option value="LineChartOutlined"><LineChartOutlined /> LineChartOutlined</Select.Option>
                    <Select.Option value="PieChartOutlined"><PieChartOutlined /> PieChartOutlined</Select.Option>
                    <Select.Option value="StarOutlined"><StarOutlined /> StarOutlined</Select.Option>
                    <Select.Option value="HeartOutlined"><HeartOutlined /> HeartOutlined</Select.Option>
                  </Select>
                </Form.Item>
                <Form.Item label="排序" name="order"><InputNumber style={{ width: 120 }} disabled={!selected} /></Form.Item>
                <Form.Item label="显示" name="visible" valuePropName="checked"><Switch disabled={!selected} /></Form.Item>
                {selected && (
                  <>
                    <Form.Item label="Key"><Input value={selected.key} disabled /></Form.Item>
                    <Form.Item label="类型"><Input value={selected.target_type} disabled /></Form.Item>
                    {selected.target_type === 'group' && (
                      <Form.Item label="二级菜单">
                        <Checkbox.Group
                          value={editSelectedModels}
                          onChange={(values) => setEditSelectedModels(values as number[])}
                          disabled={!selected}
                        >
                          {editModels.map(model => (
                            <Checkbox key={model.id} value={model.id} disabled={model.disabled}>
                              {model.name} ({model.mold_type === 'list' ? '列表' : '单页'})
                            </Checkbox>
                          ))}
                        </Checkbox.Group>
                      </Form.Item>
                    )}
                  </>
                )}
              </Form>
            </Card>
          </Col>
        </Row>

        <Modal
          title="新增菜单"
          open={addModalVisible}
          afterOpenChange={(open) => {
            if (open) {
              // 打开弹窗时，重置新增菜单的选中状态，并加载所有模型（不排除任何菜单）
              setAddSelectedModels([]);
              loadModels(undefined, true);
            }
          }}
          onCancel={() => {
            setAddModalVisible(false);
            addForm.resetFields();
            setAddSelectedModels([]);
          }}
          onOk={handleAddMenu}
          okText="确定"
          cancelText="取消"
        >
          <Form form={addForm} labelCol={{ span: 5 }} wrapperCol={{ span: 16 }}>
            <Form.Item label="标题" name="title" rules={[{ required: true, message: '请输入标题' }]}>
              <Input placeholder="菜单标题" />
            </Form.Item>
            <Form.Item label="图标" name="icon">
              <Select placeholder="选择图标" allowClear>
                <Select.Option value="HomeOutlined"><HomeOutlined /> HomeOutlined</Select.Option>
                <Select.Option value="FileTextOutlined"><FileTextOutlined /> FileTextOutlined</Select.Option>
                <Select.Option value="FolderOutlined"><FolderOutlined /> FolderOutlined</Select.Option>
                <Select.Option value="BookOutlined"><BookOutlined /> BookOutlined</Select.Option>
                <Select.Option value="TagsOutlined"><TagsOutlined /> TagsOutlined</Select.Option>
                <Select.Option value="ShoppingOutlined"><ShoppingOutlined /> ShoppingOutlined</Select.Option>
                <Select.Option value="ShoppingCartOutlined"><ShoppingCartOutlined /> ShoppingCartOutlined</Select.Option>
                <Select.Option value="TeamOutlined"><TeamOutlined /> TeamOutlined</Select.Option>
                <Select.Option value="UserOutlined"><UserOutlined /> UserOutlined</Select.Option>
                <Select.Option value="SettingOutlined"><SettingOutlined /> SettingOutlined</Select.Option>
                <Select.Option value="ToolOutlined"><ToolOutlined /> ToolOutlined</Select.Option>
                <Select.Option value="BellOutlined"><BellOutlined /> BellOutlined</Select.Option>
                <Select.Option value="MessageOutlined"><MessageOutlined /> MessageOutlined</Select.Option>
                <Select.Option value="CalendarOutlined"><CalendarOutlined /> CalendarOutlined</Select.Option>
                <Select.Option value="ClockCircleOutlined"><ClockCircleOutlined /> ClockCircleOutlined</Select.Option>
                <Select.Option value="BarChartOutlined"><BarChartOutlined /> BarChartOutlined</Select.Option>
                <Select.Option value="LineChartOutlined"><LineChartOutlined /> LineChartOutlined</Select.Option>
                <Select.Option value="PieChartOutlined"><PieChartOutlined /> PieChartOutlined</Select.Option>
                <Select.Option value="StarOutlined"><StarOutlined /> StarOutlined</Select.Option>
                <Select.Option value="HeartOutlined"><HeartOutlined /> HeartOutlined</Select.Option>
              </Select>
            </Form.Item>
            <Form.Item label="排序" name="order" initialValue={0}>
              <InputNumber style={{ width: 120 }} />
            </Form.Item>
            <Form.Item label="显示" name="visible" valuePropName="checked" initialValue={true}>
              <Switch />
            </Form.Item>
            <Form.Item label="二级菜单（内容模型）">
              <Checkbox.Group
                value={addSelectedModels}
                onChange={(values) => setAddSelectedModels(values as number[])}
              >
                {addModels.map(model => (
                  <Checkbox key={model.id} value={model.id} disabled={model.disabled}>
                    {model.name} ({model.mold_type === 'list' ? '列表' : '单页'})
                  </Checkbox>
                ))}
              </Checkbox.Group>
            </Form.Item>
          </Form>
        </Modal>
      </div>
    </div>
  );
};

export default function Page() { return <MenusPage />; }
