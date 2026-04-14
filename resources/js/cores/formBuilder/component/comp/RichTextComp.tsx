import { Form, Modal, Input, Button, Space, Typography, Divider, message, InputNumber } from 'antd';
import { FormItemProps } from 'antd';
import { middleOneType } from "../MiddleOne";
import React, { useState, useRef, useCallback, useEffect, useMemo } from 'react';
import ReactQuill, { Quill } from 'react-quill';
import 'react-quill/dist/quill.snow.css';
import ImageResize from 'quill-image-resize';
import api from '../../../../util/Service';

// 注册图片缩放模块
Quill.register('modules/imageResize', ImageResize);

const RichTextComp = {
    typeName: '富文本',
    component: ({ child, form }: middleOneType) => {
        const quillRef = useRef<ReactQuill>(null);
        const [aiOpen, setAiOpen] = useState(false);
        const [aiLoading, setAiLoading] = useState(false);
        const [aiInstruction, setAiInstruction] = useState('');
        const [aiTargetLength, setAiTargetLength] = useState<number | null>(null);
        const [aiMessages, setAiMessages] = useState<Array<{ role: 'user' | 'assistant'; content: string }>>([]);

        const formOneProp: FormItemProps = {
            label: child.label,
            name: child.field,
            rules: child.rules,
            required: child.rules?.some((rule: any) => rule.required)
        };

        const handleChange = (content: string) => {
            if (form) {
                form.setFieldsValue({ [child.field]: content });
            }
        };

        const imageHandler = useCallback(() => {
            const input = document.createElement('input');
            input.setAttribute('type', 'file');
            input.setAttribute('accept', 'image/*');
            input.click();

            input.onchange = async () => {
                if (!input.files || !input.files[0]) return;

                const file = input.files[0];
                const formData = new FormData();
                formData.append('file', file);

                try {
                    const res: any = await api.post('/media/upload', formData);
                    const url = res?.data?.url || res?.url;

                    if (!url) {
                        throw new Error('上传失败：未返回URL');
                    }

                    const quill = quillRef.current?.getEditor();
                    const sel = quill?.getSelection();
                    const index = typeof sel === 'object' && sel ? (sel as any).index : undefined;
                    if (index !== undefined) {
                        // 插入图片并添加 style 属性
                        quill?.insertEmbed(index, 'image', url);
                        // 获取刚插入的图片元素并设置样式
                        const img = quill?.root.querySelector(`img[src="${url}"]`);
                        if (img) {
                            img.style.maxWidth = '100%';
                            img.style.height = 'auto';
                        }
                        quill?.setSelection({ index: index + 1, length: 0 } as any);
                    }
                    message.success('图片上传成功');
                } catch (error) {
                    console.error('上传图片失败:', error);
                    message.error('图片上传失败');
                }
            };
        }, []);

        const modules = {
            toolbar: {
                container: [
                    [{ 'font': [] }, { 'size': [] }],
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'script': 'sub'}, { 'script': 'super' }],
                    [{ 'align': [] }, { 'direction': 'rtl' }],
                    [{ 'indent': '-1'}, { 'indent': '+1' }],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link', 'image', 'video'],
                    ['clean']
                ],
                handlers: {
                    image: imageHandler
                }
            },
            clipboard: {
                matchVisual: false
            },
            imageResize: {
                parchment: Quill.import('parchment'),
                modules: ['Resize', 'DisplaySize', 'Toolbar']
            }
        };

        const formats = [
            'font', 'size',
            'header',
            'bold', 'italic', 'underline', 'strike',
            'color', 'background',
            'script',
            'align', 'direction',
            'indent',
            'blockquote', 'code-block',
            'list', 'bullet',
            'link', 'image', 'video'
        ];

        // 同步表单值到编辑器（受控组件）。当 form 未传入时，使用初始值回退。
        const watchedValue = form ? Form.useWatch(child.field, form) : (child.curValue || '');

        const plainTextLength = (() => {
            const html = (watchedValue ?? '') as string;
            const text = html
                .replace(/<[^>]*>/g, '')
                .replace(/&nbsp;/g, ' ')
                .replace(/&amp;/g, '&')
                .replace(/&lt;/g, '<')
                .replace(/&gt;/g, '>')
                .trim();
            return text.length;
        })();

        const TOTAL_RTE_HEIGHT = 500; // 组件整体目标高度（按钮行 + 工具栏 + 编辑区）
        const HEADER_ROW_H = 36;      // 顶部按钮行预估高度
        const [toolbarH, setToolbarH] = useState<number>(42);
        const wrapperClass = useMemo(() => `rte-${String(child.field || 'editor')}`, [child.field]);

        useEffect(() => {
            const wrapper = document.querySelector(`.${wrapperClass}`) as HTMLElement | null;
            const tb = wrapper?.querySelector('.ql-toolbar') as HTMLElement | null;
            if (tb && tb.offsetHeight) {
                setToolbarH(tb.offsetHeight);
            }
        }, [wrapperClass, aiOpen]);

        const containerHeight = Math.max(120, TOTAL_RTE_HEIGHT - HEADER_ROW_H - toolbarH);

        const handleAiEdit = useCallback(async () => {
            try {
                setAiLoading(true);
                const currentHtml = (watchedValue ?? '') as string;
                const payload: any = {
                    instruction: aiInstruction,
                    html: currentHtml,
                };
                // 用户填写优先生效；否则长文才传 target_length，用于触发后端分段 step 生成
                if (aiTargetLength && aiTargetLength > 0) {
                    payload.target_length = aiTargetLength;
                } else if (plainTextLength > 1200) {
                    payload.target_length = plainTextLength;
                }

                const res: any = await api.post('/gpt/rich_text_edit', payload);
                const info = res?.data ?? res;
                const taskId = (info?.task_id ?? info?.taskId) as number | undefined;
                if (!taskId) {
                    throw new Error(info?.error_message || '创建任务失败');
                }

                setAiMessages(prev => ([...prev, { role: 'user', content: aiInstruction }]));

                const interval = 2000;
                // 默认最多等待 4 分钟；若检测到分段 step，则按步数动态延长
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
                        // 每步按 60s 估算，给足余量
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
                        const newHtml = sInfo?.result?.html ?? currentHtml;
                        setAiMessages(prev => ([...prev, { role: 'assistant', content: resultText || '已完成修改' }]));
                        if (form) {
                            form.setFieldsValue({ [child.field]: newHtml });
                        }
                        setAiInstruction('');
                        setAiTargetLength(null);
                        message.success('AI已修改');
                        return;
                    }

                    if (status === 'failed') {
                        throw new Error(sInfo?.error_message || 'AI修改失败');
                    }

                    await new Promise((resolve) => setTimeout(resolve, interval));
                }

                throw new Error('AI修改超时，请稍后在任务中心查看结果');
            } catch (e: any) {
                message.error(e?.message || 'AI修改失败');
            } finally {
                setAiLoading(false);
            }
        }, [aiInstruction, aiTargetLength, plainTextLength, watchedValue, form, child.field]);

        return (
            <>
                <Form.Item
                    {...formOneProp}
                    initialValue={child.curValue || ''}
                >
                    <div>
                        {/* 动态控制容器高度，确保总高度约 300 */}
                        <style>{`
                            .${wrapperClass} .ql-container{height:${containerHeight}px}
                            .${wrapperClass}-wrapper { position: relative; }
                            .${wrapperClass}-ai-btn {
                                position: absolute;
                                bottom: 8px;
                                right: 8px;
                                z-index: 10;
                                display: flex;
                                gap: 8px;
                                align-items: center;
                            }
                            .${wrapperClass} img {
                                max-width: 100%;
                                height: auto;
                                cursor: pointer;
                            }
                            .${wrapperClass} .ql-resize-handle {
                                position: absolute;
                                width: 10px;
                                height: 10px;
                                background: #fff;
                                border: 1px solid #ccc;
                                border-radius: 50%;
                                z-index: 1000;
                            }
                            .${wrapperClass} .ql-resize-handle.tl { top: -5px; left: -5px; cursor: nwse-resize; }
                            .${wrapperClass} .ql-resize-handle.tr { top: -5px; right: -5px; cursor: nesw-resize; }
                            .${wrapperClass} .ql-resize-handle.bl { bottom: -5px; left: -5px; cursor: nesw-resize; }
                            .${wrapperClass} .ql-resize-handle.br { bottom: -5px; right: -5px; cursor: nwse-resize; }
                        `}</style>
                        <div className={`${wrapperClass}-wrapper`}>
                            <ReactQuill
                                ref={quillRef}
                                theme="snow"
                                value={watchedValue ?? ''}
                                onChange={handleChange}
                                modules={modules}
                                formats={formats}
                                placeholder={'请输入内容...'}
                                className={wrapperClass}
                            />
                            {/* AI 按钮和字数统计放在右下角 */}
                            <div className={`${wrapperClass}-ai-btn`}>
                                <div style={{ 
                                    color: '#999', 
                                    fontSize: 12, 
                                    background: 'rgba(255,255,255,0.9)', 
                                    padding: '2px 6px',
                                    borderRadius: 4
                                }}>
                                    {plainTextLength} 字
                                </div>
                                <Button 
                                    size="small" 
                                    type="primary"
                                    icon={<span style={{ fontSize: 12 }}>✨</span>}
                                    onClick={() => { setAiMessages([]); setAiOpen(true); }}
                                    style={{ 
                                        background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                                        border: 'none'
                                    }}
                                >
                                    AI修改
                                </Button>
                            </div>
                        </div>
                    </div>
                </Form.Item>

                <Modal
                    title="AI 修改富文本"
                    open={aiOpen}
                    onCancel={() => { setAiOpen(false); setAiTargetLength(null); }}
                    footer={
                        <div style={{ display: 'flex', justifyContent: 'flex-end', alignItems: 'center', gap: 8 }}>
                            <Button onClick={() => setAiOpen(false)}>关闭</Button>
                            <Button type="primary" loading={aiLoading} onClick={handleAiEdit} disabled={!aiInstruction.trim()}>
                                开始修改
                            </Button>
                        </div>
                    }
                >
                    <div style={{ marginBottom: 12 }}>
                        <Typography.Text strong>预期字数（可选）</Typography.Text>
                        <Divider style={{ margin: '8px 0' }} />
                        <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
                            <InputNumber
                                style={{ width: 180 }}
                                min={300}
                                max={20000}
                                step={100}
                                value={aiTargetLength}
                                onChange={(v) => setAiTargetLength(typeof v === 'number' ? v : null)}
                                placeholder="例如：3000"
                            />
                            <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                                不填写则按模型默认输出；长文会自动按当前字数估算并分段生成。
                            </Typography.Text>
                        </div>
                    </div>
                    <div style={{ marginBottom: 12 }}>
                        <Typography.Text strong>编辑需求</Typography.Text>
                        <Divider style={{ margin: '8px 0' }} />
                        <Input.TextArea
                            rows={4}
                            placeholder="例如：优化结构，语气更正式，保留关键要点"
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
                                    <div key={i} style={{ display: 'flex', justifyContent: m.role === 'user' ? 'flex-end' : 'flex-start', marginBottom: 8 }}>
                                        <div style={{
                                            background: m.role === 'user' ? '#1677ff' : '#f5f5f5',
                                            color: m.role === 'user' ? '#fff' : '#333',
                                            borderRadius: 6,
                                            padding: '6px 8px',
                                            maxWidth: '80%',
                                            whiteSpace: 'pre-wrap',
                                            wordBreak: 'break-word'
                                        }}>
                                            {m.content}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </Modal>
            </>
        );
    },
    formatValue: (value: any) => {
        return value || '';
    }
};

export default RichTextComp;