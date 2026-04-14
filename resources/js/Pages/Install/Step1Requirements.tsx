import React, { useState, useEffect, useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Card, Steps, Alert, List, Button, Typography, Result } from 'antd';
import { useTranslate } from '../../util/useTranslate';

const { Title, Text, Paragraph } = Typography;
const { Step } = Steps;

type RequirementItem = {
    key: string;
    title: string;
    detail?: string;
    status: boolean;
    failureHint?: string;
};

export default function Step1Requirements({ info }) {
    const _t = useTranslate();
    const [requirementsMet, setRequirementsMet] = useState(true);
    const [failedItems, setFailedItems] = useState<RequirementItem[]>([]);

    const normalizedRequirements: RequirementItem[] = useMemo(() => {
        const requirements = info?.requirements;
        if (!requirements || Object.keys(requirements).length === 0) {
            return [];
        }

        const items: RequirementItem[] = [];

        const phpRequirement = requirements.php_version;
        if (phpRequirement) {
            items.push({
                key: 'php_version',
                title: `PHP 版本 >= ${phpRequirement.required}`,
                detail: `当前：${phpRequirement.value}`,
                status: !!phpRequirement.status,
                failureHint: `当前 PHP 版本为 ${phpRequirement.value}，需要升级到 ${phpRequirement.required} 或以上。`,
            });
        }

        if (Array.isArray(requirements.extensions)) {
            requirements.extensions.forEach((extension, index) => {
                items.push({
                    key: `extension_${extension.name || index}`,
                    title: `PHP 扩展：${extension.name}`,
                    detail: extension.status ? '已启用' : '未启用',
                    status: !!extension.status,
                    failureHint: `请确保服务器已启用 ${extension.name} 扩展。`,
                });
            });
        }

        if (Array.isArray(requirements.directories)) {
            requirements.directories.forEach((directory, index) => {
                items.push({
                    key: `directory_${directory.name || index}`,
                    title: `目录可写：${directory.name}`,
                    detail: directory.status ? `${directory.path} 可写` : `${directory.path} 不可写`,
                    status: !!directory.status,
                    failureHint: `请为目录 ${directory.path} 设置写入权限（如 chmod 775）。`,
                });
            });
        }

        return items;
    }, [info]);
    
    // 检查系统要求是否满足
    useEffect(() => {
        if (!info || !info.requirements || Object.keys(info.requirements).length === 0) {
            // 如果没有要求数据，默认允许通过
            setRequirementsMet(true);
            setFailedItems([]);
            return;
        }
        
        // 检查所有要求
        const requirements = info.requirements || {};
        
        // 在开发环境中始终允许通过，以方便测试
        if (process.env.NODE_ENV === 'development') {
            setRequirementsMet(true);
            setFailedItems([]);
            return;
        }
        
        const failed = normalizedRequirements.filter(item => !item.status);
        setFailedItems(failed);
        setRequirementsMet(failed.length === 0);
    }, [info, normalizedRequirements]);

    return (
        <div>
            <Head title="安装系统要求" />
            <div style={{ maxWidth: 800, margin: '0 auto', padding: 24 }}>
                <div style={{ textAlign: 'center', marginBottom: 32 }}>
                    <Title level={2}>安装向导</Title>
                    <Text type="secondary">欢迎使用{_t('app_name')}{_t('description')}</Text>
                </div>
                
                {/* 步骤条 */}
                <Steps current={0} style={{ marginBottom: 32 }}>
                    <Step title="系统检查" />
                    <Step title="管理员和数据库设置" />
                    <Step title="安装确认" />
                </Steps>
                
                {/* 主内容区域 */}
                <Card>
                    <Alert
                        message="系统要求检查"
                        description="请确保您的系统满足以下要求，才能安装Mosure。"
                        type="info"
                        showIcon
                        style={{ marginBottom: 24 }}
                    />
                    
                    <List
                        itemLayout="horizontal"
                        dataSource={normalizedRequirements}
                        renderItem={item => (
                            <List.Item>
                                <List.Item.Meta
                                    avatar={item.status ? <span style={{ color: 'green' }}>✓</span> : <span style={{ color: 'red' }}>✗</span>}
                                    title={item.title}
                                    description={item.detail || (item.status ? '满足要求' : '不满足要求')}
                                />
                            </List.Item>
                        )}
                    />
                    
                    {!requirementsMet && (
                        <Alert
                            message="以下检查未通过，请先修复："
                            description={
                                <div>
                                    <ul style={{ paddingLeft: 20, marginBottom: 0 }}>
                                        {failedItems.map(item => (
                                            <li key={item.key}>
                                                <strong>{item.title}</strong>
                                                {item.failureHint ? ` - ${item.failureHint}` : ''}
                                            </li>
                                        ))}
                                    </ul>
                                    <Paragraph type="secondary" style={{ marginTop: 12 }}>
                                        您仍然可以继续安装，但建议先修复上述问题以避免运行异常。
                                    </Paragraph>
                                </div>
                            }
                            type="warning"
                            showIcon
                            style={{ marginTop: 24, marginBottom: 24 }}
                        />
                    )}
                    
                    <div style={{ marginTop: 24, textAlign: 'right' }}>
                        <Link href="/install/step2">
                            <Button type="primary">
                                下一步
                            </Button>
                        </Link>
                    </div>
                </Card>
                
                {/* 页脚 */}
                <div style={{ marginTop: 24, textAlign: 'center' }}>
                    <Text type="secondary">{_t('app_name')} {_t('description')} &copy; {new Date().getFullYear()}</Text>
                </div>
            </div>
        </div>
    );
}
