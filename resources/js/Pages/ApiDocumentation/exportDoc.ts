import { DemoExamples, generateApiDemos, generateSubjectApiDemos, generateMediaApiDemos, generateFunctionApiDemos } from './ApiDemo';
import { MoldItem, MoldField } from '../../Types/Mold';

const escapeHtml = (s: string) => (s || '')
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;');

const buildExampleSection = (title: string, examples: any, languages: string[]) => {
  let html = `<h3>${escapeHtml(title)}示例</h3>`;
  
  languages.forEach(lang => {
    const langName = lang === 'curl' ? 'cURL' : 
                     lang === 'javascript' ? 'JavaScript' : 
                     lang === 'php' ? 'PHP' : 'Python';
    const code = examples[lang] || '';
    if (code) {
      html += `
        <h4>${langName}</h4>
        <pre>${escapeHtml(code)}</pre>
      `;
    }
  });
  
  return html;
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
      return `${field.label || (field as any).title || field.field || '字段'}示例`;
  }
};

export type LanguageKey = 'curl' | 'javascript' | 'php' | 'python';

interface ExportOptions {
  languages?: LanguageKey[]; // default all
  operations?: { select?: boolean; create?: boolean; update?: boolean; delete?: boolean }; // default all true
}

const buildExamplesBlock = (title: string, group: Record<string, string>, onlyLangs?: LanguageKey[]) => {
  const keys: LanguageKey[] = ['curl', 'javascript', 'php', 'python'];
  const blocks = keys
    .filter(k => !!(group as any)[k])
    .filter(k => !onlyLangs || onlyLangs.includes(k))
    .map(k => `<h4>${k.toUpperCase()}</h4><pre>${escapeHtml((group as any)[k])}</pre>`)
    .join('');
  return blocks ? `<div class=\"section\"><h3>${title}</h3>${blocks}</div>` : '';
};

