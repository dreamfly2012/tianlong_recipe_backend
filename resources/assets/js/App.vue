<template>
  <div class="min-h-screen bg-gray-100">
    <!-- 导航栏 -->
    <AppNavbar />
    
    <!-- 通知消息 -->
    <AppNotification />
    
    <!-- 全局加载状态 -->
    <AppLoading />
    
    <!-- 页面内容 -->
    <main class="container mx-auto px-4 py-6">
      <router-view v-slot="{ Component, route }">
        <transition name="fade" mode="out-in">
          <component :is="Component" :key="route.path" />
        </transition>
      </router-view>
    </main>
    
    <!-- 页脚 -->
    <footer class="bg-white shadow-inner mt-12 py-6">
      <div class="container mx-auto px-4 text-center text-gray-600">
        &copy; {{ new Date().getFullYear() }} Recipe Box. All rights reserved.
      </div>
    </footer>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useFlash } from '@/composables/useFlash';
import AppNavbar from '@/components/AppNavbar.vue';
import AppNotification from '@/components/AppNotification.vue';
import AppLoading from '@/components/AppLoading.vue';

const router = useRouter();
const authStore = useAuthStore();
const { showError } = useFlash();

// 全局错误处理
const handleError = (error) => {
  console.error('App error:', error);
  
  // 处理 HTTP 错误
  if (error.response) {
    const { status, data } = error.response;
    
    // 未授权错误
    if (status === 401) {
      authStore.logout();
      router.push({ name: 'login' });
      showError('Your session has expired. Please log in again.');
      return;
    }
    
    // 验证错误
    if (status === 422 && data.errors) {
      const errors = Object.values(data.errors).flat();
      errors.forEach(msg => showError(msg));
      return;
    }
    
    // 其他服务器错误
    showError(data.message || 'An error occurred. Please try again.');
  } else if (error.request) {
    // 请求已发出但没有响应
    showError('Network error. Please check your connection.');
  } else {
    // 其他错误
    showError(error.message || 'An unexpected error occurred.');
  }
};

// 组件挂载时初始化
defineOptions({
  inheritAttrs: false,
  name: 'App'
});

// 设置全局错误处理和初始化
onMounted(() => {
  // 优先初始化认证状态
  authStore.initialize();
  
  // 全局错误处理
  const handleGlobalError = (event) => {
    if (event instanceof PromiseRejectionEvent) {
      handleError(event.reason);
    } else if (event instanceof ErrorEvent) {
      handleError(event.error);
    } else {
      handleError(event);
    }
  };
  
  window.addEventListener('unhandledrejection', handleGlobalError);
  window.addEventListener('error', handleGlobalError);
  
  // 清理函数
  return () => {
    window.removeEventListener('unhandledrejection', handleGlobalError);
    window.removeEventListener('error', handleGlobalError);
  };
});
</script>

<style>
/* 全局过渡效果 */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* 平滑滚动 */
html {
  scroll-behavior: smooth;
}

/* 移除按钮和链接的默认样式 */
button,
a {
  -webkit-tap-highlight-color: transparent;
}

/* 优化字体渲染 */
body {
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-rendering: optimizeLegibility;
}

/* 响应式容器 */
.container {
  width: 100%;
  margin-right: auto;
  margin-left: auto;
  padding-right: 1rem;
  padding-left: 1rem;
}

@media (min-width: 640px) {
  .container {
    max-width: 640px;
  }
}

@media (min-width: 768px) {
  .container {
    max-width: 768px;
  }
}

@media (min-width: 1024px) {
  .container {
    max-width: 1024px;
  }
}

@media (min-width: 1280px) {
  .container {
    max-width: 1280px;
  }
}
</style>
