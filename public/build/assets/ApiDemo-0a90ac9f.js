const g=[{key:"curl",label:"cURL"},{key:"javascript",label:"JavaScript"},{key:"php",label:"PHP"},{key:"python",label:"Python"}],O=[{key:"select",title:"查看接口 Demo",method:"GET",languages:g},{key:"update",title:"修改接口 Demo",method:"PUT",languages:g}],D=[{key:"select",title:"查看接口 Demo",method:"GET",languages:g},{key:"create",title:"新增接口 Demo",method:"POST",languages:g},{key:"update",title:"修改接口 Demo",method:"PUT",languages:g},{key:"delete",title:"删除接口 Demo",method:"DELETE",languages:g}],w=[{key:"select",title:"查询接口 Demo",method:"GET",languages:g},{key:"create",title:"创建接口 Demo",method:"POST",languages:g},{key:"update",title:"更新接口 Demo",method:"PUT",languages:g},{key:"delete",title:"删除接口 Demo",method:"DELETE",languages:g}],t="x-api-key",s="YOUR_API_KEY",f=n=>JSON.stringify(n).replace(/'/g,"\\'"),m=n=>JSON.stringify(n,null,2),T=n=>Object.entries(n).map(([e,a])=>`    '${e}' => ${b(a)},`).join(`
`),E=n=>JSON.stringify(n,null,2).replace(/"([^"(]+)":/g,"'$1':").replace(/"/g,"'"),b=n=>typeof n=="number"?n:typeof n=="boolean"?n?"true":"false":`'${String(n)}'`,_=()=>({curl:"",javascript:"",php:"",python:""}),y=n=>n.join(`
`),A=(n,e,a,c)=>{const d=f(c),i=m(c),$=T(c),r=E(c),l=`${n}/detail/${e}`,h=`${n}/update/${e}`,j={curl:y([`# 获取${a}详情`,`curl -X GET '${l}' \\`,"  -H 'Accept: application/json' \\",`  -H '${t}: ${s}'`]),javascript:y([`const headers = { '${t}': '${s}' };`,"",`// 获取${a}详情`,`const response = await fetch('${l}', { headers });`,"console.log(await response.json());"]),php:y(["<?php","","use GuzzleHttp\\Client;","","$client = new Client();",`$headers = ['${t}' => '${s}'];`,"",`// 获取${a}详情`,`$response = $client->get('${l}', ['headers' => $headers]);`,"$detail = json_decode($response->getBody()->getContents(), true);"]),python:y(["import requests","",`headers = {'${t}': '${s}'}`,"",`# 获取${a}详情`,`response = requests.get('${l}', headers=headers)`,"print(response.json())"])},o={curl:y([`# 更新${a}`,`curl -X PUT '${h}' \\`,"  -H 'Content-Type: application/json' \\",`  -H '${t}: ${s}' \\`,`  -d '${d}'`]),javascript:y(["const headers = {","  'Content-Type': 'application/json',",`  '${t}': '${s}',`,"};",`const payload = ${i};`,"",`// 更新${a}`,`await fetch('${h}', {`,"  method: 'PUT',","  headers,","  body: JSON.stringify(payload),","});"]),php:y(["<?php","","use GuzzleHttp\\Client;","","$client = new Client();",`$headers = ['${t}' => '${s}', 'Content-Type' => 'application/json'];`,"$payload = [",`${$}`,"];","",`// 更新${a}`,`$client->put('${h}', [`,"    'headers' => $headers,","    'json' => $payload,","]);"]),python:y(["import requests","",`headers = {'${t}': '${s}', 'Content-Type': 'application/json'}`,`payload = ${r}`,"",`# 更新${a}`,`requests.put('${h}', json=payload, headers=headers)`])};return{select:j,create:_(),update:o,delete:_()}},q=(n,e,a,c)=>{const d=f(c),i=m(c),$=T(c),r=E(c),l=`${n}/list/${e}`,h=`${n}/detail/${e}/1`,j=`${n}/count/${e}`,o=`${n}/create/${e}`,p=`${n}/update/${e}/1`,u=`${n}/delete/${e}/1`,C={curl:`# 获取${a}列表（含分页/字段/排序/过滤）
curl -X GET '${l}?page=1&page_size=10&fields=id,title,created_at&sort=-created_at,title' \\
  -H 'Accept: application/json' \\
  -H '${t}: ${s}'

# 获取${a}详情
curl -X GET '${h}' \\
  -H 'Accept: application/json' \\
  -H '${t}: ${s}'

# 统计${a}数量
curl -X GET '${j}' \\
  -H 'Accept: application/json' \\
  -H '${t}: ${s}'`,javascript:`// 公共请求头
const headers = { '${t}': '${s}' };

// 获取${a}列表（含 meta/links）
const listResponse = await fetch("${l}?page=1&page_size=10&fields=id,title,created_at&sort=-created_at,title", { headers });
const listJson = await listResponse.json();
const { data: listItems, meta: listMeta, links: listLinks } = listJson.data;
console.log(listItems, listMeta, listLinks);

// 获取${a}详情
const detailResponse = await fetch('${h}', { headers });
const detailJson = await detailResponse.json();
console.log(detailJson.data);`,php:`<?php

use GuzzleHttp\\Client;

$client = new Client();

$headers = ['${t}' => '${s}'];

// 获取${a}列表（含分页/字段/排序/过滤）
$listResponse = $client->get('${l}', [
    'headers' => $headers,
    'query' => [
        'page' => 1,
        'page_size' => 10,
        'fields' => 'id,title,created_at',
        'sort' => '-created_at,title',
        'filter' => [
            'status' => 'published',
            'title' => ['op' => 'like', 'value' => '示例']
        ],
    ],
]);
$listJson = json_decode($listResponse->getBody()->getContents(), true);
$listData = $listJson['data'] ?? [];

// 获取${a}详情
$detailResponse = $client->get('${h}', [
    'headers' => $headers,
]);
$detailJson = json_decode($detailResponse->getBody()->getContents(), true);
$detailData = $detailJson['data'] ?? [];`,python:`import requests

base_url = '${n}'
table_name = '${e}'
headers = {'${t}': '${s}'}

# 获取${a}列表（含分页/字段/排序/过滤）
params = {
  'page': 1,
  'page_size': 10,
  'fields': 'id,title,created_at',
  'sort': '-created_at,title',
  'filter[status]': 'published',
  'filter[title][op]': 'like',
  'filter[title][value]': '示例'
}
list_res = requests.get(f"{base_url}/list/{table_name}", params=params, headers=headers)
list_json = list_res.json()
print(list_json.get('data'))

# 获取${a}详情
detail_res = requests.get(f"{base_url}/detail/{table_name}/1", headers=headers)
print(detail_res.json().get('data'))`},R={curl:`# 创建${a}
curl -X POST '${o}' \\
  -H 'Content-Type: application/json' \\
  -H '${t}: ${s}' \\
  -d '${d}'`,javascript:`// 创建${a}
const payload = ${i};

const response = await fetch('${o}', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    '${t}': '${s}',
  },
  body: JSON.stringify(payload),
});
console.log(await response.json());`,php:`<?php

