import { useState, useEffect, useRef } from 'react';
import { message } from 'antd';
import api from '../../../util/Service';
import type { Agent, Session, Message } from '../types';

const POLL_INTERVAL = 6000;
const POLL_MAX_COUNT = 20;

export function useChatSession() {
  const [agents, setAgents] = useState<Agent[]>([]);
  const [sessions, setSessions] = useState<Session[]>([]);
  const [selectedSession, setSelectedSession] = useState<Session | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);
  const [loading, setLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [hasMore, setHasMore] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);

  const pollingRef = useRef(false);
  const pollTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const pollCountRef = useRef(0);
  const currentSessionIdRef = useRef<number | null>(null);
  const lastMessageIdRef = useRef(0);
  const oldestMessageIdRef = useRef<number>(0);

  const stopPolling = () => {
    pollingRef.current = false;
    if (pollTimerRef.current) {
      clearTimeout(pollTimerRef.current);
      pollTimerRef.current = null;
    }
    pollCountRef.current = 0;
  };

  const pollOnce = async () => {
    if (!pollingRef.current || !currentSessionIdRef.current) return;
    if (pollCountRef.current >= POLL_MAX_COUNT) {
      stopPolling();
      return;
    }

    pollCountRef.current += 1;

    try {
      const res = await api.get(`/ai/sessions/${currentSessionIdRef.current}/poll`, {
        params: { last_id: lastMessageIdRef.current }
      });

      const msgs = res.data.messages || [];
      if (msgs.length > 0) {
        const newMessages: Message[] = msgs.map((item: any) => ({
          id: item.id,
          role: item.sender_type === 'user' ? 'user' : 'assistant',
          content: item.content,
          created_at: item.created_at,
          agent_type: item.agent_type,
          agent_name: item.agent_name || item.sender_name,
        }));

        setMessages(prev => {
          const existingIds = new Set(prev.map(m => m.id));
          const trulyNew = newMessages.filter(m => !existingIds.has(m.id));
          if (trulyNew.length > 0) {
            return [...prev, ...trulyNew];
          }
          return prev;
        });

        const maxId = Math.max(...newMessages.map(m => m.id));
        if (maxId > lastMessageIdRef.current) {
          lastMessageIdRef.current = maxId;
        }
      }

      pollTimerRef.current = setTimeout(pollOnce, POLL_INTERVAL);
    } catch (error) {
      pollTimerRef.current = setTimeout(pollOnce, POLL_INTERVAL);
    }
  };

  const startPolling = () => {
    stopPolling();
    pollingRef.current = true;
    pollOnce();
  };

  const loadAgents = async () => {
    try {
      const res = await api.get('/ai/agents/list');
      setAgents(res.data.items || []);
    } catch (error) {
      console.error('加载Agent失败:', error);
    }
  };

  const loadSessions = async () => {
    try {
      const res = await api.get('/ai/sessions/list');
      setSessions(res.data.items || []);
    } catch (error) {
      console.error('加载会话失败:', error);
    }
  };

  const loadSessionMessages = async () => {
    if (!selectedSession) return;
    currentSessionIdRef.current = selectedSession.id;
    lastMessageIdRef.current = 0;
    setLoading(true);
    setHasMore(false);
    try {
      const res = await api.get(`/ai/sessions/${selectedSession.id}/messages/list`, {
        params: { last_id: 0, limit: 10 }
      });

      const msgs = res.data.items || [];
      const newMessages: Message[] = msgs.map((item: any) => ({
        id: item.id,
        role: item.role || (item.sender_type === 'user' ? 'user' : 'assistant'),
        content: item.content,
        created_at: item.created_at,
        mentions: item.mentions,
        agent_type: item.agent_type,
        agent_name: item.agent_name || item.sender_name,
      }));

      setMessages(newMessages);
      if (newMessages.length > 0) {
        const minId = Math.min(...newMessages.map(m => m.id));
        const totalCount = res.data.total || 0;
        oldestMessageIdRef.current = minId;
        setHasMore(totalCount > newMessages.length);
        lastMessageIdRef.current = Math.max(...newMessages.map(m => m.id));
      }
      startPolling();
    } catch (error) {
      console.error('加载消息失败:', error);
    } finally {
      setLoading(false);
    }
  };

  const loadMoreMessages = async () => {
    if (!selectedSession || loadingMore || !hasMore) return;
    setLoadingMore(true);
    try {
      const res = await api.get(`/ai/sessions/${selectedSession.id}/messages/list`, {
        params: { last_id: oldestMessageIdRef.current - 1, limit: 10 }
      });

      const msgs = res.data.items || [];
      if (msgs.length === 0) {
        setHasMore(false);
        return;
      }

      const newMessages: Message[] = msgs.map((item: any) => ({
        id: item.id,
        role: item.role || (item.sender_type === 'user' ? 'user' : 'assistant'),
        content: item.content,
        created_at: item.created_at,
        mentions: item.mentions,
        agent_type: item.agent_type,
        agent_name: item.agent_name || item.sender_name,
      }));

      setMessages(prev => [...newMessages, ...prev]);
      const minId = Math.min(...newMessages.map(m => m.id));
      oldestMessageIdRef.current = minId;

      const totalCount = res.data.total || 0;
      const currentCount = messages.length + newMessages.length;
      setHasMore(currentCount < totalCount);
    } catch (error) {
      console.error('加载更多消息失败:', error);
    } finally {
      setLoadingMore(false);
    }
  };

  const sendMessage = async (content: string, mentions?: { id: number; type: string; name: string }[]) => {
    if (!content.trim() || !selectedSession) return;

    setSending(true);

    try {
      await api.post(`/ai/sessions/${selectedSession.id}/messages/send`, {
        content,
        mentions: mentions?.length ? mentions : null,
      });

      const maxId = messages.length > 0 ? Math.max(...messages.map(m => m.id)) : 0;
      lastMessageIdRef.current = maxId;
      startPolling();
    } catch (error: any) {
      console.error('[sendMessage] error:', error);
      message.error(error?.response?.data?.message || error?.message || '发送消息失败');
    } finally {
      setSending(false);
    }
  };

  const createSession = async (values: { session_type: string; members: number[]; title?: string }) => {
    try {
      const res = await api.post('/ai/sessions/create', {
        session_type: values.session_type,
        member_ids: values.members,
        title: values.title,
      });
      if (res.data.item) {
        setSessions(prev => [res.data.item, ...prev]);
        setSelectedSession(res.data.item);
        message.success('会话创建成功');
        return true;
      }
    } catch (error) {
      message.error('创建会话失败');
    }
    return false;
  };

  const deleteSession = async (sessionId: number) => {
    try {
      await api.delete(`/ai/sessions/delete/${sessionId}`);
      setSessions(prev => prev.filter(s => s.id !== sessionId));
      if (selectedSession?.id === sessionId) {
        setSelectedSession(null);
      }
      message.success('会话已删除');
      return true;
    } catch (error) {
      message.error('删除失败');
      return false;
    }
  };

  const updateSession = async (sessionId: number, updates: { title?: string; avatar?: string }) => {
    try {
      const res = await api.put(`/ai/sessions/update/${sessionId}`, updates);
      if (res.data) {
        setSelectedSession(prev => prev ? { ...prev, ...res.data } : null);
        setSessions(prev => prev.map(s => s.id === sessionId ? { ...s, ...res.data } : s));
        return true;
      }
    } catch (error) {
      message.error('更新会话失败');
    }
    return false;
  };

  const createAgent = async (values: any) => {
    try {
      const res = await api.post('/ai/agents/create', {
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
      if (res.data) {
        setAgents(prev => [...prev, { ...res.data, type: 'custom' }]);
        message.success('成员创建成功');
        return true;
      }
    } catch (error) {
      message.error('创建失败');
    }
    return false;
  };

  const startPrivateChat = async (agent: Agent) => {
    try {
      const res = await api.post(`/ai/agents/${agent.type}/${agent.identifier}/private-chat`);
      if (res.data.item) {
        const exists = sessions.find(s => s.id === res.data.item.id);
        if (!exists) {
          setSessions(prev => [res.data.item, ...prev]);
        }
        setSelectedSession(res.data.item);
        return true;
      }
    } catch (error) {
      message.error('创建私聊失败');
    }
    return false;
  };

  const clearMessages = async (sessionId: number) => {
    try {
      await api.delete(`/ai/sessions/${sessionId}/messages`);
      setMessages([]);
      message.success('聊天记录已清空');
      return true;
    } catch (error) {
      message.error('清空聊天记录失败');
      return false;
    }
  };

  useEffect(() => {
    if (selectedSession) {
      loadSessionMessages();
    } else {
      stopPolling();
    }
  }, [selectedSession?.id]);

  useEffect(() => {
    return () => {
      stopPolling();
    };
  }, []);

  return {
    agents,
    setAgents,
    sessions,
    setSessions,
    selectedSession,
    setSelectedSession,
    messages,
    loading,
    sending,
    hasMore,
    loadingMore,
    loadAgents,
    loadSessions,
    loadSessionMessages,
    loadMoreMessages,
    clearMessages,
    sendMessage,
    createSession,
    deleteSession,
    updateSession,
    createAgent,
    startPrivateChat,
  };
}
