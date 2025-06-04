import { computed } from 'vue';
import { useThemeStore } from '../stores/theme';

export type Theme = 'light' | 'dark' | 'system';

export function initializeTheme() {
    const themeStore = useThemeStore();
    themeStore.init();
}

export function useAppearance() {
    const themeStore = useThemeStore();

    return {
        appearance: computed(() => themeStore.theme),
        currentTheme: computed(() => themeStore.currentTheme),
        isDark: computed(() => themeStore.isDark),
        updateAppearance: (value: Theme) => themeStore.setTheme(value),
    };
}