use GuzzleHttp\\Client;

$client = new Client();

$payload = [
${$}
];
$headers = ['${t}' => '${s}', 'Content-Type' => 'application/json'];

// 创建${a}
$response = $client->post('${o}', [
    'headers' => $headers,
    'json' => $payload,
]);
$created = json_decode($response->getBody()->getContents(), true);`,python:`import requests

base_url = '${n}'
table_name = '${e}'

payload = ${r}
headers = {'${t}': '${s}', 'Content-Type': 'application/json'}

# 创建${a}
create_res = requests.post(f"{base_url}/create/{table_name}", json=payload, headers=headers)
print(create_res.json())`},P={curl:`# 更新${a}
curl -X PUT '${p}' \\
  -H 'Content-Type: application/json' \\
  -H '${t}: ${s}' \\
  -d '${d}'`,javascript:`// 更新${a}
const payload = ${i};

await fetch('${p}', {
  method: 'PUT',
  headers: {
    'Content-Type': 'application/json',
    '${t}': '${s}',
  },
  body: JSON.stringify(payload),
});`,php:`<?php

use GuzzleHttp\\Client;

$client = new Client();

$payload = [
${$}
];
$headers = ['${t}' => '${s}', 'Content-Type' => 'application/json'];

// 更新${a}
$client->put('${p}', [
    'headers' => $headers,
    'json' => $payload,
]);`,python:`import requests

