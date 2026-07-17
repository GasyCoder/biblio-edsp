<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps<{ studentGroups: { data: any[]; links: any[]; total: number }; filters: { search: string; status: string; from: string; to: string }; stats: { active: number; today: number; closedToday: number; total: number }; ownOnly: boolean }>();
const filters = reactive({ ...props.filters });
const applyFilters = () => router.get(route('visits.index'), filters, { preserveState: true, replace: true });
const setStatus = (status: string) => { filters.status = status; applyFilters(); };
const reset = () => { Object.assign(filters, { search: '', status: '', from: '', to: '' }); applyFilters(); };
const duration = (visit: any) => {
    const end = visit.checked_out_at ? new Date(visit.checked_out_at) : new Date();
    const minutes = Math.max(0, Math.floor((end.getTime() - new Date(visit.checked_in_at).getTime()) / 60000));
    return minutes < 60 ? `${minutes} min` : `${Math.floor(minutes / 60)} h ${minutes % 60} min`;
};
const roleLabel = (role?: string) => ({ superadmin: 'Super administrateur', secretaire: 'Secrétaire', etudiant: 'Étudiant' }[role || ''] || role || 'Rôle non archivé');
const operatorRole = (visit: any, direction: 'in' | 'out') => {
    const snapshot = direction === 'in' ? visit.checked_in_role : visit.checked_out_role;
    const operator = direction === 'in' ? visit.checked_in_by : visit.checked_out_by;
    return roleLabel(snapshot || operator?.roles?.[0]?.name);
};
const operatorLabel = (visit: any, direction: 'in' | 'out') => {
    const operator = direction === 'in' ? visit.checked_in_by : visit.checked_out_by;
    const role = direction === 'in' ? visit.checked_in_role : visit.checked_out_role;
    if (role === 'superadmin' || operator?.roles?.[0]?.name === 'superadmin') return 'SuperAdmin';
    return operator?.name || 'Compte inconnu';
};
</script>

