export type AiProviderConfig = {
  label?: string;
  completion_url?: string;
  api_key?: string;
  model?: string;
  models?: string[];
};

export type AiProvidersConfig = {
  active_provider?: string;
  zhipu?: AiProviderConfig;
  deepseek?: AiProviderConfig;
  tencent?: AiProviderConfig;
  alibaba?: AiProviderConfig;
  kimi?: AiProviderConfig;
  custom?: AiProviderConfig;
};

export type MailConfig = {
  mailer?: string;
  host?: string;
  port?: number;
  username?: string;
  password?: string;
  encryption?: string;
  from_address?: string;
  from_name?: string;
};

export type StorageS3 = {
  provider?: 'generic' | 'aliyun' | 'cos' | 'qiniu' | 'aws';
  key?: string;
  secret?: string;
  region?: string;
  bucket?: string;
  endpoint?: string;
  url?: string;
  use_path_style_endpoint?: boolean;
};

export type StorageCos = {
  secret_id?: string;
  secret_key?: string;
  region?: string;
  bucket?: string;
  cdn_url?: string;
};

export type StorageConfig = {
  default?: 'local' | 's3';
  s3?: StorageS3;
  cos?: StorageCos;
};

export type SecurityConfig = {
  // TODO: define security config
};

export type SystemConfigData = {
  storage?: StorageConfig;
  ai_providers?: AiProvidersConfig;
  mail?: MailConfig;
  security?: SecurityConfig;
};
