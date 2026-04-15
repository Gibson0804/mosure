import{r as _,R as e}from"./vendor-react-4af8f23c.js";import{S as Ie}from"./vendor-inertia-efb81232.js";import{generateMediaApiDemos as $e,generateFunctionApiDemos as Ee,generateApiDemos as xe,generateSubjectApiDemos as ke,demoMediaOperations as Oe,demoOperations as Ae,demoSubjectOperations as Ce}from"./ApiDemo-0a90ac9f.js";import{u as ze}from"./useTranslate-a3aada98.js";import{a as Ne}from"./Service-450f2c78.js";import{ad as Z,h as He,_ as Q,S as L,a5 as T,ae as ge,K as ye,N as H,af as re,Y as R,a7 as _e,ag as Ge,a4 as Me,T as j,a6 as P,a2 as qe,m as F}from"./vendor-antd-9384dbd2.js";import"./vendor-amis-19b28984.js";import"./vendor-misc-5a281d24.js";import"./vendor-markdown-762f2404.js";import"./vendor-katex-98160839.js";import"./vendor-dayjs-2ddd460c.js";const u=a=>(a||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;"),O=(a,n,l)=>{let i=`<h3>${u(a)}示例</h3>`;return l.forEach(r=>{const c=r==="curl"?"cURL":r==="javascript"?"JavaScript":r==="php"?"PHP":"Python",p=n[r]||"";p&&(i+=`
        <h4>${c}</h4>
        <pre>${u(p)}</pre>
      `)}),i},De=(a,n)=>{switch((a.type||"").toLowerCase()){case"number":case"int":case"integer":case"float":case"decimal":case"double":return n+1;case"boolean":return n%2===0;case"date":return"2025-01-01";case"datetime":case"timestamp":return"2025-01-01T12:00:00Z";default:return`${a.label||a.title||a.field||"字段"}示例`}},U=(a,n,l)=>{const r=["curl","javascript","php","python"].filter(c=>!!n[c]).filter(c=>!l||l.includes(c)).map(c=>`<h4>${c.toUpperCase()}</h4><pre>${u(n[c])}</pre>`).join("");return r?`<div class="section"><h3>${a}</h3>${r}</div>`:""},be=(a,n,l,i,r)=>{var x,g,I,C;const c=Xe(a,n,l,i),p=Array.isArray(n.fields)?n.fields:[],h=Ke(n),m=JSON.stringify(h,null,2),$=a==="content"?xe(l,n.table_name,n.name,h):ke(i,n.table_name,n.name,h),b=r!=null&&r.languages&&r.languages.length>0?r.languages:["curl","javascript","php","python"],y={select:((x=r==null?void 0:r.operations)==null?void 0:x.select)!==!1,create:((g=r==null?void 0:r.operations)==null?void 0:g.create)!==!1,update:((I=r==null?void 0:r.operations)==null?void 0:I.update)!==!1,delete:((C=r==null?void 0:r.operations)==null?void 0:C.delete)!==!1},E=[];return y.select&&E.push(U("查看/查询 示例",$.select,b)),y.create&&E.push(U("新增 示例",$.create,b)),y.update&&E.push(U("修改 示例",$.update,b)),y.delete&&E.push(U("删除 示例",$.delete,b)),`
    <div class="section">
      <h2>${u(n.name)}（${u(n.table_name)}）</h2>
      ${n.description?`<p class="meta">${u(n.description)}</p>`:""}
      ${n.updated_at?`<p class="meta">最近更新：${u(n.updated_at)}</p>`:""}

      <h3>字段定义</h3>
      ${p.length===0?'<p class="meta">暂无字段定义</p>':`
        <table>
          <thead><tr><th>字段名</th><th>含义</th></tr></thead>
          <tbody>
            ${p.map((f,G)=>`
              <tr>
                <td class="endpoint">${u(f.field||`field_${G+1}`)}</td>
                <td>${u(f.label||f.title||f.field||"-")}</td>
              </tr>
            `).join("")}
          </tbody>
        </table>
      `}

      <h3>接口定义</h3>
      <table>
        <thead><tr><th>方法</th><th>URL</th><th>说明</th></tr></thead>
        <tbody>
          ${c.map(f=>`
            <tr>
              <td>${f.method}</td>
              <td class="endpoint">${u(f.url)}</td>
              <td>${u(f.desc)}</td>
            </tr>
          `).join("")}
        </tbody>
      </table>

      <h3>示例请求载荷（部分字段示例）</h3>
      <pre>${u(m)}</pre>

      ${E.join(`
`)}
    </div>
    <div class="page-break"></div>
  `},fe=(a,n,l,i,r,c,p,h,m,$,b,y,E,x)=>{const g="API 文档（导出）",I=`
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
  `,C=(a||[]).filter(k=>r.includes(k.id)),f=(n||[]).filter(k=>c.includes(k.id)),G=C.map(k=>be("content",k,l,i,p)).join(""),z=f.map(k=>be("subject",k,l,i,p)).join("");let M="";h&&m&&(M=Re(m,p));let X="";$&&b&&y&&(X=$.filter(A=>b.includes(A.id)).map(A=>Fe(A,y,p)).join(""));const J=E&&x?Le(x,p):"";return`<!doctype html>
  <html>
    <head>
      <meta charset="utf-8" />
      <title>${u(g)}</title>
      <style>${I}</style>
    </head>
    <body>
      <h1>${u(g)}</h1>
      ${G}
      ${z}
      ${M}
      ${X}
      ${J}
    </body>
  </html>`},Le=(a,n)=>{const l=(n==null?void 0:n.languages)||["curl","javascript","php","python"],i=`
    <tr><td>POST</td><td class="endpoint">${a}/login</td><td>项目用户登录，返回 pu_* 登录态</td></tr>
    <tr><td>POST</td><td class="endpoint">${a}/register</td><td>项目用户注册（需开启允许公开注册），返回 pu_* 登录态</td></tr>
    <tr><td>GET</td><td class="endpoint">${a}/me</td><td>获取当前项目用户信息</td></tr>
    <tr><td>POST</td><td class="endpoint">${a}/logout</td><td>退出登录并撤销当前登录态</td></tr>
  `,r={login:{curl:`# 项目用户登录
curl -X POST '${a}/login'   -H 'Content-Type: application/json'   -d '{"account":"user@example.com","password":"123456"}'`,javascript:`const response = await fetch('${a}/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ account: 'user@example.com', password: '123456' }),
});
const { data } = await response.json();
localStorage.setItem('project_user_token', data.token);`,php:`<?php
// 使用 GuzzleHttpClient POST ${a}/login
$payload = ['account' => 'user@example.com', 'password' => '123456'];`,python:`import requests
payload = {'account': 'user@example.com', 'password': '123456'}
response = requests.post('${a}/login', json=payload)
print(response.json())`},register:{curl:`# 项目用户注册
curl -X POST '${a}/register'   -H 'Content-Type: application/json'   -d '{"email":"user@example.com","password":"123456","name":"张三"}'`,javascript:`const response = await fetch('${a}/register', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email: 'user@example.com', password: '123456', name: '张三' }),
});
console.log(await response.json());`,php:`<?php
// 使用 GuzzleHttpClient POST ${a}/register
$payload = ['email' => 'user@example.com', 'password' => '123456', 'name' => '张三'];`,python:`import requests
payload = {'email': 'user@example.com', 'password': '123456', 'name': '张三'}
response = requests.post('${a}/register', json=payload)
print(response.json())`},me:{curl:`# 当前项目用户
curl -X GET '${a}/me'   -H 'Authorization: Bearer pu_{projectPrefix}_xxx'`,javascript:`const token = localStorage.getItem('project_user_token');
const response = await fetch('${a}/me', {
  headers: { Authorization: \`Bearer \${token}\` },
});
console.log(await response.json());`,php:`<?php
// 使用 Authorization Bearer 调用 ${a}/me
$token = 'pu_xxx';`,python:`import requests
token = 'pu_xxx'
response = requests.get('${a}/me', headers={'Authorization': f'Bearer {token}'})
print(response.json())`},logout:{curl:`# 退出登录
curl -X POST '${a}/logout'   -H 'Authorization: Bearer pu_{projectPrefix}_xxx'`,javascript:`const token = localStorage.getItem('project_user_token');
await fetch('${a}/logout', {
  method: 'POST',
  headers: { Authorization: \`Bearer \${token}\` },
});`,php:`<?php
// 使用 Authorization Bearer POST ${a}/logout
$token = 'pu_xxx';`,python:`import requests
token = 'pu_xxx'
requests.post('${a}/logout', headers={'Authorization': f'Bearer {token}'})`}};return`
    <div class="section">
      <h2>项目用户认证 API</h2>
      <p class="meta">登录/注册成功后返回 pu_* 登录态；调用 /open/* 时使用 Authorization: Bearer pu_*。</p>
      
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
  
      <h3>接口列表</h3>
      <table>
        <thead><tr><th>方法</th><th>接口地址</th><th>说明</th></tr></thead>
        <tbody>${i}</tbody>
      </table>
      ${O("项目用户登录",r.login,l)}
      ${O("项目用户注册",r.register,l)}
      ${O("当前项目用户",r.me,l)}
      ${O("退出登录",r.logout,l)}
    </div>
  `},Re=(a,n)=>{const l=(n==null?void 0:n.operations)||{select:!0,create:!1,update:!1,delete:!1},i=(n==null?void 0:n.languages)||["curl","javascript","php","python"],r=`
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
  `;let c="";l.select&&(c+=`
      <tr><td>GET</td><td class="endpoint">${a}/list</td><td>获取媒体列表</td></tr>
      <tr><td>GET</td><td class="endpoint">${a}/detail/{id}</td><td>获取媒体详情</td></tr>
      <tr><td>GET</td><td class="endpoint">${a}/search</td><td>搜索媒体</td></tr>
    `),l.create&&(c+=`<tr><td>POST</td><td class="endpoint">${a}/create</td><td>创建媒体（上传文件）</td></tr>`),l.update&&(c+=`<tr><td>PUT</td><td class="endpoint">${a}/update/{id}</td><td>更新媒体信息</td></tr>`),l.delete&&(c+=`<tr><td>DELETE</td><td class="endpoint">${a}/delete/{id}</td><td>删除媒体</td></tr>`);const p=$e(a);let h="";return l.select&&p.select&&(h+=O("查询媒体",p.select,i)),l.create&&p.create&&(h+=O("创建媒体",p.create,i)),l.update&&p.update&&(h+=O("更新媒体",p.update,i)),l.delete&&p.delete&&(h+=O("删除媒体",p.delete,i)),`
    <div class="section">
      <h2>媒体资源 API</h2>
      ${r}
      <h3>接口列表</h3>
      <table>
        <thead>
          <tr><th>方法</th><th>接口地址</th><th>说明</th></tr>
        </thead>
        <tbody>
          ${c}
        </tbody>
      </table>
      ${h}
    </div>
  `},Fe=(a,n,l)=>{const i=(a.http_method||"POST").toUpperCase(),r=a.slug||"",c=`${n}/${r}`,p=(l==null?void 0:l.languages)||["curl","javascript","php","python"];let h="";if(a.input_schema&&a.input_schema.properties){const y=a.input_schema.properties,E=a.input_schema.required||[];h=Object.keys(y).map(x=>{const g=y[x];return`<tr>
        <td>${u(x)}</td>
        <td>${u(g.type||"string")}</td>
        <td>${E.includes(x)?"是":"否"}</td>
        <td>${u(g.description||"-")}</td>
      </tr>`}).join("")}let m="";if(a.output_schema&&a.output_schema.properties){const y=a.output_schema.properties;m=Object.keys(y).map(E=>{const x=y[E];return`<tr>
        <td>${u(E)}</td>
        <td>${u(x.type||"string")}</td>
        <td>${u(x.description||"-")}</td>
      </tr>`}).join("")}const $=Ee(n,r,a.http_method||"POST",[],a.input_schema),b=$.select?O("请求",$.select,p):"";return`
    <div class="section">
      <h2>Web 函数：${u(a.name)}</h2>
      <p><strong>请求地址：</strong><code class="endpoint">${i} ${c}</code></p>
      ${a.remark?`<p><strong>说明：</strong>${u(a.remark)}</p>`:""}
      
      ${h?`
        <h3>输入参数</h3>
        <table>
          <thead>
            <tr><th>参数名</th><th>类型</th><th>必填</th><th>说明</th></tr>
          </thead>
          <tbody>
            ${h}
          </tbody>
        </table>
      `:"<p>无输入参数</p>"}
      
      ${m?`
        <h3>输出参数</h3>
        <table>
          <thead>
            <tr><th>参数名</th><th>类型</th><th>说明</th></tr>
          </thead>
          <tbody>
            ${m}
          </tbody>
        </table>
      `:"<p>无输出参数定义</p>"}
      
      ${b}
    </div>
  `},Ke=a=>{const n={},l=Array.isArray(a.fields)?a.fields:[];return l.length===0?(n.title=`${a.name||a.table_name}示例标题`,n.summary=`${a.name||a.table_name}示例摘要`,n):(l.slice(0,5).forEach((i,r)=>{const c=i.field&&i.field.trim()!==""?i.field:`field_${r+1}`;n[c]=De(i,r)}),n)},Xe=(a,n,l,i)=>{const r=n.table_name;return a==="content"?[{method:"GET",url:`${l}/list/${r}`,desc:"获取列表（支持分页/字段/排序/过滤）"},{method:"GET",url:`${l}/detail/${r}/{id}`,desc:"获取详情"},{method:"GET",url:`${l}/count/${r}`,desc:"统计数量"},{method:"POST",url:`${l}/create/${r}`,desc:"新增"},{method:"PUT",url:`${l}/update/${r}/{id}`,desc:"更新"},{method:"DELETE",url:`${l}/delete/${r}/{id}`,desc:"删除"}]:[{method:"GET",url:`${i}/detail/${r}`,desc:"获取单页详情"},{method:"PUT",url:`${i}/update/${r}`,desc:"更新单页内容"}]},{Text:S,Paragraph:B}=j,{TabPane:Je}=_e,Ye={whiteSpace:"pre",overflowX:"auto",overflowY:"hidden"},ee=()=>({curl:"",javascript:"",php:"",python:""}),We=()=>({select:ee(),create:ee(),update:ee(),delete:ee()}),Ve={id:0,name:"媒体资源",description:"媒体资源管理接口，支持图片、视频、音频、文档等文件的查询",table_name:"media",mold_type:-1,fields:[],subject_content:{},list_show_fields:[],updated_at:null},Ze={id:-2,name:"项目用户认证",description:"项目用户登录、注册、当前用户和退出登录接口。登录/注册成功后返回 pu_* 登录态，可用于 Authorization Bearer 调用 /open/*。",table_name:"auth",mold_type:-1,fields:[{field:"account",label:"登录账号：邮箱 / 用户名 / 手机号",type:"input"},{field:"email",label:"注册邮箱",type:"input"},{field:"username",label:"注册用户名（可选）",type:"input"},{field:"name",label:"显示名称（可选）",type:"input"},{field:"password",label:"密码",type:"input"},{field:"Authorization",label:"Bearer pu_* 登录态，用于 me/logout 及其它 /open/* 接口",type:"header"}],subject_content:{},list_show_fields:[],updated_at:null},K=[{key:"curl",label:"cURL"},{key:"javascript",label:"JavaScript"},{key:"php",label:"PHP"},{key:"python",label:"Python"}],Qe=(a,n)=>[{key:"select",title:a,method:n,languages:K}],Ue=a=>{switch((a??"").toUpperCase()){case"GET":return"GET";case"PUT":return"PUT";case"DELETE":return"DELETE";default:return"POST"}},Be=[{key:"create",title:"登录接口 Demo",method:"POST",languages:K},{key:"update",title:"注册接口 Demo",method:"POST",languages:K},{key:"select",title:"当前用户接口 Demo",method:"GET",languages:K},{key:"delete",title:"退出登录接口 Demo",method:"POST",languages:K}],et=a=>{const n=`${a}/login`,l=`${a}/register`,i=`${a}/me`,r=`${a}/logout`,c='{"account":"user@example.com","password":"123456"}',p='{"email":"user@example.com","password":"123456","name":"张三"}';return{create:{curl:["# 项目用户登录",`curl -X POST '${n}' \\`,"  -H 'Content-Type: application/json' \\",`  -d '${c}'`].join(`
`),javascript:[`const response = await fetch('${n}', {`,"  method: 'POST',","  headers: { 'Content-Type': 'application/json' },",`  body: JSON.stringify(${c}),`,"});","const { data } = await response.json();","localStorage.setItem('project_user_token', data.token);"].join(`
`),php:["<?php",`$payload = ${c};`,`// 使用 GuzzleHttpClient POST ${n}`].join(`
`),python:["import requests",`payload = ${c}`,`response = requests.post('${n}', json=payload)`,"print(response.json())"].join(`
`)},update:{curl:["# 项目用户注册",`curl -X POST '${l}' \\`,"  -H 'Content-Type: application/json' \\",`  -d '${p}'`].join(`
`),javascript:[`const response = await fetch('${l}', {`,"  method: 'POST',","  headers: { 'Content-Type': 'application/json' },",`  body: JSON.stringify(${p}),`,"});","console.log(await response.json());"].join(`
`),php:["<?php",`$payload = ${p};`,`// 使用 GuzzleHttpClient POST ${l}`].join(`
`),python:["import requests",`payload = ${p}`,`response = requests.post('${l}', json=payload)`,"print(response.json())"].join(`
`)},select:{curl:["# 获取当前项目用户",`curl -X GET '${i}' \\`,"  -H 'Authorization: Bearer pu_{projectPrefix}_xxx'"].join(`
`),javascript:["const token = localStorage.getItem('project_user_token');",`const response = await fetch('${i}', {`,"  headers: { Authorization: `Bearer ${token}` },","});","console.log(await response.json());"].join(`
`),php:["<?php","$token = 'pu_xxx';",`// 使用 Authorization Bearer 调用 ${i}`].join(`
`),python:["import requests","token = 'pu_xxx'",`response = requests.get('${i}', headers={'Authorization': f'Bearer {token}'})`,"print(response.json())"].join(`
`)},delete:{curl:["# 退出登录",`curl -X POST '${r}' \\`,"  -H 'Authorization: Bearer pu_{projectPrefix}_xxx'"].join(`
`),javascript:["const token = localStorage.getItem('project_user_token');",`await fetch('${r}', {`,"  method: 'POST',","  headers: { Authorization: `Bearer ${token}` },","});"].join(`
`),php:["<?php","$token = 'pu_xxx';",`// 使用 Authorization Bearer POST ${r}`].join(`
`),python:["import requests","token = 'pu_xxx'",`requests.post('${r}', headers={'Authorization': f'Bearer {token}'})`].join(`
`)}}};function mt({contents:a=[],subjects:n=[],functions:l=[],openContentBase:i,openSubjectBase:r,openFunctionBase:c,openAuthBase:p="",projectAuthEnabled:h=!1}){var he;const[m,$]=_.useState("content"),[b,y]=_.useState(null),[E,x]=_.useState(!1),[g,I]=_.useState(null),[C,f]=_.useState(We()),[G,z]=_.useState(null),M=m==="function"?l.find(t=>t.id===b)??null:null,[X,J]=_.useState(!1),[k,A]=_.useState(a.map(t=>t.id)),[q,Y]=_.useState(n.map(t=>t.id)),[D,W]=_.useState(l.map(t=>t.id)),[te,oe]=_.useState(!0),[ae,se]=_.useState(h),ie=["curl","javascript","php","python"],[ne,de]=_.useState(ie),[le,ce]=_.useState({select:!0,create:!1,update:!1,delete:!1}),[N,pe]=_.useState(""),w=ze(),ve=m==="auth"?Be:m==="media"?Oe:m==="function"?Qe("调用接口 Demo",Ue(M==null?void 0:M.http_method)):m==="content"?Ae:Ce,me=async(t,o)=>{$(t),y(o),x(!0),z(null),Ne.get(`/api-docs/molds/${t}/${o}`).then(s=>{const d=s.data;I(d),f(t==="content"?xe(i,d.table_name,d.name,ue(d)):ke(r,d.table_name,d.name,ue(d)))}).catch(s=>{console.error("Error fetching API examples:",s),z(s.message?s.message:"获取示例失败")}).finally(()=>{x(!1)})},Se=()=>{A(a.map(t=>t.id)),Y(n.map(t=>t.id)),W(l.map(t=>t.id)),oe(!0),se(h),de(ie),ce({select:!0,create:!1,update:!1,delete:!1}),pe(""),J(!0)},we=()=>{const t=i.replace("/content","/media");let s=fe(a,n,i,r,k,q,{languages:ne,operations:le},te,t,l,D,c,ae,p);N&&N.trim()&&(s=s.replace(/YOUR_API_KEY/g,N.trim()));const d=window.open("","_blank");d?(d.document.open(),d.document.write(s),d.document.close(),d.focus()):F.error("浏览器阻止了弹窗，请允许后重试")},Te=()=>{const t=i.replace("/content","/media");let s=fe(a,n,i,r,k,q,{languages:ne,operations:le},te,t,l,D,c,ae,p);N&&N.trim()&&(s=s.replace(/YOUR_API_KEY/g,N.trim()));const d=s.replace("</body>",'<script>window.addEventListener("load", () => setTimeout(() => window.print(), 300));<\/script></body>'),v=window.open("","_blank");v?(v.document.open(),v.document.write(d),v.document.close(),v.focus()):F.error("浏览器阻止了弹窗，请允许后重试")},Pe=async t=>{if(!t){F.warning("当前示例暂无内容");return}try{await navigator.clipboard.writeText(t),F.success("复制成功")}catch(o){console.error("Copy failed:",o),F.error("复制失败，请手动复制")}},ue=t=>{const o={},s=Array.isArray(t.fields)?t.fields:[];return s.length===0?(o.title=`${t.name||t.table_name}示例标题`,o.summary=`${t.name||t.table_name}示例摘要`,o):(s.slice(0,5).forEach((d,v)=>{const V=d.field&&d.field.trim()!==""?d.field:`field_${v+1}`;o[V]=je(d,v)}),o)},je=(t,o)=>{switch((t.type||"").toLowerCase()){case"number":case"int":case"integer":case"float":case"decimal":case"double":return o+1;case"boolean":return o%2===0;case"date":return"2025-01-01";case"datetime":case"timestamp":return"2025-01-01T12:00:00Z";default:return`${t.label||t.title||t.field||"字段"}示例`}};return e.createElement("div",{className:"py-6"},e.createElement(Ie,{title:"API 文档"}),e.createElement("div",{className:"max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"},e.createElement(Z,{title:e.createElement("span",null,e.createElement(He,{className:"mr-2"}),"API 文档"),extra:e.createElement(Q,{type:"primary",onClick:Se},"导出API文档")},e.createElement(Z,{className:"mb-6"},e.createElement(L,{direction:"vertical",style:{width:"100%"},size:"large"},e.createElement("div",null,e.createElement(S,{strong:!0,className:"block mb-2"},w("content_list","内容列表")," "),e.createElement(T.Group,{value:m==="content"?b:null,onChange:t=>me("content",t.target.value)},a.map(t=>e.createElement(T.Button,{key:t.id,value:t.id},t.name)))),e.createElement("div",null,e.createElement(S,{strong:!0,className:"block mb-2"},w("content_single","内容单页 ")," "),e.createElement(T.Group,{value:m==="subject"?b:null,onChange:t=>me("subject",t.target.value)},n.map(t=>e.createElement(T.Button,{key:t.id,value:t.id},t.name)))),e.createElement("div",null,e.createElement(S,{strong:!0,className:"block mb-2"},"媒体资源   "),e.createElement(T.Group,{value:m==="media"?1:null,onChange:()=>{$("media"),y(1),I(Ve);const t=i.replace("/content","/media");f($e(t)),z(null)}},e.createElement(T.Button,{value:1},"媒体资源 API"))),h&&e.createElement("div",null,e.createElement(S,{strong:!0,className:"block mb-2"},"项目用户"),e.createElement(T.Group,{value:m==="auth"?1:null,onChange:()=>{$("auth"),y(1),I(Ze),f(et(p)),z(null)}},e.createElement(T.Button,{value:1},"项目用户 API"))),e.createElement("div",null,e.createElement(S,{strong:!0,className:"block mb-2"},"Web 函数  "),e.createElement(T.Group,{value:m==="function"?b:null,onChange:t=>{const o=t.target.value,s=l.find(d=>d.id===o);s&&($("function"),y(o),I({id:s.id,name:s.name,description:s.description,table_name:s.slug,mold_type:-1,fields:[],subject_content:{},list_show_fields:[],updated_at:null}),f(Ee(c,s.slug,s.http_method,s.fields,s.input_schema)),z(null))}},l.map(t=>e.createElement(T.Button,{key:t.id,value:t.id},t.name)))))),G&&e.createElement(ge,{message:"获取示例失败",description:G,type:"error",showIcon:!0,className:"mb-4"}),g&&e.createElement(e.Fragment,null,e.createElement(ye,{gutter:[24,24],style:{marginTop:8}},e.createElement(H,{xs:24,lg:10},e.createElement(Z,{loading:E,className:"h-full"},e.createElement(L,{direction:"vertical",size:"large",style:{width:"100%"}},e.createElement("div",null,e.createElement(S,{strong:!0,className:"block text-base"},m==="function"?"函数名":w("model_name","模型名称")),e.createElement(B,{className:"mb-1",ellipsis:{rows:2}},g.name),e.createElement(S,{strong:!0,className:"block text-base"},m==="function"?"请求地址":w("table_name","模型标识ID")),e.createElement(B,{className:"mb-1",copyable:!0,ellipsis:{rows:2}},m==="auth"?(()=>p.replace(/^https?:\/\/[^/]+/i,""))():m==="function"?(()=>{const t=l.find(o=>o.id===b);if(t){const o=(t.http_method||"POST").toUpperCase(),s=t.slug||"",d=c.replace(/^https?:\/\/[^/]+/i,"");return`${o} ${d}/${s}`}return g.table_name})():g.table_name),g.description&&e.createElement(e.Fragment,null,e.createElement(S,{strong:!0,className:"block text-base"},w("description","描述")),e.createElement(B,{className:"mb-1",ellipsis:{rows:3}},g.description)),g.updated_at&&e.createElement(e.Fragment,null,e.createElement(S,{strong:!0,className:"block text-base"},w("updated_at","最近更新")),e.createElement(B,{className:"mb-0"},g.updated_at))),e.createElement("div",null,e.createElement(S,{strong:!0,className:"block text-base mb-2"},m==="function"?"输入参数定义":w("field_definition","字段定义")),m==="function"?e.createElement(e.Fragment,null,e.createElement(re,{dataSource:(()=>{const t=l.find(d=>d.id===b);if(!t||!t.input_schema||!t.input_schema.properties)return[];const o=t.input_schema.properties,s=t.input_schema.required||[];return Object.keys(o).map((d,v)=>{const V=o[d];return{key:v,field:d,type:V.type||"string",required:s.includes(d)?"是":"否",description:V.description||"-"}})})(),pagination:!1,bordered:!0,size:"small",columns:[{title:"参数名",dataIndex:"field",key:"field",width:"25%"},{title:"类型",dataIndex:"type",key:"type",width:"20%"},{title:"必填",dataIndex:"required",key:"required",width:"15%"},{title:"说明",dataIndex:"description",key:"description"}],locale:{emptyText:"暂无输入参数"}}),e.createElement(R,{style:{margin:"16px 0"}}),e.createElement(S,{strong:!0,className:"block text-base mb-2"},"输出参数定义"),e.createElement(re,{dataSource:(()=>{const t=l.find(s=>s.id===b);if(!t||!t.output_schema||!t.output_schema.properties)return[];const o=t.output_schema.properties;return Object.keys(o).map((s,d)=>{const v=o[s];return{key:d,field:s,type:v.type||"string",description:v.description||"-"}})})(),pagination:!1,bordered:!0,size:"small",columns:[{title:"参数名",dataIndex:"field",key:"field",width:"30%"},{title:"类型",dataIndex:"type",key:"type",width:"25%"},{title:"说明",dataIndex:"description",key:"description"}],locale:{emptyText:"暂无输出参数"}})):e.createElement(re,{dataSource:m==="media"?[{key:"id",field:"id",label:"媒体ID",comment:"唯一标识"},{key:"filename",field:"filename",label:"文件名",comment:"存储的文件名"},{key:"title",field:"title",label:"标题",comment:"媒体标题"},{key:"alt",field:"alt",label:"Alt文本",comment:"图片替代文本"},{key:"description",field:"description",label:"描述",comment:"媒体描述"},{key:"url",field:"url",label:"URL",comment:"访问地址"},{key:"type",field:"type",label:"类型",comment:"image/video/audio/document"},{key:"mime_type",field:"mime_type",label:"MIME类型",comment:"文件MIME类型"},{key:"size",field:"size",label:"大小",comment:"文件大小（字节）"},{key:"width",field:"width",label:"宽度",comment:"图片/视频宽度"},{key:"height",field:"height",label:"高度",comment:"图片/视频高度"},{key:"duration",field:"duration",label:"时长",comment:"音视频时长（秒）"},{key:"tags",field:"tags",label:"标签",comment:"标签数组"},{key:"folder_path",field:"folder_path",label:"文件夹路径",comment:"所在文件夹完整路径"},{key:"created_at",field:"created_at",label:"创建时间",comment:"上传时间"}]:((he=g.fields)==null?void 0:he.map((t,o)=>({key:`${t.field||o}`,field:t.field||`field_${o+1}`,label:t.label||t.title||"-",comment:""})))||[],pagination:!1,bordered:!0,size:"small",columns:[{title:w("field_name","字段名"),dataIndex:"field",key:"field",width:"30%"},{title:w("meaning","含义"),dataIndex:"label",key:"label",width:"35%"},{title:w("remark","备注"),dataIndex:"comment",key:"comment"}],locale:{emptyText:"暂无字段定义"}}))))),e.createElement(H,{xs:24,lg:14},e.createElement(L,{direction:"vertical",size:"large",style:{width:"100%"}},ve.map(t=>{var o;return e.createElement(Z,{key:t.key,title:`${t.title}`,loading:E},e.createElement(_e,{defaultActiveKey:((o=t.languages[0])==null?void 0:o.key)||"curl",size:"small"},t.languages.map(s=>e.createElement(Je,{tab:s.label,key:s.key},e.createElement("div",{className:"bg-gray-100 p-4 rounded",style:{position:"relative"}},e.createElement(Q,{size:"small",icon:e.createElement(Ge,null),onClick:()=>{var d;return Pe(((d=C[t.key])==null?void 0:d[s.key])??"")},style:{position:"absolute",top:-10,right:8}},"复制"),e.createElement("pre",{className:"whitespace-pre text-sm",style:Ye},C[t.key][s.key],e.createElement("br",null),e.createElement("br",null),e.createElement("br",null)))))))}))))),!g&&e.createElement(ge,{message:"提示",description:"请从上方选择要查看的模型，系统将自动生成对应的API调用示例。",type:"info",showIcon:!0,className:"mt-4"}),e.createElement(Me,{title:"导出API文档",open:X,onCancel:()=>J(!1),footer:null,width:880},e.createElement(L,{direction:"vertical",style:{width:"100%"},size:"middle"},e.createElement("div",null,e.createElement(j.Text,{strong:!0},"选择接口类型"),e.createElement(R,{style:{margin:"8px 0"}}),e.createElement(ye,{gutter:16},e.createElement(H,{xs:24,md:12},e.createElement("div",{style:{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:8}},e.createElement(j.Text,{type:"secondary"},"内容模型"),e.createElement(P,{checked:k.length===a.length,indeterminate:k.length>0&&k.length<a.length,onChange:t=>{t.target.checked?A(a.map(o=>o.id)):A([])}},"全选")),e.createElement("div",null,e.createElement(P.Group,{style:{width:"100%"},value:k,onChange:t=>A(t),options:(a||[]).map(t=>({label:t.name,value:t.id}))}))),e.createElement(H,{xs:24,md:12},e.createElement("div",{style:{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:8}},e.createElement(j.Text,{type:"secondary"},"单页模型"),e.createElement(P,{checked:q.length===n.length,indeterminate:q.length>0&&q.length<n.length,onChange:t=>{t.target.checked?Y(n.map(o=>o.id)):Y([])}},"全选")),e.createElement("div",null,e.createElement(P.Group,{style:{width:"100%"},value:q,onChange:t=>Y(t),options:(n||[]).map(t=>({label:t.name,value:t.id}))}))),e.createElement(H,{xs:24,md:12},e.createElement(j.Text,{type:"secondary"},"媒体资源"),e.createElement("div",null,e.createElement(P,{checked:te,onChange:t=>oe(t.target.checked)},"包含媒体资源接口"))),h&&e.createElement(H,{xs:24,md:12},e.createElement(j.Text,{type:"secondary"},"项目用户认证"),e.createElement("div",null,e.createElement(P,{checked:ae,onChange:t=>se(t.target.checked)},"包含项目用户 API"))),e.createElement(H,{xs:24,md:12},e.createElement("div",{style:{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:8}},e.createElement(j.Text,{type:"secondary"},"Web函数"),e.createElement(P,{checked:D.length===l.length,indeterminate:D.length>0&&D.length<l.length,onChange:t=>{t.target.checked?W(l.map(o=>o.id)):W([])}},"全选")),e.createElement("div",null,e.createElement(P.Group,{style:{width:"100%"},value:D,onChange:t=>W(t),options:(l||[]).map(t=>({label:t.name,value:t.id}))}))))),e.createElement("div",null,e.createElement(j.Text,{strong:!0},"选择示例语言"),e.createElement(R,{style:{margin:"8px 0"}}),e.createElement(P.Group,{value:ne,onChange:t=>de(t),options:[{label:"cURL",value:"curl"},{label:"JavaScript",value:"javascript"},{label:"PHP",value:"php"},{label:"Python",value:"python"}]})),e.createElement("div",null,e.createElement(j.Text,{strong:!0},"选择接口类型"),e.createElement(R,{style:{margin:"8px 0"}}),e.createElement(P.Group,{value:Object.entries(le).filter(([,t])=>t).map(([t])=>t),onChange:t=>{const o=new Set(t);ce({select:o.has("select"),create:o.has("create"),update:o.has("update"),delete:o.has("delete")})},options:[{label:"读取",value:"select"},{label:"写入",value:"create"},{label:"修改",value:"update"},{label:"删除",value:"delete"}]}),e.createElement("div",{style:{color:"#999",marginTop:4}},"默认仅导出读取接口，可按需勾选写入/修改/删除。")),e.createElement("div",null,e.createElement(j.Text,{strong:!0},"API Key（可选）",e.createElement("span",{style:{color:"red",marginLeft:4}},"注意API Key安全，不要将含有API Key的文档泄露给不信任的人")),e.createElement(R,{style:{margin:"8px 0"}}),e.createElement(qe.Password,{placeholder:"填写后将自动补全到示例请求头中 (x-api-key)",value:N,onChange:t=>pe(t.target.value)})),e.createElement(L,{style:{justifyContent:"flex-end",width:"100%",marginTop:4}},e.createElement(Q,{onClick:we},"预览"),e.createElement(Q,{type:"primary",onClick:Te},"导出")))))))}export{mt as default};
