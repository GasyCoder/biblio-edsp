<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import InputError from '@/Components/InputError.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{ categories: any[]; filters: { search: string } }>();
const page = usePage();
const canCreate = page.props.auth.permissions.includes('categories.create');
const canUpdate = page.props.auth.permissions.includes('categories.update');
const canDelete = page.props.auth.permissions.includes('catalog.manage');
const form = useForm({ name: '', inventory_code: '', description: '' });
const editing = ref<any | null>(null);
const selected = ref<number[]>([]);
const search = ref(props.filters.search);
const allSelected = computed(() => props.categories.length > 0 && props.categories.every(item => selected.value.includes(item.id)));
const toggleAll = () => selected.value = allSelected.value ? [] : props.categories.map(item => item.id);
const create = () => form.post(route('categories.store'), { onSuccess: () => form.reset() });
const save = () => editing.value && router.patch(route('categories.update', editing.value.id), editing.value, { onSuccess: () => editing.value = null });
const remove = (item: any) => confirm(`Supprimer la catégorie « ${item.name} » ?`) && router.delete(route('categories.destroy', item.id));
const removeSelected = () => selected.value.length && confirm(`Supprimer ${selected.value.length} catégorie(s) ?`) && router.delete(route('categories.destroy.bulk'), { data: { ids: selected.value }, onSuccess: () => selected.value = [] });
const find = () => router.get(route('categories.index'), { search: search.value }, { preserveState: true, replace: true });
</script>

<template><Head title="Catégories"/><AuthenticatedLayout>
    <template #header><div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><Link :href="route('catalog-references.index')" class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">← Référentiels</Link><h1 class="dw-page-title">Catégories</h1><p class="dw-page-description">Classement des ouvrages et génération des préfixes d’inventaire.</p></div></div></template>
    <div v-if="$page.props.flash?.success" class="mb-5 rounded-md bg-emerald-50 p-4 text-sm text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ $page.props.flash.success }}</div>
    <InputError class="mb-4" :message="$page.props.errors?.category"/>
    <div class="grid gap-6 xl:grid-cols-[360px_1fr]">
        <section v-if="canCreate" class="dw-card self-start p-5"><h2 class="text-lg font-bold">Nouvelle catégorie</h2><form class="mt-5 space-y-4" @submit.prevent="create"><div><label class="mb-2 block text-sm">Nom *</label><input v-model="form.name" class="dw-field" required/><InputError :message="form.errors.name"/></div><div><label class="mb-2 block text-sm">Code inventaire</label><input v-model="form.inventory_code" class="dw-field uppercase" maxlength="10" placeholder="Automatique"/><InputError :message="form.errors.inventory_code"/></div><div><label class="mb-2 block text-sm">Description</label><textarea v-model="form.description" class="dw-field"/></div><button class="dw-btn-primary w-full">Ajouter la catégorie</button></form></section>
        <section class="dw-card overflow-hidden"><div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-900"><div><h2 class="text-lg font-bold">Liste des catégories</h2><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ categories.length }} résultat(s)</p></div><button v-if="selected.length" class="dw-btn-secondary text-red-600" @click="removeSelected"><AppIcon name="trash" class="h-4 w-4"/> Supprimer ({{ selected.length }})</button></div><form class="flex gap-2 border-b border-gray-200 p-4 dark:border-gray-900" @submit.prevent="find"><div class="relative flex-1"><AppIcon name="search" class="absolute start-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500 dark:text-slate-400"/><input v-model="search" class="dw-field ps-10" placeholder="Rechercher par nom, code ou description…"/></div><button class="dw-btn-primary">Rechercher</button></form><div class="overflow-x-auto"><table class="dw-table min-w-[700px]"><thead><tr><th v-if="canDelete" class="w-12 p-4"><input type="checkbox" :checked="allSelected" @change="toggleAll"/></th><th class="p-4 text-start">Catégorie</th><th class="p-4 text-start">Code</th><th class="p-4 text-center">Ouvrages</th><th class="p-4 text-center">Actions</th></tr></thead><tbody><tr v-for="item in categories" :key="item.id"><td v-if="canDelete" class="p-4 text-center"><input v-model="selected" type="checkbox" :value="item.id"/></td><td class="p-4"><template v-if="editing?.id === item.id"><input v-model="editing.name" class="dw-field h-9"/></template><div v-else><strong class="text-slate-700 dark:text-white">{{ item.name }}</strong><p class="mt-1 max-w-md truncate text-xs text-slate-500 dark:text-slate-400">{{ item.description || 'Sans description' }}</p></div></td><td class="p-4"><input v-if="editing?.id === item.id" v-model="editing.inventory_code" class="dw-field h-9 w-28 uppercase"/><span v-else class="font-mono text-xs font-bold text-primary-600">{{ item.inventory_code }}</span></td><td class="p-4 text-center">{{ item.books_count }}</td><td class="p-4"><div class="flex justify-center gap-1"><template v-if="editing?.id === item.id"><button class="h-9 w-9 text-emerald-600" @click="save"><AppIcon name="check" class="mx-auto h-4 w-4"/></button><button class="h-9 w-9 text-slate-500 dark:text-slate-400" @click="editing=null"><AppIcon name="close" class="mx-auto h-4 w-4"/></button></template><template v-else><button v-if="canUpdate" class="h-9 w-9 text-amber-600" @click="editing={...item}"><AppIcon name="edit" class="mx-auto h-4 w-4"/></button><button v-if="canDelete" class="h-9 w-9 text-red-600" @click="remove(item)"><AppIcon name="trash" class="mx-auto h-4 w-4"/></button></template></div></td></tr><tr v-if="!categories.length"><td :colspan="canDelete?5:4" class="p-10 text-center text-slate-500 dark:text-slate-400">Aucune catégorie trouvée.</td></tr></tbody></table></div></section>
    </div>
</AuthenticatedLayout></template>
