<script setup lang="ts">
defineProps<{ name: string }>();
</script>

<template>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <template v-if="name === 'dashboard'"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></template>
        <template v-else-if="name === 'scan'"><path d="M4 7V4h3M17 4h3v3M20 17v3h-3M7 20H4v-3"/><path d="M7 9h10M7 12h10M7 15h6"/></template>
        <template v-else-if="name === 'students' || name === 'users'"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></template>
        <template v-else-if="name === 'books' || name === 'book-open'"><path d="M3 5.5A3.5 3.5 0 0 1 6.5 2H11v17H6.5A3.5 3.5 0 0 0 3 22z"/><path d="M21 5.5A3.5 3.5 0 0 0 17.5 2H13v17h4.5A3.5 3.5 0 0 1 21 22z"/></template>
        <template v-else-if="name === 'copies'"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h5"/></template>
        <template v-else-if="name === 'visits' || name === 'activity'"><path d="M3 12h4l2-5 4 10 2-5h6"/></template>
        <template v-else-if="name === 'loans'"><path d="M7 3h10v4H7zM5 7h14v14H5z"/><path d="M9 11h6M9 15h4"/></template>
        <template v-else-if="name === 'reports'"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/></template>
        <template v-else-if="name === 'settings'"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.4-2.4 1a8 8 0 0 0-1.7-1L14.5 3h-5l-.4 3a8 8 0 0 0-1.7 1L5 6.1 3 9.5 5.1 11a7 7 0 0 0 0 2L3 14.5 5 18l2.4-1a8 8 0 0 0 1.7 1l.4 3h5l.4-3a8 8 0 0 0 1.7-1l2.4 1 2-3.5-2.1-1.5a7 7 0 0 0 .1-1Z"/></template>
        <template v-else-if="name === 'menu'"><path d="M4 6h16M4 12h16M4 18h16"/></template>
        <template v-else-if="name === 'search'"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></template>
        <template v-else-if="name === 'bell'"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M13.7 21h-3.4"/></template>
        <template v-else-if="name === 'sun'"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></template>
        <template v-else-if="name === 'moon'"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></template>
        <template v-else-if="name === 'chevron-down'"><path d="m6 9 6 6 6-6"/></template>
        <template v-else-if="name === 'logout'"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></template>
        <template v-else-if="name === 'user'"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></template>
        <template v-else-if="name === 'clock'"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></template>
        <template v-else-if="name === 'eye'"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12"/><circle cx="12" cy="12" r="2.5"/></template>
        <template v-else><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 4.2A10.7 10.7 0 0 1 12 4c6.5 0 10 8 10 8a18 18 0 0 1-2.1 3.1M6.6 6.6C3.6 8.6 2 12 2 12s3.5 8 10 8a10 10 0 0 0 5.4-1.6"/></template>
    </svg>
</template>
