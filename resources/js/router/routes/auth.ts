import { RouteRecordRaw } from 'vue-router';

// 认证相关组件
const Login = () => import('../../pages/auth/Login.vue');
const Register = () => import('../../pages/auth/Register.vue');
const ForgotPassword = () => import('../../pages/auth/ForgotPassword.vue');
const ResetPassword = () => import('../../pages/auth/ResetPassword.vue');
const VerifyEmail = () => import('../../pages/auth/VerifyEmail.vue');

export const authRoutes: RouteRecordRaw[] = [
    {
        path: '/login',
        name: 'login',
        component: Login,
        meta: {
            guest: true,
            layout: 'guest'
        }
    },
    {
        path: '/register',
        name: 'register',
        component: Register,
        meta: {
            guest: true,
            layout: 'guest'
        }
    },
    {
        path: '/forgot-password',
        name: 'forgot-password',
        component: ForgotPassword,
        meta: {
            guest: true,
            layout: 'guest'
        }
    },
    {
        path: '/reset-password/:token',
        name: 'reset-password',
        component: ResetPassword,
        meta: {
            guest: true,
            layout: 'guest'
        }
    },
    {
        path: '/verify-email',
        name: 'verify-email',
        component: VerifyEmail,
        meta: {
            requiresAuth: true,
            layout: 'app'
        }
    }
];