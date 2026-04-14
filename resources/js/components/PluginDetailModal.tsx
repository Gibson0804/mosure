import React, { useState } from 'react';
import { Modal, Typography, Tag, Divider, Image, Button } from 'antd';

const { Text, Paragraph } = Typography;

interface PluginDetailModalProps {
  visible: boolean;
  plugin: any;
  onCancel: () => void;
  footer?: React.ReactNode;
}

const PluginDetailModal: React.FC<PluginDetailModalProps> = ({
  visible,
  plugin,
  onCancel,
  footer,
}) => {
  const [currentImageIndex, setCurrentImageIndex] = useState(0);

  if (!plugin) return null;

  const snapshotImages = plugin.snapshot_images || [];
  const hasSnapshots = snapshotImages.length > 0;

  const nextImage = () => {
    setCurrentImageIndex((prev) => (prev + 1) % snapshotImages.length);
  };

  const prevImage = () => {
    setCurrentImageIndex((prev) => (prev - 1 + snapshotImages.length) % snapshotImages.length);
  };

  return (
    <Modal
      title={plugin.name}
      open={visible}
      onCancel={onCancel}
      footer={footer}
      width={1000}
    >
      <div style={{ display: 'flex', gap: 24 }}>
        {/* 左侧：插件信息 */}
        <div style={{ flex: 1 }}>
          <Paragraph>
            <Text strong>描述：</Text>
            <br />
            {plugin.description}
          </Paragraph>

          <div style={{ marginTop: 16 }}>
            <Text strong>版本：</Text>
            <Text>{plugin.version}</Text>
          </div>

          <div style={{ marginTop: 8 }}>
            <Text strong>作者：</Text>
            <Text>{plugin.author}</Text>
          </div>

          {plugin.installed && (
            <>
              <div style={{ marginTop: 8 }}>
                <Text strong>状态：</Text>
                <Tag color={plugin.status === 'enabled' ? 'green' : 'red'}>
                  {plugin.status === 'enabled' ? '已启用' : '已禁用'}
                </Tag>
              </div>

              <div style={{ marginTop: 8 }}>
                <Text strong>安装时间：</Text>
                <Text>{plugin.installed_at}</Text>
              </div>
            </>
          )}

          {plugin.is_installed && (
            <>
              <div style={{ marginTop: 8 }}>
                <Text strong>状态：</Text>
                <Tag color="green">已安装</Tag>
              </div>

              <div style={{ marginTop: 8 }}>
                <Text strong>安装版本：</Text>
                <Text>{plugin.installed_version}</Text>
              </div>
            </>
          )}

          <Divider />

          <div style={{ marginTop: 16 }}>
            <Text strong>提供的资源：</Text>
            <ul>
              <li>内容模型：{plugin.provides?.models_count || 0} 个</li>
              <li>Web 函数：{plugin.provides?.web_functions_count || 0} 个</li>
              <li>触发函数：{plugin.provides?.trigger_functions_count || 0} 个</li>
              <li>菜单：{plugin.provides?.menus ? '是' : '否'}</li>
              <li>配置变量：{plugin.provides?.variables ? '是' : '否'}</li>
              <li>定时任务：{plugin.provides?.schedules ? '是' : '否'}</li>
              <li>前端页面：{plugin.has_frontend ? '是' : '否'}</li>
            </ul>
          </div>

          <div style={{ marginTop: 16 }}>
            <Text strong>标签：</Text>
            <div style={{ marginTop: 8 }}>
              {(plugin.tags || []).map((tag: string) => (
                <Tag key={tag}>{tag}</Tag>
              ))}
            </div>
          </div>

          {plugin.homepage && (
            <div style={{ marginTop: 16 }}>
              <Text strong>主页：</Text>
              <br />
              <a href={plugin.homepage} target="_blank" rel="noopener noreferrer">
                {plugin.homepage}
              </a>
            </div>
          )}
        </div>

        {/* 右侧：图片展示 */}
        {hasSnapshots && (
          <div style={{ width: 400 }}>
            <div style={{ marginBottom: 16 }}>
              <Text strong>插件截图：</Text>
            </div>
            <div style={{ position: 'relative' }}>
              <img
                src={snapshotImages[currentImageIndex]?.url}
                alt={`插件截图 ${currentImageIndex + 1}`}
                style={{
                  width: '100%',
                  maxWidth: '100%',
                  maxHeight: 300,
                  borderRadius: 8,
                  cursor: 'pointer',
                  display: 'block',
                  margin: '0 auto',
                  objectFit: 'contain',
                }}
                onClick={() => {
                  const img = document.createElement('img');
                  img.src = snapshotImages[currentImageIndex]?.url;
                  const modal = document.createElement('div');
                  modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);display:flex;align-items:center;justify-content:center;z-index:9999;cursor:zoom-out;';
                  modal.onclick = () => document.body.removeChild(modal);
                  img.style.cssText = 'max-width:90%;max-height:90%;';
                  modal.appendChild(img);
                  document.body.appendChild(modal);
                }}
              />
              
              {snapshotImages.length > 1 && (
                <>
                  <Button
                    type="primary"
                    shape="circle"
                    icon="←"
                    onClick={prevImage}
                    disabled={snapshotImages.length <= 1}
                    style={{
                      position: 'absolute',
                      left: 8,
                      top: '50%',
                      transform: 'translateY(-50%)',
                      zIndex: 1,
                    }}
                  />
                  <Button
                    type="primary"
                    shape="circle"
                    icon="→"
                    onClick={nextImage}
                    disabled={snapshotImages.length <= 1}
                    style={{
                      position: 'absolute',
                      right: 8,
                      top: '50%',
                      transform: 'translateY(-50%)',
                      zIndex: 1,
                    }}
                  />
                </>
              )}
            </div>

            {snapshotImages.length > 1 && (
              <div style={{ marginTop: 12, textAlign: 'center' }}>
                <Text type="secondary">
                  {currentImageIndex + 1} / {snapshotImages.length}
                </Text>
              </div>
            )}

            <div style={{ marginTop: 12 }}>
              {snapshotImages.map((image: any, index: number) => (
                <img
                  key={image.name}
                  src={image.url}
                  alt={image.name}
                  style={{
                    width: 60,
                    height: 60,
                    objectFit: 'cover',
                    borderRadius: 4,
                    margin: '0 4px',
                    cursor: 'pointer',
                    border: index === currentImageIndex ? '2px solid #1890ff' : '2px solid transparent',
                  }}
                  onClick={() => setCurrentImageIndex(index)}
                />
              ))}
            </div>
          </div>
        )}
      </div>
    </Modal>
  );
};

export default PluginDetailModal;
