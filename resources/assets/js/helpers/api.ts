import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse, AxiosError } from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useLoadingStore } from '@/stores/loading';

// 创建 axios 实例
const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  timeout: 10000,
  headers: {
    'Accept': 'application/json',
  },
  withCredentials: true,
});

// 请求拦截器
api.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore();
    const loadingStore = useLoadingStore();
    
    // 添加认证头
    if (authStore.isAuthenticated && authStore.user?.api_token) {
      config.headers.Authorization = `Bearer ${authStore.user.api_token}`;
    }
    
    // 显示加载状态
    loadingStore.startLoading();
    
    return config;
  },
  (error: AxiosError) => {
    const loadingStore = useLoadingStore();
    loadingStore.stopLoading();
    return Promise.reject(error);
  }
);

// 响应拦截器
api.interceptors.response.use(
  (response: AxiosResponse) => {
    const loadingStore = useLoadingStore();
    loadingStore.stopLoading();
    return response;
  },
  (error: AxiosError) => {
    const loadingStore = useLoadingStore();
    loadingStore.stopLoading();
    
    // 处理 401 未授权
    if (error.response?.status === 401) {
      const authStore = useAuthStore();
      authStore.logout();
    }
    
    return Promise.reject(error);
  }
);

export default api;
