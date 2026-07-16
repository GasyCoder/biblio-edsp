<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { computed } from "vue";

interface Metric {
    label: string;
    value: number;
    icon: string;
    tone: string;
    detail: string;
}
interface QuickAction {
    label: string;
    description: string;
    icon: string;
    permission: string;
    available: boolean;
    href: string | null;
}
interface Alert {
    title: string;
    message: string;
    level: string;
}
interface Traffic {
    label: string;
    value: number;
}
interface Activity {
    id: number;
    student: string;
    number: string;
    photo: string | null;
    checkedInAt: string;
    status: string;
    active: boolean;
}

const props = defineProps<{
    dashboard: {
        role: string | null;
        metrics: Metric[];
        quickActions: QuickAction[];
        alerts: Alert[];
        traffic: Traffic[];
        recentActivity: Activity[];
        inventory: {
            available: number;
            consultation: number;
            borrowed: number;
            unavailable: number;
        };
    };
}>();

const page = usePage();
const firstName = computed(
    () => String(page.props.auth.user.name).split(" ")[0],
);
const today = new Intl.DateTimeFormat("fr-FR", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
}).format(new Date());
const roleLabel = computed(
    () =>
        ({
            superadmin: "Super administrateur",
            secretaire: "Secrétaire",
            etudiant: "Espace étudiant",
        })[props.dashboard.role || ""] || "Compte actif",
);
const maxTraffic = computed(() =>
    Math.max(...props.dashboard.traffic.map((item) => item.value), 1),
);
const inventoryTotal = computed(() =>
    Object.values(props.dashboard.inventory).reduce(
        (total, value) => total + value,
        0,
    ),
);
const toneClasses: Record<string, string> = {
    primary:
        "bg-primary-50 text-primary-600 dark:bg-primary-950/50 dark:text-primary-300",
    emerald:
        "bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300",
    amber: "bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-300",
    cyan: "bg-cyan-50 text-cyan-600 dark:bg-cyan-950/50 dark:text-cyan-300",
};
</script>

