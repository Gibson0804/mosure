import React, { useState, useEffect, useRef } from 'react';
import { Head, usePage } from '@inertiajs/react';
import {
  Layout, Avatar, Tag, Button, Empty, Modal, Form, Radio, Checkbox,
  Descriptions, Divider, Input, Upload, message, DescriptionsProps, Space,
} from 'antd';
import {
  RobotOutlined, SendOutlined, PlusOutlined, DeleteOutlined,
  EditOutlined, MessageOutlined, CameraOutlined, FolderOutlined,
  TeamOutlined, UserOutlined,
} from '@ant-design/icons';
import { usePage as useInertiaPage } from '@inertiajs/react';
import ChatSidebar from './components/ChatSidebar';
import ChatMessages from './components/ChatMessages';
import ChatInput from './components/ChatInput';
import AgentModal from './components/AgentModal';
import AgentEditModal from './components/AgentEditModal';
import { useChatSession } from './hooks/useChatSession';
import type { Agent, Session } from './types';
import api from '../../util/Service';

const { Header, Sider, Content } = Layout;

const getAgentAvatarColor = (type: string) => {
  switch (type) {
    case 'secretary': return '#1890ff';
    case 'project': return '#52c41a';
    case 'custom': return '#722ed1';
    default: return '#999';
  }
};

const getAgentFirstChar = (name: string) => {
  return name ? name.charAt(0).toUpperCase() : '?';
};

const getAgentIcon = (type: string) => {
  switch (type) {
    case 'secretary': return <RobotOutlined style={{ color: '#1890ff' }} />;
    case 'project': return <FolderOutlined style={{ color: '#52c41a' }} />;
    case 'custom': return <TeamOutlined style={{ color: '#722ed1' }} />;
    default: return <UserOutlined />;
  }
};

const getAgentTypeName = (type: string) => {
  switch (type) {
    case 'secretary': return '秘书';
    case 'project': return '项目';
    case 'custom': return '自定义助手';
    default: return '未知';
  }
};

const getAgentTypeColor = (type: string) => {
  switch (type) {
    case 'secretary': return 'blue';
    case 'project': return 'green';
    case 'custom': return 'purple';
    default: return 'default';
  }
};

const formatTime = (timeStr?: string) => {
  if (!timeStr) return '';
  const date = new Date(timeStr);
  return date.toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
};

