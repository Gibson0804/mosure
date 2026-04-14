import React, { useState, useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Tabs, Row, Modal, message, Spin, Space, Typography, Form, Radio, Input, Upload, Table, Button, Tag, Divider } from 'antd';
import {
  UploadOutlined,
  ExclamationCircleOutlined,
} from '@ant-design/icons';
import type { UploadProps } from 'antd';
import api from '../../util/Service';
import PluginCard from '../../components/PluginCard';
import PluginDetailModal from '../../components/PluginDetailModal';

const { Title, Paragraph, Text } = Typography;
const { confirm } = Modal;

interface Plugin {
  id: string;
  name: string;
  version: string;
  description: string;
  author: string;
  icon: string | null;
  type: string;
  tags: string[];
  installed: boolean;
  status: 'enabled' | 'disabled' | null;
  installed_version: string | null;
  installed_at: string | null;
  has_frontend?: boolean;
  has_src?: boolean;
  provides: {
    models_count: number;
    web_functions_count: number;
    trigger_functions_count: number;
    menus: boolean;
    variables: boolean;
    schedules: boolean;
  };
}

interface UsePageReturnType {
  props: {
    project_info?: {
      prefix?: string;
    };
  };
}

const PluginsPage: React.FC = () => {
  const page = usePage() as UsePageReturnType;
  const projectPrefix = page.props.project_info?.prefix || '';
  const [loading, setLoading] = useState(false);
  const [plugins, setPlugins] = useState<Plugin[]>([]);
  const [activeTab, setActiveTab] = useState('all');
  const [detailModalVisible, setDetailModalVisible] = useState(false);
  const [selectedPlugin, setSelectedPlugin] = useState<Plugin | null>(null);
  const [installModalVisible, setInstallModalVisible] = useState(false);
  const [installTarget, setInstallTarget] = useState<Plugin | null>(null);
  const [uploadModalVisible, setUploadModalVisible] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [checkingConflicts, setCheckingConflicts] = useState(false);
  const [conflictData, setConflictData] = useState<any>(null);

  useEffect(() => {
    loadPlugins();
  }, []);

  const loadPlugins = async () => {
    setLoading(true);
    try {
      const response = await api.get('/plugins/list');
      setPlugins(response.data || []);
    } catch (error: any) {
      message.error('获取插件列表失败：' + (error.msg || error.message || '未知错误'));
    } finally {
      setLoading(false);
    }
  };

  const openInstall = async (plugin: Plugin) => {
    setInstallTarget(plugin);
    setInstallModalVisible(true);
    setConflictData(null);
    
    // 检测冲突
    setCheckingConflicts(true);
    try {
      const response = await api.post('/plugins/check-conflicts', {
        plugin_id: plugin.id,
      });
      if (response.data && response.data.conflicts) {
        setConflictData(response.data);
      }
    } catch (error: any) {
      console.error('检测冲突失败:', error);
    } finally {
      setCheckingConflicts(false);
    }
  };

  const submitInstall = async () => {
    try {
      if (!installTarget) return;
      await api.post('/plugins/install', {
        plugin_id: installTarget.id,
      });
      message.success('插件安装成功');
      setInstallModalVisible(false);
      setInstallTarget(null);
      setConflictData(null);
      window.location.reload();
    } catch (error: any) {
      message.error('插件安装失败：' + (error.msg || error.message || '未知错误'));
    }
  };

  const handleUninstall = async (pluginId: string, removeData: boolean = false) => {
    confirm({
      title: removeData ? '确认完全卸载' : '确认卸载',
      icon: <ExclamationCircleOutlined />,
      content: removeData
        ? '确定要完全卸载此插件吗？这将删除插件创建的所有数据，包括模型、表结构、内容等，此操作不可恢复！'
        : '确定要卸载此插件吗？插件创建的数据将被保留。',
      okText: '确定',
      okType: removeData ? 'danger' : 'primary',
      cancelText: '取消',
      onOk: async () => {
        try {
          await api.post('/plugins/uninstall', {
            plugin_id: pluginId,
            remove_data: removeData,
          });
          message.success('插件卸载成功');
          window.location.reload();
        } catch (error: any) {
          message.error('插件卸载失败：' + (error.msg || error.message || '未知错误'));
        }
      },
    });
  };

  const handleDelete = async (pluginId: string) => {
    Modal.confirm({
      title: '删除插件',
      content: '确定要删除此插件吗？删除后插件目录将被永久删除。',
      okText: '确定',
      cancelText: '取消',
      okType: 'danger',
      onOk: async () => {
        try {
          await api.delete('/plugins/delete', {
            params: { plugin_id: pluginId }
          });
          message.success('插件删除成功');
          loadPlugins();
        } catch (error: any) {
          message.error('删除插件失败：' + (error.msg || error.message || '未知错误'));
        }
      }
    });
  };

  // 已移除启用/禁用逻辑与操作按钮

  const showDetail = async (pluginId: string) => {
    try {
      const response = await api.get('/plugins/detail?id=' + pluginId);
      setSelectedPlugin(response.data);
      setDetailModalVisible(true);
    } catch (error: any) {
      message.error('获取插件详情失败：' + (error.msg || error.message || '未知错误'));
    }
  };

  const handleUpload: UploadProps['customRequest'] = async (options) => {
    const { file, onSuccess, onError } = options;
    const formData = new FormData();
    formData.append('file', file);

    setUploading(true);
    try {
      const response = await api.post('/plugins/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      message.success('插件上传成功');
      onSuccess?.(response.data);
      setUploadModalVisible(false);
      loadPlugins();
    } catch (error: any) {
      message.error('插件上传失败：' + (error.msg || error.message || '未知错误'));
      onError?.(error);
    } finally {
      setUploading(false);
    }
  };

  const previewFrontend = (plugin: Plugin) => {
    if (!plugin.has_frontend) {
      message.warning('该插件未提供前端页面');
      return;
    }
    if (!projectPrefix) {
      message.warning('未获取到项目前缀');
      return;
    }
    // 未安装
    if (!plugin.installed) {
      message.warning('插件未安装');
      return;
    }
    const url = `/frontend/${projectPrefix}/${plugin.id}/dist/index.html`;
    window.open(url, '_blank');
  };

  const filteredPlugins = plugins.filter((plugin) => {
    if (activeTab === 'all') return true;
    if (activeTab === 'installed') return plugin.installed;
    if (activeTab === 'available') return !plugin.installed;
    return true;
  });

  return (
    <div>
      <Head title="插件管理" />
      <div style={{ padding: 24 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <Title level={2}>插件管理</Title>
          <Button
            type="primary"
            icon={<UploadOutlined />}
            onClick={() => setUploadModalVisible(true)}
          >
            上传插件
          </Button>
        </div>
        <Paragraph>
          通过插件扩展系统功能，插件可以提供模型、云函数、菜单等资源。
        </Paragraph>

        <Tabs
          activeKey={activeTab}
          onChange={setActiveTab}
          style={{ marginTop: 24 }}
          items={[
            {
              key: 'all',
              label: `全部 (${plugins.length})`
            },
            {
              key: 'installed',
              label: `已安装 (${plugins.filter((p) => p.installed).length})`
            },
            {
              key: 'available',
              label: `可用 (${plugins.filter((p) => !p.installed).length})`
            }
          ]}
        />

        <Spin spinning={loading}>
          <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
            {filteredPlugins.map((plugin) => (
              <PluginCard
                key={plugin.id}
                plugin={plugin}
                onInstall={(pluginId) => openInstall(plugin)}
                onUninstall={(pluginId) => handleUninstall(pluginId, true)}
                onDelete={(pluginId) => handleDelete(pluginId)}
                onDetail={(pluginId) => showDetail(pluginId)}
                onPreviewFrontend={(plugin) => previewFrontend(plugin)}
              />
            ))}
          </Row>
        </Spin>

        <PluginDetailModal
          visible={detailModalVisible}
          plugin={selectedPlugin}
          onCancel={() => setDetailModalVisible(false)}
          footer={null}
        />

        <Modal
          title="安装插件"
          open={installModalVisible}
          onCancel={() => {
            setInstallModalVisible(false);
            setInstallTarget(null);
            setConflictData(null);
          }}
          onOk={submitInstall}
          okText="安装"
          okButtonProps={{
            disabled: conflictData && conflictData.conflicts && Object.keys(conflictData.conflicts).length > 0,
          }}
          destroyOnHidden
          width={800}
        >
          <Spin spinning={checkingConflicts}>
            {installTarget && (
              <div>
                {conflictData && conflictData.conflicts && Object.keys(conflictData.conflicts).length > 0 && (
                  <>
                    <p style={{ marginBottom: 16, color: '#faad14' }}>
                      检测到以下名称冲突，请手动处理：
                    </p>
                    <Table
                      dataSource={Object.entries(conflictData.conflicts).map(([key, items]: [string, any]) => ({
                        key,
                        type: key === 'models' ? '模型' : key === 'menus' ? '菜单' : key,
                        items: items.join(', '),
                      }))}
                      columns={[
                        {
                          title: '类型',
                          dataIndex: 'type',
                          key: 'type',
                          width: 120,
                        },
                        {
                          title: '冲突项',
                          dataIndex: 'items',
                          key: 'items',
                        },
                      ]}
                      pagination={false}
                      size="small"
                      style={{ marginBottom: 16 }}
                    />
                    <Divider />
                  </>
                )}
                <Paragraph>
                  即将安装插件：<Text strong>{installTarget.name}</Text>
                </Paragraph>
                <Paragraph>
                  版本：<Tag>{installTarget.version}</Tag>
                </Paragraph>
                <Divider />
                <Title level={5}>插件清单</Title>
                <ul style={{ paddingLeft: 20 }}>
                  {installTarget.provides.models_count > 0 && (
                    <li style={{ marginBottom: 8 }}>
                      <Text>内容模型：{installTarget.provides.models_count} 个</Text>
                    </li>
                  )}
                  {installTarget.provides.web_functions_count > 0 && (
                    <li style={{ marginBottom: 8 }}>
                      <Text>Web 函数：{installTarget.provides.web_functions_count} 个</Text>
                    </li>
                  )}
                  {installTarget.provides.trigger_functions_count > 0 && (
                    <li style={{ marginBottom: 8 }}>
                      <Text>触发函数：{installTarget.provides.trigger_functions_count} 个</Text>
                    </li>
                  )}
                  {installTarget.provides.menus && (
                    <li style={{ marginBottom: 8 }}>
                      <Text>菜单：是</Text>
                    </li>
                  )}
                  {installTarget.provides.variables && (
                    <li style={{ marginBottom: 8 }}>
                      <Text>配置变量：是</Text>
                    </li>
                  )}
                  {installTarget.provides.schedules && (
                    <li style={{ marginBottom: 8 }}>
                      <Text>定时任务：是</Text>
                    </li>
                  )}
                  {installTarget.has_frontend && (
                    <li style={{ marginBottom: 8 }}>
                      <Text>前端页面：是</Text>
                    </li>
                  )}
                  {installTarget.has_src && (
                    <li style={{ marginBottom: 8 }}>
                      <Text>PHP 源代码：是</Text>
                    </li>
                  )}
                </ul>
                <Divider />
                <Paragraph type="secondary">
                  {conflictData && conflictData.conflicts && Object.keys(conflictData.conflicts).length > 0
                    ? '检测到名称冲突，安装按钮已禁用。请手动处理冲突后再安装。'
                    : '点击"安装"后，系统将自动安装上述所有资源到当前项目。'
                  }
                </Paragraph>
              </div>
            )}
          </Spin>
        </Modal>

        <Modal
          title="上传插件"
          open={uploadModalVisible}
          onCancel={() => setUploadModalVisible(false)}
          footer={null}
        >
          <div style={{ padding: '20px 0' }}>
            <Paragraph>
              上传包含 plugin.json 的 ZIP 文件，插件将自动安装到系统中。
            </Paragraph>
            <Upload.Dragger
              name="file"
              accept=".zip"
              customRequest={handleUpload}
              showUploadList={false}
              disabled={uploading}
            >
              <p className="ant-upload-drag-icon">
                <UploadOutlined style={{ fontSize: 48 }} />
              </p>
              <p className="ant-upload-text">点击或拖拽文件到此区域上传</p>
              <p className="ant-upload-hint">
                仅支持 ZIP 格式，最大 100MB
              </p>
            </Upload.Dragger>
          </div>
        </Modal>
      </div>
    </div>
  );
};

export default function Page() {
  return <PluginsPage />;
}