const buildModelSectionHtmlWithOptions = (
  type: 'content' | 'subject',
  mold: MoldItem,
  openContentBase: string,
  openSubjectBase: string,
  opts?: ExportOptions,
): string => {
  const endpoints = buildEndpointRows(type, mold, openContentBase, openSubjectBase);
  const fields = Array.isArray(mold.fields) ? mold.fields : [];
  const payload = buildSamplePayload(mold);
  const payloadPretty = JSON.stringify(payload, null, 2);
  const demo = type === 'content'
    ? generateApiDemos(openContentBase, mold.table_name, mold.name, payload)
    : generateSubjectApiDemos(openSubjectBase, mold.table_name, mold.name, payload);

  const langs = (opts?.languages && opts.languages.length > 0 ? opts.languages : ['curl', 'javascript', 'php', 'python']) as LanguageKey[];
  const ops = {
    select: opts?.operations?.select !== false,
    create: opts?.operations?.create !== false,
    update: opts?.operations?.update !== false,
    delete: opts?.operations?.delete !== false,
  };

  const opBlocks: string[] = [];
  if (ops.select) opBlocks.push(buildExamplesBlock('查看/查询 示例', demo.select as unknown as Record<string, string>, langs));
  if (ops.create) opBlocks.push(buildExamplesBlock('新增 示例', demo.create as unknown as Record<string, string>, langs));
  if (ops.update) opBlocks.push(buildExamplesBlock('修改 示例', demo.update as unknown as Record<string, string>, langs));
  if (ops.delete) opBlocks.push(buildExamplesBlock('删除 示例', demo.delete as unknown as Record<string, string>, langs));

  return `
    <div class=\"section\">
      <h2>${escapeHtml(mold.name)}（${escapeHtml(mold.table_name)}）</h2>
      ${mold.description ? `<p class=\"meta\">${escapeHtml(mold.description)}</p>` : ''}
      ${mold.updated_at ? `<p class=\"meta\">最近更新：${escapeHtml(mold.updated_at)}</p>` : ''}

      <h3>字段定义</h3>
      ${fields.length === 0 ? '<p class=\"meta\">暂无字段定义</p>' : `
        <table>
          <thead><tr><th>字段名</th><th>含义</th></tr></thead>
          <tbody>
            ${fields.map((f, i) => `
              <tr>
                <td class=\"endpoint\">${escapeHtml(f.field || `field_${i + 1}`)}</td>
                <td>${escapeHtml((f.label || (f as any).title || f.field || '-') as string)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `}

      <h3>接口定义</h3>
      <table>
        <thead><tr><th>方法</th><th>URL</th><th>说明</th></tr></thead>
        <tbody>
          ${endpoints.map(ep => `
            <tr>
              <td>${ep.method}</td>
              <td class=\"endpoint\">${escapeHtml(ep.url)}</td>
              <td>${escapeHtml(ep.desc)}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>

      <h3>示例请求载荷（部分字段示例）</h3>
      <pre>${escapeHtml(payloadPretty)}</pre>

      ${opBlocks.join('\n')}
    </div>
    <div class=\"page-break\"></div>
  `;
};

export const buildFullDocHtmlWithOptions = (
  contents: MoldItem[],
  subjects: MoldItem[],
  openContentBase: string,
  openSubjectBase: string,
  selectedContentIds: number[],
  selectedSubjectIds: number[],
  opts?: ExportOptions,
  includeMedia?: boolean,
  mediaBaseUrl?: string,
  functions?: any[],
  selectedFunctionIds?: number[],
  functionBaseUrl?: string,
  includeAuth?: boolean,
  authBaseUrl?: string,
): string => {
  const pageTitle = 'API 文档（导出）';
  const css = `
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans SC', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif; padding: 24px; color: #111827; }
    h1 { font-size: 22px; margin: 0 0 8px; }
    h2 { font-size: 18px; margin: 24px 0 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; }
    h3 { font-size: 16px; margin: 16px 0 6px; }
    p, li { font-size: 13px; line-height: 1.65; }
    code, pre { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 12px; }
    pre { background: #f3f4f6; padding: 12px; border-radius: 6px; overflow: auto; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #e5e7eb; padding: 8px; font-size: 12px; text-align: left; vertical-align: top; }
    th { background: #f9fafb; }
    .meta { color: #6b7280; }
    .endpoint { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; }
    .section { page-break-inside: avoid; }
    .page-break { page-break-after: always; height: 1px; }
  `;

  const selectedContents = (contents || []).filter(m => selectedContentIds.includes(m.id as any));
  const selectedSubjects = (subjects || []).filter(m => selectedSubjectIds.includes(m.id as any));

  const contentSections = selectedContents.map(m => buildModelSectionHtmlWithOptions('content', m, openContentBase, openSubjectBase, opts)).join('');
  const subjectSections = selectedSubjects.map(m => buildModelSectionHtmlWithOptions('subject', m, openContentBase, openSubjectBase, opts)).join('');
  
  // 媒体资源部分
  let mediaSection = '';
  if (includeMedia && mediaBaseUrl) {
    mediaSection = buildMediaSectionHtml(mediaBaseUrl, opts);
  }
  
  // Web 函数部分
  let functionSections = '';
  if (functions && selectedFunctionIds && functionBaseUrl) {
    const selectedFunctions = functions.filter(f => selectedFunctionIds.includes(f.id));
    functionSections = selectedFunctions.map(f => buildFunctionSectionHtml(f, functionBaseUrl, opts)).join('');
  }

  const authSection = includeAuth && authBaseUrl ? buildAuthSectionHtml(authBaseUrl, opts) : '';

  return `<!doctype html>
  <html>
    <head>
      <meta charset=\"utf-8\" />
      <title>${escapeHtml(pageTitle)}</title>
      <style>${css}</style>
    </head>
    <body>
      <h1>${escapeHtml(pageTitle)}</h1>
      ${contentSections}
      ${subjectSections}
      ${mediaSection}
      ${functionSections}
      ${authSection}
    </body>
  </html>`;
};

const buildAuthSectionHtml = (authBaseUrl: string, opts?: ExportOptions) => {
  const languages = opts?.languages || ['curl', 'javascript', 'php', 'python'];
  const endpointRows = `
    <tr><td>POST</td><td class="endpoint">${authBaseUrl}/login</td><td>项目用户登录，返回 pu_* 登录态</td></tr>
    <tr><td>POST</td><td class="endpoint">${authBaseUrl}/register</td><td>项目用户注册（需开启允许公开注册），返回 pu_* 登录态</td></tr>
    <tr><td>GET</td><td class="endpoint">${authBaseUrl}/me</td><td>获取当前项目用户信息</td></tr>
    <tr><td>POST</td><td class="endpoint">${authBaseUrl}/logout</td><td>退出登录并撤销当前登录态</td></tr>
  `;
  const examples = {
    login: {
      curl: `# 项目用户登录
curl -X POST '${authBaseUrl}/login' \
  -H 'Content-Type: application/json' \
  -d '{"account":"user@example.com","password":"123456"}'`,
      javascript: `const response = await fetch('${authBaseUrl}/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ account: 'user@example.com', password: '123456' }),
});
const { data } = await response.json();
localStorage.setItem('project_user_token', data.token);`,
      php: `<?php
// 使用 GuzzleHttp\Client POST ${authBaseUrl}/login
$payload = ['account' => 'user@example.com', 'password' => '123456'];`,
      python: `import requests
payload = {'account': 'user@example.com', 'password': '123456'}
response = requests.post('${authBaseUrl}/login', json=payload)
print(response.json())`,
    },
    register: {
      curl: `# 项目用户注册
curl -X POST '${authBaseUrl}/register' \
  -H 'Content-Type: application/json' \
  -d '{"email":"user@example.com","password":"123456","name":"张三"}'`,
      javascript: `const response = await fetch('${authBaseUrl}/register', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email: 'user@example.com', password: '123456', name: '张三' }),
});
console.log(await response.json());`,
      php: `<?php
// 使用 GuzzleHttp\Client POST ${authBaseUrl}/register
$payload = ['email' => 'user@example.com', 'password' => '123456', 'name' => '张三'];`,
      python: `import requests
payload = {'email': 'user@example.com', 'password': '123456', 'name': '张三'}
response = requests.post('${authBaseUrl}/register', json=payload)
print(response.json())`,
    },
    me: {
      curl: `# 当前项目用户
curl -X GET '${authBaseUrl}/me' \
  -H 'Authorization: Bearer pu_{projectPrefix}_xxx'`,
      javascript: `const token = localStorage.getItem('project_user_token');
const response = await fetch('${authBaseUrl}/me', {
  headers: { Authorization: \`Bearer \${token}\` },
});
console.log(await response.json());`,
      php: `<?php
// 使用 Authorization Bearer 调用 ${authBaseUrl}/me
$token = 'pu_xxx';`,
      python: `import requests
token = 'pu_xxx'
response = requests.get('${authBaseUrl}/me', headers={'Authorization': f'Bearer {token}'})
print(response.json())`,
    },
    logout: {
      curl: `# 退出登录
curl -X POST '${authBaseUrl}/logout' \
  -H 'Authorization: Bearer pu_{projectPrefix}_xxx'`,
      javascript: `const token = localStorage.getItem('project_user_token');
await fetch('${authBaseUrl}/logout', {
  method: 'POST',
  headers: { Authorization: \`Bearer \${token}\` },
});`,
      php: `<?php
// 使用 Authorization Bearer POST ${authBaseUrl}/logout
$token = 'pu_xxx';`,
      python: `import requests
token = 'pu_xxx'
requests.post('${authBaseUrl}/logout', headers={'Authorization': f'Bearer {token}'})`,
    },
  };

  const fieldsHtml = `
    <h3>字段定义</h3>
    <table>
      <thead><tr><th>字段名</th><th>类型</th><th>说明</th></tr></thead>
      <tbody>
        <tr><td>projectPrefix</td><td>string</td><td>项目前缀，例如 kxm11</td></tr>
        <tr><td>account</td><td>string</td><td>登录账号，可填写邮箱、用户名或手机号</td></tr>
        <tr><td>email</td><td>string</td><td>注册邮箱；和 username 至少填写一项</td></tr>
        <tr><td>username</td><td>string</td><td>注册用户名；和 email 至少填写一项</td></tr>
        <tr><td>name</td><td>string</td><td>项目用户显示名称</td></tr>
        <tr><td>password</td><td>string</td><td>登录或注册密码</td></tr>
      </tbody>
    </table>
  `;

  return `
    <div class="section">
      <h2>项目用户认证 API</h2>
      <p class="meta">登录/注册成功后返回 pu_* 登录态；调用 /open/* 时使用 Authorization: Bearer pu_*。</p>
      ${fieldsHtml}
      <h3>接口列表</h3>
      <table>
        <thead><tr><th>方法</th><th>接口地址</th><th>说明</th></tr></thead>
        <tbody>${endpointRows}</tbody>
      </table>
      ${buildExampleSection('项目用户登录', examples.login, languages)}
      ${buildExampleSection('项目用户注册', examples.register, languages)}
      ${buildExampleSection('当前项目用户', examples.me, languages)}
      ${buildExampleSection('退出登录', examples.logout, languages)}
    </div>
  `;
};

const buildMediaSectionHtml = (mediaBaseUrl: string, opts?: ExportOptions) => {
  const operations = opts?.operations || { select: true, create: false, update: false, delete: false };
  const languages = opts?.languages || ['curl', 'javascript', 'php', 'python'];
  
  // 字段定义
  const fieldsHtml = `
    <h3>字段定义</h3>
    <table>
      <thead>
        <tr><th>字段名</th><th>类型</th><th>说明</th></tr>
      </thead>
      <tbody>
        <tr><td>id</td><td>number</td><td>媒体ID，唯一标识</td></tr>
        <tr><td>filename</td><td>string</td><td>存储的文件名</td></tr>
        <tr><td>title</td><td>string</td><td>媒体标题</td></tr>
        <tr><td>alt</td><td>string</td><td>图片替代文本</td></tr>
        <tr><td>description</td><td>string</td><td>媒体描述</td></tr>
        <tr><td>url</td><td>string</td><td>访问地址</td></tr>
        <tr><td>type</td><td>string</td><td>类型：image/video/audio/document</td></tr>
        <tr><td>mime_type</td><td>string</td><td>文件MIME类型</td></tr>
        <tr><td>size</td><td>number</td><td>文件大小（字节）</td></tr>
        <tr><td>width</td><td>number</td><td>图片/视频宽度</td></tr>
        <tr><td>height</td><td>number</td><td>图片/视频高度</td></tr>
        <tr><td>duration</td><td>number</td><td>音视频时长（秒）</td></tr>
        <tr><td>tags</td><td>array</td><td>标签数组</td></tr>
        <tr><td>folder_path</td><td>string</td><td>所在文件夹完整路径</td></tr>
        <tr><td>created_at</td><td>string</td><td>上传时间</td></tr>
      </tbody>
    </table>
  `;
  
  // 接口列表
  let endpointsHtml = '';
  if (operations.select) {
    endpointsHtml += `
      <tr><td>GET</td><td class="endpoint">${mediaBaseUrl}/list</td><td>获取媒体列表</td></tr>
      <tr><td>GET</td><td class="endpoint">${mediaBaseUrl}/detail/{id}</td><td>获取媒体详情</td></tr>
      <tr><td>GET</td><td class="endpoint">${mediaBaseUrl}/search</td><td>搜索媒体</td></tr>
    `;
  }
  if (operations.create) {
    endpointsHtml += `<tr><td>POST</td><td class="endpoint">${mediaBaseUrl}/create</td><td>创建媒体（上传文件）</td></tr>`;
  }
  if (operations.update) {
    endpointsHtml += `<tr><td>PUT</td><td class="endpoint">${mediaBaseUrl}/update/{id}</td><td>更新媒体信息</td></tr>`;
  }
  if (operations.delete) {
    endpointsHtml += `<tr><td>DELETE</td><td class="endpoint">${mediaBaseUrl}/delete/{id}</td><td>删除媒体</td></tr>`;
  }
  
  // 生成示例代码
  const demos = generateMediaApiDemos(mediaBaseUrl);
  let examplesHtml = '';
  
  if (operations.select && demos.select) {
    examplesHtml += buildExampleSection('查询媒体', demos.select, languages);
  }
  if (operations.create && demos.create) {
    examplesHtml += buildExampleSection('创建媒体', demos.create, languages);
  }
  if (operations.update && demos.update) {
    examplesHtml += buildExampleSection('更新媒体', demos.update, languages);
  }
  if (operations.delete && demos.delete) {
    examplesHtml += buildExampleSection('删除媒体', demos.delete, languages);
  }
  
  return `
    <div class="section">
      <h2>媒体资源 API</h2>
      ${fieldsHtml}
      <h3>接口列表</h3>
      <table>
        <thead>
          <tr><th>方法</th><th>接口地址</th><th>说明</th></tr>
        </thead>
        <tbody>
          ${endpointsHtml}
        </tbody>
      </table>
      ${examplesHtml}
    </div>
  `;
};

const buildFunctionSectionHtml = (func: any, functionBaseUrl: string, opts?: ExportOptions) => {
  const method = (func.http_method || 'POST').toUpperCase();
  const slug = func.slug || '';
  const url = `${functionBaseUrl}/${slug}`;
  const languages = opts?.languages || ['curl', 'javascript', 'php', 'python'];
  
  let inputParamsHtml = '';
  if (func.input_schema && func.input_schema.properties) {
    const properties = func.input_schema.properties;
    const required = func.input_schema.required || [];
    inputParamsHtml = Object.keys(properties).map(key => {
      const prop = properties[key];
      return `<tr>
        <td>${escapeHtml(key)}</td>
        <td>${escapeHtml(prop.type || 'string')}</td>
        <td>${required.includes(key) ? '是' : '否'}</td>
        <td>${escapeHtml(prop.description || '-')}</td>
      </tr>`;
    }).join('');
  }
  
  let outputParamsHtml = '';
  if (func.output_schema && func.output_schema.properties) {
    const properties = func.output_schema.properties;
    outputParamsHtml = Object.keys(properties).map(key => {
      const prop = properties[key];
      return `<tr>
        <td>${escapeHtml(key)}</td>
        <td>${escapeHtml(prop.type || 'string')}</td>
        <td>${escapeHtml(prop.description || '-')}</td>
      </tr>`;
    }).join('');
  }
  
  // 生成请求示例
  const demos = generateFunctionApiDemos(
    functionBaseUrl,
    slug,
    func.http_method || 'POST',
    [],
    func.input_schema
  );
  const examplesHtml = demos.select ? buildExampleSection('请求', demos.select, languages) : '';
  
  return `
    <div class="section">
      <h2>Web 函数：${escapeHtml(func.name)}</h2>
      <p><strong>请求地址：</strong><code class="endpoint">${method} ${url}</code></p>
      ${func.remark ? `<p><strong>说明：</strong>${escapeHtml(func.remark)}</p>` : ''}
      
      ${inputParamsHtml ? `
        <h3>输入参数</h3>
        <table>
          <thead>
            <tr><th>参数名</th><th>类型</th><th>必填</th><th>说明</th></tr>
          </thead>
          <tbody>
            ${inputParamsHtml}
          </tbody>
        </table>
      ` : '<p>无输入参数</p>'}
      
      ${outputParamsHtml ? `
        <h3>输出参数</h3>
        <table>
          <thead>
            <tr><th>参数名</th><th>类型</th><th>说明</th></tr>
          </thead>
          <tbody>
            ${outputParamsHtml}
          </tbody>
        </table>
      ` : '<p>无输出参数定义</p>'}
      
      ${examplesHtml}
    </div>
  `;
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

const buildEndpointRows = (
  type: 'content' | 'subject' | 'media' | 'function',
  m: MoldItem,
  openContentBase: string,
  openSubjectBase: string,
) => {
  const table = m.table_name;
  if (type === 'content') {
    return [
      { method: 'GET', url: `${openContentBase}/list/${table}`, desc: '获取列表（支持分页/字段/排序/过滤）' },
      { method: 'GET', url: `${openContentBase}/detail/${table}/{id}`, desc: '获取详情' },
      { method: 'GET', url: `${openContentBase}/count/${table}`, desc: '统计数量' },
      { method: 'POST', url: `${openContentBase}/create/${table}`, desc: '新增' },
      { method: 'PUT', url: `${openContentBase}/update/${table}/{id}`, desc: '更新' },
      { method: 'DELETE', url: `${openContentBase}/delete/${table}/{id}`, desc: '删除' },
    ];
  }
  return [
    { method: 'GET', url: `${openSubjectBase}/detail/${table}`, desc: '获取单页详情' },
    { method: 'PUT', url: `${openSubjectBase}/update/${table}`, desc: '更新单页内容' },
  ];
};

export const buildDocHtml = (
  type: 'content' | 'subject' | 'media' | 'function',
  mold: MoldItem,
  demo: DemoExamples,
  openContentBase: string,
  openSubjectBase: string,
): string => {
  const endpoints = buildEndpointRows(type, mold, openContentBase, openSubjectBase);
  const fields = Array.isArray(mold.fields) ? mold.fields : [];
  const payload = buildSamplePayload(mold);
  const payloadPretty = JSON.stringify(payload, null, 2);
  const pageTitle = `API 文档 - ${mold.name}`;

  const css = `
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans SC', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif; padding: 24px; color: #111827; }
    h1 { font-size: 22px; margin: 0 0 8px; }
    h2 { font-size: 18px; margin: 24px 0 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; }
    h3 { font-size: 16px; margin: 16px 0 6px; }
    p, li { font-size: 13px; line-height: 1.65; }
    code, pre { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 12px; }
    pre { background: #f3f4f6; padding: 12px; border-radius: 6px; overflow: auto; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #e5e7eb; padding: 8px; font-size: 12px; text-align: left; vertical-align: top; }
    th { background: #f9fafb; }
    .meta { color: #6b7280; }
    .endpoint { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; }
    .section { page-break-inside: avoid; }
    .page-break { page-break-after: always; height: 1px; }
  `;

  const examplesBlock = (title: string, group: Record<string, string>) => {
    const keys = ['curl', 'javascript', 'php', 'python'] as const;
    const blocks = keys
      .filter(k => !!(group as any)[k])
      .map(k => `<h4>${k.toUpperCase()}</h4><pre>${escapeHtml((group as any)[k])}</pre>`)
      .join('');
    return blocks ? `<div class="section"><h3>${title}</h3>${blocks}</div>` : '';
  };

  return `<!doctype html>
  <html>
    <head>
      <meta charset="utf-8" />
      <title>${escapeHtml(pageTitle)}</title>
      <style>${css}</style>
    </head>
    <body>
      <h1>${escapeHtml(mold.name)}（${escapeHtml(mold.table_name)}）</h1>
      ${mold.description ? `<p class="meta">${escapeHtml(mold.description)}</p>` : ''}
      ${mold.updated_at ? `<p class="meta">最近更新：${escapeHtml(mold.updated_at)}</p>` : ''}

      <div class="section">
        <h2>字段定义</h2>
        ${fields.length === 0 ? '<p class="meta">暂无字段定义</p>' : `
          <table>
            <thead><tr><th>字段名</th><th>含义</th></tr></thead>
            <tbody>
              ${fields.map((f, i) => `
                <tr>
                  <td class="endpoint">${escapeHtml(f.field || `field_${i + 1}`)}</td>
                  <td>${escapeHtml((f.label || (f as any).title || f.field || '-') as string)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        `}
      </div>

      <div class="section">
        <h2>接口定义</h2>
        <table>
          <thead><tr><th>方法</th><th>URL</th><th>说明</th></tr></thead>
          <tbody>
            ${endpoints.map(ep => `
              <tr>
                <td>${ep.method}</td>
                <td class="endpoint">${escapeHtml(ep.url)}</td>
                <td>${escapeHtml(ep.desc)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>

      <div class="section">
        <h2>示例请求载荷（部分字段示例）</h2>
        <pre>${escapeHtml(payloadPretty)}</pre>
      </div>

      ${examplesBlock('查看/查询 示例', demo.select as unknown as Record<string, string>)}
      ${examplesBlock('新增 示例', demo.create as unknown as Record<string, string>)}
      ${examplesBlock('修改 示例', demo.update as unknown as Record<string, string>)}
      ${examplesBlock('删除 示例', demo.delete as unknown as Record<string, string>)}

      <script>
        // 在导出 PDF 模式下可自动触发打印（由调用者决定是否插入此脚本）
      </script>
    </body>
  </html>`;
};
