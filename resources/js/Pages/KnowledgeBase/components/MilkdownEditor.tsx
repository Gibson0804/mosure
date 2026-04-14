import React, { useEffect, useRef, useCallback, useState, useMemo } from 'react';
import { Crepe, CrepeFeature } from '@milkdown/crepe';
import { editorViewCtx, parserCtx, commandsCtx } from '@milkdown/kit/core';
import {
    toggleStrongCommand,
    toggleEmphasisCommand,
    toggleInlineCodeCommand,
    wrapInHeadingCommand,
    wrapInBulletListCommand,
    wrapInOrderedListCommand,
    wrapInBlockquoteCommand,
    insertHrCommand,
} from '@milkdown/kit/preset/commonmark';
import { toggleStrikethroughCommand } from '@milkdown/kit/preset/gfm';
import { toggleLinkCommand } from '@milkdown/kit/component/link-tooltip';
import { Modal, Input, Button, message, Typography, Divider, Spin, Tooltip } from 'antd';
import api from '../../../util/Service';
import { KB_ROUTES } from '../../../Constants/routes';
import '@milkdown/kit/prose/view/style/prosemirror.css';
import '@milkdown/crepe/theme/common/reset.css';
import '@milkdown/crepe/theme/common/block-edit.css';
import '@milkdown/crepe/theme/common/code-mirror.css';
import '@milkdown/crepe/theme/common/cursor.css';
import '@milkdown/crepe/theme/common/image-block.css';
import '@milkdown/crepe/theme/common/link-tooltip.css';
import '@milkdown/crepe/theme/common/list-item.css';
import '@milkdown/crepe/theme/common/placeholder.css';
import '@milkdown/crepe/theme/common/toolbar.css';
import '@milkdown/crepe/theme/common/table.css';
import '@milkdown/crepe/theme/nord.css';

// 设置编辑器内容区域背景为白色，调整 padding
const style = document.createElement('style');
style.textContent = `
.milkdown-editor-wrapper .ProseMirror {
    background: #fff !important;
    padding: 16px !important;
}
.milkdown .ProseMirror {
    padding: 16px !important;
}
.milkdown-source-textarea {
    width: 100%;
    height: 100%;
    min-height: 500px;
    border: none;
    outline: none;
    resize: none;
    padding: 16px;
    font-family: 'SF Mono', 'Monaco', 'Menlo', 'Consolas', 'Liberation Mono', 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.6;
    color: #333;
    background: #fafafa;
    tab-size: 4;
}
.milkdown-source-textarea:focus {
    background: #fff;
}
`;
if (!document.head.querySelector('style[data-milkdown-bg]')) {
    style.setAttribute('data-milkdown-bg', 'true');
    document.head.appendChild(style);
}

interface MilkdownEditorProps {
    value?: string;
    onChange?: (markdown: string) => void;
    readonly?: boolean;
}

// AI 帮改 SVG 图标
// const aiIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a4 4 0 0 0-4 4c0 2 1 3 2 4l-4 4v2h4l4-4c1-1 2-2 4-2a4 4 0 0 0 0-8h-6z"/><circle cx="14" cy="8" r="1"/></svg>`;
const aiIcon = 'AI';

