import{r as l,R as e}from"./vendor-react-4af8f23c.js";import{a as J}from"./Service-450f2c78.js";import{X as Q}from"./vendor-inertia-efb81232.js";import{F as Y}from"./vendor-monaco-1b45f172.js";import{S as y,aj as Z,ao as ee,ad as P,_ as v,m as i,ae as te,K as ae,N as C,a2 as w,V as f}from"./vendor-antd-9384dbd2.js";import"./vendor-amis-19b28984.js";import"./vendor-misc-5a281d24.js";import"./vendor-markdown-762f2404.js";import"./vendor-katex-98160839.js";import"./vendor-dayjs-2ddd460c.js";const de=()=>{var X,j,H,F;const c=Q(),O=(X=c==null?void 0:c.props)==null?void 0:X.id,u=((j=c==null?void 0:c.props)==null?void 0:j.type)||"endpoint",$=((F=(H=c==null?void 0:c.props)==null?void 0:H.project_info)==null?void 0:F.prefix)||"",[t,T]=l.useState(null),[g,h]=l.useState(`<?php 

`),[B,R]=l.useState(`<?php 

`),[_,x]=l.useState(!1),[L,k]=l.useState(!1),[U,E]=l.useState(!1),[S,W]=l.useState(`{
  "userId": 123,
  "action": "publish",
  "data": {
    "title": "Hello",
    "tags": ["a","b"]
  }
}`),[q,b]=l.useState(""),[N,m]=l.useState(""),[I,D]=l.useState("vs-dark"),K={hello:`<?php
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
return $plugin->call('Plugins\\Demo\\Hello@run', [$payload, $env]);`},z=a=>{if(!a){i.warning("请选择一个模板");return}const r=K[a];h(r||""),i.success("模板已导入")},A=async()=>{x(!0);try{const r=(await J.get(`/manage/functions/detail/${O}`,{params:{type:u}})).data;T(r),h((r==null?void 0:r.code)||`<?php 

`),R((r==null?void 0:r.code)||`<?php 

`)}catch(a){i.error((a==null?void 0:a.message)||"获取详情失败")}finally{x(!1)}};l.useEffect(()=>{A()},[O,u]);const M=async()=>{var a,r,o,s,d;if(t){k(!0);try{await J.post(`/manage/functions/update/${t.id}`,{type:u,code:g}),i.success("代码已保存"),m(""),R(g),T(n=>n&&{...n,code:g})}catch(n){const p=((o=(r=(a=n==null?void 0:n.response)==null?void 0:a.data)==null?void 0:r.errors)==null?void 0:o.message)||((d=(s=n==null?void 0:n.response)==null?void 0:s.data)==null?void 0:d.error)||(n==null?void 0:n.message)||"保存失败";m(String(p)),i.error(String(p))}finally{k(!1)}}},V=async()=>{var a,r;if(t){if(!$){i.error("未选择项目，无法测试");return}if(!t.slug&&u==="endpoint"){i.error("缺少 Slug，无法测试");return}E(!0);try{let o={};try{o=S?JSON.parse(S):{}}catch{const s="请求体 JSON 解析失败";m(s),i.error(s),E(!1);return}if((t.type||u)==="hook"){const d=await(await fetch(`/manage/functions/test/${t.id}`,{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest","X-XSRF-TOKEN":decodeURIComponent(((a=document.cookie.match(/XSRF-TOKEN=([^;]+)/))==null?void 0:a[1])||"")},body:JSON.stringify({payload:o})})).text();let n=null;try{n=JSON.parse(d)}catch{}const p=n?JSON.stringify(n,null,2):d;b(p||""),m("")}else{const s=decodeURIComponent(((r=document.cookie.match(/XSRF-TOKEN=([^;]+)/))==null?void 0:r[1])||""),n=await(await fetch(`/manage/functions/invoke/${encodeURIComponent(t.slug||"")}`,{method:"POST",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest",...s?{"X-XSRF-TOKEN":s}:{}},body:JSON.stringify(o)})).text();let p=null;try{p=JSON.parse(n)}catch{}const G=p?JSON.stringify(p,null,2):n;b(G||""),m("")}}catch(o){const s=String((o==null?void 0:o.message)||o);m(s),b("")}finally{E(!1)}}};return e.createElement("div",{style:{padding:24}},e.createElement(y,{style:{marginBottom:12}},e.createElement("span",{style:{color:"#999"}},"仅支持 PHP 运行时代码"),e.createElement(y,{size:8,wrap:!0},e.createElement("span",{style:{color:"#666"}},"编辑器主题"),e.createElement(Z,{checkedChildren:"暗色",unCheckedChildren:"亮色",checked:I==="vs-dark",onChange:a=>D(a?"vs-dark":"vs")}),e.createElement("span",{style:{color:"#666"}},"模板"),e.createElement(ee,{onChange:a=>z(a),placeholder:"选择模板",style:{width:180},options:[{label:"HelloWorld",value:"hello"},{label:"基础参数处理",value:"params"},{label:"远程请求",value:"http"},{label:"数据库操作",value:"db"},{label:"插件调用",value:"plugin"}]}))),e.createElement(P,{loading:_,style:{marginBottom:16},title:t?`函数代码：${t.name}${t.slug?` (${t.slug})`:""}`:"函数代码"},e.createElement("div",{style:{border:"1px solid #f0f0f0",borderRadius:6,overflow:"hidden"}},e.createElement(Y,{height:"420px",language:"php",theme:I,value:g,options:{fontSize:12,minimap:{enabled:!1},automaticLayout:!0,wordWrap:"on",padding:{top:12,bottom:12}},onChange:a=>h(a??"")})),e.createElement(y,{style:{marginTop:12}},e.createElement(v,{type:"primary",onClick:M,loading:L,disabled:g===B},"保存代码"),e.createElement(v,{onClick:()=>{h((t==null?void 0:t.code)||""),i.success("已重置为已保存的代码")}},"重置")),N?e.createElement(te,{style:{marginTop:12},type:"error",showIcon:!0,message:N}):null),e.createElement(P,{title:"测试"},e.createElement(ae,{gutter:12},e.createElement(C,{span:10},e.createElement(y,{direction:"vertical",style:{width:"100%"}},((t==null?void 0:t.type)||u)!=="hook"?e.createElement(e.Fragment,null,e.createElement(w,{addonBefore:"方法",value:((t==null?void 0:t.http_method)||"POST").toUpperCase(),readOnly:!0}),e.createElement(w,{addonBefore:"URL",value:t&&$?`/open//func/${$}_${t.slug||""}`:"",readOnly:!0})):null,e.createElement(f,{layout:"vertical"},e.createElement(f.Item,{label:"请求体 (JSON) "},e.createElement(w.TextArea,{rows:10,value:S,onChange:a=>W(a.target.value)}))))),e.createElement(C,{span:4},e.createElement("div",{style:{display:"flex",height:"100%",alignItems:"start",justifyContent:"center",flexDirection:"column",gap:8}},e.createElement(v,{type:"primary",onClick:V,loading:U,disabled:!t},"测试"),e.createElement("span",{style:{color:"#999",fontSize:12}},"请先保存代码后再测试"))),e.createElement(C,{span:10},e.createElement(f,{layout:"vertical"},e.createElement(f.Item,{label:"执行结果"},e.createElement("pre",{style:{background:"#fafafa",padding:12,border:"1px solid #f0f0f0",borderRadius:4,minHeight:240,maxHeight:340,overflow:"auto",whiteSpace:"pre-wrap"}},q||"（无）")))))))};export{de as default};
