import React,{ useState, useContext, useRef, useEffect  } from 'react'
import { Input,Form,Col,Flex, Radio ,Select,Slider,Switch, Collapse } from 'antd';
import { useSchema } from '../context/SchemaContext';
import { useSchemasDispatch, type SchemasChildType } from '../context/SchemaContext';
import { pickChildInfoLog } from '../utils/LogUtil'
import { GetMiddleCanChangeParam } from './MiddleOne';
import { useTranslate } from '../../../util/useTranslate';


const rightBarStyle = {
    // padding: '30px',
}

export function RightBar({errors}) {

    const _t = useTranslate();

    const schemasDispatch = useSchemasDispatch();

    const schema = useSchema()
        
    const children = schema.children;
    
    // 获取children中isPick=true的一项
    const pickChildrenList = children.filter(child => child.isPick === true);
    let pickChildren: SchemasChildType | null = null;
    if (pickChildrenList.length > 0) {
        pickChildren = pickChildrenList[0];
    }
    
    function handleInputChange(event: React.ChangeEvent<HTMLInputElement>) {
        const { name, value } = event.target;
        
        if (!pickChildren) return; // Add null check
        
        schemasDispatch({
            type: 'changed_by_id',
            id: pickChildren.id,
            field: name,
            value: value,
        });
    }

    function handlePageInfoChange(event) {
        const { name, value } = event.target;
        schemasDispatch({
            type: 'changed_page_info',
            field: name,
            value: value,
        }) 
    }

    function handleSliderChange(num) {
        // const { name, value } = event.target;
    
        if (!pickChildren) return; // Add null check
        schemasDispatch({
            type: 'changed_by_id',
            id: pickChildren.id,
            field: "length",
            value: num,
        })
    }

    function handleSwitchChange(checked: boolean) {
        if(!pickChildren) return

        // let rules = []
        let rules: Array<{ required: boolean; message: string }> = [];

        if(checked) {
            rules = [
                {
                    required: true,
                    message: '字段必填',
                },
            ]
        }
        schemasDispatch({
            type: 'changed_by_id',
            id: pickChildren.id,
            field: "rules",
            value: rules,
        })
      };

    const marks = {
        4: '4',
        8: '8',
        12: '12',
        16: '16',
        20: '20',
        24: '24'
      };

    return (
        <div style={rightBarStyle}>
            <Collapse 
                defaultActiveKey={['1']} 
                items={[
                    {
                        key: '1',
                        label: '基本信息',
                        children: (
                            <>
                                <p>名称</p>
                                <Input name="page_name"
                                    value={schema.page_name}
                                    onChange={handlePageInfoChange}
                                    status={errors.name ? 'error' : ''}
                                />
                                {errors.name && <p style={{color: 'red'}}>{errors.name}</p>}

                                <p>标识ID</p>
                                <Input name="page_id"
                                    value={schema.page_id}
                                    onChange={handlePageInfoChange}
                                    disabled={window.location.pathname.includes('/edit/')}
                                    status={errors.table_name ? 'error' : ''}
                                />
                                {errors.table_name && <p style={{color: 'red'}}>{errors.table_name}</p>}

                                <p>模型类型</p>
                                <Radio.Group name="mold_type" onChange={handlePageInfoChange} value={schema.mold_type}>
                                    <Radio value="list">{_t('content_list', '内容列表')}</Radio>
                                    <Radio value="single">{_t('content_single', '内容单页')}</Radio>
                                </Radio.Group>
                            </>
                        ),
                    },
                ]}
            />


            {pickChildren != null && <>

                <hr style={{marginTop: 20 }}></hr>

                {pickChildren.label !== null &&
                    <>
                        <p>字段名称</p>
                        <Input name="label"
                            value={pickChildren.label}
                            onChange={handleInputChange}
                        />
                    </>
                }
                {pickChildren.id !== null &&
                    <>
                        <p>字段标识ID</p>
                        <Input name="field"
                            value={pickChildren.field}
                            onChange={handleInputChange}
                        />
                    </>
                }


                <p>长度</p>
                <Slider marks={marks} max={24}
                            onChange={handleSliderChange} step={null} value={ pickChildren.length ? pickChildren.length : 12} />

                <p>是否必填</p>
                <Switch onChange={handleSwitchChange} />            


                {GetMiddleCanChangeParam(pickChildren.type??'', pickChildren, schemasDispatch)}

            </>}


        </div>
    )
}



