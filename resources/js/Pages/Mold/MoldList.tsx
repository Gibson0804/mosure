import React, { useState, useEffect } from 'react';
import { Space, Table, Modal, Tag, Button, message, Select, Form, Checkbox, Divider, Input } from 'antd';
import type { ColumnsType } from 'antd/es/table';
import { Link, router } from '@inertiajs/react'
import { ExclamationCircleFilled, LinkOutlined } from '@ant-design/icons';
import { MOLD_ROUTES, SUBJECT_ROUTES } from '../../Constants/routes';
import api from '../../util/Service';
import { CONTENT_ROUTES } from '../../Constants/routes';


const { confirm } = Modal

interface InfoProps {
    info: DataType[]
    hooks?: any[]
    eventTypes?: string[]
}

interface DataType {
    key: number;
    name: string;
    table_name: string;
    mold_type: string;
    created_at: string;
}

const handleDeleteConfirm = (record: DataType) => {


    api.post(MOLD_ROUTES.deleteCheck(record.key))
        .then(function (response) {

            let moldCountMsg = '';
            if (response.data.moldContentCount > 0) {
                moldCountMsg = '有' + response.data.moldContentCount + '个内容在使用该模型，确认删除吗？';
            }

            confirm({
                title: '确认删除 ' + record.name + '?',
                icon: <ExclamationCircleFilled />,
                content: moldCountMsg,
                okText: '是',
                okType: 'danger',
                maskClosable: true,
                cancelText: '否',
                onOk() {
                    router.post(MOLD_ROUTES.delete(record.key), {}, {
                        preserveScroll: true,
                        preserveState: true,
                        onSuccess: () => {
                            message.success('操作成功', 1);
                        },
                        onError: (error) => {
                            message.error('操作失败：' + error.message);
                        },
                    })
                },
            });
        }).catch(function (error) {
            message.error("操作错误：" + error);
        })
};


const columns: ColumnsType<DataType> = [
    {
        title: '名称',
        dataIndex: 'name',
        key: 'name',
        render: (text, record) => (
            <Link href={record.mold_type == 'single' ? SUBJECT_ROUTES.edit(record.key) : CONTENT_ROUTES.list(record.key)}>
                {text}
            </Link>
        ),
    },
    {
        title: '标识ID',
        dataIndex: 'table_name',
        key: 'table_name',
    },
    {
        title: '创建时间',
        dataIndex: 'created_at',
        key: 'created_at',
        sorter: (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
        defaultSortOrder: 'descend', // 默认降序
        showSorterTooltip: { title: '点击排序' }, // 添加提示
    },
    {
        title: '操作',
        key: 'action',
        render: (_, record) => (
            <Space size="middle">
                <Button onClick={(e) => handleDeleteConfirm(record)}>删除</Button>
                <Button href={MOLD_ROUTES.edit(record.key)}>修改</Button>
            </Space>
        ),
    },
];

const App: React.FC<InfoProps> = ({ info, hooks, eventTypes }) => {
    return (
        <Table columns={columns} dataSource={info} />
    )
}

export default App;