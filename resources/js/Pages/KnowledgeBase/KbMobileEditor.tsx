import React, { useEffect, useState, useRef, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import { Crepe, CrepeFeature } from '@milkdown/crepe';
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

interface KbMobileEditorProps {
    articleId?: number;
    token: string;
    defaultCategoryId?: number | null;
}

const apiBase = window.location.origin + '/client/kb';

async function apiFetch(url: string, token: string, options: RequestInit = {}) {
    const res = await fetch(url, {
        ...options,
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(options.headers || {}),
        },
    });
    return res.json();
}

export default function KbMobileEditor({ articleId, token, defaultCategoryId }: KbMobileEditorProps) {
    const [loading, setLoading] = useState(!!articleId);
    const [saving, setSaving] = useState(false);
    const [title, setTitle] = useState('');
    const [content, setContent] = useState('');
    const [categoryId, setCategoryId] = useState<number | null>(defaultCategoryId ?? null);
    const [status, setStatus] = useState('private');
    const [tags, setTags] = useState<string[]>([]);
    const [summary, setSummary] = useState('');
    const [categories, setCategories] = useState<{ id: number; title: string; depth: number }[]>([]);
    const [currentId, setCurrentId] = useState<number | undefined>(articleId);
    const [toast, setToast] = useState('');
    const [showSettings, setShowSettings] = useState(false);
    const [tagInput, setTagInput] = useState('');
    const [dataReady, setDataReady] = useState(!articleId);

    const editorRef = useRef<HTMLDivElement>(null);
    const crepeRef = useRef<Crepe | null>(null);
    const contentRef = useRef(content);

    const showToast = (msg: string) => {
        setToast(msg);
        setTimeout(() => setToast(''), 2000);
    };

    // 加载分类
    useEffect(() => {
        apiFetch(`${apiBase}/categories/tree`, token)
            .then((res: any) => {
                const tree = res.data || [];
                setCategories(flattenTree(tree));
            })
            .catch(() => {});
    }, [token]);

    // 加载文章
    useEffect(() => {
        if (!articleId) return;
        apiFetch(`${apiBase}/articles/detail/${articleId}`, token)
            .then((res: any) => {
                const d = res.data || {};
                setTitle(d.title || '');
                setContent(d.content || '');
                contentRef.current = d.content || '';
                setCategoryId(d.category_id);
                setStatus(d.status || 'private');
                setTags(d.tags || []);
                setSummary(d.summary || '');
                setCurrentId(d.id);
                setDataReady(true);
            })
            .catch(() => showToast('加载文章失败'))
            .finally(() => setLoading(false));
    }, [articleId, token]);

    // 初始化 Milkdown 编辑器
    useEffect(() => {
        if (!dataReady || !editorRef.current || crepeRef.current) return;

        const init = async () => {
            const crepe = new Crepe({
                root: editorRef.current!,
                defaultValue: contentRef.current || '',
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
                        text: '开始写作...',
                        mode: 'doc',
                    },
                    [CrepeFeature.ImageBlock]: {
                        onUpload: async (file: File) => {
                            try {
                                const formData = new FormData();
                                formData.append('file', file);
                                const res = await fetch(`${apiBase}/upload-image`, {
                                    method: 'POST',
                                    headers: { 'Authorization': `Bearer ${token}` },
                                    body: formData,
                                });
                                const data = await res.json();
                                return data.data?.url || '';
                            } catch {
                                return '';
                            }
                        },
                    },
                },
            });

            crepe.on((listener) => {
                listener.markdownUpdated((_ctx, markdown) => {
                    contentRef.current = markdown;
                    setContent(markdown);
                });
            });

            await crepe.create();
            crepeRef.current = crepe;
        };

        init();

        return () => {
            crepeRef.current?.destroy();
            crepeRef.current = null;
        };
    }, [dataReady, token]);

    // 保存
    const handleSave = async () => {
        if (!title.trim()) {
            showToast('请输入标题');
            return;
        }
        setSaving(true);
        try {
            const payload = {
                title: title.trim(),
                content: contentRef.current,
                category_id: categoryId,
                tags,
                summary: summary.trim(),
                status,
            };

            if (currentId) {
                await apiFetch(`${apiBase}/articles/update/${currentId}`, token, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                showToast('保存成功');
            } else {
                const res = await apiFetch(`${apiBase}/articles/create`, token, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                const newId = res.data?.id;
                if (newId) setCurrentId(newId);
                showToast('创建成功');
            }
        } catch {
            showToast('保存失败');
        } finally {
            setSaving(false);
        }
    };

    const handleAddTag = () => {
        const t = tagInput.trim();
        if (t && !tags.includes(t)) setTags([...tags, t]);
        setTagInput('');
    };

    return (
        <>
            <Head title={currentId ? `编辑 - ${title}` : '新建文章'} />
            <div style={styles.page}>
                {/* 功能栏（保存/设置） */}
                <div style={styles.topBar}>
                    <div />
                    <div style={styles.topActions}>
                        <button style={styles.settingsBtn} onClick={() => setShowSettings(true)}>
                            ⚙
                        </button>
                        <button
                            style={{
                                ...styles.saveBtn,
                                opacity: saving ? 0.6 : 1,
                            }}
                            onClick={handleSave}
                            disabled={saving}
                        >
                            {saving ? '保存中...' : '保存'}
                        </button>
                    </div>
                </div>

                {/* 标题输入 */}
                <input
                    style={styles.titleInput}
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    placeholder="请输入文章标题..."
                />

                {/* 编辑器 */}
                {loading ? (
                    <div style={styles.loading}>加载中...</div>
                ) : (
                    <div ref={editorRef} style={styles.editor} />
                )}

                {/* Toast */}
                {toast && <div style={styles.toast}>{toast}</div>}

                {/* 设置面板 */}
                {showSettings && (
                    <div style={styles.overlay} onClick={() => setShowSettings(false)}>
                        <div style={styles.settingsPanel} onClick={(e) => e.stopPropagation()}>
                            <div style={styles.settingsPanelHeader}>
                                <span style={{ fontWeight: 600, fontSize: 16 }}>文章设置</span>
                                <button style={styles.closeBtn} onClick={() => setShowSettings(false)}>✕</button>
                            </div>

                            <div style={styles.formGroup}>
                                <label style={styles.label}>分类</label>
                                <select
                                    style={styles.select}
                                    value={categoryId ?? ''}
                                    onChange={(e) => setCategoryId(e.target.value ? Number(e.target.value) : null)}
                                >
                                    <option value="">未分类</option>
                                    {categories.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {'　'.repeat(c.depth)}{c.title}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div style={styles.formGroup}>
                                <label style={styles.label}>摘要</label>
                                <textarea
                                    style={styles.textarea}
                                    value={summary}
                                    onChange={(e) => setSummary(e.target.value)}
                                    placeholder="文章摘要（可选）"
                                    rows={3}
                                />
                            </div>

                            <div style={styles.formGroup}>
                                <label style={styles.label}>标签</label>
                                <div style={styles.tagsWrap}>
                                    {tags.map((t) => (
                                        <span key={t} style={styles.tagItem}>
                                            {t}
                                            <span style={styles.tagRemove} onClick={() => setTags(tags.filter(x => x !== t))}>✕</span>
                                        </span>
                                    ))}
                                </div>
                                <div style={{ display: 'flex', gap: 8, marginTop: 6 }}>
                                    <input
                                        style={{ ...styles.input, flex: 1 }}
                                        value={tagInput}
                                        onChange={(e) => setTagInput(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && handleAddTag()}
                                        placeholder="输入标签按回车"
                                    />
                                    <button style={styles.addTagBtn} onClick={handleAddTag}>添加</button>
                                </div>
                            </div>

                            <div style={styles.formGroup}>
                                <label style={styles.label}>状态</label>
                                <select
                                    style={styles.select}
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value)}
                                >
                                    <option value="private">私有</option>
                                    <option value="public">公开</option>
                                </select>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <style>{`
                html, body { margin: 0; padding: 0; background: #fff; overflow-x: hidden; }
                .milkdown .ProseMirror {
                    padding: 16px !important;
                    min-height: 60vh;
                    font-size: 16px;
                    line-height: 1.8;
                    color: #334155;
                    outline: none;
                }
                .milkdown .ProseMirror img {
                    max-width: 100%;
                    height: auto;
                }
                .milkdown .ProseMirror pre {
                    overflow-x: auto;
                    font-size: 14px;
                }
                .milkdown-toolbar { z-index: 100; }
                .milkdown-block-handle { z-index: 100; }
            `}</style>
        </>
    );
}

function flattenTree(tree: any[], depth = 0): { id: number; title: string; depth: number }[] {
    const result: { id: number; title: string; depth: number }[] = [];
    tree.forEach((item: any) => {
        result.push({ id: item.id, title: item.title, depth });
        if (item.children?.length) {
            result.push(...flattenTree(item.children, depth + 1));
        }
    });
    return result;
}

const styles: Record<string, React.CSSProperties> = {
    page: {
        maxWidth: '100vw',
        minHeight: '100vh',
        overflowX: 'hidden',
        position: 'relative',
    },
    topBar: {
        position: 'sticky',
        top: 0,
        zIndex: 50,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: '10px 16px',
        background: '#fff',
        borderBottom: '1px solid #f1f5f9',
    },
    topTitle: {
        fontSize: 16,
        fontWeight: 600,
        color: '#1e293b',
    },
    topActions: {
        display: 'flex',
        alignItems: 'center',
        gap: 10,
    },
    settingsBtn: {
        width: 36,
        height: 36,
        borderRadius: 8,
        border: '1px solid #e2e8f0',
        background: '#fff',
        fontSize: 18,
        cursor: 'pointer',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
    },
    saveBtn: {
        padding: '8px 20px',
        borderRadius: 8,
        border: 'none',
        background: '#2563eb',
        color: '#fff',
        fontSize: 14,
        fontWeight: 600,
        cursor: 'pointer',
    },
    titleInput: {
        display: 'block',
        width: '100%',
        boxSizing: 'border-box',
        padding: '12px 16px',
        fontSize: 20,
        fontWeight: 700,
        border: 'none',
        borderBottom: '1px solid #f1f5f9',
        outline: 'none',
        color: '#1e293b',
    },
    editor: {
        width: '100%',
    },
    loading: {
        padding: 40,
        textAlign: 'center',
        color: '#94a3b8',
        fontSize: 14,
    },
    toast: {
        position: 'fixed',
        bottom: 80,
        left: '50%',
        transform: 'translateX(-50%)',
        padding: '10px 24px',
        borderRadius: 8,
        background: 'rgba(0,0,0,0.75)',
        color: '#fff',
        fontSize: 14,
        zIndex: 999,
    },
    overlay: {
        position: 'fixed',
        inset: 0,
        background: 'rgba(0,0,0,0.3)',
        zIndex: 200,
        display: 'flex',
        alignItems: 'flex-end',
    },
    settingsPanel: {
        width: '100%',
        maxHeight: '80vh',
        overflowY: 'auto',
        background: '#fff',
        borderRadius: '16px 16px 0 0',
        padding: '16px',
    },
    settingsPanelHeader: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: 16,
        paddingBottom: 12,
        borderBottom: '1px solid #f1f5f9',
    },
    closeBtn: {
        width: 32,
        height: 32,
        borderRadius: 8,
        border: 'none',
        background: '#f1f5f9',
        fontSize: 16,
        cursor: 'pointer',
    },
    formGroup: {
        marginBottom: 16,
    },
    label: {
        display: 'block',
        fontSize: 14,
        fontWeight: 600,
        color: '#475569',
        marginBottom: 6,
    },
    select: {
        width: '100%',
        padding: '10px 12px',
        borderRadius: 8,
        border: '1px solid #e2e8f0',
        fontSize: 14,
        color: '#334155',
        background: '#fff',
        outline: 'none',
    },
    input: {
        padding: '10px 12px',
        borderRadius: 8,
        border: '1px solid #e2e8f0',
        fontSize: 14,
        color: '#334155',
        outline: 'none',
    },
    textarea: {
        width: '100%',
        boxSizing: 'border-box',
        padding: '10px 12px',
        borderRadius: 8,
        border: '1px solid #e2e8f0',
        fontSize: 14,
        color: '#334155',
        outline: 'none',
        resize: 'vertical',
    },
    tagsWrap: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: 6,
    },
    tagItem: {
        display: 'inline-flex',
        alignItems: 'center',
        gap: 4,
        padding: '4px 10px',
        borderRadius: 4,
        background: '#eff6ff',
        color: '#2563eb',
        fontSize: 13,
    },
    tagRemove: {
        cursor: 'pointer',
        fontSize: 12,
        color: '#93c5fd',
    },
    addTagBtn: {
        padding: '10px 16px',
        borderRadius: 8,
        border: '1px solid #e2e8f0',
        background: '#fff',
        fontSize: 14,
        cursor: 'pointer',
        color: '#475569',
    },
};
