export type LanguageKey = 'curl' | 'javascript' | 'php' | 'python';

export type ExampleGroup = Record<LanguageKey, string>;

export interface DemoExamples {
  select: ExampleGroup;
  create: ExampleGroup;
  update: ExampleGroup;
  delete: ExampleGroup;
}

export interface DemoLanguage {
  key: LanguageKey;
  label: string;
}

export interface DemoOperation {
  key: keyof DemoExamples;
  title: string;
  method: 'GET' | 'POST' | 'PUT' | 'DELETE';
  languages: DemoLanguage[];
}

const defaultLanguages: DemoLanguage[] = [
  { key: 'curl', label: 'cURL' },
  { key: 'javascript', label: 'JavaScript' },
  { key: 'php', label: 'PHP' },
  { key: 'python', label: 'Python' },
];

export const demoSubjectOperations:DemoOperation[] = [
    {
      key: 'select',
      title: '查看接口 Demo',
      method: 'GET',
      languages: defaultLanguages,
    },
    {
      key: 'update',
      title: '修改接口 Demo',
      method: 'PUT',
      languages: defaultLanguages,
    }
  ];

export const demoOperations: DemoOperation[] = [
  {
    key: 'select',
    title: '查看接口 Demo',
    method: 'GET',
    languages: defaultLanguages,
  },
  {
    key: 'create',
    title: '新增接口 Demo',
    method: 'POST',
    languages: defaultLanguages,
  },
  {
    key: 'update',
    title: '修改接口 Demo',
    method: 'PUT',
    languages: defaultLanguages,
  },
  {
    key: 'delete',
    title: '删除接口 Demo',
    method: 'DELETE',
    languages: defaultLanguages,
  },
];

export const demoMediaOperations: DemoOperation[] = [
  {
    key: 'select',
    title: '查询接口 Demo',
    method: 'GET',
    languages: defaultLanguages,
  },
  {
    key: 'create',
    title: '创建接口 Demo',
    method: 'POST',
    languages: defaultLanguages,
  },
  {
    key: 'update',
    title: '更新接口 Demo',
    method: 'PUT',
    languages: defaultLanguages,
  },
  {
    key: 'delete',
    title: '删除接口 Demo',
    method: 'DELETE',
    languages: defaultLanguages,
  },
];

const API_KEY_HEADER = 'x-api-key';
const API_KEY_PLACEHOLDER = 'YOUR_API_KEY';

