<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps<{ counts: { categories: number; authors: number; locations: number } }>();
const page = usePage();
const cards = [
    { key: 'categories', label: 'Catégories', description: 'Classement thématique et codes d’inventaire des ouvrages.', icon: 'books', route: 'categories.index', permission: 'categories.view' },
    { key: 'authors', label: 'Auteurs', description: 'Autorités bibliographiques associées au catalogue.', icon: 'users', route: 'authors.index', permission: 'authors.view' },
    { key: 'locations', label: 'Emplacements', description: 'Armoires, étagères et autres rangements physiques.', icon: 'copies', route: 'locations.index', permission: 'locations.view' },
] as const;
</script>

<template>
    <Head title="Référentiels" />
    <AuthenticatedLayout>
        <template #header><div><p class="dw-page-kicker">Bibliothèque</p><h1 class="dw-page-title">Référentiels</h1><p class="dw-page-description">Choisissez le référentiel que vous souhaitez administrer.</p></div></template>
        <div class="grid gap-5 md:grid-cols-3">
            <Link v-for="card in cards.filter(item => page.props.auth.permissions.includes(item.permission))" :key="card.key" :href="route(card.route)" class="dw-card group p-6 transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md dark:hover:border-primary-800">
                <span class="flex h-12 w-12 items-center justify-center rounded-md bg-primary-50 text-primary-600 dark:bg-primary-950/50 dark:text-primary-300"><AppIcon :name="card.icon" class="h-6 w-6" /></span>
                <div class="mt-5 flex items-end justify-between gap-4"><div><h2 class="font-heading text-lg font-bold">{{ card.label }}</h2><p class="mt-2 text-sm leading-6 text-slate-500">{{ card.description }}</p></div><strong class="font-heading text-3xl font-bold text-primary-600">{{ props.counts[card.key] }}</strong></div>
                <span class="mt-5 inline-flex items-center text-xs font-bold text-primary-600">Gérer {{ card.label.toLowerCase() }} <span class="ms-2 transition group-hover:translate-x-1">→</span></span>
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
