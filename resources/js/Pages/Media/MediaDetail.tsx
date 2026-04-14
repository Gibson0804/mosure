import React from 'react';
import { usePage } from '@inertiajs/react';
import { 
  Card, Typography, Breadcrumb, Row, Col, Button, 
  Descriptions, Tag, Image, Space, Divider, 
  Avatar, Tooltip, message
} from 'antd';
import { 
  EditOutlined, DownloadOutlined, DeleteOutlined,
  RollbackOutlined, PlayCircleOutlined,
  EyeOutlined
} from '@ant-design/icons';
import { getMediaTypeConfig, isImage, isVideo, isAudio } from './mediaTypes';
import { Link } from '@inertiajs/react';
import Layout from '../../Layouts/BaseLayout';
import { MEDIA_TAG_ROUTES } from '../../Constants/routes';
import api from '../../util/Service';

const { Title } = Typography;

interface Media {
  id: number;
  filename: string;
  original_filename: string;
  mime_type: string;
  extension: string;
  path: string;
  url: string;
  thumbnail_url?: string;
  size: number;
  type: string;
  description: string;
  created_at: string;
  updated_at: string;
  readable_size: string;
  is_image: boolean;
  is_video: boolean;
  icon: string;
  folder?: {
    id: number;
    name: string;
    full_path?: string;
  } | null;
  tags?: string[];
  metadata: {
    width?: number;
    height?: number;
    [key: string]: any;
  };
  user?: {
    id: number;
    name: string;
  };
}

const MediaDetail: React.FC = () => {
  const { media } = usePage<{ media: Media }>().props;
  const videoRef = React.useRef<HTMLVideoElement>(null);
  const [isPlaying, setIsPlaying] = React.useState(false);
  const [allTags, setAllTags] = React.useState<Array<{ id: number; name: string; color: string }>>([]);
  const mediaType = getMediaTypeConfig(media.type);

  React.useEffect(() => {
    api.get(MEDIA_TAG_ROUTES.list)
      .then((res) => {
        setAllTags(res.data || []);
      })
      .catch(() => {});
  }, []);

  // Removed custom play/pause handling to use native controls only

  return (
    <div>
      <div style={{ padding: '0 24px', minHeight: 280 }}>
        <Breadcrumb 
          style={{ margin: '16px 0' }}
          items={[
            { title: '媒体资源', href: '/media' },
            { title: '媒体详情' }
          ]}
        />
        
        <Card>

          <Row justify="space-between" align="middle" style={{ marginBottom: 16 }}>
            <Col>
              <Title level={4}>
                {mediaType.icon} {media.original_filename}
              </Title>
            </Col>
            <Col>
              <Space>
                <Link href={`/media/edit/${media.id}`}>
                  <Button type="primary" icon={<EditOutlined />}>编辑</Button>
                </Link>
                <Button 
                  icon={<DownloadOutlined />}
                  onClick={() => {
                    const link = document.createElement('a');
                    link.href = media.url;
                    link.download = media.original_filename || media.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    message.success('开始下载');
                  }}
                >
                  下载
                </Button>
                <Button 
                  danger 
                  icon={<DeleteOutlined />}
                  onClick={() => {
                    // 这里添加删除确认和删除逻辑
                    message.warning('删除功能待实现');
                  }}
                >
                  删除
                </Button>
                <Link href="/media">
                  <Button icon={<RollbackOutlined />}>返回列表</Button>
                </Link>
              </Space>
            </Col>
          </Row>
          
          <Divider />
          
          {media.is_image && (
            <Row justify="center" style={{ marginBottom: 24 }}>
              <Col>
                <Image
                  src={media.url}
                  alt={media.original_filename}
                  style={{ maxWidth: '100%', maxHeight: '400px' }}
                  fallback="data:image/png;base64,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"
                />
              </Col>
            </Row>
          )}
          
          {media.is_video && (
            <Row justify="center" style={{ marginBottom: 24 }}>
              <Col>
                <video 
                  controls 
                  style={{ maxWidth: '100%', maxHeight: '400px' }}
                  src={media.url}
                >
                  您的浏览器不支持视频标签
                </video>
              </Col>
            </Row>
          )}
          
          <Descriptions bordered column={2}>
            <Descriptions.Item label="文件名">
              <Tooltip title={media.original_filename}>
                <span style={{ display: 'inline-block', maxWidth: '300px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                  {media.original_filename}
                </span>
              </Tooltip>
            </Descriptions.Item>
            <Descriptions.Item label="文件类型">
              <Tag color={mediaType.color}>
                {mediaType.icon}
                {/* 空格间隙 */}
                &nbsp;
                {media.mime_type}
              </Tag>
            </Descriptions.Item>
            <Descriptions.Item label="文件大小">{media.readable_size}</Descriptions.Item>
            <Descriptions.Item label="上传时间">{new Date(media.created_at).toLocaleString()}</Descriptions.Item>
            <Descriptions.Item label="最后修改">{new Date(media.updated_at).toLocaleString()}</Descriptions.Item>
            <Descriptions.Item label="上传用户">
              {media.user ? (
                <Tag color="blue">{media.user.name}</Tag>
              ) : isAudio(media.type) ? (
                <div style={{ textAlign: 'center', width: '100%' }}>
                  <div style={{ fontSize: '64px', marginBottom: '24px' }}>
                    {mediaType.icon}
                  </div>
                  <audio
                    src={media.url}
                    controls
                    style={{ width: '80%' }}
                  />
                </div>
              ) : (
                <Tag>系统</Tag>
              )}
            </Descriptions.Item>
            {media.is_image && media.metadata?.width && media.metadata?.height && (
              <Descriptions.Item label="图片尺寸">
                {media.metadata.width} × {media.metadata.height} 像素
              </Descriptions.Item>
            )}
            <Descriptions.Item label="所在文件夹" span={2}>
              {media.folder ? (
                <span style={{ color: '#666' }}>{media.folder.full_path || media.folder.name}</span>
              ) : (
                <span style={{ color: '#999' }}>-</span>
              )}
            </Descriptions.Item>
            {media.tags && media.tags.length > 0 && (
              <Descriptions.Item label="标签" span={2}>
                {media.tags.map((tagName, idx) => {
                  const tag = allTags.find(t => t.name === tagName);
                  return (
                    <Tag key={idx} color={tag?.color || 'blue'} style={{ marginRight: 4 }}>
                      {tagName}
                    </Tag>
                  );
                })}
              </Descriptions.Item>
            )}
            <Descriptions.Item label="访问链接" span={2}>
              <a href={media.url} target="_blank" rel="noopener noreferrer">
                {media.url}
              </a>
            </Descriptions.Item>
            {media.description && (
              <Descriptions.Item label="描述" span={2} style={{ whiteSpace: 'pre-line' }}>
                {media.description}
              </Descriptions.Item>
            )}
            {media.metadata && Object.keys(media.metadata).length > 0 && (
              <Descriptions.Item label="元数据" span={2}>
                <pre style={{ 
                  backgroundColor: '#f5f5f5', 
                  padding: '12px', 
                  borderRadius: '4px',
                  maxHeight: '200px',
                  overflow: 'auto',
                  margin: 0
                }}>
                  {JSON.stringify(media.metadata, null, 2)}
                </pre>
              </Descriptions.Item>
            )}
          </Descriptions>
        </Card>
      </div>
    </div>
  );
};

export default MediaDetail;
