import { Layout } from 'antd';
import React, { ReactNode } from 'react';
import useFlashMessage from '../hooks/useFlashMessage';

const BaseLayout= ({ children }: { children: ReactNode }) => {
  useFlashMessage();


  // 只做最基础的容器和全局功能，具体结构由子Layout决定
  return (
    <Layout style={{ minHeight: '100vh', background: '#fff' }}>
      {children}
    </Layout>
  );
};

export default BaseLayout;