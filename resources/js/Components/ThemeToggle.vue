<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import { useTheme } from '@/Composables/useTheme';

const { isDark, toggleTheme } = useTheme();
</script>

<template>
    <button
        type="button"
        class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-primary-600 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-primary-300"
        :aria-label="isDark ? 'Activer le thème clair' : 'Activer le thème sombre'"
        :title="isDark ? 'Mode clair' : 'Mode sombre'"
        @click="toggleTheme"
    >
        <AppIcon :name="isDark ? 'sun' : 'moon'" class="h-5 w-5" />
    </button>
</template>
