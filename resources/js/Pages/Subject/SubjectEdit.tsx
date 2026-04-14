import React,{ useState,useContext, useEffect }  from 'react';
import { FormBuilder,SchemasChildTypeOut,SchemasTypeOut } from '../../cores/formBuilder/Main';
import { router, usePage } from '@inertiajs/react'
import { EditContent } from '../../cores/formBuilder/EditContent';
import api from '../../util/Service';
import { Card, message, Space } from 'antd';
import { AiButton } from '../../components/AiGenerateModal';


const onFinishFunc:Function = (values : any, pageId : string) => {

    router.post(window.location.pathname, values, {
        onSuccess: () => {
            message.success('修改成功', 1);
        },
        onError: (errors) => {
            message.error('修改失败' + errors.message);
        }
    });

}

interface SubjectPageReturnType {
    props: {
        schema: Array<SchemasChildTypeOut>,
        pageId: string,
        pageName: string,
        tableName: string,
        subjectContent: object,
    };
}

const App: React.FC<SubjectPageReturnType> =  () => {

    const page = usePage() as SubjectPageReturnType;

    const [openAi, setOpenAi] = useState<(() => void) | null>(null);
    return (

        <div style={{ padding: 24 }}>
        <Card
        title={page.props.pageName || '内容编辑'}
        extra={
            <Space>
                <AiButton onClick={() => openAi && openAi()} />
            </Space>
        }
    >
        <EditContent {...page.props} onFinishHandlerFunc={onFinishFunc} onGetAiOpenHandler={(fn) => setOpenAi(() => fn)} ></EditContent>
    </Card>
    </div>
    )
};

export default App