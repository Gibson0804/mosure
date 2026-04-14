import React, { useEffect } from 'react';
import { Modal, Form, Input, Button, Radio, Checkbox, Divider, message } from 'antd';
import api from '../../../util/Service';
import type { Agent } from '../types';

interface AgentEditModalProps {
  open: boolean;
  agent: Agent | null;
  onCancel: () => void;
  onSuccess: () => void;
}

export default function AgentEditModal({ open, agent, onCancel, onSuccess }: AgentEditModalProps) {
  const [form] = Form.useForm();
  const [loading, setLoading] = React.useState(false);

  useEffect(() => {
    if (open && agent) {
      form.setFieldsValue({
        name: agent.name,
        description: agent.description || '',
        tone: agent.personality?.tone || 'friendly',
        traits: agent.personality?.traits || [],
        greeting: agent.personality?.greeting || '',
        length: agent.dialogue_style?.length || 'medium',
        format: agent.dialogue_style?.format || 'markdown',
        emoji_usage: agent.dialogue_style?.emoji_usage || 'normal',
        core_prompt: agent.core_prompt || '',
      });
    }
  }, [open, agent, form]);

  const handleSubmit = async (values: any) => {
    if (!agent) return;
    setLoading(true);
    try {
      await api.put(`/ai/agents/update/${agent.id}`, {
        name: values.name,
        description: values.description || '',
        personality: {
          tone: values.tone || 'friendly',
          traits: values.traits || [],
          greeting: values.greeting || `你好！我是${values.name}，有什么可以帮你的？`,
        },
        dialogue_style: {
          length: values.length || 'medium',
          format: values.format || 'markdown',
          emoji_usage: values.emoji_usage || 'normal',
        },
        core_prompt: values.core_prompt || '',
      });
      message.success('修改成功');
      onSuccess();
      onCancel();
    } catch (error) {
      message.error('修改失败');
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      title="编辑成员"
      open={open}
      onCancel={onCancel}
      footer={null}
      width={600}
    >
      <Form form={form} layout="vertical" onFinish={handleSubmit}>
        <Form.Item name="name" label="成员名称" rules={[{ required: true, message: '请输入成员名称' }]}>
          <Input placeholder="请输入成员名称" />
        </Form.Item>
        <Form.Item name="description" label="成员描述">
          <Input.TextArea placeholder="请输入成员描述" rows={2} />
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
          </Checkbox.Group>
        </Form.Item>

        <Form.Item name="greeting" label="开场问候语">
          <Input placeholder="如：你好！我是{name}，有什么可以帮你的？" />
        </Form.Item>

        <Divider>对话风格</Divider>

        <Form.Item name="length" label="回复长度">
          <Radio.Group>
            <Radio value="short">简洁</Radio>
            <Radio value="medium">适中</Radio>
            <Radio value="long">详细</Radio>
          </Radio.Group>
        </Form.Item>

        <Form.Item name="format" label="格式偏好">
          <Radio.Group>
            <Radio value="plain">纯文本</Radio>
            <Radio value="markdown">Markdown</Radio>
          </Radio.Group>
        </Form.Item>

        <Divider>核心提示词</Divider>

        <Form.Item name="core_prompt" label="系统提示词">
          <Input.TextArea placeholder="自定义该成员的系统提示词" rows={4} />
        </Form.Item>

        <Form.Item>
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
            <Button onClick={onCancel}>取消</Button>
            <Button type="primary" htmlType="submit" loading={loading}>保存</Button>
          </div>
        </Form.Item>
      </Form>
    </Modal>
  );
}
