import React, { useEffect, useRef, useState, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import { Crepe, CrepeFeature } from '@milkdown/crepe';
import '@milkdown/kit/prose/view/style/prosemirror.css';
import '@milkdown/crepe/theme/common/reset.css';
import '@milkdown/crepe/theme/common/code-mirror.css';
import '@milkdown/crepe/theme/common/image-block.css';
import '@milkdown/crepe/theme/common/list-item.css';
import '@milkdown/crepe/theme/common/table.css';
import '@milkdown/crepe/theme/nord.css';

interface KbMobileViewProps {
    article: {
        id: number;
        title: string;
        content: string;
        summary: string;
        slug: string;
        status: string;
        tags: string[];
        view_count: number;
        created_at: string;
        updated_at: string;
    };
    token: string;
}

const apiBase = window.location.origin + '/client/kb';

export default function KbMobileView({ article, token }: KbMobileViewProps) {
    const editorRef = useRef<HTMLDivElement>(null);
    const crepeRef = useRef<Crepe | null>(null);
    const [toast, setToast] = useState('');
    const [fabOpen, setFabOpen] = useState(false);

    const showToast = (msg: string) => {
        setToast(msg);
        setTimeout(() => setToast(''), 2000);
    };

    useEffect(() => {
        if (!editorRef.current || crepeRef.current) return;

        const init = async () => {
            const crepe = new Crepe({
                root: editorRef.current!,
                defaultValue: article.content || '',
                features: {
                    [CrepeFeature.BlockEdit]: false,
                    [CrepeFeature.Toolbar]: false,
                    [CrepeFeature.Placeholder]: false,
                    [CrepeFeature.LinkTooltip]: false,
                    [CrepeFeature.ListItem]: true,
                    [CrepeFeature.Table]: true,
                    [CrepeFeature.CodeMirror]: true,
                    [CrepeFeature.ImageBlock]: false,
                    [CrepeFeature.Cursor]: false,
                    [CrepeFeature.Latex]: false,
                },
            });

            crepe.setReadonly(true);
            await crepe.create();
            crepeRef.current = crepe;
        };

        init();

        return () => {
            crepeRef.current?.destroy();
            crepeRef.current = null;
        };
    }, []);

    const handleEdit = () => {
        window.location.href = `/m/kb/edit/${article.id}`;
    };

    const handleShare = () => {
        if (!article.slug) {
            showToast('文章未公开，无法分享');
            return;
        }
        const shareUrl = `${window.location.origin}/kb/share/${article.slug}`;
        // fallback: 用隐藏 input 复制，兼容 WebView 环境
        try {
            const input = document.createElement('input');
            input.setAttribute('readonly', 'readonly');
            input.setAttribute('value', shareUrl);
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.appendChild(input);
            input.select();
            input.setSelectionRange(0, shareUrl.length);
            document.execCommand('copy');
            document.body.removeChild(input);
            showToast('分享链接已复制');
        } catch {
            showToast('复制失败');
        }
    };

    const handleDelete = async () => {
        if (!confirm('确定要删除这篇文章吗？删除后不可恢复。')) return;
        try {
            await fetch(`${apiBase}/articles/delete/${article.id}`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                },
            });
            showToast('删除成功');
            setTimeout(() => window.history.back(), 500);
        } catch {
            showToast('删除失败');
        }
    };

    return (
        <>
            <Head title={article.title} />
            <div style={styles.page}>
                {/* 标题区域 */}
                <div style={styles.header}>
                    <h1 style={styles.title}>{article.title}</h1>
                    <div style={styles.meta}>
                        <span style={{
                            padding: '2px 8px',
                            borderRadius: 4,
                            fontSize: 12,
                            background: article.status === 'public' ? '#f0fdf4' : '#f1f5f9',
                            color: article.status === 'public' ? '#22c55e' : '#94a3b8',
                            marginRight: 8,
                        }}>
                            {article.status === 'public' ? '公开' : '私有'}
                        </span>
                        <span>{article.updated_at}</span>
                        <span style={styles.dot}>·</span>
                        <span>阅读 {article.view_count}</span>
                    </div>
                    {article.tags?.length > 0 && (
                        <div style={styles.tags}>
                            {article.tags.map((tag, i) => (
                                <span key={i} style={styles.tag}>{tag}</span>
                            ))}
                        </div>
                    )}
                </div>

                {/* 内容区域 */}
                <div ref={editorRef} style={styles.content} />

                {/* 右下角浮动操作按钮 */}
                <div style={styles.fabContainer}>
                    {fabOpen && (
                        <div style={styles.fabMenu}>
                            {article.status === 'public' && article.slug && (
                                <button style={styles.fabItem} onClick={() => { handleShare(); setFabOpen(false); }}>
                                    🔗 分享
                                </button>
                            )}
                            <button style={styles.fabItem} onClick={() => { handleEdit(); setFabOpen(false); }}>
                                ✏️ 编辑
                            </button>
                            <button style={{ ...styles.fabItem, color: '#ef4444' }} onClick={() => { handleDelete(); setFabOpen(false); }}>
                                🗑️ 删除
                            </button>
                        </div>
                    )}
                    <button
                        style={{
                            ...styles.fabMain,
                            transform: fabOpen ? 'rotate(45deg)' : 'rotate(0deg)',
                        }}
                        onClick={() => setFabOpen(!fabOpen)}
                    >
                        +
                    </button>
                </div>

                {/* 遮罩 */}
                {fabOpen && <div style={styles.fabOverlay} onClick={() => setFabOpen(false)} />}

                {/* Toast */}
                {toast && <div style={styles.toast}>{toast}</div>}
            </div>

            <style>{`
                html, body { margin: 0; padding: 0; background: #fff; }
                .milkdown .ProseMirror {
                    padding: 0 !important;
                    font-size: 16px;
                    line-height: 1.8;
                    color: #334155;
                }
                .milkdown .ProseMirror img {
                    max-width: 100%;
                    height: auto;
                }
                .milkdown .ProseMirror pre {
                    overflow-x: auto;
                    font-size: 14px;
                }
                .milkdown .ProseMirror table {
                    display: block;
                    overflow-x: auto;
                    max-width: 100%;
                }
            `}</style>
        </>
    );
}

