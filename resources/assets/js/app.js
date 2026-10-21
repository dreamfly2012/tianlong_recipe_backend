import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import api from './helpers/api';
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
