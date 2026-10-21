import axios, { AxiosInstance, AxiosError, AxiosResponse } from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useLoadingStore } from '@/stores/loading';

// 创建 axios 实例
const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  timeout: 10000,
  headers: {
    Accept: 'application/json',
  },
  withCredentials: true,
});

// 请求拦截器
api.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore();
    const loadingStore = useLoadingStore();

    if (authStore.user?.api_token) {
      config.headers.Authorization = `Bearer ${authStore.user.api_token}`;
    }

    loadingStore.startLoading();

    return config;
  },
  (error: AxiosError) => {
    useLoadingStore().stopLoading();
    return Promise.reject(error);
  }
);

// 响应拦截器
api.interceptors.response.use(
  (response: AxiosResponse) => {
    useLoadingStore().stopLoading();
    return response;
  },
  (error: AxiosError) => {
    useLoadingStore().stopLoading();

    // 处理 401 未授权：清理本地登录态并回到登录页
    if (error.response?.status === 401) {
      useAuthStore().clearUser();

      if (typeof window !== 'undefined' && window.location.pathname !== '/login') {
        const redirect = encodeURIComponent(
          `${window.location.pathname}${window.location.search}`
        );
        window.location.assign(`/login?redirect=${redirect}`);
      }
    }

    return Promise.reject(error);
  }
);

export default api;
