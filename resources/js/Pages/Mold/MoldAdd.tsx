import React  from 'react';
import { FormBuilder,SchemasTypeOut } from '../../cores/formBuilder/Main';
import { message } from 'antd';

import { router } from '@inertiajs/react';
import { MOLD_ROUTES } from '../../Constants/routes';

const handleSaveFunc:Function = (schema:SchemasTypeOut) => {

    const values = {
        'name': schema.page_name,
        'table_name' : schema.page_id,
        'fields' : schema.children,
        'mold_type' :schema.mold_type,
        'subject_content' : ''
    }

    router.post(MOLD_ROUTES.add, values, {
        onSuccess: () => {
            message.success('添加成功', 1);
        },
        onError: () => {
            message.error('添加失败');
        }
    })

}


const App: React.FC<any> =  ({info, errors}) => {
    return (
        <FormBuilder handleSaveFunc={handleSaveFunc} errors={errors} initSchemas={info}></FormBuilder>
    )
};


export default App