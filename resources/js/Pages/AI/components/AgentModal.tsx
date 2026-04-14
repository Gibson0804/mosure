import React, { useState } from 'react';
import { Modal, Form, Input, Button, Radio, Checkbox, Divider, Tabs, Space, message } from 'antd';
import api from '../../../util/Service';

interface AgentModalProps {
  open: boolean;
  onCancel: () => void;
  onSuccess: () => void;
}

export default function AgentModal({ open, onCancel, onSuccess }: AgentModalProps) {
  const [form] = Form.useForm();
  const [previewPrompt, setPreviewPrompt] = useState('');
  const [testQuestion, setTestQuestion] = useState('');
  const [testAnswer, setTestAnswer] = useState('');
  const [testLoading, setTestLoading] = useState(false);
  const [previewLoading, setPreviewLoading] = useState(false);

  const handleCreate = async (values: any) => {
    try {
      await api.post('/ai/agents/create', {
        name: values.name,
        description: values.description || '',
        avatar: values.avatar,
        personality: {
          tone: values.tone || 'friendly',
          traits: values.traits || ['友善'],
          greeting: values.greeting || `你好！我是{name}，有什么可以帮你的？`,
        },
        dialogue_style: {
          length: values.length || 'medium',
          format: values.format || 'markdown',
          emoji_usage: values.emoji_usage || 'normal',
        },
        core_prompt: values.core_prompt || '',
      });
      message.success('成员创建成功');
      onSuccess();
      handleClose();
    } catch (error) {
      message.error('创建失败');
    }
  };

  const handlePreviewPrompt = async (values: any) => {
    setPreviewLoading(true);
    try {
      const res = await api.post('/ai/agents/preview-prompt', {
        name: values.name || '助手',
        description: values.description || '',
        personality: {
          tone: values.tone || 'friendly',
          traits: values.traits || [],
          greeting: values.greeting || `你好！我是${values.name || '{name}'}，有什么可以帮你的？`,
        },
        dialogue_style: {
          length: values.length || 'medium',
          format: values.format || 'markdown',
          emoji_usage: values.emoji_usage || 'normal',
        },
        core_prompt: values.core_prompt || '',
      });
      const prompt = (res as any).prompt || (res as any).data?.prompt;
      if (prompt) {
        setPreviewPrompt(prompt);
      }
    } catch (error) {
      message.error('预览生成失败');
    } finally {
      setPreviewLoading(false);
    }
  };

  const handleTestAgent = async () => {
    if (!testQuestion.trim()) {
      message.warning('请输入测试问题');
      return;
    }

    const values = form.getFieldsValue();
    setTestLoading(true);
    setTestAnswer('');

    try {
      const res = await api.post('/ai/agents/test', {
        name: values.name,
        description: values.description || '',
        personality: {
          tone: values.tone || 'friendly',
          traits: values.traits || [],
          greeting: values.greeting || `你好！我是${values.name || '{name}'}，有什么可以帮你的？`,
        },
        dialogue_style: {
          length: values.length || 'medium',
          format: values.format || 'markdown',
          emoji_usage: values.emoji_usage || 'normal',
        },
        core_prompt: values.core_prompt || previewPrompt,
        content: testQuestion,
      });
      const answer = (res as any).answer || (res as any).data?.answer;
      if (answer) {
        setTestAnswer(answer);
      }
    } catch (error) {
      message.error('测试失败');
    } finally {
      setTestLoading(false);
    }
  };

  const handleClose = () => {
    form.resetFields();
    setPreviewPrompt('');
    setTestQuestion('');
    setTestAnswer('');
    onCancel();
  };

  return (
    <Modal
      title="新建成员"
      open={open}
      onCancel={handleClose}
      footer={null}
      width={700}
    >
      <Tabs
        defaultActiveKey="settings"
        items={[
          {
            key: 'settings',
            label: '基本设置',
            children: (
              <Form form={form} layout="vertical" onFinish={handleCreate}>
                <Form.Item name="name" label="成员名称" rules={[{ required: true, message: '请输入成员名称' }]}>
                  <Input placeholder="请输入成员名称，如：小冰" />
                </Form.Item>
                <Form.Item name="description" label="成员描述">
                  <Input.TextArea placeholder="请输入成员描述，说明该成员的职责和能力" rows={2} />
                </Form.Item>

                <Divider>性格设定</Divider>

                <Form.Item name="tone" label="语气风格">
                  <Radio.Group>
                    <Radio value="friendly">友好亲切</Radio>
                    <Radio value="professional">专业严谨</Radio>
                    <Radio value="humorous">幽默风趣</Radio>
                    <Radio value="warm">温暖关怀</Radio>
                  </Radio.Group>
                </Form.Item>

                <Form.Item name="traits" label="性格特点">
                  <Checkbox.Group>
                    <Checkbox value="耐心细致">耐心细致</Checkbox>
                    <Checkbox value="逻辑清晰">逻辑清晰</Checkbox>
                    <Checkbox value="幽默风趣">幽默风趣</Checkbox>
                    <Checkbox value="简洁明了">简洁明了</Checkbox>
                    <Checkbox value="专业权威">专业权威</Checkbox>
                    <Checkbox value="亲切友善">亲切友善</Checkbox>
                    <Checkbox value="善于引导">善于引导</Checkbox>
                    <Checkbox value="严谨认真">严谨认真</Checkbox>
                  </Checkbox.Group>
                </Form.Item>

                <Form.Item name="greeting" label="开场问候语">
                  <Input placeholder="如：你好！我是{name}，有什么可以帮你的？" />
                </Form.Item>

                <Divider>对话风格</Divider>

                <Form.Item name="length" label="回复长度" initialValue="medium">
                  <Radio.Group>
                    <Radio value="short">简洁</Radio>
                    <Radio value="medium">适中</Radio>
                    <Radio value="long">详细</Radio>
                  </Radio.Group>
                </Form.Item>

                <Form.Item name="format" label="格式偏好" initialValue="markdown">
                  <Radio.Group>
                    <Radio value="plain">纯文本</Radio>
                    <Radio value="markdown">Markdown</Radio>
                    <Radio value="structured">结构化</Radio>
                  </Radio.Group>
                </Form.Item>

                <Form.Item name="emoji_usage" label="表情使用" initialValue="normal">
                  <Radio.Group>
                    <Radio value="none">不使用</Radio>
                    <Radio value="sparse">偶尔</Radio>
                    <Radio value="normal">正常</Radio>
                  </Radio.Group>
                </Form.Item>

                <Divider>高级设置</Divider>

                <Form.Item name="core_prompt" label="核心提示词（可选）">
                  <Input.TextArea
                    placeholder="自定义该成员的系统提示词，将覆盖默认生成的提示词"
                    rows={4}
                  />
                </Form.Item>

                <Form.Item>
                  <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
                    <Button onClick={handleClose}>取消</Button>
                    <Button type="primary" htmlType="submit">创建</Button>
                  </div>
                </Form.Item>
              </Form>
            ),
          },
          {
            key: 'preview',
            label: '预览测试',
            children: (
              <div>
                <Space style={{ marginBottom: 16 }}>
                  <Button onClick={() => handlePreviewPrompt(form.getFieldsValue())} loading={previewLoading}>
                    生成提示词预览
                  </Button>
                </Space>

                {previewPrompt && (
                  <>
                    <Divider>提示词预览</Divider>
                    <div style={{
                      background: '#f5f5f5',
                      padding: 12,
                      borderRadius: 4,
                      maxHeight: 200,
                      overflow: 'auto',
                      whiteSpace: 'pre-wrap',
                      fontSize: 12,
                      fontFamily: 'monospace',
                      marginBottom: 16,
                    }}>
                      {previewPrompt}
                    </div>
                  </>
                )}

                <Divider>对话测试</Divider>

                <Space.Compact style={{ width: '100%', marginBottom: 12 }}>
                  <Input
                    placeholder="输入测试问题..."
                    value={testQuestion}
                    onChange={(e) => setTestQuestion(e.target.value)}
                    onPressEnter={() => handleTestAgent()}
                  />
                  <Button type="primary" onClick={handleTestAgent} loading={testLoading}>
                    测试
                  </Button>
                </Space.Compact>

                {testAnswer && (
                  <div style={{
                    background: '#e6f7ff',
                    padding: 12,
                    borderRadius: 4,
                    maxHeight: 200,
                    overflow: 'auto',
                    whiteSpace: 'pre-wrap',
                  }}>
                    <strong>{form.getFieldValue('name') || '助手'}：</strong>
                    <div style={{ marginTop: 8 }}>{testAnswer}</div>
                  </div>
                )}

                <div style={{ marginTop: 16, textAlign: 'right' }}>
                  <Button
                    type="primary"
                    onClick={() => {
                      form.setFieldValue('core_prompt', previewPrompt);
                      message.success('已同步到高级设置，请返回"基本设置"创建成员');
                    }}
                  >
                    同步提示词到高级设置
                  </Button>
                </div>
              </div>
            ),
          },
        ]}
      />
    </Modal>
  );
}
