import React, { useState, useEffect, useRef } from 'react';
import { Form, Input, Button, Card, Typography, message, Radio, Space, Divider, Upload, Alert, Spin } from 'antd';
import { ArrowLeftOutlined, FileOutlined, BookOutlined, ShopOutlined, InboxOutlined, LoadingOutlined } from '@ant-design/icons';
import { router } from '@inertiajs/react';
import { PROJECT_ROUTES } from '../../Constants/routes';
import { EXPORT_ROUTES } from '../../Constants/routes';
import api from '../../util/Service';

const { Title } = Typography;
const { TextArea } = Input;

const ProjectCreate = () => {
  const [form] = Form.useForm();
  const [projectName, setProjectName] = useState('');
  const [projectPrefix, setProjectPrefix] = useState('');
  const [templateType, setTemplateType] = useState<string>('blank');
  const [parsedModels, setParsedModels] = useState<string[]>([]);
  const [prefixLoading, setPrefixLoading] = useState(false);
  const [templateFileList, setTemplateFileList] = useState<any[]>([]);
  const debounceTimerRef = useRef<NodeJS.Timeout | null>(null);
  
  // 当项目名称变化时，通过后端 API 获取拼音前缀（防抖 500ms）
  useEffect(() => {
    // 清除之前的定时器
    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }

    if (projectName && projectName.trim() !== '') {
      setPrefixLoading(true);
      
      // 设置防抖定时器（500ms）
      debounceTimerRef.current = setTimeout(() => {
        // 调用后端 API 获取拼音前缀
        api.get(PROJECT_ROUTES.generatePrefix + '?name=' + encodeURIComponent(projectName))
          .then(response => {
            if (response.data.prefix) {
              setProjectPrefix(response.data.prefix);
              form.setFieldsValue({ prefix: response.data.prefix });
            }
          })
          .catch(error => {
            console.error('Error generating prefix:', error);
            // 如果 API 调用失败，回退到简单的首字母生成
            const firstChar = projectName.charAt(0).toLowerCase();
            const fallbackPrefix = firstChar;
            setProjectPrefix(fallbackPrefix);
            form.setFieldsValue({ prefix: fallbackPrefix });
          })
          .finally(() => {
            setPrefixLoading(false);
          });
      }, 500);
    } else {
      setPrefixLoading(false);
    }

    // 清理函数：组件卸载时清除定时器
    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
    };
  }, [projectName, form]);

  const handleSubmit = (values: any) => {
    // 如果是导入模板且没有上传文件，提示错误
    if (values.template === 'import' && templateFileList.length === 0) {
      message.error('请选择要导入的模板文件');
      return;
    }

    // 确保 description 有默认值
    const submitData = {
      ...values,
      description: values.description || ''
    };

    // 如果是导入模板，手动添加文件到 FormData
    if (values.template === 'import' && templateFileList.length > 0) {
      const formData = new FormData();
      Object.keys(submitData).forEach(key => {
        if (key !== 'template_file') {
          formData.append(key, submitData[key]);
        }
      });
      formData.append('template_file', templateFileList[0].originFileObj as File);

      router.post(PROJECT_ROUTES.create, formData, {
        onSuccess: () => {
          message.success('项目创建成功');
        },
        onError: (errors) => {
          console.error(errors);
          // 显示具体的错误信息
          if (errors.message) {
            message.error(errors.message);
          } else if (errors.prefix) {
            message.error(`前缀错误: ${errors.prefix}`);
          } else if (errors.name) {
            message.error(`名称错误: ${errors.name}`);
          } else if (errors.template) {
            message.error(`模板错误: ${errors.template}`);
          } else {
            message.error('项目创建失败，请检查表单');
          }
        }
      });
    } else {
      router.post(PROJECT_ROUTES.create, submitData, {
        onSuccess: () => {
          message.success('项目创建成功');
        },
        onError: (errors) => {
          console.error(errors);
          // 显示具体的错误信息
          if (errors.message) {
            message.error(errors.message);
          } else if (errors.prefix) {
            message.error(`前缀错误: ${errors.prefix}`);
          } else if (errors.name) {
            message.error(`名称错误: ${errors.name}`);
          } else if (errors.template) {
            message.error(`模板错误: ${errors.template}`);
          } else {
            message.error('项目创建失败，请检查表单');
          }
        }
      });
    }
  };

  const handleCancel = () => {
    router.get(PROJECT_ROUTES.index);
  };

  const handleTemplateFileChange = (info: any) => {
    setTemplateFileList(info.fileList.slice(-1)); // 只保留最后一个文件
  };

  return (
    <div style={{ padding: '24px' , maxWidth: '800px', margin: '0 auto' }}>
      <Button 
        icon={<ArrowLeftOutlined />} 
        onClick={handleCancel}
        style={{ marginBottom: '16px' }}
      >
        返回项目列表
      </Button>
      
      <Card>
        <Title level={2} style={{ marginBottom: '24px' }}>创建新项目</Title>
        
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
            <Input 
              placeholder="请输入项目名称" 
              onChange={(e) => setProjectName(e.target.value)}
            />
          </Form.Item>
          
          <Form.Item
            name="prefix"
            label="项目前缀"
            rules={[
              { required: true, message: '请输入项目前缀' },
              { max: 20, message: '项目前缀不能超过20个字符' },
              { pattern: /^[a-z0-9_]+$/, message: '项目前缀只能包含小写字母、数字和下划线' }
            ]}
            extra="根据项目名称自动生成拼音前缀，您也可以手动修改"
          >
            <Input 
              placeholder="项目前缀" 
              value={projectPrefix}
              onChange={(e) => setProjectPrefix(e.target.value)}
              suffix={prefixLoading ? <Spin indicator={<LoadingOutlined style={{ fontSize: 14 }} spin />} /> : null}
            />
          </Form.Item>
          
          <Form.Item
            name="template"
            label="项目模板"
            initialValue="blank"
            rules={[
              { required: true, message: '请选择项目模板' }
            ]}
          >
            <Radio.Group onChange={(e) => setTemplateType(e.target.value)}>
               <Space direction="vertical">
                 <Radio value="blank">
                   <Space>
                     <FileOutlined />
                     <span>空白模板</span>
                   </Space>
                   <div style={{ color: '#999', marginLeft: '24px' }}>从零开始构建您的内容模型</div>
                 </Radio>
                 <Radio value="blog">
                   <Space>
                     <BookOutlined />
                     <span>个人博客</span>
                   </Space>
                   <div style={{ color: '#999', marginLeft: '24px' }}>包含文章、分类、标签等基础内容模型</div>
                 </Radio>
                 <Radio value="corporate">
                   <Space>
                     <ShopOutlined />
                     <span>企业网站</span>
                   </Space>
                   <div style={{ color: '#999', marginLeft: '24px' }}>包含产品、服务、团队、联系方式等企业网站常用模型</div>
                 </Radio>
                <Radio value="import">
                  <Space>
                    <InboxOutlined />
                    <span>从导出的模板包导入</span>
                  </Space>
                  <div style={{ color: '#999', marginLeft: '24px' }}>选择一个由本系统导出的 .zip 模板包，创建项目时自动导入模型配置</div>
                </Radio>
               </Space>
             </Radio.Group>
           </Form.Item>

          {templateType === 'import' && (
            <>
              <Form.Item shouldUpdate noStyle>
                {() => (
                  <Alert
                    style={{ marginBottom: 12 }}
                    message="导入模板包将自动创建项目并导入所有配置"
                    type="info"
                    showIcon
                  />
                )}
              </Form.Item>
              <div style={{ marginBottom: 16 }}>
                <p style={{ marginBottom: 8 }}>上传模板包 (.zip)：</p>
                <Upload.Dragger
                  fileList={templateFileList}
                  onChange={handleTemplateFileChange}
                  beforeUpload={() => false}
                  multiple={false}
                  maxCount={1}
                  accept=".zip"
                >
                  <p className="ant-upload-drag-icon">
                    <InboxOutlined />
                  </p>
                  <p className="ant-upload-text">点击或拖拽 ZIP 文件到此处</p>
                  <p className="ant-upload-hint">选择由本系统导出的模板包进行导入</p>
                </Upload.Dragger>
                <p style={{ marginTop: 8, color: '#999' }}>
                  仅支持本系统导出的模板包（ZIP），大小建议 &lt; 50MB
                </p>
              </div>
              {parsedModels.length > 0 && (
                <Card size="small" style={{ marginTop: 8 }}>
                  <Typography.Text strong>模板包含的模型：</Typography.Text>
                  <ul style={{ marginTop: 8, paddingLeft: 20 }}>
                    {parsedModels.map((name, idx) => (
                      <li key={idx}>{name}</li>
                    ))}
                  </ul>
                </Card>
              )}
            </>
          )}

          <Divider />
          
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
              创建项目
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

export default ProjectCreate;