<template>
    <Head :title="ownOnly ? 'Mes présences' : 'Présences'" />
    <AuthenticatedLayout>
        <template #header><div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"><div><p class="dw-page-kicker">Suivi de fréquentation</p><h1 class="dw-page-title">{{ ownOnly ? 'Mes présences' : 'Présences' }}</h1><p class="dw-page-description">Les passages répétés sont regroupés par étudiant pour une lecture plus claire.</p></div><div v-if="!ownOnly" class="flex flex-wrap gap-2"><a :href="route('visits.export.xlsx', filters)" class="dw-btn-secondary inline-flex items-center gap-2"><AppIcon name="reports" class="h-4 w-4"/> Excel</a><a :href="route('visits.export.pdf', filters)" class="dw-btn-secondary inline-flex items-center gap-2"><AppIcon name="reports" class="h-4 w-4"/> PDF</a><a :href="route('visits.print', filters)" target="_blank" class="dw-btn-primary inline-flex items-center gap-2"><AppIcon name="print" class="h-4 w-4"/> Imprimer</a></div></div></template>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <button v-for="metric in [{ label: 'Présents actuellement', value: stats.active, icon: 'visits', status: 'active', tone: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950' }, { label: 'Entrées aujourd’hui', value: stats.today, icon: 'scan', status: '', tone: 'text-primary-600 bg-primary-50 dark:bg-primary-950' }, { label: 'Sorties aujourd’hui', value: stats.closedToday, icon: 'visits', status: 'closed', tone: 'text-cyan-600 bg-cyan-50 dark:bg-cyan-950' }, { label: 'Présences enregistrées', value: stats.total, icon: 'reports', status: '', tone: 'text-slate-600 bg-slate-100 dark:bg-slate-800' }]" :key="metric.label" type="button" class="dw-card flex items-center gap-4 p-5 text-start hover:border-primary-300" @click="setStatus(metric.status)"><span class="flex h-11 w-11 items-center justify-center rounded-lg" :class="metric.tone"><AppIcon :name="metric.icon" class="h-5 w-5"/></span><span><strong class="block text-2xl text-slate-700 dark:text-white">{{ metric.value }}</strong><span class="mt-1 block text-xs text-slate-500">{{ metric.label }}</span></span></button>
        </div>

        <section class="dw-card mt-6 p-4 sm:p-5"><form class="grid gap-3 lg:grid-cols-[minmax(240px,1fr)_170px_160px_160px_auto]" @submit.prevent="applyFilters"><div class="relative"><AppIcon name="search" class="absolute start-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500 dark:text-slate-400"/><input v-model="filters.search" class="dw-field ps-11" placeholder="Nom, numéro, matricule…"/></div><select v-model="filters.status" class="dw-field"><option value="">Tous les statuts</option><option value="active">Présents</option><option value="closed">Sortis</option></select><input v-model="filters.from" type="date" class="dw-field" title="Date de début"/><input v-model="filters.to" type="date" class="dw-field" title="Date de fin"/><div class="flex gap-2"><button class="dw-btn-primary justify-center">Filtrer</button><button type="button" class="dw-btn-secondary" title="Réinitialiser" @click="reset">×</button></div></form></section>

        <section class="mt-6 space-y-3">
            <details v-for="student in studentGroups.data" :key="student.id" class="dw-card group overflow-hidden" :open="student.active_visits_count > 0">
                <summary class="flex cursor-pointer list-none flex-wrap items-center gap-4 p-4 marker:hidden sm:p-5">
                    <span v-if="student.active_visits_count" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950" title="Présence active"><AppIcon name="visits" class="h-5 w-5"/></span>
                    <img loading="lazy" decoding="async" v-if="student.photo_url" :src="student.photo_url" class="h-12 w-10 shrink-0 rounded object-cover" alt=""/><span v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300">{{ student.first_name[0] }}{{ student.last_name[0] }}</span>
                    <span class="min-w-0 flex-1"><span class="flex flex-wrap items-center gap-2"><strong class="truncate text-sm text-slate-700 dark:text-slate-200">{{ student.last_name }} {{ student.first_name }}</strong><span v-if="student.active_visits_count" class="rounded-full bg-emerald-50 px-2 py-1 text-[11px] font-bold uppercase text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Présent maintenant</span></span><span class="mt-1 block font-mono text-xs text-primary-600">{{ student.registration_number }}</span></span>
                    <span class="hidden text-end sm:block"><strong class="block text-sm text-slate-700 dark:text-slate-200">{{ student.matching_visits_count }} passage(s)</strong><span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">Dernier : {{ new Date(student.latest_visit_at).toLocaleDateString('fr-FR') }}</span></span>
                    <Link v-if="!ownOnly && student.active_visits_count" :href="route('desk.index', { q: student.registration_number })" class="rounded-md bg-primary-600 px-3 py-2 text-xs font-bold text-white" @click.stop>Gérer au comptoir</Link>
                    <AppIcon name="chevron-down" class="h-5 w-5 shrink-0 text-slate-500 dark:text-slate-400 transition group-open:rotate-180"/>
                </summary>

                <div class="border-t border-gray-200 bg-gray-50/60 dark:border-gray-900 dark:bg-gray-1000/40">
                    <div class="grid grid-cols-[18px_1fr] gap-x-3 p-4 sm:p-5">
                        <template v-for="(visit, index) in student.visits" :key="visit.id">
                            <div class="relative flex justify-center"><span class="mt-1.5 h-2.5 w-2.5 rounded-full ring-4" :class="visit.checked_out_at ? 'bg-slate-400 ring-slate-100 dark:ring-slate-800' : 'bg-emerald-500 ring-emerald-100 dark:ring-emerald-950'"/><span v-if="index < student.visits.length - 1" class="absolute bottom-[-20px] top-4 w-px bg-gray-300 dark:bg-gray-800"/></div>
                            <div class="mb-4 grid gap-4 rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-950 lg:grid-cols-[1fr_1fr_100px_110px] lg:items-center">
                                <div><p class="font-mono text-xs font-bold text-primary-600">{{ visit.visit_number }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Entrée : {{ new Date(visit.checked_in_at).toLocaleString('fr-FR') }}</p><div v-if="!ownOnly" class="mt-2 flex items-center gap-1.5" :title="`Compte d’entrée · ${operatorRole(visit, 'in')}`"><AppIcon name="user" class="h-3.5 w-3.5 text-emerald-600"/><span class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ operatorLabel(visit, 'in') }}</span></div></div>
                                <div><p v-if="visit.checked_out_at" class="text-xs text-slate-500">Sortie : {{ new Date(visit.checked_out_at).toLocaleString('fr-FR') }}</p><span v-else class="text-xs font-bold text-emerald-600">Toujours présent</span><div v-if="!ownOnly && visit.checked_out_at" class="mt-2 flex items-center gap-1.5" :title="`Compte de sortie · ${operatorRole(visit, 'out')}`"><AppIcon name="user" class="h-3.5 w-3.5 text-red-500"/><span class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ operatorLabel(visit, 'out') }}</span></div></div>
                                <span class="text-xs font-bold text-slate-600 dark:text-slate-300">{{ duration(visit) }}</span><span class="text-xs text-slate-500">{{ visit.consultation_sessions_count }} consultation(s)</span>
                            </div>
                        </template>
                    </div>
                </div>
            </details>
            <div v-if="!studentGroups.data.length" class="dw-card p-12 text-center"><AppIcon name="visits" class="mx-auto h-10 w-10 text-slate-300"/><p class="mt-3 text-sm font-bold text-slate-600">Aucune présence trouvée</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Modifiez les filtres pour afficher d’autres passages.</p></div>
        </section>

        <div v-if="studentGroups.links?.length > 3" class="mt-5 flex flex-wrap justify-center gap-1"><Link v-for="link in studentGroups.links" :key="link.label" :href="link.url || '#'" class="rounded-md border px-3 py-2 text-xs font-semibold transition-colors" :class="link.active ? 'border-primary-600 bg-primary-600 text-white shadow-sm' : link.url ? 'border-gray-300 bg-white text-slate-600 hover:border-primary-300 hover:text-primary-600 dark:border-gray-800 dark:bg-gray-950 dark:text-slate-300 dark:hover:border-primary-700 dark:hover:text-primary-400' : 'pointer-events-none border-gray-200 text-slate-400 dark:border-gray-900 dark:text-slate-600'" v-html="link.label"/></div>
    </AuthenticatedLayout>
</template>
