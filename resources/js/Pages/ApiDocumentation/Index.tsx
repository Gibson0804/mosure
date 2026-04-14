import React, { useState, useEffect } from 'react';
import { Card, Typography, Tabs, Space, Alert, Row, Col, Table, Radio, Button, message, Modal, Checkbox, Divider, Input } from 'antd';
import { ApiOutlined, CopyOutlined } from '@ant-design/icons';
import { Head } from '@inertiajs/react';
import { generateApiDemos, demoOperations, generateSubjectApiDemos, demoSubjectOperations, generateMediaApiDemos, demoMediaOperations, generateFunctionApiDemos, DemoOperation, DemoLanguage } from './ApiDemo';
import { buildDocHtml, buildFullDocHtmlWithOptions, LanguageKey } from './exportDoc';
import { MoldItem, MoldField } from '../../Types/Mold';

import { useTranslate } from '../../util/useTranslate';
import api from '../../util/Service';

const { Text, Paragraph } = Typography;
const { TabPane } = Tabs;

const codeBlockStyle: React.CSSProperties = {
  whiteSpace: 'pre',
  overflowX: 'auto',
  overflowY: 'hidden',
};

interface FunctionItem {
  id: number;
  name: string;
  slug: string;
  description: string;
  http_method: string;
  input_schema: any;
  output_schema: any;
  fields: any[];
}

interface ApiDocPageProps {
  contents: MoldItem[];
  subjects: MoldItem[];
  functions: FunctionItem[];
  openContentBase: string;
  openSubjectBase: string;
  openFunctionBase: string;
  openAuthBase?: string;
  projectAuthEnabled?: boolean;
}

type ExampleGroup = Record<LanguageKey, string>;

interface DemoExamples {
  select: ExampleGroup;
  create: ExampleGroup;
  update: ExampleGroup;
  delete: ExampleGroup;
}

const createEmptyGroup = (): ExampleGroup => ({
  curl: '',
  javascript: '',
  php: '',
  python: '',
});

const createEmptyExamples = (): DemoExamples => ({
  select: createEmptyGroup(),
  create: createEmptyGroup(),
  update: createEmptyGroup(),
  delete: createEmptyGroup(),
});

const mediaMoldPreset: MoldItem = {
  id: 0,
  name: '媒体资源',
  description: '媒体资源管理接口，支持图片、视频、音频、文档等文件的查询',
  table_name: 'media',
  mold_type: -1,
  fields: [],
  subject_content: {},
  list_show_fields: [],
  updated_at: null,
};

const authMoldPreset: MoldItem = {
  id: -2,
  name: '项目用户认证',
  description: '项目用户登录、注册、当前用户和退出登录接口。登录/注册成功后返回 pu_* 登录态，可用于 Authorization Bearer 调用 /open/*。',
  table_name: 'auth',
  mold_type: -1,
  fields: [
    { field: 'account', label: '登录账号：邮箱 / 用户名 / 手机号', type: 'input' } as any,
    { field: 'email', label: '注册邮箱', type: 'input' } as any,
    { field: 'username', label: '注册用户名（可选）', type: 'input' } as any,
    { field: 'name', label: '显示名称（可选）', type: 'input' } as any,
    { field: 'password', label: '密码', type: 'input' } as any,
    { field: 'Authorization', label: 'Bearer pu_* 登录态，用于 me/logout 及其它 /open/* 接口', type: 'header' } as any,
  ],
  subject_content: {},
  list_show_fields: [],
  updated_at: null,
};

const defaultDemoLanguages: DemoLanguage[] = [
  { key: 'curl', label: 'cURL' },
  { key: 'javascript', label: 'JavaScript' },
  { key: 'php', label: 'PHP' },
  { key: 'python', label: 'Python' },
];

const buildSingleOperation = (title: string, method: DemoOperation['method']): DemoOperation[] => ([
  {
    key: 'select',
    title,
    method,
    languages: defaultDemoLanguages,
  },
]);

const normalizeHttpMethod = (method?: string): DemoOperation['method'] => {
  switch ((method ?? '').toUpperCase()) {
    case 'GET':
      return 'GET';
    case 'PUT':
      return 'PUT';
    case 'DELETE':
      return 'DELETE';
    default:
      return 'POST';
  }
};

