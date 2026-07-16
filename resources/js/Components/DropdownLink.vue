<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    href: string;
}>();
</script>

<template>
    <Link
        :href="href"
        class="block w-full px-4 py-2 text-start text-sm leading-5 text-slate-600 transition duration-150 ease-in-out hover:bg-gray-50 focus:bg-gray-50 focus:outline-none dark:text-slate-300 dark:hover:bg-gray-900 dark:focus:bg-gray-900"
    >
        <slot />
    </Link>
</template>
