import React from 'react';
import { Modal, Typography, Divider, Alert, Space } from 'antd';
import { QuestionCircleOutlined } from '@ant-design/icons';

const { Title, Paragraph, Text } = Typography;

interface CorsHelpModalProps {
  visible: boolean;
  onClose: () => void;
}

const CorsHelpModal: React.FC<CorsHelpModalProps> = ({ visible, onClose }) => {
  return (
    <Modal
      title="CORS 跨域配置帮助"
      open={visible}
      onCancel={onClose}
      footer={null}
      width={700}
    >
      <Typography>
        <Title level={4}>什么是 CORS？</Title>
        <Paragraph>
          CORS（跨域资源共享）是一种浏览器安全机制，用于控制不同域之间的资源访问。
          当前端应用（如网站）尝试从与其自身不同的域名、协议或端口请求资源时，浏览器会实施同源策略限制。
          CORS 允许服务器声明哪些源可以访问其资源，从而安全地进行跨域请求。
        </Paragraph>

        <Divider />
        
        <Title level={4}>CORS 配置选项</Title>
        <Paragraph>
          <Space direction="vertical" style={{ width: '100%' }}>
            <Alert
              message="启用 CORS"
              description="开启后，允许跨域请求访问 API。关闭后，所有非同源请求都将被拒绝。仅影响 /open 开头的接口。"
              type="info"
              showIcon
            />
            
            <Alert
              message="允许的 Origin"
              description={<>
                <p>指定允许访问 API 的域名列表。每行一个域名，格式如：</p>
                <ul>
                  <li><Text code>http://example.com</Text> - 精确匹配单个域名</li>
                  <li><Text code>https://*.example.com</Text> - 使用通配符匹配子域名</li>
                </ul>
                <p><strong>留空表示允许所有域名访问</strong>（即返回 Access-Control-Allow-Origin: *）</p>
              </>}
              type="info"
              showIcon
            />
          </Space>
        </Paragraph>

        <Divider />
        
        <Title level={4}>常见配置示例</Title>
        <Paragraph>
          <Space direction="vertical" style={{ width: '100%' }}>
            <Alert
              message="允许所有域名访问（默认）"
              description="不添加任何域名，系统将允许任何网站访问 API。"
              type="success"
              showIcon
            />
            
            <Alert
              message="仅允许特定域名"
              description={<>
                添加您的网站域名，如：
                <ul>
                  <li><Text code>https://www.yoursite.com</Text></li>
                  <li><Text code>http://localhost:3000</Text> (开发环境)</li>
                </ul>
              </>}
              type="success"
              showIcon
            />
            
            <Alert
              message="允许所有子域名"
              description={<>
                使用通配符匹配所有子域名：
                <ul>
                  <li><Text code>https://*.yoursite.com</Text></li>
                </ul>
              </>}
              type="success"
              showIcon
            />
          </Space>
        </Paragraph>

        <Divider />
        
        <Title level={4}>注意事项</Title>
        <Paragraph>
          <Space direction="vertical" style={{ width: '100%' }}>
            <Alert
              message="安全性考虑"
              description="为提高安全性，建议仅允许您信任的域名访问 API，避免使用通配符 * 允许所有域名。"
              type="warning"
              showIcon
            />
            
            <Alert
              message="协议敏感"
              description="http:// 和 https:// 被视为不同的源，如需同时支持，请分别添加。"
              type="warning"
              showIcon
            />
            
            <Alert
              message="端口敏感"
              description="不同端口被视为不同的源，如 http://localhost:3000 和 http://localhost:9445 需要分别添加。"
              type="warning"
              showIcon
            />
          </Space>
        </Paragraph>
      </Typography>
    </Modal>
  );
};

export default CorsHelpModal;
