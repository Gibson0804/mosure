import React, { useState, useContext, useRef, useEffect, CSSProperties } from 'react'
import { useSchema } from '../context/SchemaContext.js';
import { Form, Col, Flex, Modal, Collapse, Tooltip } from 'antd';
import { Input, Radio, Switch, Checkbox, Select, InputNumber, ColorPicker, DatePicker, TimePicker, Upload, Button, Spin, message } from 'antd';
import { DeleteOutlined, UploadOutlined, StarOutlined, CaretRightFilled, InfoCircleOutlined, CopyOutlined } from '@ant-design/icons';
import { useSchemasDispatch, SchemasType, SchemasChildType } from '../context/SchemaContext.js';
import { clickOutsideLog, clickInsideLog } from '../utils/LogUtil.jsx'
import { MiddleOne } from './MiddleOne'
import { ReactSortable } from 'react-sortablejs';
import { GetRandomString, TransformChildren } from '../utils/stringUtil.js';
import api from '../../../util/Service.js';
import { MOLD_ROUTES } from '../../../Constants/routes.js';
import AiGenerateModal, { AiButton } from '../../../components/AiGenerateModal';

const { TextArea } = Input;

const middleBox = {
    marginLeft: 20,
    width: '100%',
    height: '100%',
    // backgroundColor: 'red',
};

const middleContentStyle = {
    padding: '20px',
    height: '100%',
    overflow: 'auto'
}

const defaultBox = {
    border: "1px solid white",
    padding: "5px"
}

const overColor = "#eee"
const mouseOverIcon = {
    position: "absolute",
    top: 4,
    right: 4,
    cursor: "pointer",
    // color: "#eee"
    color: overColor
}
const mouseOverBox = {
    border: "1px solid white",
    padding: "5px",
    boxShadow: "1px 1px 4px 1px rgba(0, 0, 0, 0.1)",
    borderRadius: 6
}

const pickColor = "#aaa"
const pickInputBox = {
    border: "1px solid " + pickColor,
    padding: "5px",
    boxShadow: "1px 1px 4px 1px rgba(0, 0, 0, 0.1)",
    borderRadius: 6
}
const pickFloatIcon: CSSProperties = {
    position: "absolute",
    top: 4,
    right: 4,
    cursor: "pointer",
    color: pickColor
}


function useClickOutside(refObject: React.RefObject<HTMLDivElement>, callbackOutside: Function, callbackInside: Function) {

    const handleClickOutside = (e: MouseEvent) => {
        const target = e.target as Node; // 添加类型断言

        if (refObject?.current && !refObject.current.contains(target)) {
            // callback()
        } else {
            callbackInside()

        }
    };

    useEffect(() => {
        document.addEventListener('mousedown', handleClickOutside);
        //   return () => document.removeEventListener('mousedown', handleClickOutside);  
    }, []); // 注意这里应该是一个空的依赖数组  
}


function MiddleOneInside({ child }) {

    const [showDelIcons, setShowDelIcons] = useState(false);
    // const [showDetailIcons, setShowDetailIcons] = useState(false);


    // const domRef = useRef(null);

    const domRef = useRef<HTMLDivElement>(null);

    useClickOutside(domRef
        , () => {}
        , () => {
            if (domRef == null || domRef.current == null || typeof domRef.current.dataset == undefined) {
                return
            }

            schemasDispatch({
                type: 'pick_id',
                id: domRef.current.dataset.cid,
            })
        }
    );

    const schemasDispatch = useSchemasDispatch();


    const handleMouseOver = () => {
        setShowDelIcons(true);
    }

    const handleMouseOut = () => {
        setShowDelIcons(false);
    }

    // const handleClick = () => {
    //     setShowDetailIcons(true);
    // }

    const deleteOne = () => {

        schemasDispatch({
            type: 'deleted',
            id: child.id
        })
    }

    let curFormStyle = defaultBox
    let showPick = false
    let showMouseOver = false
    if (child.isPick) {
        showPick = true
        curFormStyle = pickInputBox
    } else {
        if (showDelIcons) {
            curFormStyle = mouseOverBox
            showMouseOver = true
        }
    }

    let colLength = 11
    if (child.type === 'dividingLine') {
        colLength = 23
    }
    if (child.length) {
        colLength = child.length - 1
    }

    return (

        <>
            <Col
                onMouseOut={() => handleMouseOut()}
                onMouseOver={() => handleMouseOver()}
                // onClick={()=> handleClick()}

                data-cid={child.id}
                ref={domRef}

                style={curFormStyle}
                span={colLength}>

                <MiddleOne child={child} form={null}></MiddleOne>
                {showPick && <DeleteOutlined style={pickFloatIcon} onClick={deleteOne} />}
            </Col>
        </>
    )
}


