import React from 'react';
import { Button, Card, List, Typography, Space, Popconfirm, message, Tag } from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined, EnterOutlined, FileOutlined, BookOutlined, ShopOutlined } from '@ant-design/icons';
import { router } from '@inertiajs/react';
import { PROJECT_ROUTES } from '../../Constants/routes';
import { usePage } from '@inertiajs/react';
import moment from 'moment';

const { Title, Paragraph } = Typography;

interface Project {
  id: number;
  name: string;
  prefix: string;
  template: string;
  description: string;
  created_at: string;
}

interface ProjectListProps {
  projects: Project[];
}

const ProjectList = () => {
  const { projects } = usePage().props as unknown as ProjectListProps;

  // 根据模板类型渲染不同图标和颜色的标签
  const renderTemplateTag = (template: string) => {
    switch (template) {
      case 'blank':
        return <Tag icon={<FileOutlined />} color="default">空白模板</Tag>;
      case 'blog':
        return <Tag icon={<BookOutlined />} color="green">个人博客</Tag>;
      case 'corporate':
        return <Tag icon={<ShopOutlined />} color="orange">企业网站</Tag>;
      default:
        return <Tag color="default">{template}</Tag>;
    }
  };

  const handleCreateProject = () => {
    router.get(PROJECT_ROUTES.create);
  };

  const handleEditProject = (id: number) => {
    router.get(PROJECT_ROUTES.edit(id));
  };

  const handleSelectProject = (id: number) => {
    router.get(PROJECT_ROUTES.select(id));
  };

  const handleDeleteProject = (id: number) => {
    router.delete(PROJECT_ROUTES.delete(id), {
      onSuccess: () => {
        message.success('项目已删除');
      },
    });
  };

  return (
    <div style={{ padding: '92px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
        <Title level={2}>我的项目</Title>
        <Button 
          type="primary" 
          icon={<PlusOutlined />} 
          onClick={handleCreateProject}
        >
          创建新项目
        </Button>
      </div>

      {projects.length === 0 ? (
        <Card>
          <div style={{ textAlign: 'center', padding: '48px 0' }}>
            <Title level={4}>您还没有创建任何项目</Title>
            <Paragraph>点击"创建新项目"按钮开始使用Mosure</Paragraph>
            <Button 
              type="primary" 
              icon={<PlusOutlined />} 
              onClick={handleCreateProject}
              style={{ marginTop: '16px' }}
            >
              创建新项目
            </Button>
          </div>
        </Card>
      ) : (
        <List
          grid={{ gutter: 16, xs: 1, sm: 2, md: 2, lg: 3, xl: 4, xxl: 4 }}
          dataSource={projects}
          renderItem={(project) => (
            <List.Item>
              <Card
                hoverable
                title={project.name}
                actions={[
                  <Button 
                    type="text" 
                    icon={<EnterOutlined />} 
                    onClick={() => handleSelectProject(project.id)}
                    key="enter"
                  >
                    进入
                  </Button>,
                  <Button 
                    type="text" 
                    icon={<EditOutlined />} 
                    onClick={() => handleEditProject(project.id)}
                    key="edit"
                  >
                    编辑
                  </Button>,
                  <Popconfirm
                    title="确定要删除这个项目吗?"
                    onConfirm={() => handleDeleteProject(project.id)}
                    okText="确定"
                    cancelText="取消"
                    key="delete"
                  >
                    <Button 
                      type="text" 
                      danger 
                      icon={<DeleteOutlined />}
                    >
                      删除
                    </Button>
                  </Popconfirm>,
                ]}
              >
                <Card.Meta
                  description={
                    <div>
                      <Space direction="vertical" style={{ width: '100%' }}>
                        <Paragraph ellipsis={{ rows: 2 }}>
                          {project.description || '暂无描述'}
                        </Paragraph>
                        
                        <div>
                          <Tag color="blue">前缀: {project.prefix}</Tag>
                          {renderTemplateTag(project.template)}
                        </div>
                        
                        <div style={{ color: '#8c8c8c', fontSize: '12px', marginTop: '8px' }}>
                          创建时间: {moment(project.created_at).format('YYYY-MM-DD HH:mm:ss')}
                        </div>
                      </Space>
                    </div>
                  }
                />
              </Card>
            </List.Item>
          )}
        />
      )}
    </div>
  );
};

export default ProjectList;
