import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth'; // 添加导入

const routes = [
    {
        path: '/',
        redirect: { name: 'recipes' }
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/Auth/Login.vue'),
        meta: { guest: true }
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('@/views/Auth/Register.vue'),
        meta: { guest: true }
    },
    {
        path: '/recipes',
        name: 'recipes',
        component: () => import('@/views/Recipe/Index.vue'),
        meta: { requiresAuth: true }
    },
    {
        path: '/recipes/create',
        name: 'recipe.create',
        component: () => import('@/views/Recipe/Form.vue'),
        meta: { requiresAuth: true, mode: 'create' }
    },
    {
        path: '/recipes/:id/edit',
        name: 'recipe.edit',
        component: () => import('@/views/Recipe/Form.vue'),
        meta: { requiresAuth: true, mode: 'edit' },
        props: true
    },
    {
        path: '/recipes/:id',
        name: 'recipe.show',
        component: () => import('@/views/Recipe/Show.vue'),
        props: true,
        meta: { requiresAuth: true } // 添加此行，要求身份验证
    },
    {
        path: '/not-found',
        name: 'not-found',
        component: () => import('@/views/NotFound.vue')
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/not-found'
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    linkActiveClass: 'active',
    linkExactActiveClass: 'exact-active'
});

// 添加全局路由守卫
router.beforeEach((to, from, next) => {
    const authStore = useAuthStore();
    // 修复：使用 isAuthenticated 计算属性替代直接访问 api_token
    const isAuthenticated = authStore.isAuthenticated;

    // 未登录用户访问需要认证的页面
    if (to.meta.requiresAuth && !isAuthenticated) {
        return next({
            name: 'login',
            query: { redirect: to.fullPath } // 保存当前路径用于登录后重定向
        });
    }

    // 已登录用户访问登录/注册页
    if (to.meta.guest && isAuthenticated) {
        return next({ name: 'recipes' });
    }

    next();
});

export default router;
