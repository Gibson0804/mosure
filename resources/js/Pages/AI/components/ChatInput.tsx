import React, { useState, useRef, useEffect } from 'react';
import { Input, Button, Avatar, Tag } from 'antd';
import { SendOutlined } from '@ant-design/icons';
import type { Agent } from '../types';

const { TextArea } = Input;

interface ChatInputProps {
  value: string;
  onChange: (value: string) => void;
  onSend: () => void;
  sending: boolean;
  agents: Agent[];
  sessionType?: 'private' | 'group';
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

const getAgentTypeName = (type: string) => {
  switch (type) {
    case 'secretary': return '秘书';
    case 'project': return '项目';
    case 'custom': return '自定义助手';
    default: return '未知';
  }
};

export default function ChatInput({
  value,
  onChange,
  onSend,
  sending,
  agents,
  sessionType,
}: ChatInputProps) {
  const [showMentionPicker, setShowMentionPicker] = useState(false);
  const [mentionSearch, setMentionSearch] = useState('');
  const [mentionIndex, setMentionIndex] = useState(0);
  const itemRefs = useRef<(HTMLDivElement | null)[]>([]);

  const getSessionMembers = () => agents;

  const filteredAgents = () => {
    const members = getSessionMembers();
    if (!mentionSearch) return members;
    const search = mentionSearch.toLowerCase();
    return members.filter(m => m.name.toLowerCase().includes(search));
  };

  const insertMention = (agent: Agent) => {
    const lastAtIndex = value.lastIndexOf('@');
    const beforeAt = value.substring(0, lastAtIndex);
    const mentionText = `@${agent.name} `;
    onChange(beforeAt + mentionText);
    setShowMentionPicker(false);
    setMentionSearch('');
    setMentionIndex(0);
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    const newValue = e.target.value;
    onChange(newValue);
    setMentionIndex(0);

    const lastAtIndex = newValue.lastIndexOf('@');
    if (lastAtIndex !== -1) {
      const textAfterAt = newValue.slice(lastAtIndex + 1);
      if (!textAfterAt.includes(' ')) {
        setShowMentionPicker(true);
        setMentionSearch(textAfterAt);
        return;
      }
    }
    setShowMentionPicker(false);
    setMentionSearch('');
  };

  const scrollToIndex = (index: number) => {
    const item = itemRefs.current[index];
    if (item) {
      item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (!showMentionPicker) return;

    const filtered = filteredAgents();
    if (filtered.length === 0) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setMentionIndex(prev => {
          const newIndex = (prev + 1) % filtered.length;
          scrollToIndex(newIndex);
          return newIndex;
        });
        break;
      case 'ArrowUp':
        e.preventDefault();
        setMentionIndex(prev => {
          const newIndex = (prev - 1 + filtered.length) % filtered.length;
          scrollToIndex(newIndex);
          return newIndex;
        });
        break;
      case 'Enter':
        e.preventDefault();
        if (filtered[mentionIndex]) {
          insertMention(filtered[mentionIndex]);
        }
        break;
      case 'Escape':
        e.preventDefault();
        setShowMentionPicker(false);
        setMentionSearch('');
        break;
    }
  };

  useEffect(() => {
    setMentionIndex(0);
    itemRefs.current = [];
  }, [showMentionPicker]);

  const filtered = filteredAgents();

  return (
    <div style={{ padding: '16px 24px', borderTop: '1px solid #f0f0f0' }}>
      <div style={{ position: 'relative' }}>
        <TextArea
          value={value}
          onChange={handleInputChange}
          onKeyDown={handleKeyDown}
          onPressEnter={(e) => {
            if (!e.shiftKey && !showMentionPicker) {
              e.preventDefault();
              onSend();
            }
          }}
          placeholder={sessionType === 'group' ? "发送消息... 输入 @ 提及成员" : "发送消息..."}
          autoSize={{ minRows: 1, maxRows: 4 }}
          disabled={sending}
          style={{ borderRadius: '8px' }}
        />
        {showMentionPicker && filtered.length > 0 && (
          <div
            style={{
              position: 'absolute',
              bottom: '100%',
              left: 0,
              right: 0,
              background: '#fff',
              border: '1px solid #d9d9d9',
              borderRadius: '8px',
              boxShadow: '0 2px 8px rgba(0,0,0,0.15)',
              maxHeight: '200px',
              overflow: 'auto',
              zIndex: 100,
            }}
          >
            {filtered.map((agent, idx) => (
              <div
                key={agent.id}
                ref={(el) => { itemRefs.current[idx] = el; }}
                onClick={() => insertMention(agent)}
                style={{
                  padding: '8px 12px',
                  cursor: 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  gap: '8px',
                  background: idx === mentionIndex ? '#e6f7ff' : 'transparent',
                }}
              >
                <Avatar size="small" style={{ backgroundColor: getAgentAvatarColor(agent.type) }}>
                  {getAgentFirstChar(agent.name)}
                </Avatar>
                <span>{agent.name}</span>
                <Tag style={{ fontSize: '10px' }}>{getAgentTypeName(agent.type)}</Tag>
              </div>
            ))}
          </div>
        )}
      </div>
      <div style={{ marginTop: '8px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <span style={{ fontSize: '12px', color: '#999' }}>按 Enter 发送，Shift + Enter 换行</span>
        <Button
          type="primary"
          icon={<SendOutlined />}
          onClick={onSend}
          loading={sending}
          disabled={!value.trim()}
        >
          发送
        </Button>
      </div>
    </div>
  );
}