const authOperations: DemoOperation[] = [
  { key: 'create', title: '登录接口 Demo', method: 'POST', languages: defaultDemoLanguages },
  { key: 'update', title: '注册接口 Demo', method: 'POST', languages: defaultDemoLanguages },
  { key: 'select', title: '当前用户接口 Demo', method: 'GET', languages: defaultDemoLanguages },
  { key: 'delete', title: '退出登录接口 Demo', method: 'POST', languages: defaultDemoLanguages },
];

const generateAuthApiDemos = (baseUrl: string): DemoExamples => {
  const loginUrl = `${baseUrl}/login`;
  const registerUrl = `${baseUrl}/register`;
  const meUrl = `${baseUrl}/me`;
  const logoutUrl = `${baseUrl}/logout`;
  const loginPayload = `{"account":"user@example.com","password":"123456"}`;
  const registerPayload = `{"email":"user@example.com","password":"123456","name":"张三"}`;

  return {
    create: {
      curl: [`# 项目用户登录`, `curl -X POST '${loginUrl}' \\`, `  -H 'Content-Type: application/json' \\`, `  -d '${loginPayload}'`].join('\n'),
      javascript: [`const response = await fetch('${loginUrl}', {`, `  method: 'POST',`, `  headers: { 'Content-Type': 'application/json' },`, `  body: JSON.stringify(${loginPayload}),`, `});`, `const { data } = await response.json();`, `localStorage.setItem('project_user_token', data.token);`].join('\n'),
      php: [`<?php`, `$payload = ${loginPayload};`, `// 使用 GuzzleHttp\Client POST ${loginUrl}`].join('\n'),
      python: [`import requests`, `payload = ${loginPayload}`, `response = requests.post('${loginUrl}', json=payload)`, `print(response.json())`].join('\n'),
    },
    update: {
      curl: [`# 项目用户注册`, `curl -X POST '${registerUrl}' \\`, `  -H 'Content-Type: application/json' \\`, `  -d '${registerPayload}'`].join('\n'),
      javascript: [`const response = await fetch('${registerUrl}', {`, `  method: 'POST',`, `  headers: { 'Content-Type': 'application/json' },`, `  body: JSON.stringify(${registerPayload}),`, `});`, `console.log(await response.json());`].join('\n'),
      php: [`<?php`, `$payload = ${registerPayload};`, `// 使用 GuzzleHttp\Client POST ${registerUrl}`].join('\n'),
      python: [`import requests`, `payload = ${registerPayload}`, `response = requests.post('${registerUrl}', json=payload)`, `print(response.json())`].join('\n'),
    },
    select: {
      curl: [`# 获取当前项目用户`, `curl -X GET '${meUrl}' \\`, `  -H 'Authorization: Bearer pu_${'{'}projectPrefix${'}'}_xxx'`].join('\n'),
      javascript: [`const token = localStorage.getItem('project_user_token');`, `const response = await fetch('${meUrl}', {`, `  headers: { Authorization: \`Bearer ${'$'}{token}\` },`, `});`, `console.log(await response.json());`].join('\n'),
      php: [`<?php`, `$token = 'pu_xxx';`, `// 使用 Authorization Bearer 调用 ${meUrl}`].join('\n'),
      python: [`import requests`, `token = 'pu_xxx'`, `response = requests.get('${meUrl}', headers={'Authorization': f'Bearer {token}'})`, `print(response.json())`].join('\n'),
    },
    delete: {
      curl: [`# 退出登录`, `curl -X POST '${logoutUrl}' \\`, `  -H 'Authorization: Bearer pu_${'{'}projectPrefix${'}'}_xxx'`].join('\n'),
      javascript: [`const token = localStorage.getItem('project_user_token');`, `await fetch('${logoutUrl}', {`, `  method: 'POST',`, `  headers: { Authorization: \`Bearer ${'$'}{token}\` },`, `});`].join('\n'),
      php: [`<?php`, `$token = 'pu_xxx';`, `// 使用 Authorization Bearer POST ${logoutUrl}`].join('\n'),
      python: [`import requests`, `token = 'pu_xxx'`, `requests.post('${logoutUrl}', headers={'Authorization': f'Bearer {token}'})`].join('\n'),
    },
  };
};

