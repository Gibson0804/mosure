import{r as v,R as t}from"./vendor-react-4af8f23c.js";import{S as we}from"./vendor-inertia-efb81232.js";import{generateMediaApiDemos as he,generateFunctionApiDemos as ge,generateApiDemos as ye,generateSubjectApiDemos as be,demoMediaOperations as Se,demoOperations as Te,demoSubjectOperations as Pe}from"./ApiDemo-0a90ac9f.js";import{u as Ie}from"./useTranslate-a3aada98.js";import{a as Ce}from"./Service-450f2c78.js";import{ad as W,h as Ne,_ as J,S as L,a5 as A,ae as oe,K as me,N as z,af as te,Y as H,a7 as fe,ag as Ae,a4 as Me,T as C,a6 as I,a2 as je,m as D}from"./vendor-antd-9384dbd2.js";import"./vendor-amis-19b28984.js";import"./vendor-misc-5a281d24.js";import"./vendor-markdown-762f2404.js";import"./vendor-katex-98160839.js";import"./vendor-dayjs-2ddd460c.js";const u=a=>(a||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;"),R=(a,l,n)=>{let o=`<h3>${u(a)}示例</h3>`;return n.forEach(r=>{const m=r==="curl"?"cURL":r==="javascript"?"JavaScript":r==="php"?"PHP":"Python",i=l[r]||"";i&&(o+=`
        <h4>${m}</h4>
        <pre>${u(i)}</pre>
      `)}),o},Oe=(a,l)=>{switch((a.type||"").toLowerCase()){case"number":case"int":case"integer":case"float":case"decimal":case"double":return l+1;case"boolean":return l%2===0;case"date":return"2025-01-01";case"datetime":case"timestamp":return"2025-01-01T12:00:00Z";default:return`${a.label||a.title||a.field||"字段"}示例`}},V=(a,l,n)=>{const r=["curl","javascript","php","python"].filter(m=>!!l[m]).filter(m=>!n||n.includes(m)).map(m=>`<h4>${m.toUpperCase()}</h4><pre>${u(l[m])}</pre>`).join("");return r?`<div class="section"><h3>${a}</h3>${r}</div>`:""},pe=(a,l,n,o,r)=>{var b,_,P,M;const m=He(a,l,n,o),i=Array.isArray(l.fields)?l.fields:[],h=Le(l),g=JSON.stringify(h,null,2),E=a==="content"?ye(n,l.table_name,l.name,h):be(o,l.table_name,l.name,h),k=r!=null&&r.languages&&r.languages.length>0?r.languages:["curl","javascript","php","python"],y={select:((b=r==null?void 0:r.operations)==null?void 0:b.select)!==!1,create:((_=r==null?void 0:r.operations)==null?void 0:_.create)!==!1,update:((P=r==null?void 0:r.operations)==null?void 0:P.update)!==!1,delete:((M=r==null?void 0:r.operations)==null?void 0:M.delete)!==!1},p=[];return y.select&&p.push(V("查看/查询 示例",E.select,k)),y.create&&p.push(V("新增 示例",E.create,k)),y.update&&p.push(V("修改 示例",E.update,k)),y.delete&&p.push(V("删除 示例",E.delete,k)),`
    <div class="section">
      <h2>${u(l.name)}（${u(l.table_name)}）</h2>
      ${l.description?`<p class="meta">${u(l.description)}</p>`:""}
      ${l.updated_at?`<p class="meta">最近更新：${u(l.updated_at)}</p>`:""}

      <h3>字段定义</h3>
      ${i.length===0?'<p class="meta">暂无字段定义</p>':`
        <table>
          <thead><tr><th>字段名</th><th>含义</th></tr></thead>
          <tbody>
            ${i.map((f,N)=>`
              <tr>
                <td class="endpoint">${u(f.field||`field_${N+1}`)}</td>
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
          ${m.map(f=>`
            <tr>
              <td>${f.method}</td>
              <td class="endpoint">${u(f.url)}</td>
              <td>${u(f.desc)}</td>
            </tr>
          `).join("")}
        </tbody>
      </table>

      <h3>示例请求载荷（部分字段示例）</h3>
      <pre>${u(g)}</pre>

      ${p.join(`
`)}
    </div>
    <div class="page-break"></div>
  `},ue=(a,l,n,o,r,m,i,h,g,E,k,y)=>{const p="API 文档（导出）",b=`
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
  `,_=(a||[]).filter(x=>r.includes(x.id)),P=(l||[]).filter(x=>m.includes(x.id)),M=_.map(x=>pe("content",x,n,o,i)).join(""),f=P.map(x=>pe("subject",x,n,o,i)).join("");let N="";h&&g&&(N=Ge(g,i));let F="";return E&&k&&y&&(F=E.filter(w=>k.includes(w.id)).map(w=>ze(w,y,i)).join("")),`<!doctype html>
  <html>
    <head>
      <meta charset="utf-8" />
      <title>${u(p)}</title>
      <style>${b}</style>
    </head>
    <body>
      <h1>${u(p)}</h1>
      ${M}
      ${f}
      ${N}
      ${F}
    </body>
  </html>`},Ge=(a,l)=>{const n=(l==null?void 0:l.operations)||{select:!0,create:!1,update:!1,delete:!1},o=(l==null?void 0:l.languages)||["curl","javascript","php","python"],r=`
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
  `;let m="";n.select&&(m+=`
      <tr><td>GET</td><td class="endpoint">${a}/list</td><td>获取媒体列表</td></tr>
      <tr><td>GET</td><td class="endpoint">${a}/detail/{id}</td><td>获取媒体详情</td></tr>
      <tr><td>GET</td><td class="endpoint">${a}/search</td><td>搜索媒体</td></tr>
    `),n.create&&(m+=`<tr><td>POST</td><td class="endpoint">${a}/create</td><td>创建媒体（上传文件）</td></tr>`),n.update&&(m+=`<tr><td>PUT</td><td class="endpoint">${a}/update/{id}</td><td>更新媒体信息</td></tr>`),n.delete&&(m+=`<tr><td>DELETE</td><td class="endpoint">${a}/delete/{id}</td><td>删除媒体</td></tr>`);const i=he(a);let h="";return n.select&&i.select&&(h+=R("查询媒体",i.select,o)),n.create&&i.create&&(h+=R("创建媒体",i.create,o)),n.update&&i.update&&(h+=R("更新媒体",i.update,o)),n.delete&&i.delete&&(h+=R("删除媒体",i.delete,o)),`
    <div class="section">
      <h2>媒体资源 API</h2>
      ${r}
      <h3>接口列表</h3>
      <table>
        <thead>
          <tr><th>方法</th><th>接口地址</th><th>说明</th></tr>
        </thead>
        <tbody>
          ${m}
        </tbody>
      </table>
      ${h}
    </div>
  `},ze=(a,l,n)=>{const o=(a.http_method||"POST").toUpperCase(),r=a.slug||"",m=`${l}/${r}`,i=(n==null?void 0:n.languages)||["curl","javascript","php","python"];let h="";if(a.input_schema&&a.input_schema.properties){const y=a.input_schema.properties,p=a.input_schema.required||[];h=Object.keys(y).map(b=>{const _=y[b];return`<tr>
        <td>${u(b)}</td>
        <td>${u(_.type||"string")}</td>
        <td>${p.includes(b)?"是":"否"}</td>
        <td>${u(_.description||"-")}</td>
      </tr>`}).join("")}let g="";if(a.output_schema&&a.output_schema.properties){const y=a.output_schema.properties;g=Object.keys(y).map(p=>{const b=y[p];return`<tr>
        <td>${u(p)}</td>
        <td>${u(b.type||"string")}</td>
        <td>${u(b.description||"-")}</td>
      </tr>`}).join("")}const E=ge(l,r,a.http_method||"POST",[],a.input_schema),k=E.select?R("请求",E.select,i):"";return`
    <div class="section">
      <h2>Web 函数：${u(a.name)}</h2>
      <p><strong>请求地址：</strong><code class="endpoint">${o} ${m}</code></p>
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
      
      ${g?`
        <h3>输出参数</h3>
        <table>
          <thead>
            <tr><th>参数名</th><th>类型</th><th>说明</th></tr>
          </thead>
          <tbody>
            ${g}
          </tbody>
        </table>
      `:"<p>无输出参数定义</p>"}
      
      ${k}
    </div>
  `},Le=a=>{const l={},n=Array.isArray(a.fields)?a.fields:[];return n.length===0?(l.title=`${a.name||a.table_name}示例标题`,l.summary=`${a.name||a.table_name}示例摘要`,l):(n.slice(0,5).forEach((o,r)=>{const m=o.field&&o.field.trim()!==""?o.field:`field_${r+1}`;l[m]=Oe(o,r)}),l)},He=(a,l,n,o)=>{const r=l.table_name;return a==="content"?[{method:"GET",url:`${n}/list/${r}`,desc:"获取列表（支持分页/字段/排序/过滤）"},{method:"GET",url:`${n}/detail/${r}/{id}`,desc:"获取详情"},{method:"GET",url:`${n}/count/${r}`,desc:"统计数量"},{method:"POST",url:`${n}/create/${r}`,desc:"新增"},{method:"PUT",url:`${n}/update/${r}/{id}`,desc:"更新"},{method:"DELETE",url:`${n}/delete/${r}/{id}`,desc:"删除"}]:[{method:"GET",url:`${o}/detail/${r}`,desc:"获取单页详情"},{method:"PUT",url:`${o}/update/${r}`,desc:"更新单页内容"}]},{Text:T,Paragraph:Z}=C,{TabPane:De}=fe,Re={whiteSpace:"pre",overflowX:"auto",overflowY:"hidden"},X=()=>({curl:"",javascript:"",php:"",python:""}),Fe=()=>({select:X(),create:X(),update:X(),delete:X()}),Ue={id:0,name:"媒体资源",description:"媒体资源管理接口，支持图片、视频、音频、文档等文件的查询",table_name:"media",mold_type:-1,fields:[],subject_content:{},list_show_fields:[],updated_at:null},Ke=[{key:"curl",label:"cURL"},{key:"javascript",label:"JavaScript"},{key:"php",label:"PHP"},{key:"python",label:"Python"}],qe=(a,l)=>[{key:"select",title:a,method:l,languages:Ke}],Ye=a=>{switch((a??"").toUpperCase()){case"GET":return"GET";case"PUT":return"PUT";case"DELETE":return"DELETE";default:return"POST"}};function nt({contents:a=[],subjects:l=[],functions:n=[],openContentBase:o,openSubjectBase:r,openFunctionBase:m}){var ce;const[i,h]=v.useState("content"),[g,E]=v.useState(null),[k,y]=v.useState(!1),[p,b]=v.useState(null),[_,P]=v.useState(Fe()),[M,f]=v.useState(null),N=i==="function"?n.find(e=>e.id===g)??null:null,[F,x]=v.useState(!1),[w,U]=v.useState(a.map(e=>e.id)),[O,K]=v.useState(l.map(e=>e.id)),[G,q]=v.useState(n.map(e=>e.id)),[Q,ae]=v.useState(!0),le=["curl","javascript","php","python"],[B,ne]=v.useState(le),[ee,re]=v.useState({select:!0,create:!1,update:!1,delete:!1}),[j,de]=v.useState(""),S=Ie(),Ee=i==="media"?Se:i==="function"?qe("调用接口 Demo",Ye(N==null?void 0:N.http_method)):i==="content"?Te:Pe,se=async(e,d)=>{h(e),E(d),y(!0),f(null),Ce.get(`/api-docs/molds/${e}/${d}`).then(s=>{const c=s.data;b(c),P(e==="content"?ye(o,c.table_name,c.name,ie(c)):be(r,c.table_name,c.name,ie(c)))}).catch(s=>{console.error("Error fetching API examples:",s),f(s.message?s.message:"获取示例失败")}).finally(()=>{y(!1)})},$e=()=>{U(a.map(e=>e.id)),K(l.map(e=>e.id)),q(n.map(e=>e.id)),ae(!0),ne(le),re({select:!0,create:!1,update:!1,delete:!1}),de(""),x(!0)},ve=()=>{const e=o.replace("/content","/media");let s=ue(a,l,o,r,w,O,{languages:B,operations:ee},Q,e,n,G,m);j&&j.trim()&&(s=s.replace(/YOUR_API_KEY/g,j.trim()));const c=window.open("","_blank");c?(c.document.open(),c.document.write(s),c.document.close(),c.focus()):D.error("浏览器阻止了弹窗，请允许后重试")},ke=()=>{const e=o.replace("/content","/media");let s=ue(a,l,o,r,w,O,{languages:B,operations:ee},Q,e,n,G,m);j&&j.trim()&&(s=s.replace(/YOUR_API_KEY/g,j.trim()));const c=s.replace("</body>",'<script>window.addEventListener("load", () => setTimeout(() => window.print(), 300));<\/script></body>'),$=window.open("","_blank");$?($.document.open(),$.document.write(c),$.document.close(),$.focus()):D.error("浏览器阻止了弹窗，请允许后重试")},xe=async e=>{if(!e){D.warning("当前示例暂无内容");return}try{await navigator.clipboard.writeText(e),D.success("复制成功")}catch(d){console.error("Copy failed:",d),D.error("复制失败，请手动复制")}},ie=e=>{const d={},s=Array.isArray(e.fields)?e.fields:[];return s.length===0?(d.title=`${e.name||e.table_name}示例标题`,d.summary=`${e.name||e.table_name}示例摘要`,d):(s.slice(0,5).forEach((c,$)=>{const Y=c.field&&c.field.trim()!==""?c.field:`field_${$+1}`;d[Y]=_e(c,$)}),d)},_e=(e,d)=>{switch((e.type||"").toLowerCase()){case"number":case"int":case"integer":case"float":case"decimal":case"double":return d+1;case"boolean":return d%2===0;case"date":return"2025-01-01";case"datetime":case"timestamp":return"2025-01-01T12:00:00Z";default:return`${e.label||e.title||e.field||"字段"}示例`}};return t.createElement("div",{className:"py-6"},t.createElement(we,{title:"API 文档"}),t.createElement("div",{className:"max-w-7xl mx-auto px-4 sm:px-6 lg:px-8"},t.createElement(W,{title:t.createElement("span",null,t.createElement(Ne,{className:"mr-2"}),"API 文档"),extra:t.createElement(J,{type:"primary",onClick:$e},"导出API文档")},t.createElement(W,{className:"mb-6"},t.createElement(L,{direction:"vertical",style:{width:"100%"},size:"large"},t.createElement("div",null,t.createElement(T,{strong:!0,className:"block mb-2"},S("content_list","内容列表")," "),t.createElement(A.Group,{value:i==="content"?g:null,onChange:e=>se("content",e.target.value)},a.map(e=>t.createElement(A.Button,{key:e.id,value:e.id},e.name)))),t.createElement("div",null,t.createElement(T,{strong:!0,className:"block mb-2"},S("content_single","内容单页 ")," "),t.createElement(A.Group,{value:i==="subject"?g:null,onChange:e=>se("subject",e.target.value)},l.map(e=>t.createElement(A.Button,{key:e.id,value:e.id},e.name)))),t.createElement("div",null,t.createElement(T,{strong:!0,className:"block mb-2"},"媒体资源   "),t.createElement(A.Group,{value:i==="media"?1:null,onChange:()=>{h("media"),E(1),b(Ue);const e=o.replace("/content","/media");P(he(e)),f(null)}},t.createElement(A.Button,{value:1},"媒体资源 API"))),t.createElement("div",null,t.createElement(T,{strong:!0,className:"block mb-2"},"Web 函数  "),t.createElement(A.Group,{value:i==="function"?g:null,onChange:e=>{const d=e.target.value,s=n.find(c=>c.id===d);s&&(h("function"),E(d),b({id:s.id,name:s.name,description:s.description,table_name:s.slug,mold_type:-1,fields:[],subject_content:{},list_show_fields:[],updated_at:null}),P(ge(m,s.slug,s.http_method,s.fields,s.input_schema)),f(null))}},n.map(e=>t.createElement(A.Button,{key:e.id,value:e.id},e.name)))))),M&&t.createElement(oe,{message:"获取示例失败",description:M,type:"error",showIcon:!0,className:"mb-4"}),p&&t.createElement(t.Fragment,null,t.createElement(me,{gutter:[24,24],style:{marginTop:8}},t.createElement(z,{xs:24,lg:10},t.createElement(W,{loading:k,className:"h-full"},t.createElement(L,{direction:"vertical",size:"large",style:{width:"100%"}},t.createElement("div",null,t.createElement(T,{strong:!0,className:"block text-base"},i==="function"?"函数名":S("model_name","模型名称")),t.createElement(Z,{className:"mb-1",ellipsis:{rows:2}},p.name),t.createElement(T,{strong:!0,className:"block text-base"},i==="function"?"请求地址":S("table_name","模型标识ID")),t.createElement(Z,{className:"mb-1",copyable:!0,ellipsis:{rows:2}},i==="function"?(()=>{const e=n.find(d=>d.id===g);if(e){const d=(e.http_method||"POST").toUpperCase(),s=e.slug||"",c=m.replace(/^https?:\/\/[^/]+/i,"");return`${d} ${c}/${s}`}return p.table_name})():p.table_name),p.description&&t.createElement(t.Fragment,null,t.createElement(T,{strong:!0,className:"block text-base"},S("description","描述")),t.createElement(Z,{className:"mb-1",ellipsis:{rows:3}},p.description)),p.updated_at&&t.createElement(t.Fragment,null,t.createElement(T,{strong:!0,className:"block text-base"},S("updated_at","最近更新")),t.createElement(Z,{className:"mb-0"},p.updated_at))),t.createElement("div",null,t.createElement(T,{strong:!0,className:"block text-base mb-2"},i==="function"?"输入参数定义":S("field_definition","字段定义")),i==="function"?t.createElement(t.Fragment,null,t.createElement(te,{dataSource:(()=>{const e=n.find(c=>c.id===g);if(!e||!e.input_schema||!e.input_schema.properties)return[];const d=e.input_schema.properties,s=e.input_schema.required||[];return Object.keys(d).map((c,$)=>{const Y=d[c];return{key:$,field:c,type:Y.type||"string",required:s.includes(c)?"是":"否",description:Y.description||"-"}})})(),pagination:!1,bordered:!0,size:"small",columns:[{title:"参数名",dataIndex:"field",key:"field",width:"25%"},{title:"类型",dataIndex:"type",key:"type",width:"20%"},{title:"必填",dataIndex:"required",key:"required",width:"15%"},{title:"说明",dataIndex:"description",key:"description"}],locale:{emptyText:"暂无输入参数"}}),t.createElement(H,{style:{margin:"16px 0"}}),t.createElement(T,{strong:!0,className:"block text-base mb-2"},"输出参数定义"),t.createElement(te,{dataSource:(()=>{const e=n.find(s=>s.id===g);if(!e||!e.output_schema||!e.output_schema.properties)return[];const d=e.output_schema.properties;return Object.keys(d).map((s,c)=>{const $=d[s];return{key:c,field:s,type:$.type||"string",description:$.description||"-"}})})(),pagination:!1,bordered:!0,size:"small",columns:[{title:"参数名",dataIndex:"field",key:"field",width:"30%"},{title:"类型",dataIndex:"type",key:"type",width:"25%"},{title:"说明",dataIndex:"description",key:"description"}],locale:{emptyText:"暂无输出参数"}})):t.createElement(te,{dataSource:i==="media"?[{key:"id",field:"id",label:"媒体ID",comment:"唯一标识"},{key:"filename",field:"filename",label:"文件名",comment:"存储的文件名"},{key:"title",field:"title",label:"标题",comment:"媒体标题"},{key:"alt",field:"alt",label:"Alt文本",comment:"图片替代文本"},{key:"description",field:"description",label:"描述",comment:"媒体描述"},{key:"url",field:"url",label:"URL",comment:"访问地址"},{key:"type",field:"type",label:"类型",comment:"image/video/audio/document"},{key:"mime_type",field:"mime_type",label:"MIME类型",comment:"文件MIME类型"},{key:"size",field:"size",label:"大小",comment:"文件大小（字节）"},{key:"width",field:"width",label:"宽度",comment:"图片/视频宽度"},{key:"height",field:"height",label:"高度",comment:"图片/视频高度"},{key:"duration",field:"duration",label:"时长",comment:"音视频时长（秒）"},{key:"tags",field:"tags",label:"标签",comment:"标签数组"},{key:"folder_path",field:"folder_path",label:"文件夹路径",comment:"所在文件夹完整路径"},{key:"created_at",field:"created_at",label:"创建时间",comment:"上传时间"}]:((ce=p.fields)==null?void 0:ce.map((e,d)=>({key:`${e.field||d}`,field:e.field||`field_${d+1}`,label:e.label||e.title||"-",comment:""})))||[],pagination:!1,bordered:!0,size:"small",columns:[{title:S("field_name","字段名"),dataIndex:"field",key:"field",width:"30%"},{title:S("meaning","含义"),dataIndex:"label",key:"label",width:"35%"},{title:S("remark","备注"),dataIndex:"comment",key:"comment"}],locale:{emptyText:"暂无字段定义"}}))))),t.createElement(z,{xs:24,lg:14},t.createElement(L,{direction:"vertical",size:"large",style:{width:"100%"}},Ee.map(e=>{var d;return t.createElement(W,{key:e.key,title:`${e.title}`,loading:k},t.createElement(fe,{defaultActiveKey:((d=e.languages[0])==null?void 0:d.key)||"curl",size:"small"},e.languages.map(s=>t.createElement(De,{tab:s.label,key:s.key},t.createElement("div",{className:"bg-gray-100 p-4 rounded",style:{position:"relative"}},t.createElement(J,{size:"small",icon:t.createElement(Ae,null),onClick:()=>{var c;return xe(((c=_[e.key])==null?void 0:c[s.key])??"")},style:{position:"absolute",top:-10,right:8}},"复制"),t.createElement("pre",{className:"whitespace-pre text-sm",style:Re},_[e.key][s.key],t.createElement("br",null),t.createElement("br",null),t.createElement("br",null)))))))}))))),!p&&t.createElement(oe,{message:"提示",description:"请从上方选择要查看的模型，系统将自动生成对应的API调用示例。",type:"info",showIcon:!0,className:"mt-4"}),t.createElement(Me,{title:"导出API文档",open:F,onCancel:()=>x(!1),footer:null,width:880},t.createElement(L,{direction:"vertical",style:{width:"100%"},size:"middle"},t.createElement("div",null,t.createElement(C.Text,{strong:!0},"选择接口类型"),t.createElement(H,{style:{margin:"8px 0"}}),t.createElement(me,{gutter:16},t.createElement(z,{xs:24,md:12},t.createElement("div",{style:{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:8}},t.createElement(C.Text,{type:"secondary"},"内容模型"),t.createElement(I,{checked:w.length===a.length,indeterminate:w.length>0&&w.length<a.length,onChange:e=>{e.target.checked?U(a.map(d=>d.id)):U([])}},"全选")),t.createElement("div",null,t.createElement(I.Group,{style:{width:"100%"},value:w,onChange:e=>U(e),options:(a||[]).map(e=>({label:e.name,value:e.id}))}))),t.createElement(z,{xs:24,md:12},t.createElement("div",{style:{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:8}},t.createElement(C.Text,{type:"secondary"},"单页模型"),t.createElement(I,{checked:O.length===l.length,indeterminate:O.length>0&&O.length<l.length,onChange:e=>{e.target.checked?K(l.map(d=>d.id)):K([])}},"全选")),t.createElement("div",null,t.createElement(I.Group,{style:{width:"100%"},value:O,onChange:e=>K(e),options:(l||[]).map(e=>({label:e.name,value:e.id}))}))),t.createElement(z,{xs:24,md:12},t.createElement(C.Text,{type:"secondary"},"媒体资源"),t.createElement("div",null,t.createElement(I,{checked:Q,onChange:e=>ae(e.target.checked)},"包含媒体资源接口"))),t.createElement(z,{xs:24,md:12},t.createElement("div",{style:{display:"flex",justifyContent:"space-between",alignItems:"center",marginBottom:8}},t.createElement(C.Text,{type:"secondary"},"Web函数"),t.createElement(I,{checked:G.length===n.length,indeterminate:G.length>0&&G.length<n.length,onChange:e=>{e.target.checked?q(n.map(d=>d.id)):q([])}},"全选")),t.createElement("div",null,t.createElement(I.Group,{style:{width:"100%"},value:G,onChange:e=>q(e),options:(n||[]).map(e=>({label:e.name,value:e.id}))}))))),t.createElement("div",null,t.createElement(C.Text,{strong:!0},"选择示例语言"),t.createElement(H,{style:{margin:"8px 0"}}),t.createElement(I.Group,{value:B,onChange:e=>ne(e),options:[{label:"cURL",value:"curl"},{label:"JavaScript",value:"javascript"},{label:"PHP",value:"php"},{label:"Python",value:"python"}]})),t.createElement("div",null,t.createElement(C.Text,{strong:!0},"选择接口类型"),t.createElement(H,{style:{margin:"8px 0"}}),t.createElement(I.Group,{value:Object.entries(ee).filter(([,e])=>e).map(([e])=>e),onChange:e=>{const d=new Set(e);re({select:d.has("select"),create:d.has("create"),update:d.has("update"),delete:d.has("delete")})},options:[{label:"读取",value:"select"},{label:"写入",value:"create"},{label:"修改",value:"update"},{label:"删除",value:"delete"}]}),t.createElement("div",{style:{color:"#999",marginTop:4}},"默认仅导出读取接口，可按需勾选写入/修改/删除。")),t.createElement("div",null,t.createElement(C.Text,{strong:!0},"API Key（可选）",t.createElement("span",{style:{color:"red",marginLeft:4}},"注意API Key安全，不要将含有API Key的文档泄露给不信任的人")),t.createElement(H,{style:{margin:"8px 0"}}),t.createElement(je.Password,{placeholder:"填写后将自动补全到示例请求头中 (x-api-key)",value:j,onChange:e=>de(e.target.value)})),t.createElement(L,{style:{justifyContent:"flex-end",width:"100%",marginTop:4}},t.createElement(J,{onClick:ve},"预览"),t.createElement(J,{type:"primary",onClick:ke},"导出")))))))}export{nt as default};
