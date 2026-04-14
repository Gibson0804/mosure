import { message } from 'antd';
import api from '../../../../../util/Service';
import { MOLD_ROUTES } from '../../../../../Constants/routes';
let modelsCache: any[] | null = null;
let modelsLoadingPromise: Promise<any[] | null> | null = null;

export async function fetchModelsAndFieldsOnce(): Promise<any[] | null> {
    if (modelsCache) return modelsCache;
    if (modelsLoadingPromise) return modelsLoadingPromise;
    modelsLoadingPromise = (async () => {
        try {
            //todo::抽离到外层传入
            const res = await api.get(MOLD_ROUTES.modelsAndFields);
            const payload = (res?.data && (res.data.data ?? res.data)) || {} as any;
            const models = Array.isArray(payload.models) ? payload.models : [];
            modelsCache = models;
            return modelsCache;
        } catch (e) {
            message.error('获取模型列表失败');
            return null;
        } finally {
            modelsLoadingPromise = null;
        }
    })();
    return modelsLoadingPromise;
}

export async function loadModelFieldsFromCache(modelId: string | number, availableModels: any[]): Promise<any[]> {
    const models = availableModels.length > 0 ? availableModels : (await fetchModelsAndFieldsOnce()) || [];
    const model = models.find((m: any) => String(m.id) === String(modelId));
    const fields = Array.isArray(model?.fields) ? model.fields : [];
    if (fields.length === 0) message.warning('该模型暂无可用字段');
    return fields;
}


