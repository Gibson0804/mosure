import React from 'react';
import { Card, Col, Button, Tag, Space, Typography, message, Popconfirm } from 'antd';
import {
  AppstoreOutlined,
  DownloadOutlined,
  DeleteOutlined,
  InfoCircleOutlined,
  CheckCircleOutlined,
  ExclamationCircleOutlined,
} from '@ant-design/icons';

const { Text, Paragraph } = Typography;

interface PluginCardProps {
  plugin: {
    id: string;
    name: string;
    version: string;
    description: string;
    author: string;
    icon?: string | null;
    installed?: boolean;
    is_installed?: boolean;
    is_downloaded?: boolean;
    downloaded_version?: string;
    installed_version?: string | null;
    can_update?: boolean;
    can_upgrade_download?: boolean;
    status?: 'enabled' | 'disabled' | null;
    tags?: string[];
    provides?: {
      models_count?: number;
      web_functions_count?: number;
      trigger_functions_count?: number;
      menus?: boolean;
      variables?: boolean;
      schedules?: boolean;
    };
    has_frontend?: boolean;
  };
  onDownload?: (pluginId: string) => void;
  onInstall?: (pluginId: string) => void;
  onUpdate?: (pluginId: string) => void;
  onUninstall?: (pluginId: string) => void;
  onDelete?: (pluginId: string) => void;
  onDetail?: (pluginId: string) => void;
  onPreviewFrontend?: (plugin: any) => void;
}

const PluginCard: React.FC<PluginCardProps> = ({
  plugin,
  onDownload,
  onInstall,
  onUpdate,
  onUninstall,
  onDelete,
  onDetail,
  onPreviewFrontend,
}) => {
  const isInstalled = plugin.installed || plugin.is_installed;
  const isDownloaded = plugin.is_downloaded;
  const canUpdate = plugin.can_update;
  const canUpgradeDownload = plugin.can_upgrade_download;

  // 决定显示哪个按钮
  const renderActionButton = () => {
    // 如果已安装且可以更新
    if (isInstalled && canUpdate && onUpdate) {
      return (
        <Button
          type="primary"
          icon={<DownloadOutlined />}
          onClick={() => onUpdate(plugin.id)}
        >
          更新
        </Button>
      );
    }
    
    // 如果已安装但不需要更新
    if (isInstalled && onUninstall) {
      return (
        <Button
          danger
          icon={<DeleteOutlined />}
          onClick={() => onUninstall(plugin.id)}
        >
          卸载
        </Button>
      );
    }

    // 如果已下载且可以升级下载
    if (isDownloaded && canUpgradeDownload && onDownload) {
      return (
        <Button
          type="primary"
          icon={<DownloadOutlined />}
          onClick={() => onDownload(plugin.id)}
        >
          升级下载
        </Button>
      );
    }

    // 如果已下载但不需要升级
    if (isDownloaded) {
      return (
        <Button
          type="default"
          disabled
          style={{ cursor: 'not-allowed' }}
        >
          已下载
        </Button>
      );
    }

    // 如果未下载
    if (onDownload) {
      return (
        <Button
          type="primary"
          icon={<DownloadOutlined />}
          onClick={() => onDownload(plugin.id)}
        >
          下载
        </Button>
      );
    }

    // 如果没有下载按钮但有安装按钮（插件管理页面）
    if (onInstall) {
      return (
        <Button
          type="primary"
          icon={<DownloadOutlined />}
          onClick={() => onInstall(plugin.id)}
        >
          安装
        </Button>
      );
    }

    return null;
  };

  return (
    <Col xs={24} sm={12} lg={8} xl={6} key={plugin.id}>
      <Card
        hoverable
        style={{
          height: '100%',
          position: 'relative',
          display: 'flex',
          flexDirection: 'column',
          cursor: 'default'
        }}
        styles={{ body: { flex: 1, display: 'flex', flexDirection: 'column' } }}
        cover={
          <div
            style={{
              height: 66,
              background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            <AppstoreOutlined style={{ fontSize: 48, color: 'white' }} />
          </div>
        }
        actions={[
          renderActionButton(),
          onDetail ? (
            <Button icon={<InfoCircleOutlined />} onClick={() => onDetail(plugin.id)}>
              详情
            </Button>
          ) : null,
          onDelete && !isInstalled ? (
            <Button danger icon={<DeleteOutlined />} onClick={() => onDelete(plugin.id)}>
              删除
            </Button>
          ) : null,
        ].filter(Boolean)}
      >
        {isInstalled && !canUpdate && plugin.status === 'enabled' && (
          <div style={{ position: 'absolute', top: 8, right: 8 }}>
            <Tag color="green">已启用</Tag>
          </div>
        )}
        {isInstalled && !canUpdate && !plugin.status && (
          <div style={{ position: 'absolute', top: 8, right: 8 }}>
            <Tag color="green">已安装</Tag>
          </div>
        )}
        {isDownloaded && !isInstalled && (
          <div style={{ position: 'absolute', top: 8, right: 8 }}>
            <Tag color="blue">已下载</Tag>
          </div>
        )}
        <Card.Meta
          title={
            <Space>
              {plugin.name}
              <Tag color="blue">{plugin.version}</Tag>
            </Space>
          }
          description={
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
              <Paragraph ellipsis={{ rows: 2 }} style={{ marginBottom: 8 }}>
                {plugin.description}
              </Paragraph>
              <div>
                {plugin.tags && plugin.tags.map((tag) => (
                  <Tag key={tag} style={{ marginBottom: 4 }}>
                    {tag}
                  </Tag>
                ))}
              </div>
              <div style={{ marginTop: 8, fontSize: 12, color: '#888' }}>
                {plugin.provides?.models_count && plugin.provides.models_count > 0 && (
                  <div>内容模型: {plugin.provides.models_count}</div>
                )}
                {plugin.provides?.web_functions_count && plugin.provides.web_functions_count > 0 && (
                  <div>Web函数: {plugin.provides.web_functions_count}</div>
                )}
                {plugin.provides?.trigger_functions_count && plugin.provides.trigger_functions_count > 0 && (
                  <div>触发函数: {plugin.provides.trigger_functions_count}</div>
                )}
                {plugin.has_frontend && onPreviewFrontend && (
                  <div
                    style={{ cursor: 'pointer' }}
                    onClick={() => {
                      const isInstalled = plugin.installed || plugin.is_installed;
                      if (!isInstalled) {
                        message.warning('插件未安装');
                        return;
                      }
                      onPreviewFrontend(plugin);
                    }}
                  >
                    前端页面：<span style={{ color: '#1890ff' }}>点击跳转</span>
                  </div>
                )}
              </div>
            </div>
          }
        />
      </Card>
    </Col>
  );
};

export default PluginCard;
