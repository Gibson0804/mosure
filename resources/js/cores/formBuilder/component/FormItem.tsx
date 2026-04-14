
import { Button, Space, Flex } from 'antd';
import React, { useState, useContext } from 'react'
import { useSchemasDispatch } from '../context/SchemaContext';
import { ReactSortable } from 'react-sortablejs';
import { CompMap } from './MiddleOne';

const iconBox = {
    cursor: 'pointer',
    border: '1px solid #d9d9d9',
    padding: '8px 10px',
    borderRadius: 4,
    width: 120,
    margin: 5
}

const boxStyle = {
    gap: '10px',
    display: 'flex',
    borderRadius: 4,
    border: '1px solid #ddd',
    padding: 10

};

type IconType = {
    id: string,
    type: string,
    name: string,
    pic?: any
}

const icons: Array<IconType> = Array.from(CompMap.entries()).map((item) => {
    return {
        'id': item[0],
        'type': item[0],
        'name': item[1].typeName,
    }
})

export function FormItem() {


    const schemasDispatch = useSchemasDispatch();

    const handleClick = (e: React.MouseEvent<HTMLElement, MouseEvent>, icon: IconType) => {
        let type = icon.type

        schemasDispatch({
            type: 'added',
            // id: nextId++,
            name: icon.name,
            icon_type: type
        })
    }

    type IconBtnType = {
        icon: IconType
    }

    const IconBtn = ({ icon }: IconBtnType) => {
        return (
            <Button size='large' data-type={icon.type}  data-name={icon.name} onClick={(e) => handleClick(e, icon)} style={iconBox}  >
                <span>
                    {icon.pic}
                </span>
                <span >
                    {icon.name}
                </span>

            </Button>
        )
    }

    return (
        <div style={boxStyle}>

            <ReactSortable list={icons} group={{ name: 'dragItem', pull: 'clone', put: false }} sort={false} setList={() => {}} >
                {icons.map(icon => <IconBtn key={icon.type} icon={icon} />)}
            </ReactSortable>
        </div>
    )
}
