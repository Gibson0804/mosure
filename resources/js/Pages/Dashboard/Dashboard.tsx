import React from 'react';
import { Card, Row, Col, Statistic, Typography, List, Progress, Tag, Avatar, Space, Badge, Empty } from 'antd';
import { 
    DashboardOutlined, 
    FileOutlined, 
    AppstoreOutlined,
    PictureOutlined,
    KeyOutlined,
    FunctionOutlined,
    ThunderboltOutlined,
    ClockCircleOutlined,
    ArrowUpOutlined,
    ArrowDownOutlined,
    FolderOutlined,
    FileImageOutlined,
    FileTextOutlined,
    VideoCameraOutlined,
    AudioOutlined,
    CheckCircleOutlined,
    CloseCircleOutlined
} from '@ant-design/icons';
import { Link } from '@inertiajs/react';

const { Title, Text, Paragraph } = Typography;

interface DashboardProps {
    stats: {
        totalProjects: number;
        totalMolds: number;
        totalSubjects: number;
        totalContents: number;
        totalMedia: number;
        totalApiKeys: number;
    };
    projectStats?: {
        totalFunctions?: number;
        enabledFunctions?: number;
        totalTriggers?: number;
        enabledTriggers?: number;
        totalSchedules?: number;
        enabledSchedules?: number;
    };
    recentMolds: Array<{
        id: number;
        name: string;
        table_name: string;
        type: string;
        created_at: string;
    }>;
    mediaTypeDistribution: Array<{
        type: string;
        count: number;
    }>;
    currentProject: string;
}

const Dashboard: React.FC<DashboardProps> = ({ 
    stats, 
    projectStats = {}, 
    recentMolds = [], 
    mediaTypeDistribution = [],
    currentProject 
}) => {
    // 媒体类型图标映射
    const getMediaIcon = (type: string) => {
        const iconMap: Record<string, React.ReactNode> = {
            'image': <FileImageOutlined style={{ fontSize: 24, color: '#52c41a' }} />,
            'video': <VideoCameraOutlined style={{ fontSize: 24, color: '#1890ff' }} />,
            'audio': <AudioOutlined style={{ fontSize: 24, color: '#722ed1' }} />,
            'document': <FileTextOutlined style={{ fontSize: 24, color: '#fa8c16' }} />,
        };
        return iconMap[type] || <FileOutlined style={{ fontSize: 24 }} />;
    };

    // 格式化文件大小
    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    };

    // 计算媒体类型百分比
    const totalMediaCount = mediaTypeDistribution.reduce((sum, item) => sum + item.count, 0);

    return (
        <div className="py-6">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="mb-6">
                    <Title level={2}>
                        <DashboardOutlined className="mr-2" />
                        仪表盘
                    </Title>
                </div>

                {/* 核心统计卡片 */}
                <Row gutter={[16, 16]} className="mb-6">
                    <Col xs={24} sm={12} lg={8}>
                        <Card hoverable>
                            <Statistic 
                                title={<Text strong>内容模型</Text>}
                                value={stats.totalContents} 
                                prefix={<AppstoreOutlined style={{ color: '#1890ff' }} />}
                                valueStyle={{ color: '#1890ff' }}
                            />
                            <div className="mt-2">
                                <Text type="secondary" style={{ fontSize: 12 }}>
                                    总模型数: {stats.totalMolds}
                                </Text>
                            </div>
                        </Card>
                    </Col>
                    <Col xs={24} sm={12} lg={8}>
                        <Card hoverable>
                            <Statistic 
                                title={<Text strong>内容单页</Text>}
                                value={stats.totalSubjects} 
                                prefix={<FileOutlined style={{ color: '#52c41a' }} />}
                                valueStyle={{ color: '#52c41a' }}
                            />
                            <div className="mt-2">
                                <Text type="secondary" style={{ fontSize: 12 }}>
                                    可用于独立页面
                                </Text>
                            </div>
                        </Card>
                    </Col>
                    <Col xs={24} sm={12} lg={8}>
                        <Card hoverable>
                            <Statistic 
                                title={<Text strong>媒体资源</Text>}
                                value={stats.totalMedia} 
                                prefix={<PictureOutlined style={{ color: '#722ed1' }} />}
                                valueStyle={{ color: '#722ed1' }}
                            />
                            <div className="mt-2">
                                <Text type="secondary" style={{ fontSize: 12 }}>
                                    图片、视频、文档等
                                </Text>
                            </div>
                        </Card>
                    </Col>
                </Row>

                {/* 项目级功能统计 */}
                <Row gutter={[16, 16]} style={{marginTop: 16}} className="mb-6">
                    <Col xs={24} sm={8}>
                        <Card size="small">
                            <Space direction="vertical" style={{ width: '100%' }}>
                                <Space>
                                    <FunctionOutlined style={{ fontSize: 18, color: '#fa8c16' }} />
                                    <Text strong>云函数</Text>
                                </Space>
                                <div>
                                    <Text style={{ fontSize: 24, fontWeight: 'bold' }}>
                                        {projectStats.totalFunctions || 0}
                                    </Text>
                                    <Text type="secondary" className="ml-2">
                                        / {projectStats.enabledFunctions || 0} 已启用
                                    </Text>
                                </div>
                            </Space>
                        </Card>
                    </Col>
                    <Col xs={24} sm={8}>
                        <Card size="small">
                            <Space direction="vertical" style={{ width: '100%' }}>
                                <Space>
                                    <ThunderboltOutlined style={{ fontSize: 18, color: '#eb2f96' }} />
                                    <Text strong>触发器</Text>
                                </Space>
                                <div>
                                    <Text style={{ fontSize: 24, fontWeight: 'bold' }}>
                                        {projectStats.totalTriggers || 0}
                                    </Text>
                                    <Text type="secondary" className="ml-2">
                                        / {projectStats.enabledTriggers || 0} 已启用
                                    </Text>
                                </div>
                            </Space>
                        </Card>
                    </Col>
                    <Col xs={24} sm={8}>
                        <Card size="small">
                            <Space direction="vertical" style={{ width: '100%' }}>
                                <Space>
                                    <ClockCircleOutlined style={{ fontSize: 18, color: '#13c2c2' }} />
                                    <Text strong>定时任务</Text>
                                </Space>
                                <div>
                                    <Text style={{ fontSize: 24, fontWeight: 'bold' }}>
                                        {projectStats.totalSchedules || 0}
                                    </Text>
                                    <Text type="secondary" className="ml-2">
                                        / {projectStats.enabledSchedules || 0} 已启用
                                    </Text>
                                </div>
                            </Space>
                        </Card>
                    </Col>
                </Row>

            </div>
        </div>
    );
};

export default Dashboard;
