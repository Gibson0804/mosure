import React from 'react';
import { Input, message, Select, Tabs } from 'antd';
import { fetchModelsAndFieldsOnce, loadModelFieldsFromCache } from './sharedModels';

type Props = {
    pickChildren: any,
    schemasDispatch: any,
};

export default function OptionsSourcePanel({ pickChildren, schemasDispatch }: Props) {
    const [availableModels, setAvailableModels] = React.useState<any[]>([]);
    const [availableFields, setAvailableFields] = React.useState<any[]>([]);

    function handleOptionsChange(event) {
        const { value: plainOptions } = event.target;
        const options = plainOptions
            .replace(/，/g, ',')
            .split(',')
            .map(v => v.trim())
            .filter(v => v.length > 0)
            .map(v => ({ label: v, value: v }));

        schemasDispatch({ type: 'changed_by_id', id: pickChildren.id, field: 'plainOptions', value: plainOptions });
        schemasDispatch({ type: 'changed_by_id', id: pickChildren.id, field: 'options', value: options });
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
    const activeKey = pickChildren.optionsSource || 'custom';

    return (
        <Tabs activeKey={activeKey} onChange={handleSourceChange} items={[
            {
                key: 'custom',
                label: '自定义选项',
                children: (
                    <>
                        <p>选项（使用逗号分隔）：</p>
                        <Input
                            name="plainOptions"
                            value={pickChildren.plainOptions}
                            placeholder="例如：选项A,选项B,选项C"
                            onChange={handleOptionsChange}
                        />
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
                        <p>选择字段</p>
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
                    </>
                )
            }
        ]} />
    );
}


