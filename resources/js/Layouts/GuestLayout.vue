<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col items-center bg-gray-50 px-4 pt-8 transition-colors sm:justify-center sm:pt-0 dark:bg-gray-1000"
    >
        <div class="absolute end-5 top-5"><ThemeToggle /></div>
        <div>
            <Link href="/">
                <ApplicationLogo class="h-16 w-16 text-primary-600" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden rounded-md border border-gray-300 bg-white px-6 py-6 shadow-sm sm:max-w-md sm:px-8 dark:border-gray-900 dark:bg-gray-950"
        >
            <slot />
        </div>
    </div>
</template>
