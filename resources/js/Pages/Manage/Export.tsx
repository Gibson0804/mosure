import React, { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Card, Typography, Form, Checkbox, Button, Row, Col, Divider, message, Space, Upload, Modal, Table } from 'antd';
import { FileTextOutlined, CloudServerOutlined, SettingOutlined, FolderOpenOutlined, ExportOutlined, ImportOutlined, UploadOutlined, InboxOutlined } from '@ant-design/icons';
import { EXPORT_ROUTES } from '../../Constants/routes';
import api from '../../util/Service';
import type { UploadFile } from 'antd/es/upload/interface';

interface PageProps {
  models: { id: number; name: string; slug: string }[];
  functions: {
    endpoints: { id: number; name: string; slug: string }[];
    hooks: { id: number; name: string; slug: string }[];
  };
  mediaFolders: any[];
  menus: any[];
}

const ExportPage: React.FC = () => {
  const page = usePage<{ props: PageProps }>();
  const { models, functions, mediaFolders, menus } = page.props as any as PageProps;
  const [loading, setLoading] = useState<boolean>(false);
  const [importLoading, setImportLoading] = useState<boolean>(false);
  const [importModalVisible, setImportModalVisible] = useState<boolean>(false);
  const [fileList, setFileList] = useState<UploadFile[]>([]);
  const [conflictData, setConflictData] = useState<any>(null);
  const [importOptions, setImportOptions] = useState<Record<string, string>>({});

  const allIds = models?.map(m => m.id) || [];
  const [form] = Form.useForm();
  const [checkedList, setCheckedList] = useState<number[]>([]);

  const isAllChecked = checkedList.length > 0 && checkedList.length === allIds.length;
  const isIndeterminate = checkedList.length > 0 && checkedList.length < allIds.length;

  // 初始化模型数据导出选项
  const [modelDataOptions, setModelDataOptions] = useState<Record<number, boolean>>({});

  // 初始化时设置所有模型的数据导出选项为 false
  React.useEffect(() => {
    const initialOptions: Record<number, boolean> = {};
    models?.forEach(m => {
      initialOptions[m.id] = false;
    });
    setModelDataOptions(initialOptions);
  }, [models]);

  // 扁平化媒体文件夹树
  const flattenMediaFolders = (folders: any[]): { label: string; value: number }[] => {
    const result: { label: string; value: number }[] = [];
    
    const traverse = (items: any[], prefix = '') => {
      items.forEach((item) => {
        const label = prefix ? `${prefix} / ${item.name}` : item.name;
        result.push({ label, value: item.id });
        
        if (item.children && item.children.length > 0) {
          traverse(item.children, label);
        }
      });
    };
    
    traverse(folders);
    return result;
  };

  const onToggleAll = (checked: boolean) => {
    form.setFieldsValue({ modelIds: checked ? allIds : [] });
    setCheckedList(checked ? allIds : []);
  };

  const handleModelCheck = (modelId: number, checked: boolean) => {
    const newCheckedList = checked 
      ? [...checkedList, modelId]
      : checkedList.filter(id => id !== modelId);
    setCheckedList(newCheckedList);
    
    // 如果取消选择模型，也取消选择数据
    if (!checked) {
      setModelDataOptions(prev => ({ ...prev, [modelId]: false }));
    }
  };

  const onExport = () => {
    setLoading(true);

    // 获取表单值
    const values = form.getFieldsValue();

    // 构建模型数据导出选项
    const modelDataMap: Record<number, boolean> = {};
    checkedList.forEach((modelId: number) => {
      modelDataMap[modelId] = modelDataOptions[modelId] || false;
    });

    // 合并表单数据，使用 checkedList 替换 modelIds
    const exportData = {
      ...values,
      modelIds: checkedList,
      modelDataMap,
    };

    // 以表单为准
    api.post(EXPORT_ROUTES.export, exportData)
    .then((res) => {
      setLoading(false);
      // 后端通过 success() 返回时，可能是 { data: {...} } 或直接 {...}
      const payload = (res?.data && (res.data.data ?? res.data)) || {} as any;
      const url = payload.download_url || payload.path; // 新版使用 download_url（签名临时链接）

      if (url) {
        // 触发下载
        window.location.href = url;
        message.success('导出成功，开始下载...');
      } else {
        console.warn('Export API response missing download URL:', res?.data);
        message.error('导出成功但未获取到下载链接');
      }
    })
    .catch((err) => {
      setLoading(false);
      message.error('导出失败');
    });

  };

  const onImport = () => {
    setImportModalVisible(true);
  };

  const handleImportFileChange = (info: any) => {
    setFileList(info.fileList.slice(-1)); // 只保留最后一个文件
  };

  const handleImportSubmit = () => {
    if (fileList.length === 0) {
      message.error('请选择要导入的文件');
      return;
    }

    const formData = new FormData();
    formData.append('file', fileList[0].originFileObj as File);

    setImportLoading(true);
    api.post('/manage/import/parse', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    .then((res) => {
      setImportLoading(false);
      const payload = res?.data ?? {};
      
      if (payload.conflicts && Object.keys(payload.conflicts).length > 0) {
        // 检查是否有实际的冲突项（非空数组）
        const hasActualConflicts = Object.values(payload.conflicts).some((items: any) => 
          Array.isArray(items) && items.length > 0
        );
        
        
        if (hasActualConflicts) {
          // 有冲突，显示冲突处理对话框
          setConflictData(payload);
        } else {
          // 无实际冲突，直接导入
          confirmImport();
        }
      } else {
        // 无冲突，直接导入
        confirmImport();
      }
    })
    .catch((err) => {
      setImportLoading(false);
      message.error('解析文件失败: ' + (err.message || ''));
    });
  };

  const confirmImport = () => {
    if (fileList.length === 0) {
      message.error('请选择要导入的文件');
      return;
    }

    const formData = new FormData();
    formData.append('file', fileList[0].originFileObj as File);

    // 添加导入选项
    if (Object.keys(importOptions).length > 0) {
      formData.append('options', JSON.stringify(importOptions));
    }

    setImportLoading(true);

    api.post('/manage/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })
    .then((res) => {
      setImportLoading(false);
      message.success('导入成功');
      setImportModalVisible(false);
      setFileList([]);
      setConflictData(null);
      setImportOptions({});
      // 导入成功后重新载入页面
      window.location.reload();
    })
    .catch((err) => {
      setImportLoading(false);
      message.error('导入失败: ' + (err.message || ''));
    });
  };

  const handleConflictOptionChange = (type: string, key: string, value: string) => {
    setImportOptions(prev => ({
      ...prev,
      [`${type}_${key}`]: value,
    }));
  };

  return (
    <div style={{ padding: 24 }}>
      <Card
        title={<><ExportOutlined /> 项目导出/导入</>}
        extra={
          <Space>
            <Button
              icon={<ImportOutlined />}
              onClick={onImport}
            >
              导入
            </Button>
            <Button
              type="primary"
              size="large"
              icon={<ExportOutlined />}
              loading={loading}
              onClick={() => form.submit()}
            >
              开始导出
            </Button>
          </Space>
        }
      >
        <Typography.Text type="secondary" style={{ display: 'block', marginBottom: 16 }}>
          选择需要导出的内容，系统将生成可导入的压缩包
        </Typography.Text>

        <Form
          form={form}
          onFinish={onExport}
          initialValues={{
            modelIds: [],
            includeMenus: [],
            includeFunctions: {
              endpoints: [],
              hooks: [],
              variables: false,
              triggers: false,
              schedules: false,
            },
            mediaFolders: []
          }}
        >
        <Row gutter={[16, 16]}>
          {/* 模型选择 */}
          <Col span={24}>
            <Card 
              title={<><FileTextOutlined /> 内容模型</>}
              extra={
                <Checkbox
                  indeterminate={isIndeterminate}
                  checked={isAllChecked}
                  onChange={(e) => onToggleAll(e.target.checked)}
                >
                  全选
                </Checkbox>
              }
            >
              <Form.Item 
                name="modelIds" 
                style={{ marginBottom: 0 }}
              >
                <div style={{ width: '100%' }}>
                  {(models || []).map((m) => (
                    <div key={m.id} style={{ marginBottom: 12, paddingBottom: 12, borderBottom: '1px solid #f0f0f0' }}>
                      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                          <Checkbox
                            value={m.id}
                            checked={checkedList.includes(m.id)}
                            onChange={(e) => handleModelCheck(m.id, e.target.checked)}
                          >
                            {m.name}
                          </Checkbox>
                        </div>
                        <Checkbox
                          checked={modelDataOptions[m.id] || false}
                          onChange={(e) => {
                            setModelDataOptions(prev => ({ ...prev, [m.id]: e.target.checked }));
                          }}
                          disabled={!checkedList.includes(m.id)}
                        >
                          包含数据
                        </Checkbox>
                      </div>
                    </div>
                  ))}
                </div>
              </Form.Item>
            </Card>
          </Col>

          {/* 云函数 */}
          <Col xs={24} lg={12}>
            <Card title={<><CloudServerOutlined /> 云函数</>}>
              <Space direction="vertical" style={{ width: '100%' }} size="middle">
                {/* Web函数 */}
                <div>
                  <div style={{ marginBottom: 8, fontSize: 14, fontWeight: 500 }}>Web函数</div>
                  <Form.Item name={['includeFunctions', 'endpoints']} style={{ marginBottom: 0 }}>
                    <Checkbox.Group
                      style={{ width: '100%' }}
                      options={functions?.endpoints?.map(fn => ({ label: fn.name, value: fn.id })) || []}
                    />
                  </Form.Item>
                </div>
                <Divider style={{ margin: '12px 0' }} />
                {/* 触发函数 */}
                <div>
                  <div style={{ marginBottom: 8, fontSize: 14, fontWeight: 500 }}>触发函数</div>
                  <Form.Item name={['includeFunctions', 'hooks']} style={{ marginBottom: 0 }}>
                    <Checkbox.Group
                      style={{ width: '100%' }}
                      options={functions?.hooks?.map(fn => ({ label: fn.name, value: fn.id })) || []}
                    />
                  </Form.Item>
                </div>
                <Divider style={{ margin: '12px 0' }} />
                {/* 其他选项 */}
                <Space direction="vertical" style={{ width: '100%' }} size="small">
                  <Form.Item name={['includeFunctions', 'variables']} valuePropName="checked" style={{ marginBottom: 0 }}>
                    <Checkbox>配置变量</Checkbox>
                  </Form.Item>
                  <Form.Item name={['includeFunctions', 'triggers']} valuePropName="checked" style={{ marginBottom: 0 }}>
                    <Checkbox>触发器配置</Checkbox>
                  </Form.Item>
                  <Form.Item name={['includeFunctions', 'schedules']} valuePropName="checked" style={{ marginBottom: 0 }}>
                    <Checkbox>定时任务</Checkbox>
                  </Form.Item>
                </Space>
              </Space>
            </Card>
          </Col>

          {/* 右侧列 */}
          <Col xs={24} lg={12}>
            {/* 媒体文件夹 todo::暂时注释掉，等确定存储用哪家*/}
            {/* <Card title={<><FolderOpenOutlined /> 媒体文件夹</>} style={{ marginBottom: 16 }}>
              <Form.Item name="mediaFolders" style={{ marginBottom: 0 }}>
                <Checkbox.Group
                  style={{ width: '100%' }}
                  options={flattenMediaFolders(mediaFolders || [])}
                />
              </Form.Item>
            </Card> */}

            {/* 用户菜单 */}
            <Card title={<><SettingOutlined /> 用户菜单</>}>
              <Form.Item name="includeMenus" style={{ marginBottom: 0 }}>
                <Checkbox.Group
                  style={{ width: '100%' }}
                  options={menus?.map((menu: any) => ({ label: menu.title, value: menu.id })) || []}
                />
              </Form.Item>
            </Card>
          </Col>
        </Row>
      </Form>
    </Card>

    {/* 导入对话框 */}
    <Modal
      title="导入项目"
      open={importModalVisible}
      onCancel={() => {
        setImportModalVisible(false);
        setFileList([]);
        setConflictData(null);
        setImportOptions({});
      }}
      footer={[
        <Button key="cancel" onClick={() => setImportModalVisible(false)}>
          取消
        </Button>,
        <Button
          key="submit"
          type="primary"
          loading={importLoading}
          onClick={conflictData ? confirmImport : handleImportSubmit}
          disabled={fileList.length === 0}
        >
          {conflictData ? '确认导入' : '开始导入'}
        </Button>,
      ]}
      width={800}
    >
      {!conflictData ? (
        <>
          <p style={{ marginBottom: 16 }}>请选择要导入的项目导出文件（.zip）：</p>
          <Upload.Dragger
            fileList={fileList}
            onChange={handleImportFileChange}
            beforeUpload={() => false}
            accept=".zip"
            maxCount={1}
          >
            <p className="ant-upload-drag-icon">
              <InboxOutlined />
            </p>
            <p className="ant-upload-text">点击或拖拽 ZIP 文件到此处</p>
            <p className="ant-upload-hint">选择由本系统导出的项目文件进行导入</p>
          </Upload.Dragger>
          <p style={{ marginTop: 16, color: '#999' }}>
            注意：导入时会自动检测名称冲突，确认导入可能会导致失败。
          </p>
        </>
      ) : (
        <div>
          <p style={{ marginBottom: 16, color: '#faad14' }}>检测到以下名称冲突，请手动处理，确认导入则直接创建，可能会导致失败：</p>
          {conflictData.conflicts && (
            <Table
              dataSource={Object.entries(conflictData.conflicts).map(([key, items]: [string, any]) => ({
                key,
                type: key,
                items: items.join(', '),
              }))}
              columns={[
                {
                  title: '类型',
                  dataIndex: 'type',
                  key: 'type',
                  width: 120,
                },
                {
                  title: '冲突项',
                  dataIndex: 'items',
                  key: 'items',
                },
              ]}
              pagination={false}
              size="small"
            />
          )}
        </div>
      )}
    </Modal>
  </div>
);
};

export default function Page() {
  return <ExportPage />;
}
