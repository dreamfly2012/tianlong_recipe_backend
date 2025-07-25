import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/helpers/api';
import { useFlash } from '@/composables/useFlash';

interface User {
  id: number;
  name: string;
  email: string;
  api_token?: string;
  is_admin: boolean;
}

export const useAuthStore = defineStore('auth', () => {
  const router = useRouter();
  const { showSuccess, showError } = useFlash();

  const user = ref<User | null>(null);
  const isLoading = ref(false);

  const isAuthenticated = computed(() => !!user.value?.api_token);

  const initialize = () => {
    try {
      const storedUser = localStorage.getItem('user');
      if (storedUser) {
        user.value = JSON.parse(storedUser);
      }
    } catch (error) {
      console.error('Failed to initialize auth:', error);
      localStorage.removeItem('user');
    }
  };

  const login = async (credentials: { email: string; password: string }) => {
    try {
      isLoading.value = true;
      const response = await api.post('/login', credentials);
      setUser(response.data);
      showSuccess('Login successful');
      router.push('/recipes');
      return true;
    } catch (error: any) {
      const message = error.response?.data?.message || 'Login failed';
      showError(message);
      throw error;
    } finally {
      isLoading.value = false;
    }
  };

  const register = async (userData: { name: string; email: string; password: string }) => {
    try {
      isLoading.value = true;
      const response = await api.post('/register', userData);
      setUser(response.data);
      showSuccess('Registration successful');
      router.push('/login');
      return true;
    } catch (error: any) {
      const message = error.response?.data?.message || 'Registration failed';
      showError(message);
      throw error;
    } finally {
      isLoading.value = false;
    }
  };

  const logout = async () => {
    try {
      await api.post('/logout');
      showSuccess('Logged out successfully');
    } catch (error) {
      console.error('Logout error:', error);
      showError('Failed to logout properly');
    } finally {
      clearUser();
      router.push('/login');
    }
  };

  const setUser = (userData: User) => {
    user.value = userData;
    localStorage.setItem('user', JSON.stringify(userData));
    setAuthHeader(userData.api_token);
  };

  const clearUser = () => {
    user.value = null;
    localStorage.removeItem('user');
    delete api.defaults.headers.common['Authorization'];
  };

  const setAuthHeader = (token?: string) => {
    if (token) {
      api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
  };

  // Initialize auth state
  if (typeof window !== 'undefined') {
    initialize();
  }

  return {
    user,
    isAuthenticated,
    isLoading: computed(() => isLoading.value),
    login,
    register,
    logout,
    initialize
  };
});
