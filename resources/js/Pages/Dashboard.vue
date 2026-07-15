<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Metric { label: string; value: number; icon: string; tone: string; detail: string }
interface QuickAction { label: string; description: string; icon: string; permission: string; available: boolean; href: string | null }
interface Alert { title: string; message: string; level: string }

const props = defineProps<{ dashboard: { role: string | null; metrics: Metric[]; quickActions: QuickAction[]; alerts: Alert[] } }>();
const page = usePage();
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);
const today = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());

const roleLabel = computed(() => props.dashboard.role === 'superadmin' ? 'Super administrateur' : props.dashboard.role === 'secretaire' ? 'Secrétaire' : props.dashboard.role === 'etudiant' ? 'Espace étudiant' : 'Compte actif');
const activityTitle = computed(() => props.dashboard.role === 'superadmin' ? 'Déploiement des modules' : props.dashboard.role === 'etudiant' ? 'Mon activité récente' : 'Fréquentation récente');
const activityDescription = computed(() => props.dashboard.role === 'superadmin' ? 'Progression fonctionnelle de la plateforme' : props.dashboard.role === 'etudiant' ? 'Vos présences, consultations et prêts' : 'Entrées enregistrées sur les 7 derniers jours');
const toneClasses: Record<string, string> = { primary: 'bg-primary-50 text-primary-600', emerald: 'bg-emerald-50 text-emerald-600', amber: 'bg-amber-50 text-amber-600', cyan: 'bg-cyan-50 text-cyan-600', red: 'bg-red-50 text-red-500' };
</script>

<template>
    <Head title="Tableau de bord" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div><p class="mb-1 text-xs font-bold uppercase tracking-[0.16em] text-primary-600">Vue générale</p><h1 class="font-heading text-2xl font-bold tracking-tight text-slate-800 sm:text-3xl">Bonjour, {{ firstName }}</h1><p class="mt-2 text-sm capitalize text-slate-500">{{ today }} · Votre espace selon vos autorisations.</p></div>
                <span class="inline-flex w-fit items-center rounded-full bg-primary-50 px-3 py-1.5 text-xs font-bold text-primary-700 ring-1 ring-primary-100">{{ roleLabel }}</span>
            </div>
        </template>

        <section v-if="dashboard.alerts.length" class="mb-6 space-y-3">
            <div v-for="alert in dashboard.alerts" :key="alert.title" class="flex gap-3 rounded-md border border-primary-100 bg-primary-50 px-4 py-3.5 text-primary-800">
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-bold">i</span><div><p class="text-sm font-bold">{{ alert.title }}</p><p class="mt-0.5 text-xs leading-5 text-primary-700">{{ alert.message }}</p></div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="metric in dashboard.metrics" :key="metric.label" class="dw-card p-5">
                <div class="flex items-start justify-between"><div><p class="text-sm font-semibold text-slate-500">{{ metric.label }}</p><p class="mt-3 font-heading text-3xl font-bold text-slate-800">{{ metric.value }}</p></div><span :class="toneClasses[metric.tone]" class="flex h-11 w-11 items-center justify-center rounded-md"><AppIcon :name="metric.icon" class="h-5 w-5"/></span></div>
                <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-400">{{ metric.detail }}</p>
            </article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[1.6fr_1fr]">
            <article class="dw-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6"><div><h2 class="font-heading text-base font-bold text-slate-800">{{ activityTitle }}</h2><p class="mt-1 text-xs text-slate-400">{{ activityDescription }}</p></div><span class="rounded-md bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-500">À venir</span></div>
                <div class="flex min-h-72 flex-col items-center justify-center px-6 py-10 text-center"><span class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-50 text-slate-300"><AppIcon name="reports" class="h-8 w-8"/></span><h3 class="mt-4 font-heading text-sm font-bold text-slate-700">Les données apparaîtront ici</h3><p class="mt-2 max-w-sm text-xs leading-5 text-slate-400">Cette zone sera alimentée automatiquement après l’activation des référentiels et des opérations métier.</p></div>
            </article>

            <article class="dw-card overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6"><h2 class="font-heading text-base font-bold text-slate-800">Actions autorisées</h2><p class="mt-1 text-xs text-slate-400">Raccourcis adaptés à votre rôle</p></div>
                <div class="space-y-2 p-4">
                    <template v-for="action in dashboard.quickActions" :key="action.permission">
                        <Link v-if="action.available && action.href" :href="action.href" class="flex w-full items-center gap-4 rounded-md border border-slate-100 p-3 text-start transition hover:border-primary-200 hover:bg-primary-50"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-primary-50 text-primary-600"><AppIcon :name="action.icon" class="h-5 w-5"/></span><span><span class="block text-sm font-semibold text-slate-700">{{ action.label }}</span><span class="text-xs text-slate-400">{{ action.description }}</span></span></Link>
                        <button v-else disabled class="flex w-full cursor-not-allowed items-center gap-4 rounded-md border border-slate-100 p-3 text-start opacity-65"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-primary-50 text-primary-600"><AppIcon :name="action.icon" class="h-5 w-5"/></span><span><span class="block text-sm font-semibold text-slate-700">{{ action.label }}</span><span class="text-xs text-slate-400">{{ action.description }}</span></span><span class="ms-auto rounded bg-slate-100 px-2 py-1 text-[9px] font-bold uppercase text-slate-400">Bientôt</span></button>
                    </template>
                    <p v-if="!dashboard.quickActions.length" class="px-4 py-8 text-center text-xs text-slate-400">Aucune action rapide disponible pour ce compte.</p>
                </div>
            </article>
        </section>
    </AuthenticatedLayout>
</template>
