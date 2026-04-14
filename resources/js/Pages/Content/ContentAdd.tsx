import React,{ useState }  from 'react';
import { SchemasChildTypeOut } from '../../cores/formBuilder/Main';
import { router, usePage, Link } from '@inertiajs/react'
import { EditContent } from '../../cores/formBuilder/EditContent';
import { Button, Card, message, Space } from 'antd';
import { AiButton } from '../../components/AiGenerateModal';
import api from '../../util/Service';

interface SubjectPageReturnType {
    props: {
        moldId: string,
        schema: Array<SchemasChildTypeOut>,
        pageId: string,
        pageName: string,
        tableName: string,
        subjectContent: object,
        errors?: {message: string},
    };
}

const App: React.FC<SubjectPageReturnType> =  () => {

    const page = usePage() as SubjectPageReturnType;

    if(page.props.errors && page.props.errors.message) {
        message.error(page.props.errors.message);
    }

    // 生成请求唯一标识，在组件级别生成一次
    const requestIdRef = React.useRef<string>('');
    const [isSubmitting, setIsSubmitting] = React.useState(false);
    
    const onFinishFunc:Function = (values : any) => {
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
                    requestIdRef.current = '';
                    setIsSubmitting(false);
                    resolve(true);
                },
                onError: (errors) => {
                    console.error('[ContentAdd] 提交失败:', errors);
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
                title={page.props.pageName || '内容新增'}
                extra={
                    <Space>
                        <AiButton onClick={() => openAi && openAi()} />
                        <Link href={`/content/list/${page.props.moldId}`}>
                            <Button>返回列表</Button>
                        </Link>
                    </Space>
                }
            >
                <EditContent
                    {...{
                        ...page.props,
                        onFinishHandlerFunc: onFinishFunc,
                        onGetAiOpenHandler: (fn) => setOpenAi(() => fn),
                    }}
                />
            </Card>
        </div>
    )
};


export default App