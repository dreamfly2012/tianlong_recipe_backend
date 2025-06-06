import { RouteRecordRaw, Router } from 'vue-router';
import { useAuthStore } from '../stores';
import Welcome from '../pages/Welcome.vue';
import Dashboard from '../pages/Dashboard.vue';

// 认证页面组件
import Login from '../pages/auth/Login.vue';
import Register from '../pages/auth/Register.vue';
import ForgotPassword from '../pages/auth/ForgotPassword.vue';
import ResetPassword from '../pages/auth/ResetPassword.vue';
import VerifyEmail from '../pages/auth/VerifyEmail.vue';

// 设置页面
import Profile from '../pages/settings/Profile.vue';
import Password from '../pages/settings/Password.vue';
import Appearance from '../pages/settings/Appearance.vue';

export const routes: RouteRecordRaw[] = [
    {
        path: '/',
        name: 'welcome',
        component: Welcome,
        meta: { guest: true, variant: 'header' }
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: Dashboard,
        meta: { requiresAuth: true, variant: 'sidebar' }
    },
    // 认证路由
    {
        path: '/login',
        name: 'login',
        component: Login,
        meta: { guest: true, variant: 'header' }
    },
    {
        path: '/register',
        name: 'register',
        component: Register,
        meta: { guest: true, variant: 'header' }
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: ForgotPassword,
        meta: { guest: true, variant: 'header' }
    },
    {
        path: '/reset-password',
        name: 'reset-password',
        component: ResetPassword,
        meta: { guest: true, variant: 'header' },
        beforeEnter: (to, from, next) => {
            // 确保有必要的查询参数
            if (!to.query.token || !to.query.email) {
                next({ name: 'login' });
            } else {
                next();
            }
        }
    },
    {
        path: '/verify-email',
        name: 'verify-email',
        component: VerifyEmail,
        meta: { requiresAuth: true, layout: 'app' },
        beforeEnter: (to, from, next) => {
            // 如果有验证URL查询参数，尝试验证
            if (to.query.verify_url) {
                // 重定向到验证URL
                window.location.href = to.query.verify_url as string;
                return;
            }
            next();
        }
    },
    // 设置路由
    {
        path: '/settings',
        name: 'settings',
        meta: { requiresAuth: true, layout: 'app' },
        redirect: { name: 'settings.profile' },
        children: [
            {
                path: 'profile',
                name: 'settings.profile',
                component: Profile,
                meta: { requiresAuth: true, layout: 'app' }
            },
            {
                path: 'password',
                name: 'settings.password',
                component: Password,
                meta: { requiresAuth: true, layout: 'app' }
            },
            {
                path: 'appearance',
                name: 'settings.appearance',
                component: Appearance,
                meta: { requiresAuth: true, layout: 'app' }
            }
        ]
    }
];

// 导入路由守卫
import { authGuard } from './guards';

// 404页面
const NotFound = () => import('../pages/errors/NotFound.vue');

// 添加重定向路由处理常见的拼写错误
routes.push({
    path: '/dashborad',
    redirect: '/dashboard'
});

// 添加404路由
routes.push({
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: NotFound,
    meta: { variant: 'header' }
});

// 路由守卫
export function setupRouterGuards(router: Router) {
    router.beforeEach(authGuard);
}