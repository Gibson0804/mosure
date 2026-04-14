import React, { ReactNode, useEffect, useState } from 'react';
import type { MenuProps } from 'antd';
import { Menu, Dropdown, Space, Typography, Avatar, Divider, Tooltip } from 'antd';
import { usePage, Link, router } from '@inertiajs/react';
import {
    DownOutlined,
    DashboardOutlined,
    UserOutlined,
    LockOutlined,
    LogoutOutlined,
    RollbackOutlined,
    DatabaseOutlined,
    UnorderedListOutlined,
    FileOutlined,
    FileImageOutlined,
    CodeOutlined,
    SettingOutlined,
    AppstoreOutlined,
    HomeOutlined,
    FileTextOutlined,
    FolderOutlined,
    BookOutlined,
    TagsOutlined,
    ShoppingOutlined,
    ShoppingCartOutlined,
    TeamOutlined,
    ToolOutlined,
    BellOutlined,
    MessageOutlined,
    CalendarOutlined,
    ClockCircleOutlined,
    BarChartOutlined,
    LineChartOutlined,
    PieChartOutlined,
    StarOutlined,
    HeartOutlined,
    ApiOutlined,
    ProjectOutlined,
    RobotOutlined,
} from '@ant-design/icons';
import { AUTH_ROUTES, PROJECT_ROUTES } from '../Constants/routes';

export interface AppProps {
    children: ReactNode;
}

interface MenuItemType {
    key: string;
    label: string;
    icon?: string;
    link?: string;
    parent_key?: string;
    children?: MenuItemType[];
}

interface UserType {
    appName: string;
    name?: string;
    email?: string;
    avatar?: string;
    id?: number;
}

interface UsePageReturnType {
    props: {
        user: UserType;
        menu: MenuItemType[];
        mainSelectedKeys: string;
        subSelectedKeys: string;
        project_info: any;
    };
}

// 主次菜单一起返回
export function NavBase(restProps: MenuProps) {
    const page = usePage() as UsePageReturnType;
    const { menu } = page.props;
    const [openKeys, setOpenKeys] = useState<string[]>([]);

    // 图标名称到组件的映射
    const iconComponentMap: Record<string, React.ReactNode> = {
        DashboardOutlined: <DashboardOutlined />,
        UserOutlined: <UserOutlined />,
        DatabaseOutlined: <DatabaseOutlined />,
        UnorderedListOutlined: <UnorderedListOutlined />,
        FileOutlined: <FileOutlined />,
        FileImageOutlined: <FileImageOutlined />,
        CodeOutlined: <CodeOutlined />,
        ApiOutlined: <ApiOutlined />,
        SettingOutlined: <SettingOutlined />,
        AppstoreOutlined: <AppstoreOutlined />,
        ProjectOutlined: <ProjectOutlined />,
        HomeOutlined: <HomeOutlined />,
        FileTextOutlined: <FileTextOutlined />,
        FolderOutlined: <FolderOutlined />,
        BookOutlined: <BookOutlined />,
        TagsOutlined: <TagsOutlined />,
        ShoppingOutlined: <ShoppingOutlined />,
        ShoppingCartOutlined: <ShoppingCartOutlined />,
        TeamOutlined: <TeamOutlined />,
        ToolOutlined: <ToolOutlined />,
        BellOutlined: <BellOutlined />,
        MessageOutlined: <MessageOutlined />,
        CalendarOutlined: <CalendarOutlined />,
        ClockCircleOutlined: <ClockCircleOutlined />,
        BarChartOutlined: <BarChartOutlined />,
        LineChartOutlined: <LineChartOutlined />,
        PieChartOutlined: <PieChartOutlined />,
        StarOutlined: <StarOutlined />,
        HeartOutlined: <HeartOutlined />,
    };

    // 初始化时设置默认展开的菜单
    useEffect(() => {
        if (page.props.mainSelectedKeys) {
            setOpenKeys([page.props.mainSelectedKeys]);
        }
    }, [page.props.mainSelectedKeys]);

    // 处理菜单展开/收起
    const onOpenChange = (keys: string[]) => {
        const latestOpenKey = keys.find(key => openKeys.indexOf(key) === -1);
        setOpenKeys(latestOpenKey ? [latestOpenKey] : []);
    };

    const itemsAll: MenuProps['items'] = menu.map(item => {
        const icon = item.icon ? iconComponentMap[item.icon] : null;
        const menuItem: any = { key: item.key, label: item.label, icon };
        
        if (item.children && item.children.length > 0) {
            menuItem.children = item.children.map(child => ({
                key: child.key,
                label: <Link href={child.link || '#'}>{child.label}</Link>,
            }));
        } else {
            menuItem.label = <Link href={item.link || '#'}>{item.label}</Link>;
        }
        
        return menuItem;
    });

    return <Menu 
        {...restProps} 
        theme="light"
        items={itemsAll} 
        selectedKeys={page.props.subSelectedKeys ? [page.props.subSelectedKeys] : []} 
        openKeys={openKeys}
        onOpenChange={onOpenChange}
    />;
}

// 仅主菜单
export function NavMain(restProps: MenuProps) {
    const page = usePage() as UsePageReturnType;
    const { menu } = page.props;

    const itemsMain: MenuProps['items'] = menu.map(item => ({
        key: item.key,
        label: <Link href={item.link || '#'}>{item.label}</Link>,
    }));

    return <Menu mode="horizontal" {...restProps} items={itemsMain} />;
}

