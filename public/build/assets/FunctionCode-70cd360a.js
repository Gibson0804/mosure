import{r as c,R as e}from"./vendor-react-5293c662.js";import{a as C}from"./Service-62f4b6a8.js";import{X as ne}from"./vendor-inertia-4fcef779.js";import{F as oe}from"./vendor-monaco-2228e498.js";import{A as re,a as se}from"./AiGenerateModal-56bc3656.js";import{S as g,aj as le,ao as ce,m as l,ad as J,_ as $,ae as ie,K as pe,N as w,a2 as x,V as f,aF as de,aG as me,a as ue,ag as he}from"./vendor-antd-e89c0c7d.js";import"./vendor-amis-97daef07.js";import"./vendor-misc-43191129.js";import"./vendor-markdown-70ffc934.js";import"./vendor-katex-98160839.js";import"./vendor-dayjs-8586a5b4.js";const Te=()=>{var F,X,_,j;const i=ne(),T=(F=i==null?void 0:i.props)==null?void 0:F.id,p=((X=i==null?void 0:i.props)==null?void 0:X.type)||"endpoint",b=((j=(_=i==null?void 0:i.props)==null?void 0:_.project_info)==null?void 0:j.prefix)||"",[n,k]=c.useState(null),[h,y]=c.useState(`<?php 

`),[D,P]=c.useState(`<?php 

`),[q,O]=c.useState(!1),[M,R]=c.useState(!1),[z,E]=c.useState(!1),[S,W]=c.useState(`{
  "userId": 123,
  "action": "publish",
  "data": {
    "title": "Hello",
    "tags": ["a","b"]
  }
}`),[L,v]=c.useState(""),[H,m]=c.useState(""),[I,U]=c.useState("vs-dark"),[G,A]=c.useState(!1),K={hello:`<?php
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
return $plugin->call('Plugins\\Demo\\Hello@run', [$payload, $env]);`},V=t=>{if(!t){l.warning("请选择一个模板");return}const a=K[t];y(a||""),l.success("模板已导入")},Q=async()=>{O(!0);try{const a=(await C.get(`/manage/functions/detail/${T}`,{params:{type:p}})).data;k(a),y((a==null?void 0:a.code)||`<?php 

`),P((a==null?void 0:a.code)||`<?php 

`)}catch(t){l.error((t==null?void 0:t.message)||"获取详情失败")}finally{O(!1)}};c.useEffect(()=>{Q()},[T,p]);const Y=async()=>{var t,a,o,s,u;if(n){R(!0);try{await C.post(`/manage/functions/update/${n.id}`,{type:p,code:h}),l.success("代码已保存"),m(""),P(h),k(r=>r&&{...r,code:h})}catch(r){const d=((o=(a=(t=r==null?void 0:r.response)==null?void 0:t.data)==null?void 0:a.errors)==null?void 0:o.message)||((u=(s=r==null?void 0:r.response)==null?void 0:s.data)==null?void 0:u.error)||(r==null?void 0:r.message)||"保存失败";m(String(d)),l.error(String(d))}finally{R(!1)}}},Z=async()=>{var t,a;if(n){if(!b){l.error("未选择项目，无法测试");return}if(!n.slug&&p==="endpoint"){l.error("缺少 Slug，无法测试");return}E(!0);try{let o={};try{o=S?JSON.parse(S):{}}catch{const s="请求体 JSON 解析失败";m(s),l.error(s),E(!1);return}if((n.type||p)==="hook"){const u=await(await fetch(`/manage/functions/test/${n.id}`,{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-XSRF-TOKEN":decodeURIComponent(((t=document.cookie.match(/XSRF-TOKEN=([^;]+)/))==null?void 0:t[1])||"")},body:JSON.stringify({payload:o})})).text();let r=null;try{r=JSON.parse(u)}catch{}const d=r?JSON.stringify(r,null,2):u;v(d||""),m("")}else{const s=decodeURIComponent(((a=document.cookie.match(/XSRF-TOKEN=([^;]+)/))==null?void 0:a[1])||""),r=await(await fetch(`/manage/functions/invoke/${encodeURIComponent(n.slug||"")}`,{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest",...s?{"X-XSRF-TOKEN":s}:{}},body:JSON.stringify(o)})).text();let d=null;try{d=JSON.parse(r)}catch{}const ae=d?JSON.stringify(d,null,2):r;v(ae||""),m("")}}catch(o){const s=String((o==null?void 0:o.message)||o);m(s),v("")}finally{E(!1)}}},N=()=>`你是一名 PHP 后端开发助手。请根据用户需求生成一段可在 Mosure 云函数沙箱中运行的 PHP 代码。

函数类型：${p==="hook"?"触发函数":"Web 函数（HTTP）"}

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

请在这里描述你想要的函数功能...`,ee=()=>{const t=N();navigator.clipboard&&navigator.clipboard.writeText?navigator.clipboard.writeText(t).then(()=>{l.success("提示词已复制到剪贴板")}).catch(()=>{B(t)}):B(t)},B=t=>{const a=document.createElement("textarea");a.value=t,document.body.appendChild(a),a.select();try{const o=document.execCommand("copy");document.body.removeChild(a),l.success(o?"提示词已复制到剪贴板":"复制失败，请手动复制")}catch{document.body.removeChild(a),l.error("复制失败，请手动复制")}},te=e.createElement(de,{size:"small",style:{marginBottom:8},items:[{key:"function-ai-prompt",label:e.createElement("span",null,e.createElement(me,{style:{marginRight:6}}),"外部 AI 工具提示词（复制给 DeepSeek / 豆包 / ChatGPT 等使用）"),children:e.createElement("div",null,e.createElement("div",{style:{marginBottom:8,color:"#666",fontSize:13}},"将以下提示词复制给任意 AI 工具，即可生成符合本系统要求的 PHP 代码，然后粘贴到编辑器中。"),e.createElement("div",{style:{position:"relative"}},e.createElement("pre",{style:{background:"#f5f5f5",padding:"12px 40px 12px 12px",borderRadius:6,fontSize:12,maxHeight:300,overflow:"auto",whiteSpace:"pre-wrap",wordBreak:"break-word",lineHeight:1.6,border:"1px solid #e8e8e8"}},N()),e.createElement(ue,{title:"复制提示词"},e.createElement($,{type:"text",icon:e.createElement(he,null),onClick:ee,style:{position:"absolute",top:8,right:8}}))))}]});return e.createElement("div",{style:{padding:24}},e.createElement(g,{style:{marginBottom:12}},e.createElement("span",{style:{color:"#999"}},"仅支持 PHP 运行时代码"),e.createElement(g,{size:8,wrap:!0},e.createElement("span",{style:{color:"#666"}},"编辑器主题"),e.createElement(le,{checkedChildren:"暗色",unCheckedChildren:"亮色",checked:I==="vs-dark",onChange:t=>U(t?"vs-dark":"vs")}),e.createElement("span",{style:{color:"#666"}},"模板"),e.createElement(ce,{onChange:t=>V(t),placeholder:"选择模板",style:{width:180},options:[{label:"HelloWorld",value:"hello"},{label:"基础参数处理",value:"params"},{label:"远程请求",value:"http"},{label:"数据库操作",value:"db"},{label:"插件调用",value:"plugin"}]}),e.createElement(re,{onClick:()=>A(!0),text:"AI 生成代码"}))),e.createElement(se,{open:G,onOpenChange:A,title:"AI 生成函数代码",description:"描述你希望函数实现的功能，AI 将根据当前项目的内容模型生成 PHP 代码",promptPlaceholder:"例如：创建一个查询文章列表的接口，支持按标签筛选和分页",hideModelSelect:!0,extraContent:te,onConfirm:async({prompt:t})=>{const a=await C.post("/manage/functions/suggest-code",{suggest:t,type:p},{timeout:4e4}),o=(a==null?void 0:a.data)??a,s=(o==null?void 0:o.task_id)??(o==null?void 0:o.id);if(!s)throw new Error("任务创建失败，请稍后重试");return s},onResult:async t=>{const a=(t==null?void 0:t.code)??(Array.isArray(t)?t[0]:t);if(typeof a=="string"&&a.trim())y(a),l.success("代码已生成，请检查并保存");else throw new Error("未生成有效的代码")}}),e.createElement(J,{loading:q,style:{marginBottom:16},title:n?`函数代码：${n.name}${n.slug?` (${n.slug})`:""}`:"函数代码"},e.createElement("div",{style:{border:"1px solid #f0f0f0",borderRadius:6,overflow:"hidden"}},e.createElement(oe,{height:"420px",language:"php",theme:I,value:h,options:{fontSize:12,minimap:{enabled:!1},automaticLayout:!0,wordWrap:"on",padding:{top:12,bottom:12}},onChange:t=>y(t??"")})),e.createElement(g,{style:{marginTop:12}},e.createElement($,{type:"primary",onClick:Y,loading:M,disabled:h===D},"保存代码"),e.createElement($,{onClick:()=>{y((n==null?void 0:n.code)||""),l.success("已重置为已保存的代码")}},"重置")),H?e.createElement(ie,{style:{marginTop:12},type:"error",showIcon:!0,message:H}):null),e.createElement(J,{title:"测试"},e.createElement(pe,{gutter:12},e.createElement(w,{span:10},e.createElement(g,{direction:"vertical",style:{width:"100%"}},((n==null?void 0:n.type)||p)!=="hook"?e.createElement(e.Fragment,null,e.createElement(x,{addonBefore:"方法",value:((n==null?void 0:n.http_method)||"POST").toUpperCase(),readOnly:!0}),e.createElement(x,{addonBefore:"URL",value:n&&b?`/open//func/${b}_${n.slug||""}`:"",readOnly:!0})):null,e.createElement(f,{layout:"vertical"},e.createElement(f.Item,{label:"请求体 (JSON) "},e.createElement(x.TextArea,{rows:10,value:S,onChange:t=>W(t.target.value)}))))),e.createElement(w,{span:4},e.createElement("div",{style:{display:"flex",height:"100%",alignItems:"start",justifyContent:"center",flexDirection:"column",gap:8}},e.createElement($,{type:"primary",onClick:Z,loading:z,disabled:!n},"测试"),e.createElement("span",{style:{color:"#999",fontSize:12}},"请先保存代码后再测试"))),e.createElement(w,{span:10},e.createElement(f,{layout:"vertical"},e.createElement(f.Item,{label:"执行结果"},e.createElement("pre",{style:{background:"#fafafa",padding:12,border:"1px solid #f0f0f0",borderRadius:4,minHeight:240,maxHeight:340,overflow:"auto",whiteSpace:"pre-wrap"}},L||"（无）")))))))};export{Te as default};