base_url = '${n}'
table_name = '${e}'

payload = ${r}
headers = {'${t}': '${s}', 'Content-Type': 'application/json'}

# 更新${a}
requests.put(f"{base_url}/update/{table_name}/1", json=payload, headers=headers)`},H={curl:`# 删除${a}
curl -X DELETE '${u}' \\
  -H 'Accept: application/json' \\
  -H '${t}: ${s}'`,javascript:`// 删除${a}
await fetch('${u}', { method: 'DELETE', headers: { '${t}': '${s}' } });`,php:`<?php

use GuzzleHttp\\Client;

$client = new Client();

$headers = ['${t}' => '${s}'];

// 删除${a}
$client->delete('${u}', [
    'headers' => $headers,
]);`,python:`import requests

base_url = '${n}'
table_name = '${e}'
headers = {'${t}': '${s}'}

# 删除${a}
requests.delete(f"{base_url}/delete/{table_name}/1", headers=headers)`};return{select:C,create:R,update:P,delete:H}},z=n=>{const e=`${n}`,a={curl:`# 获取媒体资源详情（通过ID）
curl -X GET '${e}/detail/1' \\
  -H 'Accept: application/json' \\
  -H '${t}: ${s}'

# 获取媒体资源列表
curl -X GET '${e}/list?page=1&page_size=15&type=image' \\
  -H 'Accept: application/json' \\
  -H '${t}: ${s}'

# 通过标签获取媒体
curl -X GET '${e}/by-tags?tags=banner,hero&page=1&page_size=15' \\
  -H 'Accept: application/json' \\
  -H '${t}: ${s}'

# 通过文件夹获取媒体
curl -X GET '${e}/by-folder/1?page=1&page_size=15' \\
  -H 'Accept: application/json' \\
  -H '${t}: ${s}'

# 搜索媒体资源
curl -X GET '${e}/search?keyword=logo&type=image' \\
  -H 'Accept: application/json' \\
  -H '${t}: ${s}'`,javascript:`// 公共请求头
const headers = { '${t}': '${s}' };

// 获取媒体资源详情
const detailResponse = await fetch('${e}/detail/1', { headers });
const detailJson = await detailResponse.json();
console.log(detailJson.data);

// 获取媒体资源列表
const listResponse = await fetch('${e}/list?page=1&page_size=15&type=image', { headers });
const listJson = await listResponse.json();
const { data: mediaList, meta, links } = listJson.data;
console.log(mediaList, meta, links);

// 通过标签获取媒体
const tagResponse = await fetch('${e}/by-tags?tags=banner,hero&page=1&page_size=15', { headers });
const tagJson = await tagResponse.json();
console.log(tagJson.data);

// 通过文件夹获取媒体
const folderResponse = await fetch('${e}/by-folder/1?page=1&page_size=15', { headers });
const folderJson = await folderResponse.json();
console.log(folderJson.data);

// 搜索媒体资源
const searchResponse = await fetch('${e}/search?keyword=logo&type=image', { headers });
const searchJson = await searchResponse.json();
console.log(searchJson.data);`,php:`<?php

use GuzzleHttp\\Client;

$client = new Client();
$headers = ['${t}' => '${s}'];

// 获取媒体资源详情
$detailResponse = $client->get('${e}/detail/1', [
    'headers' => $headers,
]);
$detailData = json_decode($detailResponse->getBody()->getContents(), true);

// 获取媒体资源列表
$listResponse = $client->get('${e}/list', [
    'headers' => $headers,
    'query' => [
        'page' => 1,
        'page_size' => 15,
        'type' => 'image',
    ],
]);
$listData = json_decode($listResponse->getBody()->getContents(), true);

// 通过标签获取媒体
$tagResponse = $client->get('${e}/by-tags', [
    'headers' => $headers,
    'query' => [
        'tags' => 'banner,hero',
        'page' => 1,
        'page_size' => 15,
    ],
]);
$tagData = json_decode($tagResponse->getBody()->getContents(), true);

// 通过文件夹获取媒体
$folderResponse = $client->get('${e}/by-folder/1', [
    'headers' => $headers,
    'query' => [
        'page' => 1,
        'page_size' => 15,
    ],
]);
$folderData = json_decode($folderResponse->getBody()->getContents(), true);

