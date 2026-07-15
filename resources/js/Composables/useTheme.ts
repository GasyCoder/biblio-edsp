import { computed, ref } from 'vue';

export type Theme = 'light' | 'dark';

const storageKey = 'biblio-edsp:theme';
const theme = ref<Theme>('light');
let initialized = false;

const applyTheme = (value: Theme) => {
    theme.value = value;
    document.documentElement.classList.toggle('dark', value === 'dark');
    document.documentElement.style.colorScheme = value;
};

export const initializeTheme = () => {
    if (initialized || typeof window === 'undefined') return;

    const saved = window.localStorage.getItem(storageKey);
    const preferred = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';

    applyTheme(saved === 'dark' || saved === 'light' ? saved : preferred);
    initialized = true;
};

export const useTheme = () => {
    initializeTheme();

    const isDark = computed(() => theme.value === 'dark');
    const toggleTheme = () => {
        const nextTheme: Theme = isDark.value ? 'light' : 'dark';
        window.localStorage.setItem(storageKey, nextTheme);
        applyTheme(nextTheme);
    };

    return { theme, isDark, toggleTheme };
};
