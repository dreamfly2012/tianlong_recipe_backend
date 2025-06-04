import { NavigationGuardNext, RouteLocationNormalized } from 'vue-router';
import { useAuthStore } from '../stores/auth';

export async function authGuard(
    to: RouteLocationNormalized,
    from: RouteLocationNormalized,
    next: NavigationGuardNext
) {
    const authStore = useAuthStore();

    try {
        // 如果用户未登录，尝试获取用户信息
        if (!authStore.isAuthenticated) {
            await authStore.fetchUser();
        }

        // 如果需要认证且用户未登录，重定向到登录页面
        if (to.meta.requiresAuth && !authStore.isAuthenticated) {
            next({
                name: 'login',
                query: { redirect: to.fullPath }
            });
            return;
        }

        // 如果需要验证邮箱且用户未验证，重定向到验证页面
        if (to.meta.requiresVerification && !authStore.isVerified) {
            next({ name: 'verify-email' });
            return;
        }

        // 如果是游客页面且用户已登录，重定向到首页
        if (to.meta.guest && authStore.isAuthenticated) {
            next({ name: 'home' });
            return;
        }

        next();
    } catch (error) {
        // 如果获取用户信息失败且路由需要认证，重定向到登录页面
        if (to.meta.requiresAuth) {
            next({
                name: 'login',
                query: { redirect: to.fullPath }
            });
            return;
        }
        next();
    }
}