const toCompactJson = (payload: Record<string, unknown>) =>
  JSON.stringify(payload).replace(/'/g, "\\'");

const toPrettyJson = (payload: Record<string, unknown>) =>
  JSON.stringify(payload, null, 2);

const toPhpPayloadEntries = (payload: Record<string, unknown>) =>
  Object.entries(payload)
    .map(([key, value]) => `    '${key}' => ${formatPhpValue(value)},`)
    .join('\n');

const toPythonPayload = (payload: Record<string, unknown>) =>
  JSON.stringify(payload, null, 2)
    .replace(/"([^"(]+)":/g, "'$1':")
    .replace(/"/g, "'");

const formatPhpValue = (value: unknown): string | number => {
  if (typeof value === 'number') {
    return value;
  }
  if (typeof value === 'boolean') {
    return value ? 'true' : 'false';
  }
  return `'${String(value)}'`;
};

const createEmptyGroup = (): ExampleGroup => ({
  curl: '',
  javascript: '',
  php: '',
  python: '',
});

const toMultiline = (lines: string[]): string => lines.join('\n');

export const generateSubjectApiDemos = (
  baseUrl: string,
  tableName: string,
  moldName: string,
  payload: Record<string, unknown>,
): DemoExamples => {
  const compactPayload = toCompactJson(payload);
  const prettyPayload = toPrettyJson(payload);
  const phpPayloadEntries = toPhpPayloadEntries(payload);
  const pythonPayload = toPythonPayload(payload);

    const detailUrl = `${baseUrl}/detail/${tableName}`;
    const updateUrl = `${baseUrl}/update/${tableName}`;

    const select: ExampleGroup = {
      curl: toMultiline([
        `# 获取${moldName}详情`,
        `curl -X GET '${detailUrl}' \\`,
        `  -H 'Accept: application/json' \\`,
        `  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}'`,
      ]),
      javascript: toMultiline([
        `const headers = { '${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}' };`,
        '',
        `// 获取${moldName}详情`,
        `const response = await fetch('${detailUrl}', { headers });`,
        'console.log(await response.json());',
      ]),
      php: toMultiline([
        '<?php',
        '',
        'use GuzzleHttp\\Client;',
        '',
        '$client = new Client();',
        `$headers = ['${API_KEY_HEADER}' => '${API_KEY_PLACEHOLDER}'];`,
        '',
        `// 获取${moldName}详情`,
        `$response = $client->get('${detailUrl}', ['headers' => $headers]);`,
        '$detail = json_decode($response->getBody()->getContents(), true);',
      ]),
      python: toMultiline([
        'import requests',
        '',
        `headers = {'${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}'}`, 
        '',
        `# 获取${moldName}详情`,
        `response = requests.get('${detailUrl}', headers=headers)`,
        'print(response.json())',
      ]),
    };

    const update: ExampleGroup = {
      curl: toMultiline([
        `# 更新${moldName}`,
        `curl -X PUT '${updateUrl}' \\`,
        `  -H 'Content-Type: application/json' \\`,
        `  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}' \\`,
        `  -d '${compactPayload}'`,
      ]),
      javascript: toMultiline([
        'const headers = {',
        `  'Content-Type': 'application/json',`,
        `  '${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}',`,
        '};',
        `const payload = ${prettyPayload};`,
        '',
        `// 更新${moldName}`,
        `await fetch('${updateUrl}', {`,
        "  method: 'PUT',",
        '  headers,',
        '  body: JSON.stringify(payload),',
        '});',
      ]),
      php: toMultiline([
        '<?php',
        '',
        'use GuzzleHttp\\Client;',
        '',
        '$client = new Client();',
        `$headers = ['${API_KEY_HEADER}' => '${API_KEY_PLACEHOLDER}', 'Content-Type' => 'application/json'];`,
        '$payload = [',
        `${phpPayloadEntries}`,
        '];',
        '',
        `// 更新${moldName}`,
        `$client->put('${updateUrl}', [`,
        "    'headers' => $headers,",
        "    'json' => $payload,",
        ']);',
      ]),
      python: toMultiline([
        'import requests',
        '',
        `headers = {'${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}', 'Content-Type': 'application/json'}`,
        `payload = ${pythonPayload}`,
        '',
        `# 更新${moldName}`,
        `requests.put('${updateUrl}', json=payload, headers=headers)`,
      ]),
    };

    return {
      select,
      create: createEmptyGroup(),
      update,
      delete: createEmptyGroup(),
    };
  }
  

export const generateApiDemos = (
  baseUrl: string,
  tableName: string,
  moldName: string,
  payload: Record<string, unknown>,
): DemoExamples => {
  const compactPayload = toCompactJson(payload);
  const prettyPayload = toPrettyJson(payload);
  const phpPayloadEntries = toPhpPayloadEntries(payload);
  const pythonPayload = toPythonPayload(payload);

  const listUrl = `${baseUrl}/list/${tableName}`;
  const detailUrl = `${baseUrl}/detail/${tableName}/1`;
  const countUrl = `${baseUrl}/count/${tableName}`;
  const createUrl = `${baseUrl}/create/${tableName}`;
  const updateUrl = `${baseUrl}/update/${tableName}/1`;
  const deleteUrl = `${baseUrl}/delete/${tableName}/1`;

  const select: ExampleGroup = {
    curl: `# 获取${moldName}列表（含分页/字段/排序/过滤）\ncurl -X GET '${listUrl}?page=1&page_size=10&fields=id,title,created_at&sort=-created_at,title' \\\n  -H 'Accept: application/json' \\\n  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}'\n\n# 获取${moldName}详情\ncurl -X GET '${detailUrl}' \\\n  -H 'Accept: application/json' \\\n  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}'\n\n# 统计${moldName}数量\ncurl -X GET '${countUrl}' \\\n  -H 'Accept: application/json' \\\n  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}'`,
    javascript: `// 公共请求头\nconst headers = { '${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}' };\n\n// 获取${moldName}列表（含 meta/links）\nconst listResponse = await fetch("${listUrl}?page=1&page_size=10&fields=id,title,created_at&sort=-created_at,title", { headers });\nconst listJson = await listResponse.json();\nconst { data: listItems, meta: listMeta, links: listLinks } = listJson.data;\nconsole.log(listItems, listMeta, listLinks);\n\n// 获取${moldName}详情\nconst detailResponse = await fetch('${detailUrl}', { headers });\nconst detailJson = await detailResponse.json();\nconsole.log(detailJson.data);`,
    php: `<?php\n\nuse GuzzleHttp\\Client;\n\n$client = new Client();\n\n$headers = ['${API_KEY_HEADER}' => '${API_KEY_PLACEHOLDER}'];\n\n// 获取${moldName}列表（含分页/字段/排序/过滤）\n$listResponse = $client->get('${listUrl}', [\n    'headers' => $headers,\n    'query' => [\n        'page' => 1,\n        'page_size' => 10,\n        'fields' => 'id,title,created_at',\n        'sort' => '-created_at,title',\n        'filter' => [\n            'status' => 'published',\n            'title' => ['op' => 'like', 'value' => '示例']\n        ],\n    ],\n]);\n$listJson = json_decode($listResponse->getBody()->getContents(), true);\n$listData = $listJson['data'] ?? [];\n\n// 获取${moldName}详情\n$detailResponse = $client->get('${detailUrl}', [\n    'headers' => $headers,\n]);\n$detailJson = json_decode($detailResponse->getBody()->getContents(), true);\n$detailData = $detailJson['data'] ?? [];`,
    python: `import requests\n\nbase_url = '${baseUrl}'\ntable_name = '${tableName}'\nheaders = {'${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}'}\n\n# 获取${moldName}列表（含分页/字段/排序/过滤）\nparams = {\n  'page': 1,\n  'page_size': 10,\n  'fields': 'id,title,created_at',\n  'sort': '-created_at,title',\n  'filter[status]': 'published',\n  'filter[title][op]': 'like',\n  'filter[title][value]': '示例'\n}\nlist_res = requests.get(f"{base_url}/list/{table_name}", params=params, headers=headers)\nlist_json = list_res.json()\nprint(list_json.get('data'))\n\n# 获取${moldName}详情\ndetail_res = requests.get(f"{base_url}/detail/{table_name}/1", headers=headers)\nprint(detail_res.json().get('data'))`,
  };

  const create: ExampleGroup = {
    curl: `# 创建${moldName}\ncurl -X POST '${createUrl}' \\
  -H 'Content-Type: application/json' \\
  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}' \\
  -d '${compactPayload}'`,
    javascript: `// 创建${moldName}\nconst payload = ${prettyPayload};\n\nconst response = await fetch('${createUrl}', {\n  method: 'POST',\n  headers: {\n    'Content-Type': 'application/json',\n    '${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}',\n  },\n  body: JSON.stringify(payload),\n});\nconsole.log(await response.json());`,
    php: `<?php\n\nuse GuzzleHttp\\Client;\n\n$client = new Client();\n\n$payload = [\n${phpPayloadEntries}\n];\n$headers = ['${API_KEY_HEADER}' => '${API_KEY_PLACEHOLDER}', 'Content-Type' => 'application/json'];\n\n// 创建${moldName}\n$response = $client->post('${createUrl}', [\n    'headers' => $headers,\n    'json' => $payload,\n]);\n$created = json_decode($response->getBody()->getContents(), true);`,
    python: `import requests\n\nbase_url = '${baseUrl}'\ntable_name = '${tableName}'\n\npayload = ${pythonPayload}\nheaders = {'${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}', 'Content-Type': 'application/json'}\n\n# 创建${moldName}\ncreate_res = requests.post(f"{base_url}/create/{table_name}", json=payload, headers=headers)\nprint(create_res.json())`,
  };

  const update: ExampleGroup = {
    curl: `# 更新${moldName}\ncurl -X PUT '${updateUrl}' \\
  -H 'Content-Type: application/json' \\
  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}' \\
  -d '${compactPayload}'`,
    javascript: `// 更新${moldName}\nconst payload = ${prettyPayload};\n\nawait fetch('${updateUrl}', {\n  method: 'PUT',\n  headers: {\n    'Content-Type': 'application/json',\n    '${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}',\n  },\n  body: JSON.stringify(payload),\n});`,
    php: `<?php\n\nuse GuzzleHttp\\Client;\n\n$client = new Client();\n\n$payload = [\n${phpPayloadEntries}\n];\n$headers = ['${API_KEY_HEADER}' => '${API_KEY_PLACEHOLDER}', 'Content-Type' => 'application/json'];\n\n// 更新${moldName}\n$client->put('${updateUrl}', [\n    'headers' => $headers,\n    'json' => $payload,\n]);`,
    python: `import requests\n\nbase_url = '${baseUrl}'\ntable_name = '${tableName}'\n\npayload = ${pythonPayload}\nheaders = {'${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}', 'Content-Type': 'application/json'}\n\n# 更新${moldName}\nrequests.put(f"{base_url}/update/{table_name}/1", json=payload, headers=headers)`,
  };

  const remove: ExampleGroup = {
    curl: `# 删除${moldName}\ncurl -X DELETE '${deleteUrl}' \\
  -H 'Accept: application/json' \\
  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}'`,
    javascript: `// 删除${moldName}\nawait fetch('${deleteUrl}', { method: 'DELETE', headers: { '${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}' } });`,
    php: `<?php\n\nuse GuzzleHttp\\Client;\n\n$client = new Client();\n\n$headers = ['${API_KEY_HEADER}' => '${API_KEY_PLACEHOLDER}'];\n\n// 删除${moldName}\n$client->delete('${deleteUrl}', [\n    'headers' => $headers,\n]);`,
    python: `import requests\n\nbase_url = '${baseUrl}'\ntable_name = '${tableName}'\nheaders = {'${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}'}\n\n# 删除${moldName}\nrequests.delete(f"{base_url}/delete/{table_name}/1", headers=headers)`,
  };

  return {
    select,
    create,
    update,
    delete: remove,
  };
};

export const generateMediaApiDemos = (baseUrl: string): DemoExamples => {
  const mediaBaseUrl = `${baseUrl}`;

  const select: ExampleGroup = {
    curl: `# 获取媒体资源详情（通过ID）
curl -X GET '${mediaBaseUrl}/detail/1' \\
  -H 'Accept: application/json' \\
  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}'

# 获取媒体资源列表
curl -X GET '${mediaBaseUrl}/list?page=1&page_size=15&type=image' \\
  -H 'Accept: application/json' \\
  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}'

# 通过标签获取媒体
curl -X GET '${mediaBaseUrl}/by-tags?tags=banner,hero&page=1&page_size=15' \\
  -H 'Accept: application/json' \\
  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}'

# 通过文件夹获取媒体
curl -X GET '${mediaBaseUrl}/by-folder/1?page=1&page_size=15' \\
  -H 'Accept: application/json' \\
  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}'

# 搜索媒体资源
curl -X GET '${mediaBaseUrl}/search?keyword=logo&type=image' \\
  -H 'Accept: application/json' \\
  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}'`,

    javascript: `// 公共请求头
const headers = { '${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}' };

// 获取媒体资源详情
const detailResponse = await fetch('${mediaBaseUrl}/detail/1', { headers });
const detailJson = await detailResponse.json();
console.log(detailJson.data);

// 获取媒体资源列表
const listResponse = await fetch('${mediaBaseUrl}/list?page=1&page_size=15&type=image', { headers });
const listJson = await listResponse.json();
const { data: mediaList, meta, links } = listJson.data;
console.log(mediaList, meta, links);

// 通过标签获取媒体
const tagResponse = await fetch('${mediaBaseUrl}/by-tags?tags=banner,hero&page=1&page_size=15', { headers });
const tagJson = await tagResponse.json();
console.log(tagJson.data);

// 通过文件夹获取媒体
const folderResponse = await fetch('${mediaBaseUrl}/by-folder/1?page=1&page_size=15', { headers });
const folderJson = await folderResponse.json();
console.log(folderJson.data);

// 搜索媒体资源
const searchResponse = await fetch('${mediaBaseUrl}/search?keyword=logo&type=image', { headers });
const searchJson = await searchResponse.json();
console.log(searchJson.data);`,

    php: `<?php

use GuzzleHttp\\Client;

$client = new Client();
$headers = ['${API_KEY_HEADER}' => '${API_KEY_PLACEHOLDER}'];

// 获取媒体资源详情
$detailResponse = $client->get('${mediaBaseUrl}/detail/1', [
    'headers' => $headers,
]);
$detailData = json_decode($detailResponse->getBody()->getContents(), true);

// 获取媒体资源列表
$listResponse = $client->get('${mediaBaseUrl}/list', [
    'headers' => $headers,
    'query' => [
        'page' => 1,
        'page_size' => 15,
        'type' => 'image',
    ],
]);
$listData = json_decode($listResponse->getBody()->getContents(), true);

// 通过标签获取媒体
$tagResponse = $client->get('${mediaBaseUrl}/by-tags', [
    'headers' => $headers,
    'query' => [
        'tags' => 'banner,hero',
        'page' => 1,
        'page_size' => 15,
    ],
]);
$tagData = json_decode($tagResponse->getBody()->getContents(), true);

// 通过文件夹获取媒体
$folderResponse = $client->get('${mediaBaseUrl}/by-folder/1', [
    'headers' => $headers,
    'query' => [
        'page' => 1,
        'page_size' => 15,
    ],
]);
$folderData = json_decode($folderResponse->getBody()->getContents(), true);

// 搜索媒体资源
$searchResponse = $client->get('${mediaBaseUrl}/search', [
    'headers' => $headers,
    'query' => [
        'keyword' => 'logo',
        'type' => 'image',
    ],
]);
$searchData = json_decode($searchResponse->getBody()->getContents(), true);`,

    python: `import requests

headers = {'${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}'}

# 获取媒体资源详情
detail_res = requests.get('${mediaBaseUrl}/detail/1', headers=headers)
print(detail_res.json().get('data'))

# 获取媒体资源列表
list_params = {
  'page': 1,
  'page_size': 15,
  'type': 'image'
}
list_res = requests.get('${mediaBaseUrl}/list', params=list_params, headers=headers)
print(list_res.json().get('data'))

# 通过标签获取媒体
tag_params = {
  'tags': 'banner,hero',
  'page': 1,
  'page_size': 15
}
tag_res = requests.get('${mediaBaseUrl}/by-tags', params=tag_params, headers=headers)
print(tag_res.json().get('data'))

# 通过文件夹获取媒体
folder_res = requests.get('${mediaBaseUrl}/by-folder/1', params={'page': 1, 'page_size': 15}, headers=headers)
print(folder_res.json().get('data'))

# 搜索媒体资源
search_params = {
  'keyword': 'logo',
  'type': 'image'
}
search_res = requests.get('${mediaBaseUrl}/search', params=search_params, headers=headers)
print(search_res.json().get('data'))`,
  };

  const create: ExampleGroup = {
    curl: `# 创建媒体资源（上传文件）
curl -X POST '${mediaBaseUrl}/create' \\
  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}' \\
  -F 'file=@/path/to/image.jpg' \\
  -F 'title=示例图片' \\
  -F 'alt=示例图片描述' \\
  -F 'description=这是一张示例图片' \\
  -F 'tags[]=banner' \\
  -F 'tags[]=hero' \\
  -F 'folder_id=1'`,

    javascript: `// 创建媒体资源（上传文件）
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('title', '示例图片');
formData.append('alt', '示例图片描述');
formData.append('description', '这是一张示例图片');
formData.append('tags[]', 'banner');
formData.append('tags[]', 'hero');
formData.append('folder_id', '1');

const response = await fetch('${mediaBaseUrl}/create', {
  method: 'POST',
  headers: {
    '${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}',
  },
  body: formData,
});
console.log(await response.json());`,

    php: `<?php

use GuzzleHttp\\Client;

$client = new Client();

// 创建媒体资源（上传文件）
$response = $client->post('${mediaBaseUrl}/create', [
    'headers' => ['${API_KEY_HEADER}' => '${API_KEY_PLACEHOLDER}'],
    'multipart' => [
        ['name' => 'file', 'contents' => fopen('/path/to/image.jpg', 'r')],
        ['name' => 'title', 'contents' => '示例图片'],
        ['name' => 'alt', 'contents' => '示例图片描述'],
        ['name' => 'description', 'contents' => '这是一张示例图片'],
        ['name' => 'tags[]', 'contents' => 'banner'],
        ['name' => 'tags[]', 'contents' => 'hero'],
        ['name' => 'folder_id', 'contents' => '1'],
    ],
]);
$created = json_decode($response->getBody()->getContents(), true);`,

    python: `import requests

headers = {'${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}'}

# 创建媒体资源（上传文件）
files = {'file': open('/path/to/image.jpg', 'rb')}
data = {
  'title': '示例图片',
  'alt': '示例图片描述',
  'description': '这是一张示例图片',
  'tags[]': ['banner', 'hero'],
  'folder_id': '1'
}

create_res = requests.post('${mediaBaseUrl}/create', headers=headers, files=files, data=data)
print(create_res.json())`,
  };

  const update: ExampleGroup = {
    curl: `# 更新媒体资源
curl -X PUT '${mediaBaseUrl}/update/1' \\
  -H 'Content-Type: application/json' \\
  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}' \\
  -d '{
    "title": "更新后的标题",
    "alt": "更新后的描述",
    "description": "更新后的详细描述",
    "tags": ["updated", "modified"],
    "folder_id": 2
  }'`,

    javascript: `// 更新媒体资源
const payload = {
  title: '更新后的标题',
  alt: '更新后的描述',
  description: '更新后的详细描述',
  tags: ['updated', 'modified'],
  folder_id: 2
};

await fetch('${mediaBaseUrl}/update/1', {
  method: 'PUT',
  headers: {
    'Content-Type': 'application/json',
    '${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}',
  },
  body: JSON.stringify(payload),
});`,

    php: `<?php

use GuzzleHttp\\Client;

$client = new Client();

$payload = [
    'title' => '更新后的标题',
    'alt' => '更新后的描述',
    'description' => '更新后的详细描述',
    'tags' => ['updated', 'modified'],
    'folder_id' => 2,
];

// 更新媒体资源
$client->put('${mediaBaseUrl}/update/1', [
    'headers' => ['${API_KEY_HEADER}' => '${API_KEY_PLACEHOLDER}', 'Content-Type' => 'application/json'],
    'json' => $payload,
]);`,

    python: `import requests

headers = {'${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}', 'Content-Type': 'application/json'}

payload = {
  'title': '更新后的标题',
  'alt': '更新后的描述',
  'description': '更新后的详细描述',
  'tags': ['updated', 'modified'],
  'folder_id': 2
}

# 更新媒体资源
requests.put('${mediaBaseUrl}/update/1', json=payload, headers=headers)`,
  };

  const remove: ExampleGroup = {
    curl: `# 删除媒体资源
curl -X DELETE '${mediaBaseUrl}/delete/1' \\
  -H 'Accept: application/json' \\
  -H '${API_KEY_HEADER}: ${API_KEY_PLACEHOLDER}'`,

    javascript: `// 删除媒体资源
await fetch('${mediaBaseUrl}/delete/1', {
  method: 'DELETE',
  headers: { '${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}' }
});`,

    php: `<?php

use GuzzleHttp\\Client;

$client = new Client();

// 删除媒体资源
$client->delete('${mediaBaseUrl}/delete/1', [
    'headers' => ['${API_KEY_HEADER}' => '${API_KEY_PLACEHOLDER}'],
]);`,

    python: `import requests

headers = {'${API_KEY_HEADER}': '${API_KEY_PLACEHOLDER}'}

# 删除媒体资源
requests.delete('${mediaBaseUrl}/delete/1', headers=headers)`,
  };

  return {
    select,
    create,
    update,
    delete: remove,
  };
};

/**
 * 生成Web函数API示例
 */
export const generateFunctionApiDemos = (
  baseUrl: string,
  slug: string,
  httpMethod: string = 'POST',
  fields: any[] = [],
  inputSchema: any = null
): DemoExamples => {
  const functionUrl = `${baseUrl}/${slug}`;
  const method = httpMethod.toUpperCase();
  
  // 如果有 input_schema，直接使用它作为示例参数
  let exampleParams: Record<string, any> = {};
  
  if (inputSchema && inputSchema.properties) {
    // 从 input_schema 生成示例参数
    Object.keys(inputSchema.properties).forEach(key => {
      const prop = inputSchema.properties[key];
      const type = prop.type;
      
      if (type === 'string') {
        exampleParams[key] = prop.example || prop.default || 'example_value';
      } else if (type === 'number' || type === 'integer') {
        exampleParams[key] = prop.example || prop.default || 123;
      } else if (type === 'boolean') {
        exampleParams[key] = prop.example !== undefined ? prop.example : (prop.default !== undefined ? prop.default : true);
      } else if (type === 'array') {
        exampleParams[key] = prop.example || prop.default || [];
      } else if (type === 'object') {
        exampleParams[key] = prop.example || prop.default || {};
      } else {
        exampleParams[key] = prop.example || prop.default || 'value';
      }
    });
  } else {
    // 如果没有 input_schema，使用 fields 生成
    fields.forEach(field => {
      if (field.type === 'string') {
        exampleParams[field.name] = 'example_value';
      } else if (field.type === 'number' || field.type === 'integer') {
        exampleParams[field.name] = 123;
      } else if (field.type === 'boolean') {
        exampleParams[field.name] = true;
      } else if (field.type === 'array') {
        exampleParams[field.name] = [];
      } else if (field.type === 'object') {
        exampleParams[field.name] = {};
      } else {
        exampleParams[field.name] = 'value';
      }
    });
  }

  const jsonParams = JSON.stringify(exampleParams, null, 2);
  const urlParams = Object.keys(exampleParams).map(k => `${k}=${exampleParams[k]}`).join('&');

  const select: ExampleGroup = {
    curl: method === 'GET' 
      ? `# 调用Web函数
curl -X GET '${functionUrl}${Object.keys(exampleParams).length > 0 ? '?' + urlParams : ''}' \\
  -H 'Accept: application/json' \\
  -H 'x-api-key: YOUR_API_KEY'`
      : `# 调用Web函数
curl -X ${method} '${functionUrl}' \\
  -H 'Content-Type: application/json' \\
  -H 'Accept: application/json' \\
  -H 'x-api-key: YOUR_API_KEY' \\
  -d '${jsonParams}'`,

    javascript: method === 'GET'
      ? `// 调用Web函数
const apiKey = 'YOUR_API_KEY';
const params = ${jsonParams};
const queryString = new URLSearchParams(params).toString();

fetch('${functionUrl}?' + queryString, {
  method: 'GET',
  headers: {
    'Accept': 'application/json',
    'x-api-key': apiKey
  }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));`
      : `// 调用Web函数
const apiKey = 'YOUR_API_KEY';
const params = ${jsonParams};

fetch('${functionUrl}', {
  method: '${method}',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'x-api-key': apiKey
  },
  body: JSON.stringify(params)
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));`,

    php: method === 'GET'
      ? `<?php
// 调用Web函数
$apiKey = 'YOUR_API_KEY';
$params = ${JSON.stringify(exampleParams)};
$queryString = http_build_query($params);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, '${functionUrl}?' . $queryString);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'x-api-key: ' . $apiKey
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
print_r($data);`
      : `<?php
// 调用Web函数
$apiKey = 'YOUR_API_KEY';
$params = ${JSON.stringify(exampleParams)};

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, '${functionUrl}');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, '${method}');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'x-api-key: ' . $apiKey
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
print_r($data);`,

    python: method === 'GET'
      ? `# 调用Web函数
import requests

api_key = 'YOUR_API_KEY'
params = ${jsonParams}

headers = {
    'Accept': 'application/json',
    'x-api-key': api_key
}

response = requests.get('${functionUrl}', params=params, headers=headers)
print(response.json())`
      : `# 调用Web函数
import requests
import json

api_key = 'YOUR_API_KEY'
params = ${jsonParams}

headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'x-api-key': api_key
}

response = requests.${method.toLowerCase()}('${functionUrl}', json=params, headers=headers)
print(response.json())`,
  };

  return {
    select,
    create: createEmptyGroup(),
    update: createEmptyGroup(),
    delete: createEmptyGroup(),
  };
};