type showJsonDetailType = {
    schema: SchemasType,
    schemasDispatch: Function,
    isJsonModalOpen: boolean,
    setIsJsonModalOpen: Function
}

function ShowJsonDetail({schema, schemasDispatch , isJsonModalOpen, setIsJsonModalOpen} : showJsonDetailType) {

    const oldSchemaRef = useRef('');
    const [jsonText, setJsonText] = useState('');
    const [jsonError, setJsonError] = useState<{ line: number; message: string } | null>(null);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const lineNumberRef = useRef<HTMLDivElement>(null);

    // 弹窗打开时初始化（不自动格式化，保持原始文本）
    useEffect(() => {
        if (isJsonModalOpen) {
            const raw = JSON.stringify(schema, null, 2);
            oldSchemaRef.current = raw;
            setJsonText(raw);
            setJsonError(null);
        }
    }, [isJsonModalOpen]);

    // 解析 JSON 错误定位
    const parseJsonError = (text: string): { line: number; message: string } | null => {
        try {
            JSON.parse(text);
            return null;
        } catch (err: any) {
            const errorMsg = err?.message || 'JSON 解析错误';
            const lines = text.split('\n');
            let errorLine = -1;

            const posMatch = errorMsg.match(/position\s+(\d+)/i);
            if (posMatch) {
                const pos = parseInt(posMatch[1], 10);
                let currentPos = 0;
                for (let i = 0; i < lines.length; i++) {
                    currentPos += lines[i].length + 1;
                    if (currentPos > pos) {
                        errorLine = i + 1;
                        break;
                    }
                }
            }

            return { line: errorLine, message: errorMsg };
        }
    };

    const handleJsonTextChange = (value: string) => {
        setJsonText(value);
        const error = parseJsonError(value);
        setJsonError(error);

        if (!error) {
            try {
                const next = JSON.parse(value);
                schemasDispatch({ type: 'replace', schemas: next });
            } catch {}
        }
    };

    const handleFormat = () => {
        try {
            const parsed = JSON.parse(jsonText);
            const formatted = JSON.stringify(parsed, null, 2);
            setJsonText(formatted);
            setJsonError(null);
            schemasDispatch({ type: 'replace', schemas: parsed });
        } catch {
            message.error('JSON 格式有误，无法格式化');
        }
    };

    const handleCompress = () => {
        try {
            const parsed = JSON.parse(jsonText);
            const compressed = JSON.stringify(parsed);
            setJsonText(compressed);
            setJsonError(null);
            schemasDispatch({ type: 'replace', schemas: parsed });
        } catch {
            message.error('JSON 格式有误，无法压缩');
        }
    };

    const handleShowJsonModalOk = () => {
        if (jsonError) {
            message.error('JSON 格式有误，请修正后再确认');
            return;
        }
        try {
            const parsed = JSON.parse(jsonText);
            schemasDispatch({ type: 'replace', schemas: parsed });
            oldSchemaRef.current = jsonText;
            setIsJsonModalOpen(false);
        } catch {
            message.error('JSON 格式有误，请修正后再确认');
        }
    };

    const handleShowJsonModalCancel = () => {
        try {
            schemasDispatch({ type: 'replace', schemas: JSON.parse(oldSchemaRef.current) });
        } catch {}
        setIsJsonModalOpen(false);
    };

    // 同步行号区域滚动
    const handleScroll = () => {
        if (textareaRef.current && lineNumberRef.current) {
            lineNumberRef.current.scrollTop = textareaRef.current.scrollTop;
        }
    };

    const lines = jsonText.split('\n');
    const lineCount = lines.length;

    return (
        <Modal
            title="JSON 编辑"
            open={isJsonModalOpen}
            onOk={handleShowJsonModalOk}
            onCancel={handleShowJsonModalCancel}
            width={720}
            footer={
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <Button size="small" onClick={handleFormat}>格式化</Button>
                        <Button size="small" onClick={handleCompress}>压缩</Button>
                    </div>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <Button onClick={handleShowJsonModalCancel}>取消</Button>
                        <Button type="primary" onClick={handleShowJsonModalOk}>确定</Button>
                    </div>
                </div>
            }
        >
            <div style={{ border: '1px solid #d9d9d9', borderRadius: 6, overflow: 'hidden', background: '#fafafa', height: 400 }}>
                <div
                    ref={textareaRef as any}
                    onScroll={handleScroll}
                    style={{ display: 'flex', height: '100%', overflowY: 'auto' }}
                >
                    {/* 行号区域 */}
                    <div
                        ref={lineNumberRef}
                        style={{
                            width: 48, minWidth: 48, padding: '8px 4px 8px 0', textAlign: 'right',
                            fontFamily: 'monospace', fontSize: 13, lineHeight: '20px', color: '#999',
                            background: '#f5f5f5', borderRight: '1px solid #e8e8e8',
                            userSelect: 'none', whiteSpace: 'pre', flexShrink: 0,
                        }}
                    >
                        {lines.map((_, i) => (
                            <div
                                key={i}
                                style={{
                                    height: 20,
                                    paddingRight: 8,
                                    ...(jsonError && jsonError.line === i + 1
                                        ? { background: '#fff1f0', color: '#ff4d4f', fontWeight: 600 }
                                        : {}),
                                }}
                            >
                                {i + 1}
                            </div>
                        ))}
                    </div>
                    {/* 编辑区域 */}
                    <div style={{ flex: 1, position: 'relative', minHeight: '100%' }}>
                        <textarea
                            value={jsonText}
                            onChange={(e) => handleJsonTextChange(e.target.value)}
                            spellCheck={false}
                            style={{
                                width: '100%', height: Math.max(lineCount * 20 + 16, 400),
                                padding: '8px 12px', border: 'none', outline: 'none', resize: 'none',
                                fontFamily: 'monospace', fontSize: 13, lineHeight: '20px',
                                background: 'transparent', color: '#333',
                            }}
                        />
                        {/* 错误行高亮背景层 */}
                        {jsonError && jsonError.line > 0 && (
                            <div
                                style={{
                                    position: 'absolute', top: 8 + (jsonError.line - 1) * 20,
                                    left: 0, right: 0, height: 20,
                                    background: 'rgba(255, 77, 79, 0.08)', pointerEvents: 'none',
                                }}
                            />
                        )}
                    </div>
                </div>
            </div>

            {/* 错误信息展示 */}
            {jsonError && (
                <div style={{
                    marginTop: 8, padding: '8px 12px', background: '#fff2f0',
                    border: '1px solid #ffccc7', borderRadius: 4, fontSize: 13, color: '#ff4d4f',
                }}>
                    <span style={{ fontWeight: 600 }}>
                        {jsonError.line > 0 ? `第 ${jsonError.line} 行错误：` : '格式错误：'}
                    </span>
                    {jsonError.message}
                </div>
            )}
        </Modal>
    )
}

