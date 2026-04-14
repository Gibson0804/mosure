import{r as s,R as t}from"./vendor-react-4af8f23c.js";import{S as f}from"./vendor-inertia-efb81232.js";import{C as u,a as o}from"./vendor-milkdown-a6d5ab9c.js";import"./vendor-amis-19b28984.js";import"./vendor-misc-5a281d24.js";import"./vendor-markdown-762f2404.js";import"./vendor-katex-98160839.js";import"./vendor-prosemirror-6aa0cc46.js";import"./vendor-codemirror-d246ab50.js";import"./vendor-lezer-19fe8487.js";function M({article:r}){const a=s.useRef(null),p=s.useRef(null),d=s.useMemo(()=>{if(!r.content)return[];const i=r.content.split(`
`),e=[];let n=!1;return i.forEach(l=>{if(l.trim().startsWith("```")){n=!n;return}if(n)return;const c=l.match(/^(#{1,3})\s+(.+)$/);c&&e.push({level:c[1].length,text:c[2].trim()})}),e},[r.content]),m=i=>{var n;if(!a.current)return;const e=a.current.querySelectorAll("h1, h2, h3");for(const l of e)if(((n=l.textContent)==null?void 0:n.trim())===i){l.scrollIntoView({behavior:"smooth",block:"start"});break}};return s.useEffect(()=>!a.current||p.current?void 0:((async()=>{const e=new u({root:a.current,defaultValue:r.content||"",features:{[o.BlockEdit]:!1,[o.Toolbar]:!1,[o.Placeholder]:!1,[o.LinkTooltip]:!1,[o.ListItem]:!0,[o.Table]:!0,[o.CodeMirror]:!0,[o.ImageBlock]:!0,[o.Cursor]:!1,[o.Latex]:!1}});e.setReadonly(!0),await e.create(),p.current=e})(),()=>{var e;(e=p.current)==null||e.destroy(),p.current=null}),[]),t.createElement(t.Fragment,null,t.createElement(f,{title:r.title}),t.createElement("div",{className:"kb-pv-layout"},d.length>0&&t.createElement("div",{className:"kb-pv-toc"},t.createElement("div",{style:{padding:"0 12px 8px",fontSize:13,fontWeight:600,color:"#1e293b",borderBottom:"1px solid #f0f0f0"}},"目录"),t.createElement("div",{style:{padding:"8px 0"}},d.map((i,e)=>t.createElement("div",{key:e,onClick:()=>m(i.text),className:"kb-pv-toc-item",style:{paddingLeft:12+(i.level-1)*12}},i.text)))),t.createElement("div",{className:"kb-pv-content"},t.createElement("h1",{className:"kb-pv-title"},r.title),t.createElement("div",{className:"kb-pv-meta"},r.updated_at," · 阅读 ",r.view_count),t.createElement("div",{ref:a}))),t.createElement("style",null,`
                html, body { margin: 0; padding: 0; background: #f8fafc; }
                .kb-pv-layout {
                    display: flex;
                    max-width: 1100px;
                    margin: 0 auto;
                    padding: 40px 24px 80px;
                    min-height: 100vh;
                    gap: 24px;
                }
                .kb-pv-toc {
                    width: 200px;
                    flex-shrink: 0;
                    position: sticky;
                    top: 40px;
                    align-self: flex-start;
                    max-height: calc(100vh - 80px);
                    overflow-y: auto;
                    background: #fff;
                    border-radius: 8px;
                    border: 1px solid #f0f0f0;
                    padding: 12px 0;
                }
                .kb-pv-toc-item {
                    padding: 4px 12px;
                    font-size: 12px;
                    color: #64748b;
                    cursor: pointer;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    line-height: 24px;
                }
                .kb-pv-toc-item:hover { color: #2563eb; background: #f1f5f9; }
                .kb-pv-content {
                    flex: 1;
                    min-width: 0;
                    background: #fff;
                    border-radius: 8px;
                    padding: 24px;
                }
                .kb-pv-title {
                    font-size: 32px;
                    font-weight: 700;
                    margin: 0 0 16px;
                    line-height: 1.4;
                    color: #1a1a1a;
                }
                .kb-pv-meta {
                    font-size: 13px;
                    color: #999;
                    margin-bottom: 32px;
                    padding-bottom: 16px;
                    border-bottom: 1px solid #f0f0f0;
                }
                .milkdown .ProseMirror img { max-width: 100%; height: auto; }
                .milkdown .ProseMirror img[style*="width"] { max-width: none; }
                .milkdown .ProseMirror pre { overflow-x: auto; }
                .milkdown .ProseMirror table { display: block; overflow-x: auto; max-width: 100%; }

                /* 移动端自适应 */
                @media (max-width: 768px) {
                    .kb-pv-layout {
                        padding: 16px;
                        padding-bottom: 60px;
                    }
                    .kb-pv-toc { display: none; }
                    .kb-pv-content { padding: 16px; border-radius: 0; }
                    .kb-pv-title { font-size: 22px; }
                    .kb-pv-meta { margin-bottom: 20px; }
                    .milkdown .ProseMirror { font-size: 16px; line-height: 1.8; }
                }
            `))}export{M as default};
