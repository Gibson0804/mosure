import React,{ useState,useContext, useEffect }  from 'react';
import { FormBuilder,SchemasTypeOut } from '../../cores/formBuilder/Main';
import { message } from 'antd';
import api from '../../util/Service';
// import { Redirect } from '../../util/LinkUtil';
import { MOLD_ROUTES } from '../../Constants/routes';
import { router, usePage } from '@inertiajs/react';


type propsType = {
    info :SchemasTypeOut
    successMsg: string
    id: number
    errors: any
}

  
const App: React.FC<propsType> =  ({info, id, errors}) => {

    const handleSaveFunc:Function = (schema:SchemasTypeOut) => {
    
        const values = {
            'name': schema.page_name,
            'table_name' : schema.page_id,
            'fields' : schema.children,
            'mold_type' :schema.mold_type,
        }
    
        router.post(MOLD_ROUTES.edit(id), values, {
            onSuccess: () => {
                message.success('修改成功', 1);
            },
            onError: (errors) => {
                message.error('修改失败');
            }
        })
    }
    return (
        <FormBuilder handleSaveFunc={handleSaveFunc} initSchemas={info} errors={errors}></FormBuilder>
    )
};


export default App