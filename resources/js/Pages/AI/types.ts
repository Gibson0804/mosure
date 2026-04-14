export interface Agent {
  id: number;
  type: 'secretary' | 'project' | 'custom';
  identifier: string;
  name: string;
  avatar?: string;
  description?: string;
  user_id?: number;
  project_id?: number;
  personality?: {
    tone?: string;
    traits?: string[];
    greeting?: string;
  };
  dialogue_style?: {
    length?: string;
    format?: string;
    emoji_usage?: string;
  };
  core_prompt?: string;
  tools?: any;
  capabilities?: any;
  enabled: boolean;
}

export interface Session {
  id: number;
  title: string;
  avatar?: string;
  session_type: 'private' | 'group';
  agent_type?: string;
  agent_identifier?: string;
  member_ids?: number[] | string;
  is_default?: boolean;
  last_message_at?: string;
  message_count: number;
  context_summary?: string;
  context_token_count?: number;
  created_at?: string;
  updated_at?: string;
}

export interface Message {
  id: number;
  role: 'user' | 'assistant';
  content: string;
  created_at: string;
  mentions?: { id: number; type: string; name: string }[];
  agent_type?: string;
  agent_name?: string;
  sender_type?: string;
  sender_name?: string;
  status?: number;
}

export interface Mention {
  id: number;
  type: string;
  name: string;
}
