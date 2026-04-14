import React from 'react';
import { message, Select, Tabs } from 'antd';
import { fetchModelsAndFieldsOnce, loadModelFieldsFromCache } from './sharedModels';
import { presetOptions } from './chinaRegions';

type Props = {
    pickChildren: any,
    schemasDispatch: any,
};

export default function CascaderOptionsPanel({ pickChildren, schemasDispatch }: Props) {
    const [availableModels, setAvailableModels] = React.useState<any[]>([]);
    const [availableFields, setAvailableFields] = React.useState<any[]>([]);

    // 处理预设选项选择
    function handlePresetChange(presetKey: string) {
        const preset = presetOptions[presetKey as keyof typeof presetOptions];
        if (preset) {
            schemasDispatch({ type: 'changed_by_id', id: pickChildren.id, field: 'presetKey', value: presetKey });
            schemasDispatch({ type: 'changed_by_id', id: pickChildren.id, field: 'options', value: preset.data });
        }
    }

    function handleSourceChange(key: string) {
        schemasDispatch({ type: 'changed_by_id', id: pickChildren.id, field: 'optionsSource', value: key });
    }

    async function ensureModelsLoaded() {
        if (availableModels.length > 0) return;
        const models = await fetchModelsAndFieldsOnce();
        if (Array.isArray(models)) {
            setAvailableModels(models);
            if (models.length === 0) message.warning('未获取到模型列表');
        }
    }

    async function loadModelFieldsLocal(modelId: string | number) {
        const fields = await loadModelFieldsFromCache(modelId, availableModels);
        setAvailableFields(fields);
    }

    async function handlePickModel(modelId: string | number) {
        schemasDispatch({ type: 'changed_by_id', id: pickChildren.id, field: 'sourceModelId', value: modelId });
        schemasDispatch({ type: 'changed_by_id', id: pickChildren.id, field: 'sourceFieldName', value: undefined });
        await loadModelFieldsLocal(modelId);
    }

    function handlePickField(fieldName: string) {
        schemasDispatch({ type: 'changed_by_id', id: pickChildren.id, field: 'sourceFieldName', value: fieldName });
    }

    React.useEffect(() => {
        (async () => {
            if (pickChildren.sourceModelId) {
                await ensureModelsLoaded();
                await loadModelFieldsLocal(pickChildren.sourceModelId);
            }
        })();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pickChildren.sourceModelId]);

    const modelOptions = availableModels.map(m => ({ label: m.name, value: m.id }));
    const fieldOptions = availableFields.map(f => ({ label: f.label || f.field, value: f.field }));
    const activeKey = pickChildren.optionsSource || 'preset';

    // 预设选项列表
    const presetOptionsList = Object.entries(presetOptions).map(([key, value]) => ({
        label: value.name,
        value: key
    }));

    return (
        <Tabs activeKey={activeKey} onChange={handleSourceChange} items={[
            {
                key: 'preset',
                label: '预设选项',
                children: (
                    <>
                        <p>选择预设选项：</p>
                        <Select
                            placeholder="请选择预设选项"
                            style={{ width: '100%' }}
                            options={presetOptionsList}
                            value={pickChildren.presetKey}
                            onChange={handlePresetChange}
                        />
                        <p style={{ marginTop: 8, fontSize: 12, color: '#999' }}>
                            提示：目前支持中国省市区数据
                        </p>
                    </>
                )
            },
            {
                key: 'model',
                label: '模型字段',
                children: (
                    <>
                        <p>选择模型</p>
                        <Select
                            placeholder="请选择模型"
                            style={{ width: '100%', marginBottom: 12 }}
                            options={modelOptions}
                            value={pickChildren.sourceModelId}
                            onChange={handlePickModel}
                            onDropdownVisibleChange={(open) => { if (open) ensureModelsLoaded(); }}
                            showSearch
                            optionFilterProp="label"
                        />
                        <p>选择字段（需要存储树形结构）</p>
                        <Select
                            placeholder="请选择字段"
                            style={{ width: '100%' }}
                            options={fieldOptions}
                            value={pickChildren.sourceFieldName}
                            onChange={handlePickField}
                            onDropdownVisibleChange={async (open) => {
                                if (open && pickChildren.sourceModelId && fieldOptions.length === 0) {
                                    await loadModelFieldsLocal(pickChildren.sourceModelId);
                                }
                            }}
                            showSearch
                            optionFilterProp="label"
                        />
                        <p style={{ marginTop: 8, fontSize: 12, color: '#999' }}>
                            注意：模型字段需要存储树形结构的 JSON 数据
                        </p>
                    </>
                )
            }
        ]} />
    );
}