const MilkdownEditor: React.FC<MilkdownEditorProps> = ({ value = '', onChange, readonly = false }) => {
    const editorRef = useRef<HTMLDivElement>(null);
    const crepeRef = useRef<Crepe | null>(null);
    const isInitialized = useRef(false);
    const latestValue = useRef(value);
    const onChangeRef = useRef(onChange);

    // 源码模式状态
    const [showSource, setShowSource] = useState(false);
    const sourceTextareaRef = useRef<HTMLTextAreaElement>(null);
    const sourceUpdatingRef = useRef(false); // 防止循环更新
    const syncTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // AI 帮改状态
    const [aiOpen, setAiOpen] = useState(false);
    const [aiInstruction, setAiInstruction] = useState('');
    const [aiLoading, setAiLoading] = useState(false);
    const [aiMessages, setAiMessages] = useState<{ role: string; content: string }[]>([]);
    const selectedTextRef = useRef('');
    const selectionRangeRef = useRef<{ from: number; to: number } | null>(null);

    useEffect(() => { latestValue.current = value; }, [value]);
    useEffect(() => { onChangeRef.current = onChange; }, [onChange]);

    useEffect(() => {
        if (!editorRef.current || isInitialized.current) return;
        isInitialized.current = true;

        const initEditor = async () => {
            try {
                const crepe = new Crepe({
                    root: editorRef.current!,
                    defaultValue: latestValue.current,
                    features: {
                        [CrepeFeature.BlockEdit]: true,
                        [CrepeFeature.Toolbar]: true,
                        [CrepeFeature.Placeholder]: true,
                        [CrepeFeature.LinkTooltip]: true,
                        [CrepeFeature.ListItem]: true,
                        [CrepeFeature.Table]: true,
                        [CrepeFeature.CodeMirror]: true,
                        [CrepeFeature.ImageBlock]: true,
                        [CrepeFeature.Cursor]: true,
                        [CrepeFeature.Latex]: false,
                    },
                    featureConfigs: {
                        [CrepeFeature.Placeholder]: {
                            text: '输入 / 选择样式，或直接开始写作...',
                            mode: 'doc',
                        },
                        [CrepeFeature.Toolbar]: {
                            buildToolbar: (builder: any) => {
                                builder.addGroup('ai', 'AI').addItem('ai-edit', {
                                    icon: aiIcon,
                                    active: () => false,
                                    onRun: (ctx: any) => {
                                        try {
                                            const view = ctx.get(editorViewCtx);
                                            const { from, to } = view.state.selection;
                                            const text = view.state.doc.textBetween(from, to, '\n');
                                            if (text.trim()) {
                                                selectedTextRef.current = text;
                                                selectionRangeRef.current = { from, to };
                                                setAiMessages([]);
                                                setAiInstruction('');
                                                setAiOpen(true);
                                            } else {
                                                message.warning('请先选中要修改的文本');
                                            }
                                        } catch (e) {
                                            console.error('AI toolbar error:', e);
                                        }
                                    },
                                });
                            },
                        },
                        [CrepeFeature.ImageBlock]: {
                            onUpload: async (file: File) => {
                                const formData = new FormData();
                                formData.append('file', file);
                                const res: any = await api.post(KB_ROUTES.uploadImage, formData, {
                                    headers: { 'Content-Type': 'multipart/form-data' },
                                });
                                return res.data?.url || '';
                            },
                        },
                    },
                });

                // 监听内容变更
                crepe.on((listener) => {
                    listener.markdownUpdated((_ctx, markdown) => {
                        onChangeRef.current?.(markdown);
                        // 同步到源码面板：仅当非源码面板触发、且 textarea 没有焦点时才写入
                        if (
                            !sourceUpdatingRef.current &&
                            sourceTextareaRef.current &&
                            document.activeElement !== sourceTextareaRef.current
                        ) {
                            sourceTextareaRef.current.value = markdown;
                        }
                    });
                });

                if (readonly) {
                    crepe.setReadonly(true);
                }

                await crepe.create();
                crepeRef.current = crepe;
            } catch (e) {
                console.error('Crepe editor init error:', e);
            }
        };

        initEditor();

        return () => {
            if (syncTimerRef.current) clearTimeout(syncTimerRef.current);
            crepeRef.current?.destroy();
            crepeRef.current = null;
            isInitialized.current = false;
        };
    }, []);

    // AI 帮改处理
    const handleAiEdit = useCallback(async () => {
        if (!aiInstruction.trim()) return;
        setAiLoading(true);
        try {
            const payload: any = {
                instruction: aiInstruction,
                markdown: selectedTextRef.current,
            };

            const res: any = await api.post('/gpt/markdown_edit', payload);
            const info = res?.data ?? res;
            const taskId = (info?.task_id ?? info?.taskId) as number | undefined;
            if (!taskId) {
                throw new Error(info?.error_message || '创建任务失败');
            }

            setAiMessages(prev => ([...prev, { role: 'user', content: aiInstruction }]));

            const interval = 2000;
            let deadlineAt = Date.now() + 4 * 60 * 1000;
            let lastPartial = '';

            while (Date.now() < deadlineAt) {
                const st: any = await api.post(`/task/ai/generate-status/${taskId}`, {});
                const sInfo = st?.data ?? st;
                const status = sInfo?.status as string | undefined;

                const totalSteps = Number(sInfo?.result?.total_steps ?? sInfo?.result?.totalSteps ?? 0);
                const currentStep = Number(sInfo?.result?.current_step ?? sInfo?.result?.currentStep ?? 0);
                const percent = Number(sInfo?.result?.percent ?? 0);
                if (totalSteps > 0) {
                    const expectedMs = Math.min(20 * 60 * 1000, Math.max(4 * 60 * 1000, totalSteps * 60 * 1000));
                    deadlineAt = Math.max(deadlineAt, Date.now() + expectedMs);
                }

                const partialText = (sInfo?.result?.partial_text ?? sInfo?.result?.partialText ?? '') as string;
                if (status === 'processing' && partialText && partialText !== lastPartial) {
                    lastPartial = partialText;
                    const progressLine = totalSteps > 0
                        ? `生成中：${currentStep}/${totalSteps}（${Number.isFinite(percent) ? percent : 0}%）`
                        : '生成中...';
                    setAiMessages(prev => {
                        const next = [...prev];
                        const content = `${progressLine}\n\n${partialText}`;
                        const last = next[next.length - 1];
                        if (last && last.role === 'assistant') {
                            next[next.length - 1] = { role: 'assistant', content };
                        } else {
                            next.push({ role: 'assistant', content });
                        }
                        return next;
                    });
                }

                if (status === 'success') {
                    const resultText = sInfo?.result?.result_text ?? '';
                    const newMarkdown = (sInfo?.result?.markdown ?? '').trim();

                    setAiMessages(prev => ([...prev, { role: 'assistant', content: resultText || '已完成修改' }]));

                    // 回写到编辑器：将 Markdown 解析为 ProseMirror 节点后替换
                    if (crepeRef.current && selectionRangeRef.current && newMarkdown) {
                        try {
                            (crepeRef.current as any).editor.action((ctx: any) => {
                                const view = ctx.get(editorViewCtx);
                                const parser = ctx.get(parserCtx);
                                const { from, to } = selectionRangeRef.current!;
                                // 将 Markdown 解析为 ProseMirror 文档
                                const doc = parser(newMarkdown);
                                if (doc && doc.content) {
                                    const tr = view.state.tr.replaceWith(from, to, doc.content);
                                    view.dispatch(tr);
                                }
                            });
                        } catch (e) {
                            console.error('AI 回写失败:', e);
                        }
                    }

                    setAiInstruction('');
                    message.success('AI已修改');
                    break;
                }

                if (status === 'failed' || status === 'error') {
                    const errMsg = sInfo?.result?.error_message || '任务失败';
                    setAiMessages(prev => ([...prev, { role: 'assistant', content: `❌ ${errMsg}` }]));
                    message.error(errMsg);
                    break;
                }

                await new Promise(r => setTimeout(r, interval));
            }
        } catch (e: any) {
            message.error(e.message || 'AI修改失败');
        } finally {
            setAiLoading(false);
        }
    }, [aiInstruction]);

    // 绑定原生 input 事件监听器（完全绕过 React，避免光标跳转）
    useEffect(() => {
        if (!showSource) return;
        // 等待 textarea 挂载
        const timer = requestAnimationFrame(() => {
            const textarea = sourceTextareaRef.current;
            if (!textarea) return;

            const handler = () => {
                if (syncTimerRef.current) {
                    clearTimeout(syncTimerRef.current);
                }
                sourceUpdatingRef.current = true;
                syncTimerRef.current = setTimeout(() => {
                    const newMd = textarea.value;
                    onChangeRef.current?.(newMd);
                    if (crepeRef.current) {
                        try {
                            (crepeRef.current as any).editor.action((ctx: any) => {
                                const view = ctx.get(editorViewCtx);
                                const parser = ctx.get(parserCtx);
                                const doc = parser(newMd);
                                if (doc) {
                                    const tr = view.state.tr.replaceWith(0, view.state.doc.content.size, doc.content);
                                    view.dispatch(tr);
                                }
                            });
                        } catch (e) {
                            console.error('源码同步失败:', e);
                        }
                    }
                    setTimeout(() => { sourceUpdatingRef.current = false; }, 50);
                }, 500);
            };

            textarea.addEventListener('input', handler);
            // 初始化内容
            if (crepeRef.current) {
                textarea.value = crepeRef.current.getMarkdown();
            }

            (textarea as any)._cleanupHandler = () => {
                textarea.removeEventListener('input', handler);
            };
        });

        return () => {
            cancelAnimationFrame(timer);
            const textarea = sourceTextareaRef.current;
            if (textarea && (textarea as any)._cleanupHandler) {
                (textarea as any)._cleanupHandler();
                delete (textarea as any)._cleanupHandler;
            }
            if (syncTimerRef.current) {
                clearTimeout(syncTimerRef.current);
                syncTimerRef.current = null;
            }
            sourceUpdatingRef.current = false;
        };
    }, [showSource]);

    // 切换源码模式
    const toggleSource = useCallback(() => {
        setShowSource(prev => !prev);
    }, []);

    // 点击编辑器外部白色区域时聚焦
    const handleWrapperClick = useCallback((e: React.MouseEvent) => {
        if (e.target === e.currentTarget && editorRef.current) {
            const prosemirror = editorRef.current.querySelector('.ProseMirror') as HTMLElement;
            prosemirror?.focus();
        }
    }, []);

    const runCommand = useCallback((cmdKey: any, payload?: any) => {
        if (!crepeRef.current) return;
        try {
            (crepeRef.current as any).editor.action((ctx: any) => {
                const commands = ctx.get(commandsCtx);
                commands.call(cmdKey, payload);
            });
        } catch (e) {
            console.error('Command error:', e);
        }
    }, []);

    const toolbarButtons = useMemo(() => [
        { key: 'bold', label: 'B', title: '加粗', style: { fontWeight: 800 }, cmd: toggleStrongCommand.key },
        { key: 'italic', label: 'I', title: '斜体', style: { fontStyle: 'italic' as const }, cmd: toggleEmphasisCommand.key },
        { key: 'strike', label: 'S', title: '删除线', style: { textDecoration: 'line-through' }, cmd: toggleStrikethroughCommand.key },
        { key: 'code', label: '</>', title: '行内代码', style: { fontFamily: 'monospace' }, cmd: toggleInlineCodeCommand.key },
        { key: 'sep1', sep: true },
        { key: 'h1', label: 'H1', title: '标题1', cmd: wrapInHeadingCommand.key, payload: 1 },
        { key: 'h2', label: 'H2', title: '标题2', cmd: wrapInHeadingCommand.key, payload: 2 },
        { key: 'h3', label: 'H3', title: '标题3', cmd: wrapInHeadingCommand.key, payload: 3 },
        { key: 'sep2', sep: true },
        { key: 'ul', label: '•', title: '无序列表', cmd: wrapInBulletListCommand.key },
        { key: 'ol', label: '1.', title: '有序列表', cmd: wrapInOrderedListCommand.key },
        { key: 'quote', label: '❝', title: '引用', cmd: wrapInBlockquoteCommand.key },
        { key: 'hr', label: '—', title: '分割线', cmd: insertHrCommand.key },
        { key: 'sep3', sep: true },
        { key: 'link', label: '🔗', title: '链接', cmd: toggleLinkCommand.key },
    ], []);

    return (
        <>
            {/* 顶部工具条：格式按钮 + 源码切换 */}
            <div style={{
                display: 'flex',
                alignItems: 'center',
                padding: '4px 8px',
                borderBottom: '1px solid #f0f0f0',
                background: '#fafafa',
                borderRadius: '6px 6px 0 0',
                gap: 2,
                flexWrap: 'wrap',
            }}>
                {toolbarButtons.map((btn) =>
                    btn.sep ? (
                        <div key={btn.key} style={{ width: 1, height: 20, background: '#e0e0e0', margin: '0 4px' }} />
                    ) : (
                        <Tooltip key={btn.key} title={btn.title}>
                            <button
                                style={{
                                    width: 30,
                                    height: 30,
                                    border: 'none',
                                    background: 'transparent',
                                    borderRadius: 4,
                                    cursor: 'pointer',
                                    fontSize: 13,
                                    fontWeight: 600,
                                    color: '#475569',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    ...(btn.style || {}),
                                }}
                                onMouseDown={(e) => {
                                    e.preventDefault();
                                    runCommand(btn.cmd, btn.payload);
                                }}
                            >
                                {btn.label}
                            </button>
                        </Tooltip>
                    )
                )}

                <div style={{ flex: 1 }} />

                <Tooltip title={showSource ? '关闭源码' : '查看/编辑 Markdown 源码'}>
                    <Button
                        type={showSource ? 'primary' : 'text'}
                        size="small"
                        onClick={toggleSource}
                        style={{ fontFamily: 'monospace', fontWeight: 600, fontSize: 13 }}
                    >
                        {'</>'}
                    </Button>
                </Tooltip>
            </div>

            {/* 编辑器主体：左侧渲染 + 右侧源码 */}
            <div style={{ display: 'flex', minHeight: 500 }}>
                {/* 左侧 Milkdown 编辑器 */}
                <div
                    ref={editorRef}
                    onClick={handleWrapperClick}
                    style={{
                        flex: showSource ? 1 : '1 1 100%',
                        minHeight: 500,
                        cursor: 'text',
                        overflow: 'auto',
                        borderRight: showSource ? '1px solid #e8e8e8' : 'none',
                    }}
                    className="milkdown-editor-wrapper"
                />

                {/* 右侧源码编辑器 */}
                {showSource && (
                    <div style={{ flex: 1, minHeight: 500, display: 'flex', flexDirection: 'column' }}>
                        <div style={{
                            padding: '6px 12px',
                            background: '#f5f5f5',
                            borderBottom: '1px solid #e8e8e8',
                            fontSize: 12,
                            color: '#999',
                            fontFamily: 'monospace',
                        }}>
                            Markdown 源码
                        </div>
                        <textarea
                            ref={sourceTextareaRef}
                            className="milkdown-source-textarea"
                            spellCheck={false}
                            placeholder="在此粘贴或编辑 Markdown 源码..."
                        />
                    </div>
                )}
            </div>

            {/* AI 帮改弹窗 */}
            <Modal
                title="✨ AI 帮改"
                open={aiOpen}
                onCancel={() => { setAiOpen(false); }}
                footer={
                    <div style={{ display: 'flex', justifyContent: 'flex-end', alignItems: 'center', gap: 8 }}>
                        <Button onClick={() => setAiOpen(false)}>关闭</Button>
                        <Button
                            type="primary"
                            loading={aiLoading}
                            onClick={handleAiEdit}
                            disabled={!aiInstruction.trim()}
                            style={{
                                background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                                border: 'none'
                            }}
                        >
                            开始修改
                        </Button>
                    </div>
                }
                destroyOnClose
            >
                <div style={{ marginBottom: 12 }}>
                    <Typography.Text strong>选中文本</Typography.Text>
                    <Divider style={{ margin: '8px 0' }} />
                    <div style={{
                        background: '#f5f5f5', borderRadius: 6, padding: '8px 12px',
                        maxHeight: 120, overflow: 'auto', fontSize: 13, color: '#666',
                        whiteSpace: 'pre-wrap', wordBreak: 'break-word',
                    }}>
                        {selectedTextRef.current || '无选中文本'}
                    </div>
                </div>
                <div style={{ marginBottom: 12 }}>
                    <Typography.Text strong>编辑需求</Typography.Text>
                    <Divider style={{ margin: '8px 0' }} />
                    <Input.TextArea
                        rows={3}
                        placeholder="例如：优化语句表达、语气更正式、精简内容..."
                        value={aiInstruction}
                        onChange={(e) => setAiInstruction(e.target.value)}
                    />
                </div>
                {aiMessages.length > 0 && (
                    <div>
                        <Typography.Text strong>对话</Typography.Text>
                        <Divider style={{ margin: '8px 0' }} />
                        <div style={{ maxHeight: 260, overflow: 'auto' }}>
                            {aiMessages.map((m, i) => (
                                <div key={i} style={{
                                    display: 'flex',
                                    justifyContent: m.role === 'user' ? 'flex-end' : 'flex-start',
                                    marginBottom: 8,
                                }}>
                                    <div style={{
                                        background: m.role === 'user' ? '#1677ff' : '#f5f5f5',
                                        color: m.role === 'user' ? '#fff' : '#333',
                                        borderRadius: 6,
                                        padding: '6px 8px',
                                        maxWidth: '80%',
                                        whiteSpace: 'pre-wrap',
                                        wordBreak: 'break-word',
                                        fontSize: 13,
                                    }}>
                                        {m.content}
                                    </div>
                                </div>
                            ))}
                            {aiLoading && (
                                <div style={{ textAlign: 'center', padding: 8 }}>
                                    <Spin size="small" />
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </Modal>
        </>
    );
};

export default MilkdownEditor;