export default function ApiDocumentation({ contents = [], subjects = [], functions = [], openContentBase, openSubjectBase, openFunctionBase, openAuthBase = '', projectAuthEnabled = false }: ApiDocPageProps) {
  const [selectedType, setSelectedType] = useState<'content' | 'subject' | 'media' | 'function' | 'auth'>('content');
  const [selectedItem, setSelectedItem] = useState<number | null>(null);
  const [loading, setLoading] = useState(false);
  const [selectedMold, setSelectedMold] = useState<MoldItem | null>(null);
  const [examples, setExamples] = useState<DemoExamples>(createEmptyExamples());
  const [error, setError] = useState<string | null>(null);
  const currentFunction = selectedType === 'function' ? functions.find(f => f.id === selectedItem) ?? null : null;

  // 导出 API 文档 - 弹窗状态
  const [exportVisible, setExportVisible] = useState(false);
  const [selectedContentIds, setSelectedContentIds] = useState<number[]>(contents.map(c => c.id as any));
  const [selectedSubjectIds, setSelectedSubjectIds] = useState<number[]>(subjects.map(s => s.id as any));
  const [selectedFunctionIds, setSelectedFunctionIds] = useState<number[]>(functions.map(f => f.id as any));
  const [includeMedia, setIncludeMedia] = useState(true);
  const [includeAuth, setIncludeAuth] = useState(projectAuthEnabled);
  const allLangs: LanguageKey[] = ['curl', 'javascript', 'php', 'python'];
  const [selectedLangs, setSelectedLangs] = useState<LanguageKey[]>(allLangs);
  const [ops, setOps] = useState<{select: boolean; create: boolean; update: boolean; delete: boolean}>({ select: true, create: false, update: false, delete: false });
  const [apiKey, setApiKey] = useState<string>('');

  const _t = useTranslate();

  const availableOperations: DemoOperation[] =
    selectedType === 'auth'
      ? authOperations
      : selectedType === 'media'
      ? demoMediaOperations
      : selectedType === 'function'
        ? buildSingleOperation('调用接口 Demo', normalizeHttpMethod(currentFunction?.http_method))
        : selectedType === 'content'
          ? demoOperations
          : demoSubjectOperations;

  const handleSelectionChange = async (type: 'content' | 'subject', value: number) => {
    setSelectedType(type);
    setSelectedItem(value);

    setLoading(true);
    setError(null);
    api.get(`/api-docs/molds/${type}/${value}`)
    .then(response => {
      const mold = response.data as MoldItem;
      setSelectedMold(mold);
      if (type === 'content') {
        setExamples(generateApiDemos(openContentBase, mold.table_name, mold.name, buildSamplePayload(mold)));
      } else {
        setExamples(generateSubjectApiDemos(openSubjectBase, mold.table_name, mold.name, buildSamplePayload(mold)));
      }
    })
    .catch(error => {
      console.error('Error fetching API examples:', error);
      setError(error.message ? error.message : '获取示例失败');
    })
    .finally(() => {
      setLoading(false);
    });
  };

  // ===== 导出 API 文档（整站，可选模型/语言/操作） =====
  const openExportModal = () => {
    // 初始化为当前已有模型集合，语言全选，操作默认仅读取
    setSelectedContentIds(contents.map(c => c.id as any));
    setSelectedSubjectIds(subjects.map(s => s.id as any));
    setSelectedFunctionIds(functions.map(f => f.id as any));
    setIncludeMedia(true);
    setIncludeAuth(projectAuthEnabled);
    setSelectedLangs(allLangs);
    setOps({ select: true, create: false, update: false, delete: false });
    setApiKey('');
    setExportVisible(true);
  };

  const doPreviewExport = () => {
    const mediaBase = openContentBase.replace('/content', '/media');
    const functionBase = openFunctionBase;

    let html = buildFullDocHtmlWithOptions(
      contents,
      subjects,
      openContentBase,
      openSubjectBase,
      selectedContentIds,
      selectedSubjectIds,
      { languages: selectedLangs, operations: ops },
      includeMedia,
      mediaBase,
      functions,
      selectedFunctionIds,
      functionBase,
      includeAuth,
      openAuthBase
    );
    if (apiKey && apiKey.trim()) {
      html = html.replace(/YOUR_API_KEY/g, apiKey.trim());
    }
    const w = window.open('', '_blank');
    if (w) {
      w.document.open();
      w.document.write(html);
      w.document.close();
      w.focus();
    } else {
      message.error('浏览器阻止了弹窗，请允许后重试');
    }
  };

  const doExportPdf = () => {
    const mediaBase = openContentBase.replace('/content', '/media');
    const functionBase = openFunctionBase;

    let base = buildFullDocHtmlWithOptions(
      contents,
      subjects,
      openContentBase,
      openSubjectBase,
      selectedContentIds,
      selectedSubjectIds,
      { languages: selectedLangs, operations: ops },
      includeMedia,
      mediaBase,
      functions,
      selectedFunctionIds,
      functionBase,
      includeAuth,
      openAuthBase
    );
    if (apiKey && apiKey.trim()) {
      base = base.replace(/YOUR_API_KEY/g, apiKey.trim());
    }
    const html = base.replace('</body>', '<script>window.addEventListener("load", () => setTimeout(() => window.print(), 300));</script></body>');
    const w = window.open('', '_blank');
    if (w) {
      w.document.open();
      w.document.write(html);
      w.document.close();
      w.focus();
    } else {
      message.error('浏览器阻止了弹窗，请允许后重试');
    }
  };


  const handleCopy = async (content: string) => {
    if (!content) {
      message.warning('当前示例暂无内容');
      return;
    }

    try {
      await navigator.clipboard.writeText(content);
      message.success('复制成功');
    } catch (err) {
      console.error('Copy failed:', err);
      message.error('复制失败，请手动复制');
    }
  };


  const buildSamplePayload = (mold: MoldItem) => {
    const payload: Record<string, string | number | boolean> = {};
    const fields = Array.isArray(mold.fields) ? mold.fields : [];

    if (fields.length === 0) {
      payload['title'] = `${mold.name || mold.table_name}示例标题`;
      payload['summary'] = `${mold.name || mold.table_name}示例摘要`;
      return payload;
    }

    fields.slice(0, 5).forEach((field, index) => {
      const fieldName = field.field && field.field.trim() !== '' ? field.field : `field_${index + 1}`;
      payload[fieldName] = getSampleValue(field, index);
    });

    return payload;
  };

  const getSampleValue = (field: MoldField, index: number): string | number | boolean => {
    const type = (field.type || '').toLowerCase();
    switch (type) {
      case 'number':
      case 'int':
      case 'integer':
      case 'float':
      case 'decimal':
      case 'double':
        return index + 1;
      case 'boolean':
        return index % 2 === 0;
      case 'date':
        return '2025-01-01';
      case 'datetime':
      case 'timestamp':
        return '2025-01-01T12:00:00Z';
      default:
        return `${field.label || field.title || field.field || '字段'}示例`;
    }
  };

  // ===== 导出文档（预览 / PDF） =====

  const openPreview = () => {
    if (!selectedMold) { message.warning('请先选择一个模型'); return; }
    const html = buildDocHtml(selectedType, selectedMold, examples, openContentBase, openSubjectBase);
    const w = window.open('', '_blank');
    if (w) {
      w.document.open();
      w.document.write(html);
      w.document.close();
      w.focus();
    } else {
      message.error('浏览器阻止了弹窗，请允许后重试');
    }
  };

  const exportPdf = () => {
    if (!selectedMold) { message.warning('请先选择一个模型'); return; }
    const base = buildDocHtml(selectedType, selectedMold, examples, openContentBase, openSubjectBase);
    const html = base.replace('</body>', '<script>window.addEventListener("load", () => setTimeout(() => window.print(), 300));</script></body>');
    const w = window.open('', '_blank');
    if (w) {
      w.document.open();
      w.document.write(html);
      w.document.close();
      w.focus();
    } else {
      message.error('浏览器阻止了弹窗，请允许后重试');
    }
  };

  return (
    <div className="py-6">
      <Head title="API 文档" />
      
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <Card
          title={(
            <span>
              <ApiOutlined className="mr-2" />API 文档
            </span>
          )}
          extra={(
            <Button type="primary" onClick={openExportModal}>导出API文档</Button>
          )}
        >
        <Card className="mb-6">
          <Space direction="vertical" style={{ width: '100%' }} size="large">
            <div>
              <Text strong className="block mb-2">{ _t('content_list', '内容列表')} </Text>
              <Radio.Group
                value={selectedType === 'content' ? selectedItem : null}
                onChange={(e) => handleSelectionChange('content', e.target.value)}
              >
                {contents.map((item) => (
                  <Radio.Button key={item.id} value={item.id}>
                    {item.name}
                  </Radio.Button>
                ))}
              </Radio.Group>
            </div>

            <div>
              <Text strong className="block mb-2">{ _t('content_single', '内容单页 ')} </Text>
              <Radio.Group
                value={selectedType === 'subject' ? selectedItem : null}
                onChange={(e) => handleSelectionChange('subject', e.target.value)}
              >
                {subjects.map((item) => (
                  <Radio.Button key={item.id} value={item.id}>
                    {item.name}
                  </Radio.Button>
                ))}
              </Radio.Group>
            </div>

            <div>
              <Text strong className="block mb-2">媒体资源   </Text>
              <Radio.Group
                value={selectedType === 'media' ? 1 : null}
                onChange={() => {
                  setSelectedType('media');
                  setSelectedItem(1);
                  setSelectedMold(mediaMoldPreset);
                  // 从 openContentBase 中提取 prefix，构建 media URL
                  const mediaBase = openContentBase.replace('/content', '/media');
                  setExamples(generateMediaApiDemos(mediaBase));
                  setError(null);
                }}
              >
                <Radio.Button value={1}>媒体资源 API</Radio.Button>
              </Radio.Group>
            </div>

            {projectAuthEnabled && (
              <div>
                <Text strong className="block mb-2">项目用户</Text>
                <Radio.Group
                  value={selectedType === 'auth' ? 1 : null}
                  onChange={() => {
                    setSelectedType('auth');
                    setSelectedItem(1);
                    setSelectedMold(authMoldPreset);
                    setExamples(generateAuthApiDemos(openAuthBase));
                    setError(null);
                  }}
                >
                  <Radio.Button value={1}>项目用户 API</Radio.Button>
                </Radio.Group>
              </div>
            )}

            <div>
              <Text strong className="block mb-2">Web 函数  </Text>
              <Radio.Group
                value={selectedType === 'function' ? selectedItem : null}
                onChange={(e) => {
                  const funcId = e.target.value;
                  const func = functions.find(f => f.id === funcId);
                  if (func) {
                    setSelectedType('function');
                    setSelectedItem(funcId);
                    setSelectedMold({
                      id: func.id,
                      name: func.name,
                      description: func.description,
                      table_name: func.slug,
                      mold_type: -1,
                      fields: [],
                      subject_content: {},
                      list_show_fields: [],
                      updated_at: null,
                    });
                    setExamples(generateFunctionApiDemos(openFunctionBase, func.slug, func.http_method, func.fields, func.input_schema));
                    setError(null);
                  }
                }}
              >
                {functions.map((func) => (
                  <Radio.Button key={func.id} value={func.id}>
                    {func.name}
                  </Radio.Button>
                ))}
              </Radio.Group>
            </div>
          </Space>
        </Card>

        {error && (
          <Alert
            message="获取示例失败"
            description={error}
            type="error"
            showIcon
            className="mb-4"
          />
        )}

        {selectedMold && (
          <>
          <Row gutter={[24, 24]} style={{marginTop:8}}>
            <Col xs={24} lg={10}>
              <Card loading={loading} className="h-full">
                <Space direction="vertical" size="large" style={{ width: '100%' }}>
                  <div>
                    <Text strong className="block text-base">{selectedType === 'function' ? '函数名' :_t('model_name', '模型名称')}</Text>
                    <Paragraph className="mb-1" ellipsis={{ rows: 2 }}>{selectedMold.name}</Paragraph>
                    <Text strong className="block text-base">{selectedType === 'function' ? '请求地址' :_t('table_name', '模型标识ID')}</Text>
                    <Paragraph className="mb-1" copyable ellipsis={{ rows: 2 }}>
                      {selectedType === 'auth' ? (() => {
                        const basePath = openAuthBase.replace(/^https?:\/\/[^/]+/i, '');
                        return basePath;
                      })() : selectedType === 'function' ? (() => {
                        const func = functions.find(f => f.id === selectedItem);
                        if (func) {
                          const method = (func.http_method || 'POST').toUpperCase();
                          const slug = func.slug || '';
                          const basePath = openFunctionBase.replace(/^https?:\/\/[^/]+/i, '');
                          const showUrl = `${method} ${basePath}/${slug}`;

                          return showUrl;
                        }
                        return selectedMold.table_name;
                      })() : selectedMold.table_name}
                    </Paragraph>
                    {selectedMold.description && (
                      <>
                        <Text strong className="block text-base">{ _t('description', '描述')}</Text>
                        <Paragraph className="mb-1" ellipsis={{ rows: 3 }}>{selectedMold.description}</Paragraph>
                      </>
                    )}
                    {selectedMold.updated_at && (
                      <>
                        <Text strong className="block text-base">{ _t('updated_at', '最近更新')}</Text>
                        <Paragraph className="mb-0">{selectedMold.updated_at}</Paragraph>
                      </>
                    )}
                  </div>

                  <div>
                    <Text strong className="block text-base mb-2">
                      {selectedType === 'function' ? '输入参数定义' :_t('field_definition', '字段定义')}
                    </Text>
                    {selectedType === 'function' ? (
                      <>
                        <Table
                          dataSource={(() => {
                            const func = functions.find(f => f.id === selectedItem);
                            if (!func || !func.input_schema || !func.input_schema.properties) {
                              return [];
                            }
                            const properties = func.input_schema.properties;
                            const required = func.input_schema.required || [];
                            return Object.keys(properties).map((key, index) => {
                              const prop = properties[key];
                              return {
                                key: index,
                                field: key,
                                type: prop.type || 'string',
                                required: required.includes(key) ? '是' : '否',
                                description: prop.description || '-',
                              };
                            });
                          })()}
                          pagination={false}
                          bordered
                          size="small"
                          columns={[
                            {
                              title: '参数名',
                              dataIndex: 'field',
                              key: 'field',
                              width: '25%',
                            },
                            {
                              title: '类型',
                              dataIndex: 'type',
                              key: 'type',
                              width: '20%',
                            },
                            {
                              title: '必填',
                              dataIndex: 'required',
                              key: 'required',
                              width: '15%',
                            },
                            {
                              title: '说明',
                              dataIndex: 'description',
                              key: 'description',
                            },
                          ]}
                          locale={{ emptyText: '暂无输入参数' }}
                        />
                        <Divider style={{ margin: '16px 0' }} />
                        <Text strong className="block text-base mb-2">输出参数定义</Text>
                        <Table
                          dataSource={(() => {
                            const func = functions.find(f => f.id === selectedItem);
                            if (!func || !func.output_schema || !func.output_schema.properties) {
                              return [];
                            }
                            const properties = func.output_schema.properties;
                            return Object.keys(properties).map((key, index) => {
                              const prop = properties[key];
                              return {
                                key: index,
                                field: key,
                                type: prop.type || 'string',
                                description: prop.description || '-',
                              };
                            });
                          })()}
                          pagination={false}
                          bordered
                          size="small"
                          columns={[
                            {
                              title: '参数名',
                              dataIndex: 'field',
                              key: 'field',
                              width: '30%',
                            },
                            {
                              title: '类型',
                              dataIndex: 'type',
                              key: 'type',
                              width: '25%',
                            },
                            {
                              title: '说明',
                              dataIndex: 'description',
                              key: 'description',
                            },
                          ]}
                          locale={{ emptyText: '暂无输出参数' }}
                        />
                      </>
                    ) : (
                      <Table
                        dataSource={selectedType === 'media' ? [
                          { key: 'id', field: 'id', label: '媒体ID', comment: '唯一标识' },
                          { key: 'filename', field: 'filename', label: '文件名', comment: '存储的文件名' },
                          { key: 'title', field: 'title', label: '标题', comment: '媒体标题' },
                          { key: 'alt', field: 'alt', label: 'Alt文本', comment: '图片替代文本' },
                          { key: 'description', field: 'description', label: '描述', comment: '媒体描述' },
                          { key: 'url', field: 'url', label: 'URL', comment: '访问地址' },
                          { key: 'type', field: 'type', label: '类型', comment: 'image/video/audio/document' },
                          { key: 'mime_type', field: 'mime_type', label: 'MIME类型', comment: '文件MIME类型' },
                          { key: 'size', field: 'size', label: '大小', comment: '文件大小（字节）' },
                          { key: 'width', field: 'width', label: '宽度', comment: '图片/视频宽度' },
                          { key: 'height', field: 'height', label: '高度', comment: '图片/视频高度' },
                          { key: 'duration', field: 'duration', label: '时长', comment: '音视频时长（秒）' },
                          { key: 'tags', field: 'tags', label: '标签', comment: '标签数组' },
                          { key: 'folder_path', field: 'folder_path', label: '文件夹路径', comment: '所在文件夹完整路径' },
                          { key: 'created_at', field: 'created_at', label: '创建时间', comment: '上传时间' },
                        ] : (selectedMold.fields?.map((field: any, index: number) => ({
                          key: `${field.field || index}`,
                          field: field.field || `field_${index + 1}`,
                          label: field.label || field.title || '-',
                          comment: '',
                        })) || [])}
                        pagination={false}
                        bordered
                        size="small"
                        columns={[
                          {
                            title:_t('field_name', '字段名'),
                            dataIndex: 'field',
                            key: 'field',
                            width: '30%',
                          },
                          {
                            title:_t('meaning', '含义'),
                            dataIndex: 'label',
                            key: 'label',
                            width: '35%',
                          },
                          {
                            title:_t('remark', '备注'),
                            dataIndex: 'comment',
                            key: 'comment',
                          },
                        ]}
                        locale={{ emptyText: '暂无字段定义' }}
                      />
                    )}
                  </div>
                </Space>
              </Card>
            </Col>

            <Col xs={24} lg={14}>
              <Space direction="vertical" size="large" style={{ width: '100%' }}>
                {availableOperations.map((operation) => (
                  <Card
                    key={operation.key}
                    title={`${operation.title}`}
                    loading={loading}
                  >
                    <Tabs
                      defaultActiveKey={operation.languages[0]?.key || 'curl'}
                      size="small"
                    >
                      {operation.languages.map((language) => (
                        <TabPane tab={language.label} key={language.key}>
                          <div
                            className="bg-gray-100 p-4 rounded"
                            style={{ position: 'relative' }}
                          >
                            <Button
                              size="small"
                              icon={<CopyOutlined />}
                              onClick={() =>
                                handleCopy(
                                  examples[operation.key]?.[language.key as LanguageKey] ?? '',
                                )
                              }
                              style={{ position: 'absolute', top: -10, right: 8 }}
                            >
                              复制
                            </Button>
                            <pre className="whitespace-pre text-sm" style={codeBlockStyle}>
                              {examples[operation.key][language.key]}
                              <br />
                              <br />
                              <br />
                            </pre>
                          </div>
                        </TabPane>
                      ))}
                    </Tabs>
                  </Card>
                ))}
              </Space>
            </Col>
          </Row>
          </>
        )}

        {!selectedMold && (
          <Alert
            message="提示"
            description={`请从上方选择要查看的模型，系统将自动生成对应的API调用示例。`}
            type="info"
            showIcon
            className="mt-4"
          />
        )}
        <Modal
          title="导出API文档"
          open={exportVisible}
          onCancel={() => setExportVisible(false)}
          footer={null}
          width={880}
        >
          <Space direction="vertical" style={{ width: '100%' }} size="middle">
            <div>
              <Typography.Text strong>选择接口类型</Typography.Text>
              <Divider style={{ margin: '8px 0' }} />
              <Row gutter={16}>
                <Col xs={24} md={12}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                    <Typography.Text type="secondary">内容模型</Typography.Text>
                    <Checkbox
                      checked={selectedContentIds.length === contents.length}
                      indeterminate={selectedContentIds.length > 0 && selectedContentIds.length < contents.length}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setSelectedContentIds(contents.map(c => c.id as number));
                        } else {
                          setSelectedContentIds([]);
                        }
                      }}
                    >
                      全选
                    </Checkbox>
                  </div>
                  <div>
                    <Checkbox.Group
                      style={{ width: '100%' }}
                      value={selectedContentIds}
                      onChange={(vals) => setSelectedContentIds(vals as number[])}
                      options={(contents || []).map(c => ({ label: c.name, value: c.id }))}
                    />
                  </div>
                </Col>
                <Col xs={24} md={12}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                    <Typography.Text type="secondary">单页模型</Typography.Text>
                    <Checkbox
                      checked={selectedSubjectIds.length === subjects.length}
                      indeterminate={selectedSubjectIds.length > 0 && selectedSubjectIds.length < subjects.length}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setSelectedSubjectIds(subjects.map(s => s.id as number));
                        } else {
                          setSelectedSubjectIds([]);
                        }
                      }}
                    >
                      全选
                    </Checkbox>
                  </div>
                  <div>
                    <Checkbox.Group
                      style={{ width: '100%' }}
                      value={selectedSubjectIds}
                      onChange={(vals) => setSelectedSubjectIds(vals as number[])}
                      options={(subjects || []).map(s => ({ label: s.name, value: s.id }))}
                    />
                  </div>
                </Col>
                <Col xs={24} md={12}>
                  <Typography.Text type="secondary">媒体资源</Typography.Text>
                  <div>
                    <Checkbox
                      checked={includeMedia}
                      onChange={(e) => setIncludeMedia(e.target.checked)}
                    >
                      包含媒体资源接口
                    </Checkbox>
                  </div>
                </Col>
                {projectAuthEnabled && (
                  <Col xs={24} md={12}>
                    <Typography.Text type="secondary">项目用户认证</Typography.Text>
                    <div>
                      <Checkbox
                        checked={includeAuth}
                        onChange={(e) => setIncludeAuth(e.target.checked)}
                      >
                        包含项目用户 API
                      </Checkbox>
                    </div>
                  </Col>
                )}
                <Col xs={24} md={12}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                    <Typography.Text type="secondary">Web函数</Typography.Text>
                    <Checkbox
                      checked={selectedFunctionIds.length === functions.length}
                      indeterminate={selectedFunctionIds.length > 0 && selectedFunctionIds.length < functions.length}
                      onChange={(e) => {
                        if (e.target.checked) {
                          setSelectedFunctionIds(functions.map(f => f.id as number));
                        } else {
                          setSelectedFunctionIds([]);
                        }
                      }}
                    >
                      全选
                    </Checkbox>
                  </div>
                  <div>
                    <Checkbox.Group
                      style={{ width: '100%' }}
                      value={selectedFunctionIds}
                      onChange={(vals) => setSelectedFunctionIds(vals as number[])}
                      options={(functions || []).map(f => ({ label: f.name, value: f.id }))}
                    />
                  </div>
                </Col>
              </Row>
            </div>

            <div>
              <Typography.Text strong>选择示例语言</Typography.Text>
              <Divider style={{ margin: '8px 0' }} />
              <Checkbox.Group
                value={selectedLangs}
                onChange={(vals) => setSelectedLangs(vals as LanguageKey[])}
                options={[
                  { label: 'cURL', value: 'curl' },
                  { label: 'JavaScript', value: 'javascript' },
                  { label: 'PHP', value: 'php' },
                  { label: 'Python', value: 'python' },
                ]}
              />
            </div>

            <div>
              <Typography.Text strong>选择接口类型</Typography.Text>
              <Divider style={{ margin: '8px 0' }} />
              <Checkbox.Group
                value={Object.entries(ops).filter(([,v]) => v).map(([k]) => k)}
                onChange={(vals) => {
                  const set = new Set(vals as string[]);
                  setOps({
                    select: set.has('select'),
                    create: set.has('create'),
                    update: set.has('update'),
                    delete: set.has('delete'),
                  });
                }}
                options={[
                  { label: '读取', value: 'select' },
                  { label: '写入', value: 'create' },
                  { label: '修改', value: 'update' },
                  { label: '删除', value: 'delete' },
                ]}
              />
              <div style={{ color: '#999', marginTop: 4 }}>默认仅导出读取接口，可按需勾选写入/修改/删除。</div>
            </div>
            <div>
              <Typography.Text strong>API Key（可选）<span style={{ color: 'red', marginLeft: 4 }}>注意API Key安全，不要将含有API Key的文档泄露给不信任的人</span></Typography.Text>
              <Divider style={{ margin: '8px 0' }} />
              <Input.Password placeholder="填写后将自动补全到示例请求头中 (x-api-key)" value={apiKey} onChange={(e) => setApiKey(e.target.value)} />
            </div>


            <Space style={{ justifyContent: 'flex-end', width: '100%', marginTop: 4 }}>
              <Button onClick={doPreviewExport}>预览</Button>
              <Button type="primary" onClick={doExportPdf}>导出</Button>
            </Space>
          </Space>
        </Modal>
      </Card>
      </div>
    </div>
  );
}
