import { Divider } from 'antd';
import { middleOneType } from "../MiddleOne";
import React from 'react';

const DividingLineComp = {
    typeName: '分隔线',
    component: ({ child, form }: middleOneType) => {
        return (
            <div style={{ border: "2px dotted #d9d9d9", marginBottom: 40, marginTop: 10 }}>
                <span style={{
                    top: -7,
                    position: "absolute",
                    // marginLeft: "45%",
                    background: "#fff",
                    padding: 6
                }}>{child.label}</span>
            </div>
        )
    },
    formatValue: (value: any) => {
        return ''
    }
};

export default DividingLineComp;