export default function AIChat() {
  const page = usePage<any>() as any;
  const currentUser = page?.props?.user;
  const currentUserAvatar = currentUser?.avatar;
  const currentUserName = currentUser?.name || '用户';

  const {
    agents,
    sessions,
    selectedSession,
    setSelectedSession,
    messages,
    loading,
    sending,
    hasMore,
    loadingMore,
    loadAgents,
    loadSessions,
    loadMoreMessages,
    clearMessages,
    sendMessage,
    createSession,
    deleteSession,
    updateSession,
    startPrivateChat,
  } = useChatSession();

  const [viewMode, setViewMode] = useState<'sessions' | 'agents'>('sessions');
  const [selectedAgent, setSelectedAgent] = useState<Agent | null>(null);
  const [inputValue, setInputValue] = useState('');
  const [newChatModalVisible, setNewChatModalVisible] = useState(false);
  const [newChatForm] = Form.useForm();
  const [newAgentModalVisible, setNewAgentModalVisible] = useState(false);
  const [editingAgent, setEditingAgent] = useState<Agent | null>(null);
  const [editingSessionTitle, setEditingSessionTitle] = useState(false);
  const [newSessionTitle, setNewSessionTitle] = useState('');

  useEffect(() => {
    loadAgents();
    loadSessions();
  }, []);

  const handleSendMessage = async () => {
    if (!inputValue.trim() || !selectedSession) return;

    const mentionRegex = /@([^@\s]+)/g;
    const mentions: { id: number; type: string; name: string }[] = [];
    let match;
    while ((match = mentionRegex.exec(inputValue)) !== null) {
      const mentionedName = match[1];
      const agent = agents.find(a => a.name === mentionedName);
      if (agent) {
        mentions.push({ id: agent.id, type: 'agent', name: agent.name });
      }
    }

    await sendMessage(inputValue, mentions);
    setInputValue('');
  };

  const handleCreateNewChat = async (values: any) => {
    const success = await createSession(values);
    if (success) {
      setNewChatModalVisible(false);
      newChatForm.resetFields();
      setViewMode('sessions');
    }
  };

  const handleUpdateSession = async (updates: { title?: string; avatar?: string }) => {
    if (!selectedSession) return;
    await updateSession(selectedSession.id, updates);
  };

  const handleSaveSessionTitle = async () => {
    setEditingSessionTitle(false);
    if (newSessionTitle && newSessionTitle !== selectedSession?.title) {
      await handleUpdateSession({ title: newSessionTitle });
    }
  };

  const handleDeleteSession = async () => {
    if (!selectedSession) return;
    Modal.confirm({
      title: '确认删除',
      content: '确定要删除这个会话吗？',
      onOk: async () => {
        await deleteSession(selectedSession.id);
      },
    });
  };

  const handleClearMessages = async () => {
    if (!selectedSession) return;
    Modal.confirm({
      title: '确认清空',
      content: '确定要清空这个会话的所有聊天记录吗？',
      onOk: async () => {
        await clearMessages(selectedSession.id);
      },
    });
  };

  const handleStartPrivateChat = async (agent: Agent) => {
    const success = await startPrivateChat(agent);
    if (success) {
      setViewMode('sessions');
    }
  };

  const renderSessionAvatar = (session: Session, size?: number) => {
    if (session.avatar) {
      return <Avatar src={session.avatar} size={size} style={{ backgroundColor: 'transparent' }} />;
    }
    const firstChar = session.title ? session.title.charAt(0).toUpperCase() : '?';
    return (
      <Avatar size={size} style={{ backgroundColor: session.session_type === 'private' ? '#1890ff' : '#722ed1' }}>
        {firstChar}
      </Avatar>
    );
  };

  const getSessionMembers = (session: Session) => {
    let memberIds: number[] = [];
    if (session.member_ids) {
      if (Array.isArray(session.member_ids)) {
        memberIds = session.member_ids;
      } else if (typeof session.member_ids === 'string') {
        try {
          memberIds = JSON.parse(session.member_ids);
        } catch (e) {
          memberIds = [];
        }
      }
    }

    if (memberIds.length === 0) {
      if (session.agent_type && session.agent_identifier) {
        const agent = agents.find(a => a.type === session.agent_type && a.identifier === session.agent_identifier);
        return agent ? [agent] : [];
      }
      return [];
    }
    return agents.filter(a => memberIds.includes(Number(a.id)));
  };

  return (
    <>
      <Head title="AI 助手" />
      <Layout style={{ minHeight: '100vh', background: '#f5f5f5' }}>
        <Header style={{
          background: '#fff',
          padding: '0 24px',
          borderBottom: '1px solid #f0f0f0',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          height: '60px',
        }}>
          <div style={{ fontSize: '18px', fontWeight: 500, display: 'flex', alignItems: 'center', gap: '8px' }}>
            <RobotOutlined style={{ color: '#1890ff', fontSize: '24px' }} />
            <span>AI 助手</span>
          </div>
          <div style={{ fontSize: '14px', color: '#999' }}>与 AI 助手对话，探索智能可能</div>
        </Header>

        <Layout style={{ background: '#f5f5f5', padding: '24px' }}>
          <Sider width={280} style={{ background: '#fff', borderRadius: '8px', overflow: 'hidden' }}>
            <ChatSidebar
              viewMode={viewMode}
              onViewModeChange={setViewMode}
              sessions={sessions}
              agents={agents}
              selectedSession={selectedSession}
              selectedAgent={selectedAgent}
              onSelectSession={setSelectedSession}
              onSelectAgent={setSelectedAgent}
              onNewChat={() => setNewChatModalVisible(true)}
              onNewAgent={() => setNewAgentModalVisible(true)}
            />
          </Sider>

          <Content style={{ marginLeft: '24px', display: 'flex', flexDirection: 'column' }}>
            {selectedSession ? (
              <div style={{ background: '#fff', borderRadius: '8px', flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
                <div style={{ padding: '16px 24px', borderBottom: '1px solid #f0f0f0', display: 'flex', alignItems: 'center', gap: '12px' }}>
                  {renderSessionAvatar(selectedSession)}
                  <div>
                    <div style={{ fontWeight: 500 }}>{selectedSession.title}</div>
                    <div style={{ fontSize: '12px', color: '#999' }}>
                      <Tag color={selectedSession.session_type === 'private' ? 'blue' : 'purple'}>
                        {selectedSession.session_type === 'private' ? '私聊' : '群聊'}
                      </Tag>
                      {/* {selectedSession.message_count} 条消息 */}
                    </div>
                  </div>
                </div>

                <ChatMessages
                  messages={messages}
                  loading={loading}
                  hasMore={hasMore}
                  loadingMore={loadingMore}
                  onLoadMore={loadMoreMessages}
                  currentUserAvatar={currentUserAvatar}
                  currentUserName={currentUserName}
                />

                <ChatInput
                  value={inputValue}
                  onChange={setInputValue}
                  onSend={handleSendMessage}
                  sending={sending}
                  agents={agents}
                  sessionType={selectedSession.session_type}
                />
              </div>
            ) : (
              <div style={{ background: '#fff', borderRadius: '8px', flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <Empty description="选择一个会话开始对话" />
              </div>
            )}
          </Content>

          <Sider width={300} style={{ background: '#fff', borderRadius: '8px', marginLeft: '24px' }}>
            {viewMode === 'agents' && selectedAgent ? (
              <div style={{ padding: '24px' }}>
                <div style={{ textAlign: 'center', marginBottom: '24px' }}>
                  <Avatar size={80} icon={getAgentIcon(selectedAgent.type)} style={{
                    backgroundColor: selectedAgent.type === 'secretary' ? '#1890ff' : selectedAgent.type === 'project' ? '#52c41a' : '#722ed1',
                    marginBottom: '16px',
                  }} />
                  <div style={{ fontSize: '18px', fontWeight: 500, marginBottom: '8px' }}>{selectedAgent.name}</div>
                  <Tag color={getAgentTypeColor(selectedAgent.type)}>{getAgentTypeName(selectedAgent.type)}</Tag>
                </div>
                <Divider />
                <Descriptions column={1} size="small">
                  <Descriptions.Item label="标识">{selectedAgent.identifier}</Descriptions.Item>
                  <Descriptions.Item label="状态">
                    <Tag color={selectedAgent.enabled ? 'green' : 'red'}>
                      {selectedAgent.enabled ? '已启用' : '已禁用'}
                    </Tag>
                  </Descriptions.Item>
                  <Descriptions.Item label="描述">{selectedAgent.description || '暂无描述'}</Descriptions.Item>
                </Descriptions>
                <Divider />
                <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                  <Button type="primary" icon={<MessageOutlined />} block onClick={() => handleStartPrivateChat(selectedAgent)}>
                    私聊
                  </Button>
                  <Button icon={<EditOutlined />} block onClick={() => setEditingAgent(selectedAgent)}>编辑信息</Button>
                </div>
              </div>
            ) : selectedSession ? (
              <div style={{ padding: '24px' }}>
                <div style={{ textAlign: 'center', marginBottom: '24px', position: 'relative' }}>
                  <div style={{ position: 'relative', display: 'inline-block' }}>
                    {renderSessionAvatar(selectedSession, 80)}
                    {selectedSession.session_type === 'group' && (
                      <Upload
                        showUploadList={false}
                        action="/media/upload"
                        accept="image/*"
                        withCredentials
                        onChange={(info) => {
                          if (info.file.status === 'done' && info.file.response?.data?.url) {
                            handleUpdateSession({ avatar: info.file.response.data.url });
                          } else if (info.file.status === 'error') {
                            message.error('上传失败');
                          }
                        }}
                        headers={{ 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || '') }}
                      >
                        <Button type="text" size="small" icon={<CameraOutlined />} style={{ position: 'absolute', bottom: '12px', right: '-8px' }} />
                      </Upload>
                    )}
                  </div>
                  {selectedSession.session_type === 'group' ? (
                    <div>
                      <div style={{ fontSize: '18px', fontWeight: 500, marginBottom: '8px' }}>
                        {editingSessionTitle ? (
                          <Input
                            value={newSessionTitle}
                            onChange={(e) => setNewSessionTitle(e.target.value)}
                            onPressEnter={handleSaveSessionTitle}
                            onBlur={handleSaveSessionTitle}
                            autoFocus
                            style={{ textAlign: 'center', maxWidth: '200px' }}
                          />
                        ) : (
                          <span
                            onClick={() => {
                              setEditingSessionTitle(true);
                              setNewSessionTitle(selectedSession.title);
                            }}
                            style={{ cursor: 'pointer' }}
                          >
                            {selectedSession.title} <EditOutlined style={{ fontSize: '14px', color: '#999' }} />
                          </span>
                        )}
                      </div>
                    </div>
                  ) : (
                    <div style={{ fontSize: '18px', fontWeight: 500, marginBottom: '8px' }}>{selectedSession.title}</div>
                  )}
                  <Tag color={selectedSession.session_type === 'private' ? 'blue' : 'purple'}>
                    {selectedSession.session_type === 'private' ? '私聊' : '群聊'}
                  </Tag>
                </div>
                <Divider />
                <Descriptions column={1} size="small">
                  <Descriptions.Item label="会话类型">{selectedSession.session_type === 'private' ? '私聊' : '群聊'}</Descriptions.Item>
                  {/* <Descriptions.Item label="消息数">{selectedSession.message_count}</Descriptions.Item> */}
                  <Descriptions.Item label="更新时间">{formatTime(selectedSession.last_message_at)}</Descriptions.Item>
                </Descriptions>
                {selectedSession.session_type === 'group' && (
                  <>
                    <Divider />
                    <div style={{ marginBottom: '8px' }}>
                      <div style={{ fontSize: '14px', fontWeight: 500, marginBottom: '12px' }}>参与成员</div>
                      <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                        {getSessionMembers(selectedSession).map(member => (
                          <div key={member.id} style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '4px 0' }}>
                            <Avatar size="small" style={{ backgroundColor: getAgentAvatarColor(member.type) }}>
                              {getAgentFirstChar(member.name)}
                            </Avatar>
                            <span style={{ fontSize: '14px' }}>{member.name}</span>
                            <Tag style={{ fontSize: '10px' }}>{getAgentTypeName(member.type)}</Tag>
                          </div>
                        ))}
                      </div>
                    </div>
                  </>
                )}
                <Divider />
                <Space direction="vertical" style={{ width: '100%' }}>
                  <Button icon={<DeleteOutlined />} block onClick={handleClearMessages}>
                    清空聊天记录
                  </Button>
                  {!selectedSession.is_default && selectedSession.session_type === 'private' && (
                    <Button danger icon={<DeleteOutlined />} block onClick={handleDeleteSession}>
                      删除会话
                    </Button>
                  )}
                </Space>
              </div>
            ) : (
              <div style={{ padding: '24px', textAlign: 'center', color: '#999' }}>
                <Empty description="选择一个会话或成员查看详情" />
              </div>
            )}
          </Sider>
        </Layout>
      </Layout>

      <Modal
        title="新建聊天"
        open={newChatModalVisible}
        onCancel={() => {
          setNewChatModalVisible(false);
          newChatForm.resetFields();
        }}
        footer={null}
      >
        <Form form={newChatForm} layout="vertical" onFinish={handleCreateNewChat} initialValues={{ session_type: 'group', members: [] }}>
          <Form.Item name="session_type" label="聊天类型" rules={[{ required: true }]}>
            <Radio.Group>
              <Radio value="private">私聊</Radio>
              <Radio value="group">群聊</Radio>
            </Radio.Group>
          </Form.Item>
          <Form.Item name="members" label="参与成员" rules={[{ required: true, message: '请选择至少一个成员' }]}>
            <Checkbox.Group>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                {agents.map(agent => (
                  <Checkbox key={agent.id} value={agent.id}>
                    <span style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                      <Avatar size="small" icon={getAgentIcon(agent.type)} style={{ backgroundColor: agent.type === 'secretary' ? '#1890ff' : agent.type === 'project' ? '#52c41a' : '#722ed1' }} />
                      {agent.name}
                      <Tag color={getAgentTypeColor(agent.type)} style={{ fontSize: '10px' }}>{getAgentTypeName(agent.type)}</Tag>
                    </span>
                  </Checkbox>
                ))}
              </div>
            </Checkbox.Group>
          </Form.Item>
          <Form.Item name="title" label="会话名称（可选）">
            <Input placeholder="留空自动生成" />
          </Form.Item>
          <Form.Item>
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px' }}>
              <Button onClick={() => { setNewChatModalVisible(false); newChatForm.resetFields(); }}>取消</Button>
              <Button type="primary" htmlType="submit">创建</Button>
            </div>
          </Form.Item>
        </Form>
      </Modal>

      <AgentModal
        open={newAgentModalVisible}
        onCancel={() => setNewAgentModalVisible(false)}
        onSuccess={() => {
          loadAgents();
          setNewAgentModalVisible(false);
        }}
      />

      <AgentEditModal
        open={editingAgent !== null}
        agent={editingAgent}
        onCancel={() => setEditingAgent(null)}
        onSuccess={() => {
          loadAgents();
          setEditingAgent(null);
        }}
      />
    </>
  );
}
