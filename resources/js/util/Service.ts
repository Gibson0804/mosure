import axios, { type AxiosInstance } from 'axios';

// 创建axios实例
const api: AxiosInstance = axios.create({
  timeout: 15000,
  withCredentials: true,
});

// 响应拦截器
api.interceptors.response.use((response) => {
    const res = response.data;

    if (res.code !== 0) {
      return Promise.reject(res);
    }

    return res;
  },
  error => {
    console.error('Response error:', error);
    return Promise.reject(error);
  }
);

export default api;
