import React, { useEffect, useRef, useMemo } from 'react';
import { Head } from '@inertiajs/react';
import { Crepe, CrepeFeature } from '@milkdown/crepe';
import '@milkdown/kit/prose/view/style/prosemirror.css';
import '@milkdown/crepe/theme/common/reset.css';
import '@milkdown/crepe/theme/common/code-mirror.css';
import '@milkdown/crepe/theme/common/cursor.css';
import '@milkdown/crepe/theme/common/image-block.css';
import '@milkdown/crepe/theme/common/list-item.css';
import '@milkdown/crepe/theme/common/table.css';
import '@milkdown/crepe/theme/nord.css';

interface KbPublicViewProps {
    article: {
        id: number;
        title: string;
        content: string;
        summary: string;
        tags: string[];
        view_count: number;
        created_at: string;
        updated_at: string;
    };
}

export default function KbPublicView({ article }: KbPublicViewProps) {
    const editorRef = useRef<HTMLDivElement>(null);
    const crepeRef = useRef<Crepe | null>(null);

    // 从 markdown 内容提取标题生成目录
    const tocItems = useMemo(() => {
        if (!article.content) return [];
        const lines = article.content.split('\n');
        const items: { level: number; text: string }[] = [];
        let inCodeBlock = false;
        lines.forEach((line) => {
            if (line.trim().startsWith('```')) {
                inCodeBlock = !inCodeBlock;
                return;
            }
            if (inCodeBlock) return;
            const match = line.match(/^(#{1,3})\s+(.+)$/);
            if (match) {
                items.push({ level: match[1].length, text: match[2].trim() });
            }
        });
        return items;
    }, [article.content]);

    const scrollToHeading = (text: string) => {
        if (!editorRef.current) return;
        const headings = editorRef.current.querySelectorAll('h1, h2, h3');
        for (const h of headings) {
            if (h.textContent?.trim() === text) {
                h.scrollIntoView({ behavior: 'smooth', block: 'start' });
                break;
            }
        }
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
                    [CrepeFeature.ImageBlock]: true,
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

    return (
        <>
            <Head title={article.title} />
            <div className="kb-pv-layout">
                {/* 左侧目录（桌面端显示） */}
                {tocItems.length > 0 && (
                    <div className="kb-pv-toc">
                        <div style={{ padding: '0 12px 8px', fontSize: 13, fontWeight: 600, color: '#1e293b', borderBottom: '1px solid #f0f0f0' }}>
                            目录
                        </div>
                        <div style={{ padding: '8px 0' }}>
                            {tocItems.map((item, i) => (
                                <div
                                    key={i}
                                    onClick={() => scrollToHeading(item.text)}
                                    className="kb-pv-toc-item"
                                    style={{ paddingLeft: 12 + (item.level - 1) * 12 }}
                                >
                                    {item.text}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* 正文区域 */}
                <div className="kb-pv-content">
                    <h1 className="kb-pv-title">{article.title}</h1>
                    <div className="kb-pv-meta">
                        {article.updated_at} · 阅读 {article.view_count}
                    </div>
                    <div ref={editorRef} />
                </div>
            </div>

            <style>{`
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
            `}</style>
        </>
    );
}
