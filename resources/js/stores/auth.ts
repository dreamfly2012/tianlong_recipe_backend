import { defineStore } from 'pinia';
import axios from 'axios';

interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
}

interface AuthState {
    user: User | null;
    isAuthenticated: boolean;
}

export const useAuthStore = defineStore('auth', {
    state: (): AuthState => ({
        user: null,
        isAuthenticated: false
    }),

    getters: {
        currentUser: (state) => state.user,
        isVerified: (state) => state.user?.email_verified_at !== null
    },

    actions: {
        async login(email: string, password: string, remember: boolean = false) {
            await axios.get('/sanctum/csrf-cookie');
            
            await axios.post('/login', {
                email,
                password,
                remember
            });

            await this.fetchUser();
        },

        async register(name: string, email: string, password: string, password_confirmation: string) {
            await axios.get('/sanctum/csrf-cookie');
            
            await axios.post('/register', {
                name,
                email,
                password,
                password_confirmation
            });

            await this.fetchUser();
        },

        async logout() {
            await axios.post('/logout');
            
            this.user = null;
            this.isAuthenticated = false;
        },

        async fetchUser() {
            try {
                const response = await axios.get('/api/user');
                this.user = response.data;
                this.isAuthenticated = true;
            } catch (error) {
                this.user = null;
                this.isAuthenticated = false;
                throw error;
            }
        },

        async forgotPassword(email: string) {
            await axios.post('/forgot-password', { email });
        },

        async resetPassword(token: string, email: string, password: string, password_confirmation: string) {
            await axios.post('/reset-password', {
                token,
                email,
                password,
                password_confirmation
            });
        },

        async resendVerification() {
            await axios.post('/email/verification-notification');
        },

        async verifyEmail(id: string, hash: string) {
            await axios.get(`/verify-email/${id}/${hash}`);
            await this.fetchUser();
        },

        async updateProfile(data: Partial<User>) {
            await axios.patch('/user/profile-information', data);
            await this.fetchUser();
        },

        async updatePassword(data: { current_password: string; password: string; password_confirmation: string }) {
            await axios.put('/user/password', data);
        }
    }
});