<template>
    <Head title="Tableau de bord" />
    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
            >
                <div>
                    <p
                        class="dw-page-kicker"
                    >
                        Vue générale
                    </p>
                    <h1 class="dw-page-title">Bonjour, {{ firstName }}</h1>
                    <p class="dw-page-description capitalize">
                        {{ today }} · Voici l’activité de la bibliothèque.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-primary-100 bg-primary-50 px-3 py-1.5 text-xs font-bold text-primary-700 dark:border-primary-900 dark:bg-primary-950/50 dark:text-primary-300"
                    >
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span
                        >{{ roleLabel }}
                    </span>
                    <Link
                        v-if="dashboard.role !== 'etudiant'"
                        :href="route('reports.index')"
                        class="dw-btn-secondary hidden sm:inline-flex"
                    >
                        <AppIcon name="reports" class="h-4 w-4" /> Rapports
                    </Link>
                </div>
            </div>
        </template>

        <section v-if="dashboard.alerts.length" class="mb-6 space-y-3">
            <div
                v-for="alert in dashboard.alerts"
                :key="alert.title"
                class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900"
                    ><AppIcon name="activity" class="h-5 w-5"
                /></span>
                <div>
                    <p class="text-sm font-bold">{{ alert.title }}</p>
                    <p class="mt-0.5 text-xs opacity-80">{{ alert.message }}</p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="metric in dashboard.metrics"
                :key="metric.label"
                class="dw-card group p-5 transition hover:-translate-y-0.5 hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p
                            class="text-sm font-semibold text-slate-500 dark:text-slate-400"
                        >
                            {{ metric.label }}
                        </p>
                        <p
                            class="mt-2 font-heading text-3xl font-bold text-slate-800 dark:text-white"
                        >
                            {{ metric.value.toLocaleString("fr-FR") }}
                        </p>
                    </div>
                    <span
                        :class="toneClasses[metric.tone]"
                        class="flex h-11 w-11 items-center justify-center rounded-lg"
                        ><AppIcon :name="metric.icon" class="h-5 w-5"
                    /></span>
                </div>
                <p
                    class="mt-4 border-t border-gray-100 pt-3 text-xs text-slate-500 dark:text-slate-400 dark:border-gray-800"
                >
                    {{ metric.detail }}
                </p>
            </article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[1.65fr_1fr]">
            <article class="dw-card overflow-hidden">
                <div
                    class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
                >
                    <div>
                        <h2
                            class="font-heading text-base font-bold text-slate-800 dark:text-white"
                        >
                            Fréquentation sur 7 jours
                        </h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Nombre d’entrées enregistrées quotidiennement
                        </p>
                    </div>
                    <Link
                        :href="route('visits.index')"
                        class="text-xs font-bold text-primary-600 hover:text-primary-700"
                        >Voir les présences →</Link
                    >
                </div>
                <div class="px-5 pb-5 pt-7 sm:px-6">
                    <div class="flex h-56 items-end gap-3 sm:gap-5">
                        <div
                            v-for="item in dashboard.traffic"
                            :key="item.label"
                            class="flex h-full min-w-0 flex-1 flex-col justify-end text-center"
                        >
                            <span
                                class="mb-2 text-xs font-bold text-slate-600 dark:text-slate-300"
                                >{{ item.value }}</span
                            >
                            <div
                                class="group/bar relative flex h-40 items-end overflow-hidden rounded-md bg-slate-50 dark:bg-gray-900"
                            >
                                <div
                                    class="w-full rounded-md bg-gradient-to-t from-primary-600 to-primary-400 transition-all duration-500 group-hover/bar:from-primary-700"
                                    :style="{
                                        height: `${Math.max((item.value / maxTraffic) * 100, item.value ? 8 : 2)}%`,
                                    }"
                                ></div>
                            </div>
                            <span
                                class="mt-2 text-[11px] font-semibold uppercase text-slate-500 dark:text-slate-400"
                                >{{ item.label }}</span
                            >
                        </div>
                    </div>
                </div>
            </article>

            <article class="dw-card overflow-hidden">
                <div
                    class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"
                >
                    <h2
                        class="font-heading text-base font-bold text-slate-800 dark:text-white"
                    >
                        État de l’inventaire
                    </h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Répartition des exemplaires physiques
                    </p>
                </div>
                <div class="p-5 sm:p-6">
                    <div
                        class="mx-auto flex h-36 w-36 items-center justify-center rounded-full bg-primary-50 ring-[12px] ring-primary-100 dark:bg-primary-950/50 dark:ring-primary-900/60"
                    >
                        <div class="text-center">
                            <p
                                class="font-heading text-3xl font-bold text-slate-800 dark:text-white"
                            >
                                {{ inventoryTotal }}
                            </p>
                            <p
                                class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                            >
                                Exemplaires
                            </p>
                        </div>
                    </div>
                    <div class="mt-8 grid grid-cols-2 gap-x-5 gap-y-4 text-sm">
                        <div>
                            <span
                                class="mb-1 block h-1.5 rounded-full bg-emerald-500"
                            ></span
                            ><span class="text-xs text-slate-500 dark:text-slate-400"
                                >Disponibles</span
                            ><strong
                                class="float-end text-slate-700 dark:text-slate-200"
                                >{{ dashboard.inventory.available }}</strong
                            >
                        </div>
                        <div>
                            <span
                                class="mb-1 block h-1.5 rounded-full bg-amber-400"
                            ></span
                            ><span class="text-xs text-slate-500 dark:text-slate-400"
                                >Consultation</span
                            ><strong
                                class="float-end text-slate-700 dark:text-slate-200"
                                >{{ dashboard.inventory.consultation }}</strong
                            >
                        </div>
                        <div>
                            <span
                                class="mb-1 block h-1.5 rounded-full bg-primary-500"
                            ></span
                            ><span class="text-xs text-slate-500 dark:text-slate-400"
                                >Empruntés</span
                            ><strong
                                class="float-end text-slate-700 dark:text-slate-200"
                                >{{ dashboard.inventory.borrowed }}</strong
                            >
                        </div>
                        <div>
                            <span
                                class="mb-1 block h-1.5 rounded-full bg-slate-400"
                            ></span
                            ><span class="text-xs text-slate-500 dark:text-slate-400"
                                >Indisponibles</span
                            ><strong
                                class="float-end text-slate-700 dark:text-slate-200"
                                >{{ dashboard.inventory.unavailable }}</strong
                            >
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[1.65fr_1fr]">
            <article class="dw-card overflow-hidden">
                <div
                    class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
                >
                    <div>
                        <h2
                            class="font-heading text-base font-bold text-slate-800 dark:text-white"
                        >
                            Activité récente
                        </h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Derniers passages enregistrés au comptoir
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold uppercase text-slate-500 dark:bg-gray-900"
                        >Temps réel</span
                    >
                </div>
                <div
                    v-if="dashboard.recentActivity.length"
                    class="divide-y divide-gray-100 dark:divide-gray-800"
                >
                    <div
                        v-for="activity in dashboard.recentActivity"
                        :key="activity.id"
                        class="flex items-center gap-3 px-5 py-3.5 sm:px-6"
                    >
                        <img
                            v-if="activity.photo"
                            :src="activity.photo"
                            :alt="activity.student"
                            class="h-10 w-10 rounded-full object-cover"
                        />
                        <span
                            v-else
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-50 font-bold text-primary-600 dark:bg-primary-950"
                            >{{ activity.student.charAt(0) }}</span
                        >
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-bold text-slate-700 dark:text-slate-200"
                            >
                                {{ activity.student }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ activity.number }} ·
                                {{ activity.checkedInAt }}
                            </p>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-1 text-[11px] font-bold"
                            :class="
                                activity.active
                                    ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300'
                                    : 'bg-slate-100 text-slate-500 dark:bg-gray-900'
                            "
                            >{{ activity.status }}</span
                        >
                    </div>
                </div>
                <div
                    v-else
                    class="flex min-h-52 flex-col items-center justify-center p-8 text-center"
                >
                    <span
                        class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 text-slate-300 dark:bg-gray-900"
                        ><AppIcon name="activity" class="h-6 w-6"
                    /></span>
                    <p
                        class="mt-3 text-sm font-bold text-slate-600 dark:text-slate-300"
                    >
                        Aucun passage enregistré
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Les prochains scans apparaîtront automatiquement ici.
                    </p>
                </div>
            </article>

            <article class="dw-card overflow-hidden">
                <div
                    class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"
                >
                    <h2
                        class="font-heading text-base font-bold text-slate-800 dark:text-white"
                    >
                        Actions rapides
                    </h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Raccourcis adaptés à votre rôle
                    </p>
                </div>
                <div class="grid gap-2 p-4">
                    <Link
                        v-for="action in dashboard.quickActions"
                        :key="action.permission"
                        :href="action.href || '#'"
                        class="group flex items-center gap-3 rounded-lg border border-transparent p-3 transition hover:border-primary-100 hover:bg-primary-50 dark:hover:border-primary-900 dark:hover:bg-primary-950/30"
                    >
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition group-hover:bg-primary-100 group-hover:text-primary-600 dark:bg-gray-900 dark:text-slate-300"
                            ><AppIcon :name="action.icon" class="h-5 w-5"
                        /></span>
                        <span class="min-w-0 flex-1"
                            ><span
                                class="block text-sm font-bold text-slate-700 dark:text-slate-200"
                                >{{ action.label }}</span
                            ><span
                                class="block truncate text-xs text-slate-500 dark:text-slate-400"
                                >{{ action.description }}</span
                            ></span
                        ><span class="text-primary-500">→</span>
                    </Link>
                </div>
            </article>
        </section>
    </AuthenticatedLayout>
</template>
