<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Student { id: number; registration_number: string; academic_number: string | null; last_name: string; first_name: string; level: string | null; program: string | null; status: string }
interface PageLink { url: string | null; label: string; active: boolean }
const props = defineProps<{ students: { data: Student[]; links: PageLink[]; from: number | null; to: number | null; total: number }; filters: { search: string } }>();
const search = ref(props.filters.search);
const page = usePage();
const canCreate = page.props.auth.permissions.includes('students.manage');
const canImport = page.props.auth.permissions.includes('imports.view');
const canUpdate = page.props.auth.permissions.includes('students.update');
const canDelete = page.props.auth.permissions.includes('students.manage');
const remove = (student:Student) => { if (confirm(`Supprimer ${student.last_name} ${student.first_name} ?`)) router.delete(route('students.destroy',student.id)); };
const submitSearch = () => router.get(route('students.index'), { search: search.value }, { preserveState: true, replace: true });
const statusLabel: Record<string, string> = { active: 'Actif', inactive: 'Inactif', suspended: 'Suspendu', graduated: 'Diplômé' };
</script>

<template>
    <Head title="Étudiants" />
    <AuthenticatedLayout>
        <template #header><div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="mb-1 text-xs font-bold uppercase tracking-[0.16em] text-primary-600">Référentiel</p><h1 class="font-heading text-2xl font-bold text-slate-800">Gestion des étudiants</h1><p class="mt-2 text-sm text-slate-500">Recherchez par matricule, numéro interne, nom ou prénom.</p></div><div class="flex flex-wrap gap-2"><a v-if="canImport" :href="route('student-exports.xlsx')" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-200 px-4 text-sm font-bold text-slate-600">Exporter Excel</a><Link v-if="canImport" :href="route('student-imports.index')" class="inline-flex h-10 items-center justify-center rounded-md border border-primary-200 px-4 text-sm font-bold text-primary-600">Importer</Link><Link v-if="canCreate" :href="route('students.create')" class="inline-flex h-10 items-center justify-center rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700">Ajouter un étudiant</Link></div></div></template>

        <div v-if="$page.props.flash?.success" class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ $page.props.flash.success }}</div>
        <section class="dw-card overflow-hidden">
            <div class="border-b border-slate-100 p-4 sm:p-5"><form class="flex max-w-xl gap-2" @submit.prevent="submitSearch"><div class="relative flex-1"><AppIcon name="search" class="absolute start-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"/><input v-model="search" class="dw-field ps-11" placeholder="Rechercher un étudiant…" /></div><button class="rounded-md bg-slate-800 px-4 text-sm font-bold text-white hover:bg-slate-900">Rechercher</button></form></div>
            <div class="overflow-x-auto"><table class="w-full min-w-[950px] text-start text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-400"><tr><th class="px-5 py-3 text-start">Étudiant</th><th class="px-5 py-3 text-start">N° interne</th><th class="px-5 py-3 text-start">Matricule</th><th class="px-5 py-3 text-start">Niveau / parcours</th><th class="px-5 py-3 text-start">Statut</th><th class="px-5 py-3 text-center">Actions</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="student in students.data" :key="student.id" class="hover:bg-slate-50/70"><td class="px-5 py-4 font-semibold text-slate-700">{{ student.last_name }} {{ student.first_name }}</td><td class="px-5 py-4 font-mono text-xs text-primary-700">{{ student.registration_number }}</td><td class="px-5 py-4 text-slate-500">{{ student.academic_number || '—' }}</td><td class="px-5 py-4 text-slate-500">{{ [student.level, student.program].filter(Boolean).join(' · ') || '—' }}</td><td class="px-5 py-4"><span :class="student.status === 'active' ? 'bg-emerald-50 text-emerald-700' : student.status === 'suspended' ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-600'" class="rounded-full px-2.5 py-1 text-xs font-bold">{{ statusLabel[student.status] }}</span></td><td class="px-5 py-3"><div class="flex justify-center gap-1"><Link v-if="canUpdate" :href="route('students.edit',student.id)" class="inline-flex h-9 w-9 items-center justify-center rounded-md text-amber-600 hover:bg-amber-50" title="Modifier" aria-label="Modifier l’étudiant"><AppIcon name="edit" class="h-4 w-4"/></Link><button v-if="canDelete" class="inline-flex h-9 w-9 items-center justify-center rounded-md text-red-600 hover:bg-red-50" title="Supprimer" aria-label="Supprimer l’étudiant" @click="remove(student)"><AppIcon name="trash" class="h-4 w-4"/></button></div></td></tr><tr v-if="!students.data.length"><td colspan="6" class="px-5 py-16 text-center text-sm text-slate-400">Aucun étudiant trouvé.</td></tr></tbody></table></div>
            <div v-if="students.total" class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-slate-400">Résultats {{ students.from }}–{{ students.to }} sur {{ students.total }}</p><div class="flex flex-wrap gap-1"><template v-for="link in students.links" :key="link.label"><Link v-if="link.url" :href="link.url" preserve-scroll preserve-state :class="link.active ? 'bg-primary-600 text-white' : 'border border-slate-200 bg-white text-slate-500 hover:bg-slate-50'" class="min-w-9 rounded px-3 py-2 text-center text-xs" v-html="link.label"/><span v-else class="min-w-9 rounded border border-slate-100 px-3 py-2 text-center text-xs text-slate-300" v-html="link.label"/></template></div></div>
        </section>
    </AuthenticatedLayout>
</template>
