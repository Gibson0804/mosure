import React, { useEffect, useRef } from 'react';
import { Spin, Avatar, Button } from 'antd';
import { RobotOutlined, CaretUpOutlined } from '@ant-design/icons';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import type { Message } from '../types';

interface ChatMessagesProps {
  messages: Message[];
  loading: boolean;
  hasMore: boolean;
  loadingMore: boolean;
  onLoadMore: () => void;
  currentUserAvatar?: string;
  currentUserName: string;
}

const getAgentAvatarColor = (type: string) => {
  switch (type) {
    case 'secretary': return '#1890ff';
    case 'project': return '#52c41a';
    case 'custom': return '#722ed1';
    default: return '#1890ff';
  }
};

const getAgentFirstChar = (name: string) => {
  return name ? name.charAt(0).toUpperCase() : '?';
};

export default function ChatMessages({
  messages,
  loading,
  hasMore,
  loadingMore,
  onLoadMore,
  currentUserAvatar,
  currentUserName,
}: ChatMessagesProps) {
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const prevMessagesLengthRef = useRef(0);
  const isLoadingMoreRef = useRef(false);
  const prevScrollHeightRef = useRef(0);

  useEffect(() => {
    if (messages.length > 0) {
      if (isLoadingMoreRef.current && containerRef.current) {
        isLoadingMoreRef.current = false;
        const newScrollHeight = containerRef.current.scrollHeight;
        const scrollTop = newScrollHeight - prevScrollHeightRef.current;
        containerRef.current.scrollTop = scrollTop;
      } else {
        messagesEndRef.current?.scrollIntoView({ behavior: 'auto' });
      }
    }
    prevMessagesLengthRef.current = messages.length;
  }, [messages]);

  const handleScroll = () => {
    if (containerRef.current && hasMore && !loadingMore) {
      const { scrollTop, scrollHeight } = containerRef.current;
      if (scrollTop < 100) {
        isLoadingMoreRef.current = true;
        prevScrollHeightRef.current = scrollHeight - scrollTop;
        onLoadMore();
      }
    }
  };

  return (
    <div
      ref={containerRef}
      style={{ flex: 1, overflow: 'auto', padding: '24px', display: 'flex', flexDirection: 'column', gap: '16px', maxHeight: 'calc(100vh - 280px)' }}
      onScroll={handleScroll}
    >
      {loading ? (
        <div style={{ textAlign: 'center', padding: '40px' }}>
          <Spin />
        </div>
      ) : messages.length === 0 ? (
        <div style={{ textAlign: 'center', marginTop: '100px' }}>
          <RobotOutlined style={{ fontSize: '48px', color: '#1890ff', marginBottom: '16px' }} />
          <div style={{ fontSize: '16px', color: '#666', marginBottom: '8px' }}>开始对话</div>
          <div style={{ fontSize: '14px', color: '#999' }}>发送消息开始聊天</div>
        </div>
      ) : (
        <>
          {hasMore && (
            <div style={{ textAlign: 'center' }}>
              <Button
                type="text"
                icon={<CaretUpOutlined />}
                onClick={onLoadMore}
                loading={loadingMore}
              >
                {loadingMore ? '加载中...' : '加载更多消息'}
              </Button>
            </div>
          )}
          {messages.map((msg) => (
          <div
            key={msg.id}
            style={{
              display: 'flex',
              flexDirection: msg.role === 'user' ? 'row-reverse' : 'row',
              alignItems: 'flex-start',
              gap: '12px',
            }}
          >
            {msg.role === 'user' ? (
              <>
                {currentUserAvatar ? (
                  <Avatar src={currentUserAvatar} style={{ backgroundColor: '#52c41a' }} />
                ) : (
                  <Avatar style={{ backgroundColor: '#52c41a' }}>
                    {currentUserName.charAt(0).toUpperCase()}
                  </Avatar>
                )}
                <div
                  style={{
                    maxWidth: '70%',
                    padding: '12px 16px',
                    borderRadius: '8px',
                    background: '#1890ff',
                    color: '#fff',
                    boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
                  }}
                >
                  {msg.content}
                  {msg.mentions && msg.mentions.length > 0 && (
                    <div style={{ marginTop: '8px', fontSize: '12px', opacity: 0.8 }}>
                      @{msg.mentions.map(m => m.name).join(', ')}
                    </div>
                  )}
                </div>
              </>
            ) : (
              <>
                <Avatar style={{ backgroundColor: msg.agent_type ? getAgentAvatarColor(msg.agent_type) : '#1890ff' }}>
                  {msg.agent_name ? getAgentFirstChar(msg.agent_name) : <RobotOutlined />}
                </Avatar>
                <div
                  style={{
                    maxWidth: '70%',
                    padding: '12px 16px',
                    borderRadius: '8px',
                    background: '#f5f5f5',
                    color: '#333',
                    boxShadow: '0 2px 8px rgba(0,0,0,0.1)',
                  }}
                >
                  {msg.agent_name && (
                    <div style={{ fontSize: '12px', color: '#999', marginBottom: '4px' }}>
                      {msg.agent_name}
                    </div>
                  )}
                  <ReactMarkdown remarkPlugins={[remarkGfm]}>{msg.content}</ReactMarkdown>
                </div>
              </>
            )}
          </div>
        ))}
        </>
      )}

      <div ref={messagesEndRef} />
    </div>
  );
}
