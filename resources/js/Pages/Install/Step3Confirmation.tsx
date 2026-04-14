import React, { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import { Card, Steps, Button, Alert, Divider, Typography, message, Spin, Result, Modal } from 'antd';
import axios from 'axios';
import { INSTALL_ROUTES } from '../../Constants/routes';

const { Title, Text, Paragraph } = Typography;
const { Step } = Steps;

// Define TypeScript interfaces for better type safety
interface FormData {
  dbtype: string;
  appurl?: string;
  dbhost?: string;
  dbport?: string;
  dbname?: string;
  dbuser?: string;
  dbpwd?: string;
  username: string;
  password: string;
  email: string;
  name?: string;
}

interface InstallResult {
  status: 'success' | 'error';
  message: string;
  details?: string | Record<string, unknown>;
}

export default function Step3Confirmation(): JSX.Element {
    const [formData, setFormData] = useState<FormData | null>(null);
    const [loading, setLoading] = useState<boolean>(false);
    const [installResult, setInstallResult] = useState<InstallResult | null>(null);
    
    // 从localStorage获取表单数据
    useEffect(() => {
        const savedData = localStorage.getItem('mosure_install_data');
        if (savedData) {
            try {
                const parsedData = JSON.parse(savedData);
                setFormData(parsedData);
            } catch (e) {
                console.error('恢复保存的表单数据失败:', e);
                message.error('无法获取安装数据，请返回上一步重新填写');
            }
        } else {
            message.error('未找到安装数据，请返回上一步填写必要信息');
        }
    }, []);

    // 检查URL中是否有安装结果参数
    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const installStatus = urlParams.get('install_status');
        const installMessage = urlParams.get('install_message');
        const installDetails = urlParams.get('install_details');
        
        if (installStatus) {
            setLoading(false);
            
            if (installStatus === 'success') {
                // 安装成功
                setInstallResult({
                    status: 'success',
                    message: installMessage || '安装成功！',
                });
                // 清除localStorage中的表单数据
                localStorage.removeItem('mosure_install_data');
            } else {
                // 安装失败
                setInstallResult({
                    status: 'error',
                    message: installMessage || '安装失败，请检查配置。',
                    details: installDetails || '无详细错误信息'
                });
            }
            
            // 清除URL参数，防止刷新页面时重复显示结果
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }, []);
    
    // 执行安装 - 使用axios发送请求
    const handleInstall = (): void => {
        if (!formData) {
            message.error('安装数据不完整，请返回上一步重新填写');
            return;
        }
        
        setLoading(true);
        
        // 准备请求数据
        const installData = {
            dbtype: formData.dbtype,
            appurl: formData.appurl || window.location.origin,
            username: formData.username,
            password: formData.password,
            email: formData.email,
            name: formData.name || ''
        };
        
        // 添加MySQL特定字段
        if (formData.dbtype === 'mysql') {
            Object.assign(installData, {
                dbhost: formData.dbhost || '',
                dbport: formData.dbport || '',
                dbname: formData.dbname || '',
                dbuser: formData.dbuser || '',
                dbpwd: formData.dbpwd || ''
            });
        }
        
        // 创建一个自定义的axios实例，不使用拦截器
        const installApi = axios.create({
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            },
            timeout: 60000 // 60秒超时
        });

        // 发送安装请求
        installApi.post(INSTALL_ROUTES.doInstall, installData)
            .then(response => {
                setLoading(false);
                
                if (response.data && response.data.code === 0) {
                    // 安装成功
                    setInstallResult({
                        status: 'success',
                        message: response.data.message || '安装成功！',
                    });
                    // 清除localStorage中的表单数据
                    localStorage.removeItem('mosure_install_data');
                } else {
                    // 安装失败
                    setInstallResult({
                        status: 'error',
                        message: response.data?.message || '安装失败，请检查配置。',
                        details: response.data?.data?.details || JSON.stringify(response.data)
                    });
                }
            })
            .catch(error => {
                console.error('安装请求错误:', error);
                setLoading(false);
                
                // 处理错误响应
                let errorMessage = '安装过程发生错误';
                let errorDetails = '';
                
                if (error.response) {
                    // 服务器响应了错误状态码
                    errorMessage = `服务器错误 (${error.response.status}): ${error.response.statusText}`;
                    try {
                        if (error.response.data) {
                            errorDetails = error.response.data.message || JSON.stringify(error.response.data);
                        }
                    } catch (e) {
                        errorDetails = '无法解析错误响应';
                    }
                } else if (error.request) {
                    // 请求发送了但没有收到响应
                    errorMessage = '服务器没有响应，请检查网络连接';
                    errorDetails = '请求超时或网络错误';
                } else {
                    // 设置请求时发生错误
                    errorMessage = error.message || '未知错误';
                    errorDetails = error.stack || '无错误详情';
                }
                
                setInstallResult({
                    status: 'error',
                    message: errorMessage,
                    details: errorDetails
                });
            });

    };

    // 点击重新安装按钮后的处理
    const handleRetry = (): void => {
        setInstallResult(null);
        setLoading(false);
    };

    // 渲染安装结果
    const renderInstallResult = (): JSX.Element | null => {
        if (!installResult) return null;
        
        return (
            <div className="install-result">
                <Result
                    status={installResult.status}
                    title={installResult.status === 'success' ? '安装成功' : '安装失败'}
                    subTitle={installResult.status === 'success' ? 
                        `${installResult.message}
您可以点击下方按钮前往登录页面` : 
                        installResult.message}
                    extra={
                        installResult.status === 'success' ? [
                            <Button 
                                type="primary" 
                                key="login" 
                                onClick={(e) => {
                                    e.preventDefault();
                                    // 使用替代方式导航，避免浏览器自动请求favicon
                                    const loginUrl = '/login';
                                    window.location.replace(loginUrl);
                                }}
                            >
                                立即前往登录
                            </Button>
                        ] : [
                            <Button type="primary" key="retry" onClick={handleRetry}>
                                重新安装
                            </Button>,
                            <Button 
                                key="debug" 
                                onClick={() => Modal.info({
                                    title: '错误详情',
                                    content: (
                                        <div style={{ maxHeight: '400px', overflow: 'auto' }}>
                                            <pre>{typeof installResult.details === 'object' 
                                                ? JSON.stringify(installResult.details, null, 2) 
                                                : installResult.details || '无详细错误信息'}</pre>
                                        </div>
                                    ),
                                    width: 800,
                                })}
                            >
                                查看详细错误
                            </Button>
                        ]
                    }
                >
                    {installResult.status === 'error' && (
                        <div style={{ textAlign: 'left', marginTop: 20, maxWidth: 800, margin: '0 auto' }}>
                            <Alert
                                message="错误详情"
                                description={
                                    <div>
                                        <p>请检查以下可能的问题：</p>
                                        <ul>
                                            <li>数据库连接信息是否正确</li>
                                            <li>数据库用户是否有足够权限</li>
                                            <li>应用目录是否有写入权限</li>
                                            <li>.env.example 文件是否存在于项目根目录</li>
                                            <li>邮箱格式是否正确</li>
                                        </ul>
                                        {installResult.details && (
                                            <div style={{ marginTop: 10, padding: 10, background: '#f5f5f5', borderRadius: 4, maxHeight: '200px', overflow: 'auto' }}>
                                                <pre style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>
                                                    {typeof installResult.details === 'object' 
                                                        ? JSON.stringify(installResult.details, null, 2) 
                                                        : installResult.details}
                                                </pre>
                                            </div>
                                        )}
                                    </div>
                                }
                                type="error"
                                showIcon
                            />
                        </div>
                    )}
                </Result>
            </div>
        );
    };

    return (
        <div>
            <Head title="安装确认 - Mosure" />
            
            <Card className="install-card" style={{ maxWidth: 800, margin: '0 auto' }}>
                <Steps current={2} style={{ marginBottom: 30 }}>
                    <Step title="环境检查" />
                    <Step title="配置信息" />
                    <Step title="安装确认" />
                </Steps>
                
                <div className="install-content">
                    {installResult ? (
                        renderInstallResult()
                    ) : (
                        <>
                            <Title level={3}>安装确认</Title>
                            <Paragraph>
                                请确认以下信息无误后，点击"开始安装"按钮开始安装Mosure系统。
                            </Paragraph>
                            
                            <Divider />
                            
                            {formData ? (
                                <>
                                    <div className="confirmation-info">
                                        <Card title="数据库信息" style={{ marginBottom: 16 }}>
                                            <p><strong>数据库类型:</strong> {formData.dbtype === 'sqlite' ? 'SQLite' : 'MySQL'}</p>
                                            {formData.dbtype === 'mysql' && (
                                                <>
                                                    <p><strong>数据库主机:</strong> {formData.dbhost}</p>
                                                    <p><strong>数据库端口:</strong> {formData.dbport}</p>
                                                    <p><strong>数据库名称:</strong> {formData.dbname}</p>
                                                    <p><strong>数据库用户:</strong> {formData.dbuser}</p>
                                                    <p><strong>数据库密码:</strong> {'*'.repeat(formData.dbpwd?.length || 0)}</p>
                                                </>
                                            )}
                                        </Card>
                                        
                                        <Card title="管理员信息222" style={{ marginBottom: 16 }}>
                                            <p><strong>用户名:</strong> {formData.username}</p>
                                            <p><strong>密码:</strong> {'*'.repeat(formData.password?.length || 0)}</p>
                                            <p><strong>邮箱:</strong> {formData.email}</p>
                                            {formData.name && <p><strong>姓名:</strong> {formData.name}</p>}
                                        </Card>
                                        
                                        <Card title="应用信息">
                                            <p><strong>应用URL:</strong> {formData.appurl || window.location.origin}</p>
                                        </Card>
                                    </div>
                                    

                                </>
                            ) : (
                                <div className="loading-data">
                                    <Spin tip="正在加载安装数据...">
                                        <Alert
                                            message="正在加载"
                                            description="正在从本地存储加载安装数据，请稍候..."
                                            type="info"
                                        />
                                    </Spin>
                                </div>
                            )}
                            
                            <Divider />
                            
                            <div className="action-buttons" style={{ textAlign: 'center', marginTop: 24 }}>
                                <Button 
                                    type="default" 
                                    style={{ marginRight: 16 }}
                                    onClick={(e) => {
                                        e.preventDefault();
                                        window.location.replace('/install/step2');
                                    }}
                                >
                                    返回上一步
                                </Button>
                                <Button 
                                    type="primary" 
                                    onClick={handleInstall}
                                    loading={loading}
                                    disabled={!formData}
                                    htmlType="submit"
                                >
                                    开始安装
                                </Button>
                            </div>
                        </>
                    )}
                </div>
            </Card>
        </div>
    );
}
