import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import api from '@/helpers/api';
import { useFlash } from '@/composables/useFlash';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  is_admin?: boolean;
  api_token?: string;
  [key: string]: unknown;
}

interface AuthPayload {
  user: AuthUser;
  api_token: string;
  token: string;
}

export const useAuthStore = defineStore('auth', () => {
  const { showSuccess, showError } = useFlash();

  const user = ref<AuthUser | null>(null);
  const isLoading = ref(false);

  const isAuthenticated = computed(() => Boolean(user.value?.api_token));

  const setAuthHeader = (token?: string) => {
    if (token) {
      api.defaults.headers.common.Authorization = `Bearer ${token}`;
    } else {
      delete api.defaults.headers.common.Authorization;
    }
  };

  const setUser = (userData: AuthUser, token?: string) => {
    const storedUser: AuthUser = {
      ...userData,
      api_token: token ?? userData.api_token,
    };

    user.value = storedUser;
    localStorage.setItem('user', JSON.stringify(storedUser));
    setAuthHeader(storedUser.api_token);
  };

  const clearUser = () => {
    user.value = null;
    localStorage.removeItem('user');
    setAuthHeader(undefined);
  };

  const initialize = () => {
    if (typeof window === 'undefined') {
      return;
    }

    try {
      const storedUser = localStorage.getItem('user');
      if (!storedUser) {
        return;
      }

      const parsed = JSON.parse(storedUser) as AuthUser;

      if (!parsed?.api_token) {
        localStorage.removeItem('user');
        return;
      }

      user.value = parsed;
      setAuthHeader(parsed.api_token);
    } catch (error) {
      console.error('Failed to initialize auth:', error);
      localStorage.removeItem('user');
    }
  };

  const login = async (credentials: { email: string; password: string }): Promise<AuthPayload> => {
    try {
      isLoading.value = true;
      const response = await api.post('/login', credentials);
      const payload = response.data?.data as AuthPayload;

      setUser(payload.user, payload.api_token);
      showSuccess(response.data?.message || '登录成功');

      return payload;
    } catch (error: any) {
      const message = error.response?.data?.message || '登录失败';
      showError(message);
      throw error;
    } finally {
      isLoading.value = false;
    }
  };

  const register = async (userData: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
  }): Promise<AuthPayload> => {
    try {
      isLoading.value = true;
      const response = await api.post('/register', userData);
      const payload = response.data?.data as AuthPayload;

      setUser(payload.user, payload.api_token);
      showSuccess(response.data?.message || '注册成功');

      return payload;
    } catch (error: any) {
      const message = error.response?.data?.message || '注册失败';
      showError(message);
      throw error;
    } finally {
      isLoading.value = false;
    }
  };

  const logout = async () => {
    try {
      await api.post('/logout');
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      clearUser();
      showSuccess('已退出登录');
    }
  };

  // 应用启动时从 localStorage 恢复登录态
  initialize();

  return {
    user,
    isAuthenticated,
    isLoading: computed(() => isLoading.value),
    login,
    register,
    logout,
    initialize,
    setUser,
    clearUser,
  };
});
