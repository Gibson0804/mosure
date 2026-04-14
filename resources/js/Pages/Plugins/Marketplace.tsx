import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import { Row, Button, message, Spin, Space, Typography, Input, Empty, Modal } from 'antd';
import { SearchOutlined, DownloadOutlined, ReloadOutlined } from '@ant-design/icons';
import api from '../../util/Service';
import PluginCard from '../../components/PluginCard';
import PluginDetailModal from '../../components/PluginDetailModal';

const { Title, Paragraph, Text } = Typography;
const { Search } = Input;

interface MarketplacePlugin {
  id: string;
  name: string;
  description: string;
  author: string;
  version: string;
  versions: string[];
  latest: string;
  icon?: string;
  homepage?: string;
  tags?: string[];
  has_frontend?: boolean;
  has_src?: boolean;
  provides?: {
    models_count?: number;
    web_functions_count?: number;
    trigger_functions_count?: number;
    menus?: boolean;
    variables?: boolean;
    schedules?: boolean;
  };
  is_installed: boolean;
  installed_version?: string;
  can_update: boolean;
  is_downloaded: boolean;
  downloaded_version?: string;
  can_upgrade_download: boolean;
}

export default function PluginMarketplace() {
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [plugins, setPlugins] = useState<MarketplacePlugin[]>([]);
  const [searchKeyword, setSearchKeyword] = useState('');
  const [selectedPlugin, setSelectedPlugin] = useState<MarketplacePlugin | null>(null);
  const [detailModalVisible, setDetailModalVisible] = useState(false);

  useEffect(() => {
    loadPlugins();
  }, []);

  const loadPlugins = async (keyword: string = '') => {
    setLoading(true);
    try {
      const url = keyword
        ? `/plugins/marketplace/search?keyword=${encodeURIComponent(keyword)}`
        : '/plugins/marketplace/list';
      const response = await api.get(url);

      if (response.code === 0) {
        setPlugins(response.data || []);
      } else {
        message.error(response.message || '加载失败');
      }
    } catch (error: any) {
      message.error('加载失败: ' + (error.message || '未知错误'));
    } finally {
      setLoading(false);
    }
  };

  const handleDownload = (pluginId: string) => {
    Modal.confirm({
      title: '确认下载',
      content: '确定要下载此插件吗？',
      onOk: async () => {
        try {
          const response = await api.post('/plugins/marketplace/download', {
            plugin_id: pluginId,
          });

          if (response.code === 0) {
            message.success(response.message || '下载成功');
            loadPlugins(searchKeyword);
          } else {
            message.error(response.message || '下载失败');
          }
        } catch (error: any) {
          if(error.message  && error.message == '插件文件列表为空') {
            // 频率限制错误
            Modal.error({
              title: '访问频率超限',
              content: (
                <div>
                  <p>Gitee API 访问频率超限，请稍后再试。</p>
                  <p>您也可以在 .env 文件中配置 GITEE_ACCESS_TOKEN 来提高访问频率限制。</p>
                </div>
              ),
            });
          } else {
            message.error('下载失败: ' + (error.message || '未知错误'));
          }
        }
      },
    });
  };

  const handleSearch = (value: string) => {
    setSearchKeyword(value);
    loadPlugins(value);
  };

  const handleInstall = async (pluginId: string) => {
    Modal.confirm({
      title: '确认安装',
      content: `确定要安装插件 "${pluginId}" 吗？`,
      onOk: async () => {
        try {
          const response = await api.post('/plugins/marketplace/install', {
            plugin_id: pluginId,
          });

          if (response.code === 0) {
            message.success('插件安装成功');
            loadPlugins(searchKeyword);
          } else {
            message.error(response.message || '安装失败');
          }
        } catch (error: any) {
          message.error('安装失败: ' + (error.message || '未知错误'));
        }
      },
    });
  };

  const handleUpdate = async (pluginId: string) => {
    Modal.confirm({
      title: '确认更新',
      content: `确定要更新插件 "${pluginId}" 吗？`,
      onOk: async () => {
        try {
          const response = await api.post('/plugins/marketplace/update', {
            plugin_id: pluginId,
          });

          if (response.code === 0) {
            message.success('插件更新成功');
            loadPlugins(searchKeyword);
          } else {
            message.error(response.message || '更新失败');
          }
        } catch (error: any) {
          message.error('更新失败: ' + (error.message || '未知错误'));
        }
      },
    });
  };

  const showDetail = (pluginId: string) => {
    const plugin = plugins.find(p => p.id === pluginId);
    if (plugin) {
      setSelectedPlugin(plugin);
      setDetailModalVisible(true);
    }
  };

  const handleClearCache = async () => {
    setRefreshing(true);
    try {
      const response = await api.post('/plugins/marketplace/clear-cache');
      if (response.code === 0) {
        message.success('缓存清除成功');
        loadPlugins(searchKeyword);
      } else {
        message.error(response.message || '清除缓存失败');
      }
    } catch (error: any) {
      message.error('清除缓存失败: ' + (error.message || '未知错误'));
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <>
      <Head title="插件市场" />
      <div style={{ padding: '24px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <Title level={2}>插件市场</Title>
          <Button
            icon={<ReloadOutlined />}
            loading={refreshing}
            onClick={handleClearCache}
          >
            刷新缓存
          </Button>
        </div>
        <Paragraph type="secondary">
          从 Gitee 仓库浏览和安装插件
        </Paragraph>

        <Space style={{ marginBottom: '24px' }}>
          <Search
            placeholder="搜索插件名称、描述或作者"
            allowClear
            enterButton={<SearchOutlined />}
            style={{ width: 400 }}
            onSearch={handleSearch}
            onChange={(e) => setSearchKeyword(e.target.value)}
          />
        </Space>

        {loading ? (
          <div style={{ textAlign: 'center', padding: '100px 0' }}>
            <Spin size="large" />
          </div>
        ) : plugins.length === 0 ? (
          <Empty description="暂无插件" />
        ) : (
          <Row gutter={[16, 16]}>
            {plugins.map((plugin) => (
              <PluginCard
                key={plugin.id}
                plugin={plugin}
                onDownload={(pluginId) => handleDownload(pluginId)}
                onUpdate={(pluginId) => handleUpdate(pluginId)}
                onDetail={(pluginId) => showDetail(pluginId)}
              />
            ))}
          </Row>
        )}
      </div>

      <PluginDetailModal
        visible={detailModalVisible}
        plugin={selectedPlugin}
        onCancel={() => setDetailModalVisible(false)}
        footer={[
          <Button key="close" onClick={() => setDetailModalVisible(false)}>
            关闭
          </Button>,
          selectedPlugin?.is_installed ? (
            selectedPlugin.can_update ? (
              <Button
                key="update"
                type="primary"
                icon={<DownloadOutlined />}
                onClick={() => {
                  handleUpdate(selectedPlugin.id);
                  setDetailModalVisible(false);
                }}
              >
                更新到 {selectedPlugin.latest}
              </Button>
            ) : null
          ) : (
            <Button
              key="install"
              type="primary"
              icon={<DownloadOutlined />}
              onClick={() => {
                handleInstall(selectedPlugin.id);
                setDetailModalVisible(false);
              }}
            >
              安装
            </Button>
          ),
        ]}
      />
    </>
  );
}
