<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="mt-1.5 text-sm font-medium text-red-700 dark:text-red-400">
            {{ message }}
        </p>
    </div>
</template>
