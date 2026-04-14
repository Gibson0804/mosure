import React from 'react';
import api from '../../../../../util/Service';
import { CONTENT_ROUTES } from '../../../../../Constants/routes';

export function isModelSource(child: any) {
    return !!(child && child.optionsSource === 'model' && child.sourceModelId && child.sourceFieldName);
}

export function useModelContentOptions(child: any) {
    const model = child as any;
    const modelSource = isModelSource(model);
    const [options, setOptions] = React.useState<any[] | null>(null);

    // 简单的页面级缓存：key = `${modelId}#${fieldName}`
    // 避免同一页切换多次重复请求
    const cacheRef = React.useRef<{ map: Map<string, any[]>, inflight: Map<string, Promise<any[]>> }>({
        map: new Map(),
        inflight: new Map(),
    });

    React.useEffect(() => {
        let cancelled = false;
        async function load() {
            if (!modelSource) { setOptions(null); return; }
            const key = String(model.sourceModelId) + '#' + String(model.sourceFieldName);
            const { map, inflight } = cacheRef.current;

            // 命中缓存
            if (map.has(key)) {
                if (!cancelled) setOptions(map.get(key) || []);
                return;
            }

            // 合并并发请求
            if (inflight.has(key)) {
                const p = inflight.get(key)!;
                const data = await p.catch(() => []);
                if (!cancelled) setOptions(data || []);
                return;
            }

            const promise = (async () => {
                try {
                    const res = await api.post(CONTENT_ROUTES.fieldOptions(model.sourceModelId), { field: model.sourceFieldName });
                    const payload = (res?.data && (res.data.data ?? res.data)) || [] as any[];
                    const data = Array.isArray(payload) ? payload : [];
                    map.set(key, data);
                    return data;
                } catch (e) {
                    return [] as any[];
                } finally {
                    inflight.delete(key);
                }
            })();

            inflight.set(key, promise);
            const data = await promise;
            if (!cancelled) setOptions(data || []);
        }
        load();
        return () => { cancelled = true; };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [modelSource, model?.sourceModelId, model?.sourceFieldName]);

    return { options: options, isModelSource: modelSource };
}



// 统一将各种原始 options 规范化为 antd 可用的 {label, value}[]
export function normalizeStaticOptions(raw: any): Array<{ label: string; value: string }> {
    try {
        // 数组输入
        if (Array.isArray(raw)) {
            return raw
                .map((opt: any) => {
                    if (opt == null) return null;
                    if (typeof opt === 'string' || typeof opt === 'number' || typeof opt === 'boolean') {
                        const s = String(opt);
                        return { label: s, value: s };
                    }
                    if (typeof opt === 'object') {
                        const label = opt.label != null ? String(opt.label) : (opt.value != null ? String(opt.value) : '');
                        const value = opt.value != null ? String(opt.value) : label;
                        if (!label) return null;
                        return { label, value };
                    }
                    return null;
                })
                .filter(Boolean) as Array<{ label: string; value: string }>;
        }

        // 逗号分隔字符串
        if (typeof raw === 'string' && raw.trim()) {
            const parts = raw.split(',').map(s => s.trim()).filter(Boolean);
            return parts.map(v => ({ label: v, value: v }));
        }

        // 对象映射 { key: label }
        if (raw && typeof raw === 'object') {
            return Object.entries(raw).map(([k, v]) => {
                const value = String(k);
                const label = v != null && String(v).trim() ? String(v) : value;
                return { label, value };
            });
        }

        return [];
    } catch {
        return [];
    }
}
