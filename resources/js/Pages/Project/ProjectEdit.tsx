import React, { useEffect } from 'react';
import { Form, Input, Button, Card, Typography, message } from 'antd';
import { ArrowLeftOutlined } from '@ant-design/icons';
import { router, usePage } from '@inertiajs/react';
import { PROJECT_ROUTES } from '../../Constants/routes';

const { Title } = Typography;
const { TextArea } = Input;

interface Project {
  id: number;
  name: string;
  description: string;
}

interface ProjectEditProps {
  project: Project;
}

const ProjectEdit = () => {
  const { project } = usePage().props as unknown as ProjectEditProps;
  const [form] = Form.useForm();

  useEffect(() => {
    // 设置表单初始值
    form.setFieldsValue({
      name: project.name,
      description: project.description || ''
    });
  }, [project]);

  const handleSubmit = (values: any) => {
    router.post(PROJECT_ROUTES.edit(project.id), values, {
      onSuccess: () => {
        message.success('项目更新成功');
      },
      onError: (errors) => {
        console.error(errors);
        message.error('项目更新失败，请检查表单');
      }
    });
  };

  const handleCancel = () => {
    router.get(PROJECT_ROUTES.index);
  };

  return (
    <div style={{ padding: '24px', maxWidth: '800px', margin: '0 auto' }}>
      <Button 
        icon={<ArrowLeftOutlined />} 
        onClick={handleCancel}
        style={{ marginBottom: '16px' }}
      >
        返回项目列表
      </Button>
      
      <Card>
        <Title level={2} style={{ marginBottom: '24px' }}>编辑项目</Title>
        
        <Form
          form={form}
          layout="vertical"
          onFinish={handleSubmit}
        >
          <Form.Item
            name="name"
            label="项目名称"
            rules={[
              { required: true, message: '请输入项目名称' },
              { max: 100, message: '项目名称不能超过100个字符' }
            ]}
          >
            <Input placeholder="请输入项目名称" />
          </Form.Item>
          
          <Form.Item
            name="description"
            label="项目描述"
            rules={[
              { max: 500, message: '项目描述不能超过500个字符' }
            ]}
          >
            <TextArea 
              placeholder="请输入项目描述（选填）" 
              rows={4} 
            />
          </Form.Item>
          
          <Form.Item>
            <Button type="primary" htmlType="submit" style={{ marginRight: '8px' }}>
              保存修改
            </Button>
            <Button onClick={handleCancel}>
              取消
            </Button>
          </Form.Item>
        </Form>
      </Card>
    </div>
  );
};

export default ProjectEdit;
