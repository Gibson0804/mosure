// 通用主系统布局，适用于大多数业务页面（如内容管理、权限管理、媒体管理等）。
// 使用范围：除登录/注册/安装/项目选择等特殊页面外，所有主业务页面均应使用本布局。
import React, { ReactNode, useEffect, useState } from 'react';
import { Layout, theme } from 'antd';
import { NavBase, NavLogo, NavUser } from './NavBase';
import { CodepenCircleOutlined, MenuFoldOutlined, MenuUnfoldOutlined, StepBackwardFilled, StepForwardFilled } from '@ant-design/icons';
import { Row, Col } from 'antd';
import useFlashMessage from '../hooks/useFlashMessage';

const { Sider } = Layout;

const MainLayout = ({ children }: { children: ReactNode }) => {
    useFlashMessage();

    const {
        token: { colorBgContainer },
    } = theme.useToken();

    const STORAGE_KEY = 'main_sider_collapsed';
    const [collapsed, setCollapsed] = useState<boolean>(false);

    useEffect(() => {
        try {
            const v = localStorage.getItem(STORAGE_KEY);
            if (v === '1') setCollapsed(true);
        } catch {}
    }, []);

    const handleCollapse = (val: boolean) => {
        setCollapsed(val);
        try { localStorage.setItem(STORAGE_KEY, val ? '1' : '0'); } catch {}
    };

    return (
        <Layout>
            <Layout style={{ background: '#fff', display: 'block', padding: 0, alignItems: 'center' }}>
                <Row>
                    <Col flex='250px' >
                        <NavLogo />
                    </Col>
                    <Col flex='auto' />
                    <Col flex='350px' >
                        <div style={{ display: 'flex', justifyContent: 'flex-end', alignItems: 'center', height: '46px' }}>
                            <NavUser showBackButton={true} />
                        </div>
                    </Col>
                </Row>
            </Layout>
            <Layout style={{minHeight: '80vh'}}>
                <Sider
                    collapsible
                    trigger={null}
                    collapsed={collapsed}
                    onCollapse={handleCollapse}
                    collapsedWidth={54}
                    width={250}
                    style={{ background: colorBgContainer, margin: 10, marginRight: 0, display: 'flex', flexDirection: 'column' }}
                >
                    <div style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'flex-end',
                        padding: '8px 8px',
                        borderBottom: '1px solid #f0f0f0',
                        background: colorBgContainer
                    }}>
                        <button
                            onClick={() => handleCollapse(!collapsed)}
                            style={{
                                border: '0px solid #e5e5e5',
                                background: '#fff',
                                color: '#666',
                                borderRadius: 4,
                                padding: '2px 8px',
                                cursor: 'pointer',
                                fontSize: 'large'
                            }}
                            title={collapsed ? '展开菜单' : '折叠菜单'}
                            aria-label={collapsed ? '展开菜单' : '折叠菜单'}
                        >
                            {collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
                        </button>
                    </div>
                    <div style={{ flex: 1, overflow: 'auto' }}>
                        <NavBase mode="inline" style={{ height: '100%', borderRight: 0 }} />
                    </div>
                </Sider>
                <Layout style={{ padding: '20px',margin: 10, background: colorBgContainer }}>{children}</Layout>
            </Layout>
        </Layout>
    );
};



export default MainLayout;