// 搜索媒体资源
$searchResponse = $client->get('${e}/search', [
    'headers' => $headers,
    'query' => [
        'keyword' => 'logo',
        'type' => 'image',
    ],
]);
$searchData = json_decode($searchResponse->getBody()->getContents(), true);`,python:`import requests

headers = {'${t}': '${s}'}

# 获取媒体资源详情
detail_res = requests.get('${e}/detail/1', headers=headers)
print(detail_res.json().get('data'))

# 获取媒体资源列表
list_params = {
  'page': 1,
  'page_size': 15,
  'type': 'image'
}
list_res = requests.get('${e}/list', params=list_params, headers=headers)
print(list_res.json().get('data'))

# 通过标签获取媒体
tag_params = {
  'tags': 'banner,hero',
  'page': 1,
  'page_size': 15
}
tag_res = requests.get('${e}/by-tags', params=tag_params, headers=headers)
print(tag_res.json().get('data'))

# 通过文件夹获取媒体
folder_res = requests.get('${e}/by-folder/1', params={'page': 1, 'page_size': 15}, headers=headers)
print(folder_res.json().get('data'))

# 搜索媒体资源
search_params = {
  'keyword': 'logo',
  'type': 'image'
}
search_res = requests.get('${e}/search', params=search_params, headers=headers)
print(search_res.json().get('data'))`},c={curl:`# 创建媒体资源（上传文件）
curl -X POST '${e}/create' \\
  -H '${t}: ${s}' \\
  -F 'file=@/path/to/image.jpg' \\
  -F 'title=示例图片' \\
  -F 'alt=示例图片描述' \\
  -F 'description=这是一张示例图片' \\
  -F 'tags[]=banner' \\
  -F 'tags[]=hero' \\
  -F 'folder_id=1'`,javascript:`// 创建媒体资源（上传文件）
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('title', '示例图片');
formData.append('alt', '示例图片描述');
formData.append('description', '这是一张示例图片');
formData.append('tags[]', 'banner');
formData.append('tags[]', 'hero');
formData.append('folder_id', '1');

const response = await fetch('${e}/create', {
  method: 'POST',
  headers: {
    '${t}': '${s}',
  },
  body: formData,
});
console.log(await response.json());`,php:`<?php

use GuzzleHttp\\Client;

$client = new Client();

