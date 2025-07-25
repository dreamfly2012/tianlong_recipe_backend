import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import api from './helpers/api';
import { useAuthStore } from './stores/auth';
import { useLoadingStore } from './stores/loading';
import '../../css/app.css';

const app = createApp(App);

// 注册全局 API
app.config.globalProperties.$api = api;

// 使用 Pinia
const pinia = createPinia();
app.use(pinia);

// 使用路由
app.use(router);

// 全局错误处理
app.config.errorHandler = (err, vm, info) => {
  console.error('Vue error:', err);
  console.error('Error in component:', vm?.$options?.name || 'Unknown');
  console.error('Error info:', info);
};

// 路由守卫
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();
  const loadingStore = useLoadingStore();
  
  // 显示加载状态
  loadingStore.startLoading('Loading...');
  
  try {
    // 如果用户已登录但用户数据未加载，则获取用户数据
    if (authStore.isAuthenticated && !authStore.user) {
      await authStore.fetchUser();
    }
    
    // 检查是否需要认证
    if (to.matched.some(record => record.meta.requiresAuth) && !authStore.isAuthenticated) {
      next({ name: 'login', query: { redirect: to.fullPath } });
      return;
    }
    
    // 如果用户已登录但访问的是登录/注册页面，则重定向到首页
    if (to.matched.some(record => record.meta.guest) && authStore.isAuthenticated) {
      next({ name: 'home' });
      return;
    }
    
    next();
  } catch (error) {
    console.error('Router error:', error);
    next({ name: 'not-found' });
  } finally {
    // 确保加载状态被清除
    loadingStore.stopLoading();
  }
});

// 路由导航完成后
router.afterEach(() => {
  // 确保加载状态被清除
  const loadingStore = useLoadingStore();
  loadingStore.stopLoading();
  
  // 滚动到页面顶部
  window.scrollTo(0, 0);
});

// 挂载应用
app.mount('#root');
