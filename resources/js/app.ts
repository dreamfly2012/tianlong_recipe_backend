import '../css/app.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createRouter, createWebHistory } from 'vue-router';
import type { Router } from 'vue-router';
import App from './App.vue';
import { routes, setupRouterGuards } from './router';
import { initializeTheme } from './composables/useAppearance';
import axios from 'axios';


// 配置 axios
axios.defaults.baseURL = '/api';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;

// 创建应用实例
const app = createApp(App);
const pinia = createPinia();

// 初始化 pinia
app.use(pinia);

// 创建路由
const router = createRouter({
    history: createWebHistory(),
    routes
});

// 设置路由守卫
setupRouterGuards(router);

// 挂载应用
app.use(router).mount('#app');

// 初始化主题
initializeTheme();