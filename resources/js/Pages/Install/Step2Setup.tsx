import React, { useState, useEffect, useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Card, Steps, Form, Input, Select, Button, Alert, Divider, Typography, message, Spin } from 'antd';
import { INSTALL_ROUTES } from '../../Constants/routes';
import { useTranslate } from '../../util/useTranslate';

const { Title, Text } = Typography;
const { Step } = Steps;
const { Option } = Select;

type InstallInfo = {
    app_url?: string;
    db_defaults?: {
        dbtype?: string;
        appurl?: string;
        dbhost?: string;
        dbport?: string;
        dbname?: string;
        dbuser?: string;
        dbpwd?: string;
    };
    docker_auto_db?: boolean;
    database_types?: { value: string; label: string }[];
};

type TestResult = {
    success: boolean;
    message: string;
};

export default function Step2Setup({ info }: { info: InstallInfo }) {
    const [form] = Form.useForm();
    const dbDefaults = useMemo(() => ({
        dbtype: info?.db_defaults?.dbtype ?? 'sqlite',
        appurl: info?.db_defaults?.appurl ?? info?.app_url ?? '',
        dbhost: info?.db_defaults?.dbhost ?? '127.0.0.1',
        dbport: info?.db_defaults?.dbport ?? '3306',
        dbname: info?.db_defaults?.dbname ?? 'mosure',
        dbuser: info?.db_defaults?.dbuser ?? 'root',
        dbpwd: info?.db_defaults?.dbpwd ?? '',
    }), [info]);

    const [dbType, setDbType] = useState(dbDefaults.dbtype || 'sqlite');
    const [loading, setLoading] = useState(false);
    const [testResult, setTestResult] = useState<TestResult | null>(null);
    const _t = useTranslate();
    
    // 从localStorage恢复表单数据
    useEffect(() => {
        const savedData = localStorage.getItem('mosure_install_data');
        if (savedData) {
            try {
                const parsedData = JSON.parse(savedData);
                form.setFieldsValue(parsedData);
                if (parsedData.dbtype) {
                    setDbType(parsedData.dbtype);
                }
            } catch (e) {
                console.error('恢复保存的表单数据失败:', e);
            }
        } else {
            form.setFieldsValue(dbDefaults);
            setDbType(dbDefaults.dbtype || 'sqlite');
        }
    }, [dbDefaults, form]);

    const handleDbTypeChange = (value: string) => {
        setDbType(value);
    };

    // 测试数据库连接
    const testDatabaseConnection = async () => {
        try {
            const values = form.getFieldsValue(true);
            
            if (!values.dbtype) {
                message.error('请选择数据库类型');
                return;
            }
            
            if (values.dbtype === 'mysql' && (!values.dbhost || !values.dbport || !values.dbname || !values.dbuser)) {
                message.error('请填写所有必要的数据库信息');
                return;
            }
            
            setLoading(true);
            setTestResult(null);
            
            try {
                const response = await fetch(INSTALL_ROUTES.testDbConnection, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(values)
                });
                
                const data = await response.json();
                
                setTestResult({
                    success: data.code === 0,
                    message: data.message
                });
                
                if (data.code === 0) {
                    message.success(data.message);
                } else {
                    message.error(data.message);
                }
            } catch (error) {
                const errMsg = error instanceof Error ? error.message : '未知错误';
                console.error('测试数据库连接失败:', error);
                setTestResult({
                    success: false,
                    message: '测试数据库连接失败: ' + errMsg
                });
                message.error('测试数据库连接失败: ' + errMsg);
            } finally {
                setLoading(false);
            }
        } catch (error) {
            const errMsg = error instanceof Error ? error.message : '未知错误';
            console.error('测试数据库连接错误:', error);
            message.error('测试数据库连接错误: ' + errMsg);
            setLoading(false);
        }
    };

    // 保存表单数据并前往下一步
    const handleNext = () => {
        form.validateFields()
            .then(values => {
                // 存储表单数据到 localStorage，以便刷新页面后恢复
                localStorage.setItem('mosure_install_data', JSON.stringify(values));
                // 跳转到下一步
                window.location.href = '/install/step3';
            })
            .catch(errorInfo => {
                message.error('请完成所有必填字段');
            });
    };

    return (
        <div>
            <Head title="安装 - 配置设置" />
            <div style={{ maxWidth: 800, margin: '0 auto', padding: 24 }}>
                <div style={{ textAlign: 'center', marginBottom: 32 }}>
                    <Title level={2}>安装向导</Title>
                    <Text type="secondary">欢迎使用{_t('app_name')}{_t('description')}</Text>
                </div>
                
                {/* 步骤条 */}
                <Steps current={1} style={{ marginBottom: 32 }}>
                    <Step title="系统检查" />
                    <Step title="管理员和数据库设置" />
                    <Step title="安装确认" />
                </Steps>
                
                {/* 主内容区域 */}
                <Card>
                    <Spin spinning={loading} tip="正在处理，请稍候...">
                        <Form
                            form={form}
                            layout="vertical"
                            initialValues={dbDefaults}
                        >
                            <Alert
                                message="管理员和数据库设置"
                                description="请设置系统管理员账号信息和数据库配置。"
                                type="info"
                                showIcon
                                style={{ marginBottom: 24 }}
                            />

                            {info?.docker_auto_db && (
                                <Alert
                                    message="检测到 Docker 环境"
                                    description="系统已根据容器环境自动填充数据库配置，若需要可在此进行修改。"
                                    type="success"
                                    showIcon
                                    style={{ marginBottom: 24 }}
                                />
                            )}

                            <Divider orientation="left">管理员账号</Divider>
                            
                            <Form.Item
                                name="username"
                                label="用户名"
                                rules={[
                                    { required: true, message: '请输入用户名' },
                                    { min: 3, message: '用户名至少3个字符' }
                                ]}
                            >
                                <Input placeholder="管理员用户名" />
                            </Form.Item>

                            <Form.Item
                                name="password"
                                label="密码"
                                rules={[
                                    { required: true, message: '请输入密码' },
                                    { min: 6, message: '密码至少6个字符' }
                                ]}
                            >
                                <Input.Password placeholder="管理员密码" />
                            </Form.Item>

                            <Form.Item
                                name="email"
                                label="电子邮箱"
                                rules={[
                                    { required: true, message: '请输入电子邮箱' },
                                    { type: 'email', message: '请输入有效的电子邮箱' }
                                ]}
                            >
                                <Input placeholder="管理员邮箱" />
                            </Form.Item>

                            <Form.Item
                                name="name"
                                label="姓名"
                            >
                                <Input placeholder="管理员姓名（可选）" />
                            </Form.Item>

                            <Divider orientation="left">数据库配置</Divider>

                            <Form.Item
                                name="appurl"
                                label="站点访问地址"
                                rules={[{ required: true, message: '请输入站点访问地址' }]}
                                extra="上传文件等资源的访问域名，Docker 部署时请填写服务器的真实 IP 或域名地址"
                            >
                                <Input placeholder="例如: http://192.168.1.100:9445" />
                            </Form.Item>

                            <Form.Item
                                name="dbtype"
                                label="数据库类型"
                                rules={[{ required: true, message: '请选择数据库类型' }]}
                            >
                                <Select onChange={handleDbTypeChange}>
                                    {(info.database_types ?? []).map(type => (
                                        <Option key={type.value} value={type.value}>{type.label}</Option>
                                    ))}
                                </Select>
                            </Form.Item>

                            {dbType === 'mysql' && (
                                <>
                                    <Form.Item
                                        name="dbhost"
                                        label="数据库主机"
                                        rules={[{ required: dbType === 'mysql', message: '请输入数据库主机' }]}
                                    >
                                        <Input placeholder="例如: localhost 或 127.0.0.1" />
                                    </Form.Item>

                                    <Form.Item
                                        name="dbport"
                                        label="数据库端口"
                                        rules={[{ required: dbType === 'mysql', message: '请输入数据库端口' }]}
                                    >
                                        <Input placeholder="例如: 3306" />
                                    </Form.Item>

                                    <Form.Item
                                        name="dbname"
                                        label="数据库名称"
                                        rules={[{ required: dbType === 'mysql', message: '请输入数据库名称' }]}
                                    >
                                        <Input placeholder="例如: mosure" />
                                    </Form.Item>

                                    <Form.Item
                                        name="dbuser"
                                        label="数据库用户名"
                                        rules={[{ required: dbType === 'mysql', message: '请输入数据库用户名' }]}
                                    >
                                        <Input placeholder="例如: root" />
                                    </Form.Item>

                                    <Form.Item
                                        name="dbpwd"
                                        label="数据库密码"
                                        rules={[{ required: dbType === 'mysql', message: '请输入数据库密码' }]}
                                    >
                                        <Input.Password placeholder="数据库密码" />
                                    </Form.Item>

                                    <div style={{ marginTop: 16, marginBottom: 24 }}>
                                        <Button onClick={testDatabaseConnection} loading={loading}>
                                            测试数据库连接
                                        </Button>
                                        
                                        {testResult && (
                                            <Alert
                                                message={testResult.success ? "连接成功" : "连接失败"}
                                                description={testResult.message}
                                                type={testResult.success ? "success" : "error"}
                                                showIcon
                                                style={{ marginTop: 16 }}
                                            />
                                        )}
                                    </div>
                                </>
                            )}

                            <div style={{ marginTop: 24, textAlign: 'right' }}>
                                <Link href="/install/step1" style={{ marginRight: 8 }}>
                                    <Button>
                                        上一步
                                    </Button>
                                </Link>
                                <Button type="primary" onClick={handleNext}>
                                    下一步
                                </Button>
                            </div>
                        </Form>
                    </Spin>
                </Card>
                
                {/* 页脚 */}
                <div style={{ marginTop: 24, textAlign: 'center' }}>
                    <Text type="secondary">{_t('app_name')} {_t('description')} &copy; {new Date().getFullYear()}</Text>
                </div>
            </div>
        </div>
    );
}