// 仅次级菜单
export function NavSub(restProps: MenuProps) {
    const page = usePage() as UsePageReturnType;
    const { menu, mainSelectedKeys } = page.props;

    // 使用find和可选链操作符简化代码
    const subItems = menu.find(item => item.key === mainSelectedKeys)?.children || [];
    
    const itemsSub: MenuProps['items'] = subItems.map(item => ({
        key: item.key,
        label: <Link replace href={item.link || '#'}>{item.label}</Link>,
    }));

    return <Menu {...restProps} items={itemsSub} />;
}

export function NavLogo() {
    return (
        <a href="/" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '50px', color: '#000' }}>
            <img src="/logo.png" width={60} height={60} style={{ marginRight: '2px' }} />
            <span style={{ fontSize: '20px', fontWeight: 'bold' }}>Mosure</span>
        </a>
    );
}

// 用户信息展示组件
export function NavUser({ showBackButton = false }: { showBackButton?: boolean }) {
    const page = usePage() as UsePageReturnType;
    const { user, project_info } = page.props;
    
    // 用户下拉菜单
    const userMenu: MenuProps = {
        items: [
            {
                key: '1',
                icon: <UserOutlined />,
                label: <Link href="/profile">用户信息</Link>,
            },
            {
                key: 'kb',
                icon: <BookOutlined />,
                label: <Link href="/kb">知识库</Link>,
            },
            {
                key: '2',
                icon: <LockOutlined />,
                label: <Link href="/change-password">修改密码</Link>,
            },
            {
                key: 'sys-config',
                icon: <SettingOutlined />,
                label: <Link href="/system-config">系统设置</Link>,
            },
            {
                type: 'divider',
                key: 'divider-1',
            },
            {
                key: '3',
                icon: <LogoutOutlined />,
                label: (
                    <a href="#" onClick={(e) => {
                        e.preventDefault();
                        router.post('/logout', {}, {
                            onSuccess: () => {
                                window.location.href = AUTH_ROUTES.login
                            }
                        });
                    }}>
                        退出登录
                    </a>
                ),
            },
        ],
    };

    return (
        <Space size="middle" style={{ lineHeight: '46px', textAlign: 'center', display: 'flex', alignItems: 'center' }}>
            {/* 项目信息展示与返回按钮 */}
            {showBackButton && project_info && (
                <div 
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        padding: '0 4px',
                        marginRight: '8px'
                    }}
                >
                    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start' }}>
                        <Typography.Text 
                            style={{
                                maxWidth: '180px',
                                overflow: 'hidden',
                                textOverflow: 'ellipsis',
                                whiteSpace: 'nowrap',
                                fontWeight: 'bold',
                                fontSize: '14px',
                                margin: 0
                            }}
                        >
                            {project_info.name || '未选择项目'}
                        </Typography.Text>
                        {project_info.prefix && (
                            <Typography.Text 
                                style={{
                                    fontSize: '12px',
                                    color: '#666',
                                    marginTop: '-2px',
                                    margin: 0
                                }}
                            >
                                {project_info.prefix}
                            </Typography.Text>
                        )}
                    </div>
                    
                    {/* 返回项目列表按钮 - 放在项目名称右侧 */}
                    {showBackButton && (
                        <Tooltip title="返回项目列表" placement="bottom">
                            <Link 
                                href={PROJECT_ROUTES.index} 
                                style={{
                                    marginLeft: '6px',
                                    padding: '4px',
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center'
                                }}
                            >
                                <RollbackOutlined style={{ color: '#1890ff' }} />
                            </Link>
                        </Tooltip>
                    )}
                </div>
            )}

            {/* AI 群聊入口按钮 */}
            <Tooltip title="AI 群聊">
                <Link 
                    href="/ai/chat" 
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        padding: '0 12px',
                        color: '#1890ff',
                        fontSize: '16px',
                    }}
                >
                    <RobotOutlined />
                    <span style={{ marginLeft: '4px', fontSize: '14px' }}>AI群组</span>
                </Link>
            </Tooltip>

            {/* 用户信息展示组件 - 右侧 */}
            <Dropdown menu={userMenu} placement="bottomRight">
                <div 
                    style={{ 
                        display: 'flex', 
                        alignItems: 'center', 
                        cursor: 'pointer',
                        padding: '0 12px'
                    }}
                >
                    <Avatar 
                        src={user?.avatar || undefined}
                        icon={!user?.avatar ? <UserOutlined /> : undefined}
                        size="small" 
                        style={{ 
                            marginRight: '8px', 
                            backgroundColor: '#1890ff'
                        }} 
                    />
                    <Typography.Text style={{ 
                        maxWidth: '120px', 
                        overflow: 'hidden', 
                        textOverflow: 'ellipsis', 
                        whiteSpace: 'nowrap', 
                        display: 'inline-block'
                    }}>
                        {user?.name || user?.appName || 'Admin'}
                    </Typography.Text>
                    <DownOutlined style={{ fontSize: '12px', marginLeft: '5px' }} />
                </div>
            </Dropdown>
        </Space>
    );
}
