import React from 'react';
import { List, Avatar, Tag, Button } from 'antd';
import { PlusOutlined, RobotOutlined, FolderOutlined, TeamOutlined } from '@ant-design/icons';
import type { Agent, Session } from '../types';

interface ChatSidebarProps {
  viewMode: 'sessions' | 'agents';
  onViewModeChange: (mode: 'sessions' | 'agents') => void;
  sessions: Session[];
  agents: Agent[];
  selectedSession: Session | null;
  selectedAgent: Agent | null;
  onSelectSession: (session: Session) => void;
  onSelectAgent: (agent: Agent) => void;
  onNewChat: () => void;
  onNewAgent: () => void;
}

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

const formatTime = (timeStr?: string) => {
  if (!timeStr) return '';
  const date = new Date(timeStr);
  return date.toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
};

export default function ChatSidebar({
  viewMode,
  onViewModeChange,
  sessions,
  agents,
  selectedSession,
  selectedAgent,
  onSelectSession,
  onSelectAgent,
  onNewChat,
  onNewAgent,
}: ChatSidebarProps) {
  const groupedAgents = {
    secretary: agents.filter(a => a.type === 'secretary'),
    project: agents.filter(a => a.type === 'project'),
    custom: agents.filter(a => a.type === 'custom'),
  };

  return (
    <div style={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <div style={{ padding: '12px 16px', borderBottom: '1px solid #f0f0f0', display: 'flex', alignItems: 'center', gap: '8px' }}>
        <Button
          type={viewMode === 'sessions' ? 'primary' : 'default'}
          size="small"
          onClick={() => onViewModeChange('sessions')}
          style={{ flex: 1 }}
        >
          会话
        </Button>
        <Button
          type={viewMode === 'agents' ? 'primary' : 'default'}
          size="small"
          onClick={() => onViewModeChange('agents')}
          style={{ flex: 1 }}
        >
          成员
        </Button>
        <Button
          type="primary"
          size="small"
          icon={<PlusOutlined />}
          onClick={viewMode === 'sessions' ? onNewChat : onNewAgent}
          style={{ width: '70px' }}
        >
          新建
        </Button>
      </div>

      <div style={{ flex: 1, overflow: 'auto' }}>
        {viewMode === 'sessions' ? (
          <List
            dataSource={sessions}
            renderItem={(session) => (
              <List.Item
                style={{
                  padding: '12px 16px',
                  cursor: 'pointer',
                  background: selectedSession?.id === session.id ? '#e6f7ff' : 'transparent',
                  borderLeft: selectedSession?.id === session.id ? '3px solid #1890ff' : '3px solid transparent',
                }}
                onClick={() => onSelectSession(session)}
              >
                <List.Item.Meta
                  avatar={renderSessionAvatar(session)}
                  title={
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                      <span style={{ flex: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {session.title}
                      </span>
                      <Tag color={session.session_type === 'private' ? 'blue' : 'purple'} style={{ fontSize: '10px', padding: '0 4px' }}>
                        {session.session_type === 'private' ? '私聊' : '群聊'}
                      </Tag>
                    </div>
                  }
                  description={<span style={{ fontSize: '12px', color: '#999' }}>{formatTime(session.last_message_at)}</span>}
                />
              </List.Item>
            )}
          />
        ) : (
          <div>
            {groupedAgents.secretary.length > 0 && (
              <div>
                <div style={{ padding: '12px 16px 8px', fontSize: '12px', color: '#999', fontWeight: 500 }}>秘书</div>
                {groupedAgents.secretary.map(agent => (
                  <div
                    key={agent.id}
                    style={{
                      padding: '8px 16px',
                      cursor: 'pointer',
                      background: selectedAgent?.id === agent.id ? '#e6f7ff' : 'transparent',
                      display: 'flex',
                      alignItems: 'center',
                      gap: '12px',
                    }}
                    onClick={() => onSelectAgent(agent)}
                  >
                    <Avatar style={{ backgroundColor: getAgentAvatarColor(agent.type) }}>
                      {getAgentFirstChar(agent.name)}
                    </Avatar>
                    <span>{agent.name}</span>
                  </div>
                ))}
              </div>
            )}
            {groupedAgents.project.length > 0 && (
              <div>
                <div style={{ padding: '12px 16px 8px', fontSize: '12px', color: '#999', fontWeight: 500 }}>项目</div>
                {groupedAgents.project.map(agent => (
                  <div
                    key={agent.id}
                    style={{
                      padding: '8px 16px',
                      cursor: 'pointer',
                      background: selectedAgent?.id === agent.id ? '#e6f7ff' : 'transparent',
                      display: 'flex',
                      alignItems: 'center',
                      gap: '12px',
                    }}
                    onClick={() => onSelectAgent(agent)}
                  >
                    <Avatar style={{ backgroundColor: getAgentAvatarColor(agent.type) }}>
                      {getAgentFirstChar(agent.name)}
                    </Avatar>
                    <span>{agent.name}</span>
                  </div>
                ))}
              </div>
            )}
            {groupedAgents.custom.length > 0 && (
              <div>
                <div style={{ padding: '12px 16px 8px', fontSize: '12px', color: '#999', fontWeight: 500 }}>我的助手</div>
                {groupedAgents.custom.map(agent => (
                  <div
                    key={agent.id}
                    style={{
                      padding: '8px 16px',
                      cursor: 'pointer',
                      background: selectedAgent?.id === agent.id ? '#e6f7ff' : 'transparent',
                      display: 'flex',
                      alignItems: 'center',
                      gap: '12px',
                    }}
                    onClick={() => onSelectAgent(agent)}
                  >
                    <Avatar style={{ backgroundColor: getAgentAvatarColor(agent.type) }}>
                      {getAgentFirstChar(agent.name)}
                    </Avatar>
                    <span>{agent.name}</span>
                  </div>
                ))}
              </div>
            )}
            <div style={{ padding: '12px 16px' }}>
              <Button type="dashed" icon={<PlusOutlined />} block onClick={onNewAgent}>
                新建成员
              </Button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