// 创建媒体资源（上传文件）
$response = $client->post('${e}/create', [
    'headers' => ['${t}' => '${s}'],
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
$created = json_decode($response->getBody()->getContents(), true);`,python:`import requests

headers = {'${t}': '${s}'}

# 创建媒体资源（上传文件）
files = {'file': open('/path/to/image.jpg', 'rb')}
data = {
  'title': '示例图片',
  'alt': '示例图片描述',
  'description': '这是一张示例图片',
  'tags[]': ['banner', 'hero'],
  'folder_id': '1'
}

create_res = requests.post('${e}/create', headers=headers, files=files, data=data)
print(create_res.json())`},d={curl:`# 更新媒体资源
curl -X PUT '${e}/update/1' \\
  -H 'Content-Type: application/json' \\
  -H '${t}: ${s}' \\
  -d '{
    "title": "更新后的标题",
    "alt": "更新后的描述",
    "description": "更新后的详细描述",
    "tags": ["updated", "modified"],
    "folder_id": 2
  }'`,javascript:`// 更新媒体资源
const payload = {
  title: '更新后的标题',
  alt: '更新后的描述',
  description: '更新后的详细描述',
  tags: ['updated', 'modified'],
  folder_id: 2
};

await fetch('${e}/update/1', {
  method: 'PUT',
  headers: {
    'Content-Type': 'application/json',
    '${t}': '${s}',
  },
  body: JSON.stringify(payload),
});`,php:`<?php

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
$client->put('${e}/update/1', [
    'headers' => ['${t}' => '${s}', 'Content-Type' => 'application/json'],
    'json' => $payload,
]);`,python:`import requests

headers = {'${t}': '${s}', 'Content-Type': 'application/json'}

payload = {
  'title': '更新后的标题',
  'alt': '更新后的描述',
  'description': '更新后的详细描述',
  'tags': ['updated', 'modified'],
  'folder_id': 2
}

# 更新媒体资源
requests.put('${e}/update/1', json=payload, headers=headers)`},i={curl:`# 删除媒体资源
curl -X DELETE '${e}/delete/1' \\
  -H 'Accept: application/json' \\
  -H '${t}: ${s}'`,javascript:`// 删除媒体资源
await fetch('${e}/delete/1', {
  method: 'DELETE',
  headers: { '${t}': '${s}' }
});`,php:`<?php

use GuzzleHttp\\Client;

$client = new Client();

// 删除媒体资源
$client->delete('${e}/delete/1', [
    'headers' => ['${t}' => '${s}'],
]);`,python:`import requests

headers = {'${t}': '${s}'}

# 删除媒体资源
requests.delete('${e}/delete/1', headers=headers)`};return{select:a,create:c,update:d,delete:i}},k=(n,e,a="POST",c=[],d=null)=>{const i=`${n}/${e}`,$=a.toUpperCase();let r={};d&&d.properties?Object.keys(d.properties).forEach(o=>{const p=d.properties[o],u=p.type;u==="string"?r[o]=p.example||p.default||"example_value":u==="number"||u==="integer"?r[o]=p.example||p.default||123:u==="boolean"?r[o]=p.example!==void 0?p.example:p.default!==void 0?p.default:!0:u==="array"?r[o]=p.example||p.default||[]:u==="object"?r[o]=p.example||p.default||{}:r[o]=p.example||p.default||"value"}):c.forEach(o=>{o.type==="string"?r[o.name]="example_value":o.type==="number"||o.type==="integer"?r[o.name]=123:o.type==="boolean"?r[o.name]=!0:o.type==="array"?r[o.name]=[]:o.type==="object"?r[o.name]={}:r[o.name]="value"});const l=JSON.stringify(r,null,2),h=Object.keys(r).map(o=>`${o}=${r[o]}`).join("&");return{select:{curl:$==="GET"?`# 调用Web函数
curl -X GET '${i}${Object.keys(r).length>0?"?"+h:""}' \\
  -H 'Accept: application/json' \\
  -H 'x-api-key: YOUR_API_KEY'`:`# 调用Web函数
curl -X ${$} '${i}' \\
  -H 'Content-Type: application/json' \\
  -H 'Accept: application/json' \\
  -H 'x-api-key: YOUR_API_KEY' \\
  -d '${l}'`,javascript:$==="GET"?`// 调用Web函数
const apiKey = 'YOUR_API_KEY';
const params = ${l};
const queryString = new URLSearchParams(params).toString();

fetch('${i}?' + queryString, {
  method: 'GET',
  headers: {
    'Accept': 'application/json',
    'x-api-key': apiKey
  }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));`:`// 调用Web函数
const apiKey = 'YOUR_API_KEY';
const params = ${l};

fetch('${i}', {
  method: '${$}',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'x-api-key': apiKey
  },
  body: JSON.stringify(params)
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));`,php:$==="GET"?`<?php
// 调用Web函数
$apiKey = 'YOUR_API_KEY';
$params = ${JSON.stringify(r)};
$queryString = http_build_query($params);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, '${i}?' . $queryString);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'x-api-key: ' . $apiKey
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
print_r($data);`:`<?php
// 调用Web函数
$apiKey = 'YOUR_API_KEY';
$params = ${JSON.stringify(r)};

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, '${i}');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, '${$}');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'x-api-key: ' . $apiKey
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
print_r($data);`,python:$==="GET"?`# 调用Web函数
import requests

api_key = 'YOUR_API_KEY'
params = ${l}

headers = {
    'Accept': 'application/json',
    'x-api-key': api_key
}

response = requests.get('${i}', params=params, headers=headers)
print(response.json())`:`# 调用Web函数
import requests
import json

api_key = 'YOUR_API_KEY'
params = ${l}

headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'x-api-key': api_key
}

response = requests.${$.toLowerCase()}('${i}', json=params, headers=headers)
print(response.json())`},create:_(),update:_(),delete:_()}};export{w as demoMediaOperations,D as demoOperations,O as demoSubjectOperations,q as generateApiDemos,k as generateFunctionApiDemos,z as generateMediaApiDemos,A as generateSubjectApiDemos};
