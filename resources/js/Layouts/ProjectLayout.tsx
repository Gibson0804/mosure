import React, { ReactNode } from 'react';
import { CodepenCircleOutlined } from '@ant-design/icons';
import { Col, Row, Layout, theme } from 'antd';
import { NavLogo, NavUser } from './NavBase';
import useFlashMessage from '../hooks/useFlashMessage';

const { Content } = Layout;

const ProjectLayout = ({ children }: { children: ReactNode }) => {
  useFlashMessage();

  const {
    token: { colorBgContainer },
  } = theme.useToken();

  return (
    <div style={{ minHeight: '100vh' }}>
      {/* 顶部导航栏 - 只显示Logo和用户信息 */}
          <Layout style={{ background: '#fff', display: 'block', padding: 0, alignItems: 'center' }}>
              <Row>
                  <Col flex='250px' >
                      <NavLogo />
                  </Col>
                  <Col flex='auto' />
                  <Col flex='350px' >
                      <div style={{ display: 'flex', justifyContent: 'flex-end', alignItems: 'center', height: '46px' }}>
                          <NavUser showBackButton={false} />
                      </div>
                  </Col>
              </Row>
          </Layout>

      {/* 内容区域 - 占满整个页面 */}
      <Content style={{ padding: '0', background: colorBgContainer }}>
        {children}
      </Content>
    </div>
  );
};


export default ProjectLayout;