const styles: Record<string, React.CSSProperties> = {
    page: {
        maxWidth: '100vw',
        overflowX: 'hidden',
        position: 'relative',
        minHeight: '100vh',
    },
    header: {
        padding: '16px',
        paddingBottom: 16,
        borderBottom: '1px solid #f1f5f9',
    },
    title: {
        fontSize: 22,
        fontWeight: 700,
        lineHeight: 1.4,
        color: '#1e293b',
        margin: '0 0 8px',
    },
    meta: {
        fontSize: 13,
        color: '#94a3b8',
        display: 'flex',
        alignItems: 'center',
        flexWrap: 'wrap',
    },
    dot: {
        margin: '0 6px',
    },
    tags: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: 6,
        marginTop: 10,
    },
    tag: {
        fontSize: 12,
        padding: '2px 10px',
        borderRadius: 4,
        background: '#eff6ff',
        color: '#2563eb',
    },
    content: {
        width: '100%',
        padding: '16px',
    },
    fabContainer: {
        position: 'fixed',
        right: 20,
        bottom: 30,
        zIndex: 100,
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: 8,
    },
    fabMain: {
        width: 50,
        height: 50,
        borderRadius: 25,
        border: 'none',
        background: '#2563eb',
        color: '#fff',
        fontSize: 28,
        fontWeight: 300,
        cursor: 'pointer',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        boxShadow: '0 4px 12px rgba(37,99,235,0.4)',
        transition: 'transform 0.2s ease',
    },
    fabMenu: {
        display: 'flex',
        flexDirection: 'column',
        gap: 6,
        marginBottom: 4,
    },
    fabItem: {
        padding: '8px 16px',
        borderRadius: 20,
        border: 'none',
        background: '#fff',
        color: '#334155',
        fontSize: 14,
        cursor: 'pointer',
        boxShadow: '0 2px 8px rgba(0,0,0,0.12)',
        whiteSpace: 'nowrap',
    },
    fabOverlay: {
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        background: 'rgba(0,0,0,0.15)',
        zIndex: 99,
    },
    toast: {
        position: 'fixed',
        bottom: 100,
        left: '50%',
        transform: 'translateX(-50%)',
        padding: '10px 24px',
        borderRadius: 8,
        background: 'rgba(0,0,0,0.75)',
        color: '#fff',
        fontSize: 14,
        zIndex: 999,
    },
};