type props = {
    handleSaveFunc: Function
}

export function Middle({ handleSaveFunc }: props) {
    const schema: SchemasType = useSchema()

    const schemasDispatch = useSchemasDispatch();

    const children = schema.children;

    const childrenList = children.map(child => (
        <MiddleOneInside key={child.id} child={child} />
    ))


    const savaBtnStyle = {
        marginRight: 10
    }

    const headerRowStyle = {
        display: 'flex',
        alignItems: 'center',
        gap: 16,
        margin: 0,
        marginBottom: 16,
        width: '100%'
    }

    const headerTitleStyle = {
        flex: '1 1 auto',
        minWidth: 0,
        fontSize: 32,
        fontWeight: 600,
        lineHeight: 1.2,
        whiteSpace: 'nowrap' as const,
        overflow: 'hidden' as const,
        textOverflow: 'ellipsis' as const,
    }

    const headerActionsStyle = {
        flex: '0 0 auto',
        display: 'flex',
        alignItems: 'center',
        whiteSpace: 'nowrap' as const,
    }

    const inputListStyle = {
        display: 'flex',
        flexWrap: 'wrap' as const,

    }

    const [isJsonModalOpen, setIsJsonModalOpen] = useState(false);

    const [aiModalOpen, setAiModalOpen] = useState(false);

    // 移除项目级 AI 模型读取，后端将按系统级默认模型处理

    // 生成外部 AI 工具提示词
    const generateMoldAiPrompt = (): string => {
        return `请帮我设计一个内容模型（表单），根据我的需求判断模型包含哪些字段。

## 输出要求
1. 仅输出一个 JSON 对象（不要包含任何解释、注释或 Markdown 代码块标记）
2. JSON 中不要有换行和注释

## JSON 格式说明
{
  "page_id": "模型英文标识（小写字母和下划线，如 article、product_list）",
  "page_name": "模型中文名称（如 文章管理、商品列表）",
  "mold_type": "list 或 single（list=内容列表可多条数据，single=单页面只有一条数据）",
  "children": [
    {
      "id": "字段唯一标识（必传，如 input_abc123，建议使用 type_随机字符串或和field一致，确保字段唯一）",
      "label": "字段中文名",
      "field": "字段英文名（小写字母和下划线）",
      "type": "字段表单类型（见下方可选类型）",
      "options": ["选项1", "选项2"]  // 仅 select/radio/checkbox 类型需要
    }
  ]
}

## 可用的字段表单类型
- input — 单行文本输入
- textarea — 多行文本输入
- radio — 单选按钮（需提供 options）
- switch — 开关（布尔值）
- checkbox — 多选框（需提供 options）
- select — 下拉选择（需提供 options）
- numInput — 数字输入
- colorPicker — 颜色选择器
- datePicker — 日期选择
- timePicker — 时间选择
- fileUpload — 文件上传（单文件）
- picUpload — 图片上传（单图）
- picGallery — 图片集（多图上传）
- richText — 富文本编辑器

## 示例
需求：设计一个广告宣传页
输出：{"page_id":"ad","page_name":"广告宣传页","mold_type":"list","children":[{"id":"input_abc123","label":"广告标题","type":"input","field":"title"},{"id":"textarea_def456","label":"广告描述","type":"textarea","field":"description"},{"id":"picUpload_ghi789","label":"广告图片","type":"picUpload","field":"pic"},{"id":"input_jkl012","label":"广告链接","type":"input","field":"link"},{"id":"datePicker_mno345","label":"开始时间","type":"datePicker","field":"start_time"},{"id":"datePicker_pqr678","label":"结束时间","type":"datePicker","field":"end_time"},{"id":"select_stu901","label":"展示位置","type":"select","field":"position","options":["首页","列表页"]}]}

## 我的需求
请在这里描述你想要的模型...
`;
    };

    const copyMoldAiPrompt = () => {
        const text = generateMoldAiPrompt();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                message.success('提示词已复制到剪贴板');
            }).catch(() => {
                moldFallbackCopy(text);
            });
        } else {
            moldFallbackCopy(text);
        }
    };

    const moldFallbackCopy = (text: string) => {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try {
            const ok = document.execCommand('copy');
            document.body.removeChild(ta);
            message.success(ok ? '提示词已复制到剪贴板' : '复制失败，请手动复制');
        } catch {
            document.body.removeChild(ta);
            message.error('复制失败，请手动复制');
        }
    };

    const moldPromptExtraContent = (
        <Collapse
            size="small"
            style={{ marginBottom: 8 }}
            items={[{
                key: 'mold-ai-prompt',
                label: (
                    <span>
                        <InfoCircleOutlined style={{ marginRight: 6 }} />
                        外部 AI 工具提示词（复制给 DeepSeek / 豆包 / ChatGPT 等使用）
                    </span>
                ),
                children: (
                    <div>
                        <div style={{ marginBottom: 8, color: '#666', fontSize: 13 }}>
                            将以下提示词复制给任意 AI 工具，即可生成符合本系统要求的模型 JSON，然后通过「查看json」按钮导入。
                        </div>
                        <div style={{ position: 'relative' }}>
                            <pre style={{
                                background: '#f5f5f5', padding: '12px 40px 12px 12px', borderRadius: 6, fontSize: 12,
                                maxHeight: 300, overflow: 'auto', whiteSpace: 'pre-wrap', wordBreak: 'break-word',
                                lineHeight: 1.6, border: '1px solid #e8e8e8',
                            }}>
                                {generateMoldAiPrompt()}
                            </pre>
                            <Tooltip title="复制提示词">
                                <Button
                                    type="text"
                                    icon={<CopyOutlined />}
                                    onClick={copyMoldAiPrompt}
                                    style={{ position: 'absolute', top: 8, right: 8 }}
                                />
                            </Tooltip>
                        </div>
                    </div>
                ),
            }]}
        />
    );

    // 轮询逻辑已迁移至 AiGenerateModal 内部

    return (
        <div className='middle'>
            <div className='middle-content' style={middleContentStyle}>
                <div className='middle-content-header' style={{ marginBottom: '20px' }}>
                    <AiGenerateModal
                        open={aiModalOpen}
                        onOpenChange={setAiModalOpen}
                        title="AI 生成模型与字段"
                        description="请输入提示文案，系统将自动生成模型与字段草案。"
                        promptPlaceholder="例如：创建一个文章模型，包含标题、摘要、正文、封面图、标签、多图等字段"
                        hideModelSelect={true}
                        extraContent={moldPromptExtraContent}
                        onConfirm={async ({ prompt, model }) => {
                            // 发起异步任务，返回 taskId，由 AiGenerateModal 内部轮询并驱动进度
                            const res = await api.post(MOLD_ROUTES.suggest, { suggest: prompt }, { timeout: 40000 });
                            const taskInfo = res?.data ?? res;
                            const taskId = taskInfo?.task_id ?? taskInfo?.id;
                            if (!taskId) {
                                throw new Error('任务创建失败，请稍后重试');
                            }
                            return taskId;
                        }}
                        onResult={async (result) => {
                            // 兼容不同返回结构
                            const newSchema = Array.isArray(result) ? { children: result } : (result || {});
                            if (!newSchema || !newSchema.children) {
                                throw new Error('未生成有效的模型结构');
                            }
                            const updatedChildren = TransformChildren(newSchema.children);
                            newSchema.children = updatedChildren;
                            schemasDispatch({ type: 'replace', schemas: newSchema });
                        }}
                    />
                </div>

            <div style={headerRowStyle}>
                <div style={headerTitleStyle} title={schema.page_name}>{schema.page_name}</div>
                <div style={headerActionsStyle}>
                    <Button style={savaBtnStyle} onClick={() => setIsJsonModalOpen(true)}>查看json</Button>
                    <Button style={savaBtnStyle} type="primary" onClick={() => handleSaveFunc(schema)}>保存</Button>
                    <AiButton onClick={() => setAiModalOpen(true)} />
                </div>
            </div>
                <Form>
                    <ReactSortable 
                        style={inputListStyle}
                        list={children}
                        setList={() => undefined}
                        onUpdate={(evt) => {
                            let newChild = {
                                type: 'change_input_place',
                                fromIndex: evt.oldIndex,
                                index: evt.newIndex
                            }
                            schemasDispatch(newChild)
                        }}
                        onAdd={(evt) => {
                            let newChild = {
                                type: 'added',
                                name: evt.item.getAttribute('data-name'),
                                icon_type: evt.item.getAttribute('data-type'),
                                index: evt.newIndex
                            }
                            schemasDispatch(newChild)
                        }}
                        group={{ name: 'dragItem' }}
                        animation={150}
                    >
                        {children.map(child => (
                            <MiddleOneInside key={child.id} child={child} />
                        ))}
                    </ReactSortable>
                </Form>

                <ShowJsonDetail 
                    schema={schema} 
                    schemasDispatch={schemasDispatch} 
                    isJsonModalOpen={isJsonModalOpen} 
                    setIsJsonModalOpen={setIsJsonModalOpen} 
                />
            </div>
        </div>
    )
}