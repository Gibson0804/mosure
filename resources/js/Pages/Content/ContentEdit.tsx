import React, { useState, useContext, useEffect } from 'react';
import { FormBuilder, SchemasChildTypeOut, SchemasTypeOut } from '../../cores/formBuilder/Main';
import { router, usePage, Link } from '@inertiajs/react'
import { EditContent } from '../../cores/formBuilder/EditContent';
import { message, Button, Card, Space } from 'antd';
import AiGenerateModal, { AiButton } from '../../components/AiGenerateModal';
import api from '../../util/Service';
import { Redirect } from '../../util/LinkUtil';


interface SubjectPageReturnType {
    props: {
        moldId: string,
        schema: Array<SchemasChildTypeOut>,
        pageId: string,
        pageName: string,
        tableName: string,
    };
}

const App: React.FC<SubjectPageReturnType> = () => {

    const page = usePage() as SubjectPageReturnType;

    // 生成请求唯一标识，在组件级别生成一次
    const requestIdRef = React.useRef<string>('');
    const [isSubmitting, setIsSubmitting] = React.useState(false);

    const onFinishFunc: Function = (values: any) => {
        // 如果正在提交，直接返回一个已解决的Promise
        if (isSubmitting) {
            return Promise.resolve();
        }
        
        // 如果没有requestId或者上次请求已完成，生成新的
        if (!requestIdRef.current) {
            requestIdRef.current = 'req_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        
        setIsSubmitting(true);
        
        return new Promise((resolve, reject) => {
            router.post(window.location.pathname, values, {
                headers: {
                    'X-Request-ID': requestIdRef.current,
                },
                onSuccess: () => {
                    message.success('修改成功', 1);
                    requestIdRef.current = '';
                    setIsSubmitting(false);
                    resolve(true);
                },
                onError: (errors) => {
                    message.error('修改失败' + errors);
                    console.error('[ContentEdit] 提交失败:', errors);
                    requestIdRef.current = '';
                    setIsSubmitting(false);
                    reject(errors);
                },
                onFinish: () => {
                },
            })
        });
    }

    const [openAi, setOpenAi] = useState<(() => void) | null>(null);

    return (
        <div style={{ padding: 24 }}>
            <Card
                title={page.props.pageName || '内容编辑'}
                extra={
                    <Space>
                        <AiButton onClick={() => openAi && openAi()} />
                        <Link href={`/content/list/${page.props.moldId}`}>
                            <Button>返回列表</Button>
                        </Link>
                    </Space>
                }
            >
                <EditContent {...page.props} onFinishHandlerFunc={onFinishFunc} onGetAiOpenHandler={(fn) => setOpenAi(() => fn)} />
            </Card>
        </div>
    )

};


export default App