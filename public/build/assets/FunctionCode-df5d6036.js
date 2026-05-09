import{r as l,R as e}from"./vendor-react-4af8f23c.js";import{a as g}from"./Service-450f2c78.js";import{X as se}from"./vendor-inertia-efb81232.js";import{F as le}from"./vendor-monaco-1b45f172.js";import{A as ce,a as ie}from"./AiGenerateModal-03535e36.js";import{S as f,aj as de,ao as pe,m as c,ad as J,_ as $,ae as me,K as ue,N as C,a2 as x,V as b,aF as he,aG as ye,a as ge,ag as fe}from"./vendor-antd-18d09d8b.js";import"./vendor-amis-19b28984.js";import"./vendor-misc-5a281d24.js";import"./vendor-markdown-762f2404.js";import"./vendor-katex-98160839.js";import"./vendor-dayjs-2ddd460c.js";const Oe=()=>{var j,B,F,X;const p=se(),T=(j=p==null?void 0:p.props)==null?void 0:j.id,m=((B=p==null?void 0:p.props)==null?void 0:B.type)||"endpoint",E=((X=(F=p==null?void 0:p.props)==null?void 0:F.project_info)==null?void 0:X.prefix)||"",[n,k]=l.useState(null),[h,y]=l.useState(`<?php 

`),[M,P]=l.useState(`<?php 

`),[D,O]=l.useState(!1),[q,R]=l.useState(!1),[z,S]=l.useState(!1),[v,W]=l.useState(`{
  "userId": 123,
  "action": "publish",
  "data": {
    "title": "Hello",
    "tags": ["a","b"]
  }
}`),[L,w]=l.useState(""),[H,u]=l.useState(""),[I,U]=l.useState("vs-dark"),[G,A]=l.useState(!1),[K,V]=l.useState(""),Q={hello:`<?php
return ['message' => 'Hello World', 'time' => time()];`,params:`<?php
$userId = (int)($payload['userId'] ?? 0);
$action = (string)($payload['action'] ?? '');
$data = (array)($payload['data'] ?? []);
return [
  'ok' => true,
  'env' => $env,
  'userId' => $userId,
  'action' => $action,
  'echo' => $data,
];`,http:`<?php
$city = (string)($payload['city'] ?? 'Beijing');
$resp = $Http->get('https://api.open-meteo.com/v1/forecast', [
  'latitude' => 39.9042,
  'longitude' => 116.4074,
  'current_weather' => true,
]);
return [
  'city' => $city,
  'status' => $resp->status(),
  'data' => $resp->json(),
];`,db:`<?php
$table = (string)($payload['table'] ?? ($prefix . '_users'));
$count = 0;
try { $count = (int)$db->count($table, ['active' => 1]); } catch (\\Throwable $e) { $count = 0; }
$items = [];
try { $items = $db->select($table, ['active' => 1], ['id','name'], 10); } catch (\\Throwable $e) {}
return ['table' => $table, 'count' => $count, 'items' => $items];`,plugin:`<?php
return $plugin->call('Plugins\\Demo\\Hello@run', [$payload, $env]);`},Y=t=>{if(!t){c.warning("请选择一个模板");return}const a=Q[t];y(a||""),c.success("模板已导入")},Z=async()=>{O(!0);try{const a=(await g.get(`/manage/functions/detail/${T}`,{params:{type:m}})).data;k(a),y((a==null?void 0:a.code)||`<?php 

`),P((a==null?void 0:a.code)||`<?php 

`)}catch(t){c.error((t==null?void 0:t.message)||"获取详情失败")}finally{O(!1)}},ee=async()=>{try{const t=await g.get("/mold/builder/models_and_fields"),a=(t==null?void 0:t.data)&&(t.data.data??t.data)||{},o=Array.isArray(a.models)?a.models:[];if(o.length>0){const s=o.map(i=>{const r=(i.fields||[]).map(d=>`${d.field}(${d.label})`).join(", ");return`- ${i.name} [${i.mold_type}] 表名: ${i.table_name} | 字段: ${r||"无"}`});V(`
当前项目的内容模型（可用于数据库操作）：
`+s.join(`
`))}}catch{}};l.useEffect(()=>{Z(),ee()},[T,m]);const te=async()=>{var t,a,o,s,i;if(n){R(!0);try{await g.post(`/manage/functions/update/${n.id}`,{type:m,code:h}),c.success("代码已保存"),u(""),P(h),k(r=>r&&{...r,code:h})}catch(r){const d=((o=(a=(t=r==null?void 0:r.response)==null?void 0:t.data)==null?void 0:a.errors)==null?void 0:o.message)||((i=(s=r==null?void 0:r.response)==null?void 0:s.data)==null?void 0:i.error)||(r==null?void 0:r.message)||"保存失败";u(String(d)),c.error(String(d))}finally{R(!1)}}},ae=async()=>{var t,a;if(n){if(!E){c.error("未选择项目，无法测试");return}if(!n.slug&&m==="endpoint"){c.error("缺少 Slug，无法测试");return}S(!0);try{let o={};try{o=v?JSON.parse(v):{}}catch{const s="请求体 JSON 解析失败";u(s),c.error(s),S(!1);return}if((n.type||m)==="hook"){const i=await(await fetch(`/manage/functions/test/${n.id}`,{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-XSRF-TOKEN":decodeURIComponent(((t=document.cookie.match(/XSRF-TOKEN=([^;]+)/))==null?void 0:t[1])||"")},body:JSON.stringify({payload:o})})).text();let r=null;try{r=JSON.parse(i)}catch{}const d=r?JSON.stringify(r,null,2):i;w(d||""),u("")}else{const s=decodeURIComponent(((a=document.cookie.match(/XSRF-TOKEN=([^;]+)/))==null?void 0:a[1])||""),r=await(await fetch(`/manage/functions/invoke/${encodeURIComponent(n.slug||"")}`,{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest",...s?{"X-XSRF-TOKEN":s}:{}},body:JSON.stringify(o)})).text();let d=null;try{d=JSON.parse(r)}catch{}const re=d?JSON.stringify(d,null,2):r;w(re||""),u("")}}catch(o){const s=String((o==null?void 0:o.message)||o);u(s),w("")}finally{S(!1)}}},_=()=>`你是一名 PHP 后端开发助手。请根据用户需求生成一段可在 Mosure 云函数沙箱中运行的 PHP 代码。

函数类型：${m==="hook"?"触发函数":"Web 函数（HTTP）"}${K}

可用环境变量：
- $payload: 请求参数（endpoint）或事件数据（hook）
- $env: 环境变量数组
- $ctx: 上下文信息
- $request: HTTP 请求对象（endpoint）
- $prefix: 项目前缀
- $Http: 安全的 HTTP 客户端（get/post/send 方法）
- $db: 安全的数据库操作对象
  - $db->select($table, $where, $fields, $limit)
  - $db->insert($table, $data)
  - $db->update($table, $data, $where)
  - $db->delete($table, $where)
  - $db->count($table, $where)
  - $db->first($table, $where, $fields)
  - $db->query($table) / $db->table($table) 查询构建器

代码安全约束：
- 禁止：resolve(, container(, DB::, Storage::, File::, Redis::, Cache::, Auth::, Gate::, Http::
- 禁止：exec, shell_exec, system(, passthru, proc_open, popen, curl_exec
- 禁止文件操作函数
- 禁止 include/require

输出要求：
1. 仅输出 PHP 代码，以 <?php 开头
2. 代码必须 return 一个数组
3. 数据库表名使用 $prefix . '_' 前缀
4. 包含适当的错误处理
5. 不要包含解释文字

请在这里描述你想要的函数功能...`,ne=()=>{const t=_();navigator.clipboard&&navigator.clipboard.writeText?navigator.clipboard.writeText(t).then(()=>{c.success("提示词已复制到剪贴板")}).catch(()=>{N(t)}):N(t)},N=t=>{const a=document.createElement("textarea");a.value=t,document.body.appendChild(a),a.select();try{const o=document.execCommand("copy");document.body.removeChild(a),c.success(o?"提示词已复制到剪贴板":"复制失败，请手动复制")}catch{document.body.removeChild(a),c.error("复制失败，请手动复制")}},oe=e.createElement(he,{size:"small",style:{marginBottom:8},items:[{key:"function-ai-prompt",label:e.createElement("span",null,e.createElement(ye,{style:{marginRight:6}}),"外部 AI 工具提示词（复制给 DeepSeek / 豆包 / ChatGPT 等使用）"),children:e.createElement("div",null,e.createElement("div",{style:{marginBottom:8,color:"#666",fontSize:13}},"将以下提示词复制给任意 AI 工具，即可生成符合本系统要求的 PHP 代码，然后粘贴到编辑器中。"),e.createElement("div",{style:{position:"relative"}},e.createElement("pre",{style:{background:"#f5f5f5",padding:"12px 40px 12px 12px",borderRadius:6,fontSize:12,maxHeight:300,overflow:"auto",whiteSpace:"pre-wrap",wordBreak:"break-word",lineHeight:1.6,border:"1px solid #e8e8e8"}},_()),e.createElement(ge,{title:"复制提示词"},e.createElement($,{type:"text",icon:e.createElement(fe,null),onClick:ne,style:{position:"absolute",top:8,right:8}}))))}]});return e.createElement("div",{style:{padding:24}},e.createElement(f,{style:{marginBottom:12}},e.createElement("span",{style:{color:"#999"}},"仅支持 PHP 运行时代码"),e.createElement(f,{size:8,wrap:!0},e.createElement("span",{style:{color:"#666"}},"编辑器主题"),e.createElement(de,{checkedChildren:"暗色",unCheckedChildren:"亮色",checked:I==="vs-dark",onChange:t=>U(t?"vs-dark":"vs")}),e.createElement("span",{style:{color:"#666"}},"模板"),e.createElement(pe,{onChange:t=>Y(t),placeholder:"选择模板",style:{width:180},options:[{label:"HelloWorld",value:"hello"},{label:"基础参数处理",value:"params"},{label:"远程请求",value:"http"},{label:"数据库操作",value:"db"},{label:"插件调用",value:"plugin"}]}),e.createElement(ce,{onClick:()=>A(!0),text:"AI 生成代码"}))),e.createElement(ie,{open:G,onOpenChange:A,title:"AI 生成函数代码",description:"描述你希望函数实现的功能，AI 将根据当前项目的内容模型生成 PHP 代码",promptPlaceholder:"例如：创建一个查询文章列表的接口，支持按标签筛选和分页",hideModelSelect:!0,extraContent:oe,onConfirm:async({prompt:t})=>{const a=await g.post("/manage/functions/suggest-code",{suggest:t,type:m},{timeout:4e4}),o=(a==null?void 0:a.data)??a,s=(o==null?void 0:o.task_id)??(o==null?void 0:o.id);if(!s)throw new Error("任务创建失败，请稍后重试");return s},onResult:async t=>{const a=(t==null?void 0:t.code)??(Array.isArray(t)?t[0]:t);if(typeof a=="string"&&a.trim())y(a),c.success("代码已生成，请检查并保存");else throw new Error("未生成有效的代码")}}),e.createElement(J,{loading:D,style:{marginBottom:16},title:n?`函数代码：${n.name}${n.slug?` (${n.slug})`:""}`:"函数代码"},e.createElement("div",{style:{border:"1px solid #f0f0f0",borderRadius:6,overflow:"hidden"}},e.createElement(le,{height:"420px",language:"php",theme:I,value:h,options:{fontSize:12,minimap:{enabled:!1},automaticLayout:!0,wordWrap:"on",padding:{top:12,bottom:12}},onChange:t=>y(t??"")})),e.createElement(f,{style:{marginTop:12}},e.createElement($,{type:"primary",onClick:te,loading:q,disabled:h===M},"保存代码"),e.createElement($,{onClick:()=>{y((n==null?void 0:n.code)||""),c.success("已重置为已保存的代码")}},"重置")),H?e.createElement(me,{style:{marginTop:12},type:"error",showIcon:!0,message:H}):null),e.createElement(J,{title:"测试"},e.createElement(ue,{gutter:12},e.createElement(C,{span:10},e.createElement(f,{direction:"vertical",style:{width:"100%"}},((n==null?void 0:n.type)||m)!=="hook"?e.createElement(e.Fragment,null,e.createElement(x,{addonBefore:"方法",value:((n==null?void 0:n.http_method)||"POST").toUpperCase(),readOnly:!0}),e.createElement(x,{addonBefore:"URL",value:n&&E?`/open//func/${E}_${n.slug||""}`:"",readOnly:!0})):null,e.createElement(b,{layout:"vertical"},e.createElement(b.Item,{label:"请求体 (JSON) "},e.createElement(x.TextArea,{rows:10,value:v,onChange:t=>W(t.target.value)}))))),e.createElement(C,{span:4},e.createElement("div",{style:{display:"flex",height:"100%",alignItems:"start",justifyContent:"center",flexDirection:"column",gap:8}},e.createElement($,{type:"primary",onClick:ae,loading:z,disabled:!n},"测试"),e.createElement("span",{style:{color:"#999",fontSize:12}},"请先保存代码后再测试"))),e.createElement(C,{span:10},e.createElement(b,{layout:"vertical"},e.createElement(b.Item,{label:"执行结果"},e.createElement("pre",{style:{background:"#fafafa",padding:12,border:"1px solid #f0f0f0",borderRadius:4,minHeight:240,maxHeight:340,overflow:"auto",whiteSpace:"pre-wrap"}},L||"（无）")))))))};export{Oe as default};
