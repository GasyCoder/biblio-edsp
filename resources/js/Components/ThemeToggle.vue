<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import { useTheme } from '@/Composables/useTheme';

const { isDark, toggleTheme } = useTheme();
</script>

<template>
    <button
        type="button"
        class="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 transition hover:bg-gray-100 hover:text-primary-600 dark:text-slate-300 dark:hover:bg-gray-900 dark:hover:text-primary-400"
        :aria-label="isDark ? 'Activer le thème clair' : 'Activer le thème sombre'"
        :title="isDark ? 'Mode clair' : 'Mode sombre'"
        @click="toggleTheme"
    >
        <AppIcon :name="isDark ? 'sun' : 'moon'" class="h-[1.125rem] w-[1.125rem]" />
    </button>
</template>
