import { defineStore } from 'pinia';

type Theme = 'light' | 'dark' | 'system';

interface ThemeState {
    theme: Theme;
    systemTheme: 'light' | 'dark';
}

export const useThemeStore = defineStore('theme', {
    state: (): ThemeState => ({
        theme: 'system',
        systemTheme: 'light'
    }),

    getters: {
        currentTheme(): 'light' | 'dark' {
            if (this.theme === 'system') {
                return this.systemTheme;
            }
            return this.theme;
        },
        isDark(): boolean {
            return this.currentTheme === 'dark';
        }
    },

    actions: {
        init() {
            // 从 localStorage 读取主题设置
            const savedTheme = localStorage.getItem('theme') as Theme | null;
            if (savedTheme) {
                this.theme = savedTheme;
            }

            // 检测系统主题
            if (window.matchMedia) {
                const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');
                this.systemTheme = darkQuery.matches ? 'dark' : 'light';

                // 监听系统主题变化
                darkQuery.addEventListener('change', (e) => {
                    this.systemTheme = e.matches ? 'dark' : 'light';
                    this.applyTheme();
                });
            }

            this.applyTheme();
        },

        setTheme(newTheme: Theme) {
            this.theme = newTheme;
            localStorage.setItem('theme', newTheme);
            this.applyTheme();
        },

        applyTheme() {
            const isDark = this.currentTheme === 'dark';
            document.documentElement.classList.toggle('dark', isDark);
        }
    }
});