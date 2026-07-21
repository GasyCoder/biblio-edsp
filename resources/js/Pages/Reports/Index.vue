<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, ref } from "vue";

type Ref = { id: number; name: string };
type Tab =
    | "overview"
    | "attendance"
    | "presence"
    | "absences"
    | "documents";

const props = defineProps<{
    tab: Tab;
    filters: {
        from: string;
        to: string;
        granularity: "day" | "week" | "month";
        group_by: "level" | "mention" | "program";
        level_id: number | null;
        mention_id: number | null;
        program_id: number | null;
        academic_year: string | null;
        search: string;
        status: string;
        never_only: boolean;
    };
    options: { levels: Ref[]; mentions: Ref[]; programs: Ref[]; years: string[] };
    scoreWeights: { presence: number; consultation: number; loan: number };
    overview?: any;
    attendance?: any;
    presence?: any;
    absences?: any;
    documents?: any;
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const granularity = ref(props.filters.granularity);
const groupBy = ref(props.filters.group_by);
const levelId = ref(props.filters.level_id ?? "");
const mentionId = ref(props.filters.mention_id ?? "");
const programId = ref(props.filters.program_id ?? "");
const academicYear = ref(props.filters.academic_year ?? "");
const search = ref(props.filters.search ?? "");
const status = ref(props.filters.status ?? "");
const neverOnly = ref(props.filters.never_only ?? false);

const tabs = [
    { key: "overview", label: "Vue d’ensemble", icon: "dashboard" },
    { key: "attendance", label: "Assiduité", icon: "reports" },
    { key: "presence", label: "Présences", icon: "login" },
    { key: "absences", label: "Absences", icon: "logout" },
    { key: "documents", label: "Documents", icon: "books" },
] as const;

const resetFilters = () => {
    levelId.value = "";
    mentionId.value = "";
    programId.value = "";
    academicYear.value = "";
    search.value = "";
    status.value = "";
    neverOnly.value = false;
    apply();
};
const hasSideFilters = computed(
    () =>
        !!levelId.value ||
        !!mentionId.value ||
        !!programId.value ||
        !!academicYear.value ||
        !!search.value ||
        !!status.value ||
        neverOnly.value,
);

const query = (tab: Tab = props.tab) => ({
    tab,
    from: from.value,
    to: to.value,
    granularity: granularity.value,
    group_by: groupBy.value,
    level_id: levelId.value || undefined,
    mention_id: mentionId.value || undefined,
    program_id: programId.value || undefined,
    academic_year: academicYear.value || undefined,
    search: search.value || undefined,
    status: status.value || undefined,
    never_only: neverOnly.value ? 1 : undefined,
});

const apply = (tab: Tab = props.tab) =>
    router.get(route("reports.index"), query(tab), {
        preserveState: true,
        replace: true,
    });

// Présences et Absences exportent la liste affichée (tableau brut) ;
// Assiduité exporte son rapport analytique.
const isTableTab = computed(
    () => props.tab === "presence" || props.tab === "absences",
);
const canExport = computed(
    () => isTableTab.value || props.tab === "attendance",
);
const exportUrl = (format: "xlsx" | "pdf") =>
    route(
        isTableTab.value
            ? format === "xlsx"
                ? "reports.export.xlsx"
                : "reports.export.pdf"
            : format === "xlsx"
              ? "reports.attendance.xlsx"
              : "reports.attendance.pdf",
        query(),
    );
const printUrl = computed(() => route("reports.print", query()));

const groupByLabel = computed(
    () =>
        ({ level: "Niveau", mention: "Mention", program: "Parcours" })[
            groupBy.value
        ],
);
const granularityWord = computed(
    () =>
        ({ day: "jour", week: "semaine", month: "mois" })[granularity.value],
);
const maxAttendanceTrend = computed(() =>
    Math.max(1, ...(props.attendance?.trend ?? []).map((r: any) => r.present)),
);
const maxOverviewTrend = computed(() =>
    Math.max(
        1,
        ...(props.overview?.trend ?? []).flatMap((r: any) => [
            r.visits,
            r.consultations,
        ]),
    ),
);
const maxBook = computed(() =>
    Math.max(1, ...(props.documents?.topBooks ?? []).map((b: any) => b.total)),
);
const maxCategory = computed(() =>
    Math.max(
        1,
        ...(props.documents?.topCategories ?? []).map((c: any) => c.total),
    ),
);
const maxStudent = computed(() =>
    Math.max(
        1,
        ...(props.overview?.topStudents ?? []).map((s: any) => s.activity_total),
    ),
);
const medal = (index: number) =>
    [
        "bg-amber-400 text-amber-950",
        "bg-slate-300 text-slate-800",
        "bg-orange-400 text-orange-950",
    ][index] ??
    "bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300";
const dayLabel = (value: string) =>
    new Date(value).toLocaleDateString("fr-FR", {
        day: "2-digit",
        month: "2-digit",
    });
const timeLabel = (value?: string | null) =>
    value
        ? new Date(value).toLocaleString("fr-FR", {
              day: "2-digit",
              month: "2-digit",
              hour: "2-digit",
              minute: "2-digit",
          })
        : "—";
</script>

<template>
    <Head title="Rapports" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="dw-page-kicker">Pilotage</p>
                    <h1 class="dw-page-title">Rapports</h1>
                    <p class="dw-page-description">
                        Activité, assiduité, présences, absences et documents —
                        tout sur une seule page, avec une période commune.
                    </p>
                </div>
                <div v-if="canExport" class="flex flex-wrap gap-2">
                    <a
                        v-if="isTableTab"
                        :href="printUrl"
                        target="_blank"
                        rel="noopener"
                        class="dw-btn-secondary justify-center"
                    >
                        <AppIcon name="print" class="h-4 w-4" /> Imprimer
                    </a>
                    <a :href="exportUrl('xlsx')" class="dw-btn-secondary justify-center">
                        <AppIcon name="download" class="h-4 w-4" /> Excel
                    </a>
                    <a :href="exportUrl('pdf')" class="dw-btn-secondary justify-center">
                        <AppIcon name="download" class="h-4 w-4" /> PDF
                    </a>
                </div>
            </div>
        </template>

        <!-- Onglets -->
        <nav class="mb-5 overflow-x-auto" aria-label="Sections du rapport">
            <div
                class="inline-flex min-w-full gap-1 rounded-xl border border-gray-200 bg-white p-1.5 shadow-sm dark:border-gray-800 dark:bg-gray-950"
            >
                <button
                    v-for="item in tabs"
                    :key="item.key"
                    type="button"
                    class="flex flex-1 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-2.5 text-sm font-semibold transition"
                    :class="
                        tab === item.key
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-slate-100'
                    "
                    :aria-current="tab === item.key ? 'page' : undefined"
                    @click="apply(item.key)"
                >
                    <AppIcon :name="item.icon" class="h-4 w-4 shrink-0" />
                    {{ item.label }}
                </button>
            </div>
        </nav>

        <!-- Filtres communs -->
        <section class="dw-card mb-5">
            <form class="flex flex-wrap items-end gap-x-3 gap-y-4 p-4 sm:p-5" @submit.prevent="apply()">
                <label class="min-w-40 flex-1">
                    <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Du</span>
                    <input v-model="from" type="date" class="dw-field" />
                </label>
                <label class="min-w-40 flex-1">
                    <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Au</span>
                    <input v-model="to" type="date" class="dw-field" />
                </label>

                <template v-if="tab === 'attendance'">
                    <label class="min-w-40 flex-1">
                        <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Granularité</span>
                        <select v-model="granularity" class="dw-field" @change="apply()">
                            <option value="day">Par jour</option>
                            <option value="week">Par semaine</option>
                            <option value="month">Par mois</option>
                        </select>
                    </label>
                    <label class="min-w-40 flex-1">
                        <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Regrouper par</span>
                        <select v-model="groupBy" class="dw-field" @change="apply()">
                            <option value="level">Niveau</option>
                            <option value="mention">Mention</option>
                            <option value="program">Parcours</option>
                        </select>
                    </label>
                </template>

                <template v-if="tab === 'absences'">
                    <label class="min-w-48 flex-1">
                        <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Recherche</span>
                        <input v-model="search" class="dw-field" placeholder="Matricule, nom…" />
                    </label>
                    <label class="min-w-40 flex-1">
                        <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Regrouper par</span>
                        <select v-model="groupBy" class="dw-field" @change="apply()">
                            <option value="level">Niveau</option>
                            <option value="mention">Mention</option>
                            <option value="program">Parcours</option>
                        </select>
                    </label>
                </template>

                <template v-if="tab === 'attendance' || tab === 'absences'">
                    <label class="min-w-40 flex-1">
                        <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Niveau</span>
                        <select v-model="levelId" class="dw-field" @change="apply()">
                            <option value="">Tous les niveaux</option>
                            <option v-for="l in options.levels" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </label>
                    <label class="min-w-40 flex-1">
                        <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Mention</span>
                        <select v-model="mentionId" class="dw-field" @change="apply()">
                            <option value="">Toutes les mentions</option>
                            <option v-for="m in options.mentions" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </label>
                    <label class="min-w-40 flex-1">
                        <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Parcours</span>
                        <select v-model="programId" class="dw-field" @change="apply()">
                            <option value="">Tous les parcours</option>
                            <option v-for="p in options.programs" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </label>
                </template>

                <label v-if="tab === 'attendance'" class="min-w-40 flex-1">
                    <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Année universitaire</span>
                    <select v-model="academicYear" class="dw-field" @change="apply()">
                        <option value="">Toutes les années</option>
                        <option v-for="y in options.years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </label>

                <label
                    v-if="tab === 'absences'"
                    class="flex h-11 min-w-56 flex-1 cursor-pointer items-center gap-2.5 rounded-md border border-gray-200 px-3.5 transition hover:border-primary-300 dark:border-gray-800 dark:hover:border-primary-700"
                    :class="neverOnly ? 'border-primary-300 bg-primary-50 dark:border-primary-700 dark:bg-primary-950' : ''"
                >
                    <input v-model="neverOnly" type="checkbox" class="h-4 w-4 shrink-0 rounded border-slate-300 text-primary-600" @change="apply()" />
                    <span class="text-xs font-bold text-slate-600 dark:text-slate-300">Uniquement les jamais venus</span>
                </label>

                <template v-if="tab === 'presence'">
                    <label class="min-w-48 flex-1">
                        <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Recherche</span>
                        <input v-model="search" class="dw-field" placeholder="N° passage, matricule, nom…" />
                    </label>
                    <label class="min-w-40 flex-1">
                        <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Statut</span>
                        <select v-model="status" class="dw-field" @change="apply()">
                            <option value="">Tous</option>
                            <option value="active">Encore présents</option>
                            <option value="closed">Sortis</option>
                        </select>
                    </label>
                </template>

                <div class="ms-auto flex items-end gap-2">
                    <button
                        v-if="hasSideFilters"
                        type="button"
                        class="dw-btn-secondary justify-center"
                        @click="resetFilters"
                    >
                        <AppIcon name="close" class="h-4 w-4" />Réinitialiser
                    </button>
                    <button class="dw-btn-primary justify-center">
                        <AppIcon name="search" class="h-4 w-4" />Appliquer
                    </button>
                </div>
            </form>
        </section>

        <!-- ONGLET : VUE D'ENSEMBLE -->
        <template v-if="tab === 'overview' && overview">
            <section class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="dw-card p-4"><p class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Passages</p><p class="mt-1 font-heading text-3xl font-bold text-slate-800 dark:text-white">{{ overview.metrics.visits }}</p></div>
                <div class="dw-card p-4"><p class="text-xs font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">Étudiants uniques</p><p class="mt-1 font-heading text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ overview.metrics.uniqueStudents }}</p></div>
                <div class="dw-card p-4"><p class="text-xs font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">Consultations</p><p class="mt-1 font-heading text-3xl font-bold text-primary-600 dark:text-primary-400">{{ overview.metrics.consultations }}</p></div>
                <div class="dw-card p-4"><p class="text-xs font-bold uppercase tracking-wide text-amber-600 dark:text-amber-400">Prêts</p><p class="mt-1 font-heading text-3xl font-bold text-amber-600 dark:text-amber-400">{{ overview.metrics.loans }}</p></div>
            </section>

            <section class="mb-5 grid gap-3 sm:grid-cols-3">
                <div class="dw-card flex items-center gap-3 p-4"><span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300"><AppIcon name="visits" class="h-5 w-5" /></span><div><p class="font-heading text-xl font-bold text-slate-800 dark:text-white">{{ overview.alerts.present }}</p><p class="text-xs text-slate-500 dark:text-slate-400">présents en ce moment</p></div></div>
                <div class="dw-card flex items-center gap-3 p-4"><span class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-300"><AppIcon name="loans" class="h-5 w-5" /></span><div><p class="font-heading text-xl font-bold text-slate-800 dark:text-white">{{ overview.alerts.overdueLoans }}</p><p class="text-xs text-slate-500 dark:text-slate-400">prêts en retard</p></div></div>
                <div class="dw-card flex items-center gap-3 p-4"><span class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-300"><AppIcon name="copies" class="h-5 w-5" /></span><div><p class="font-heading text-xl font-bold text-slate-800 dark:text-white">{{ overview.alerts.unavailableCopies }}</p><p class="text-xs text-slate-500 dark:text-slate-400">exemplaires abîmés/perdus</p></div></div>
            </section>

            <section class="dw-card mb-5 overflow-hidden">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="font-heading font-bold text-slate-800 dark:text-white">Tendance quotidienne</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><span class="font-bold text-primary-600">Passages</span> et <span class="font-bold text-emerald-600">consultations</span> par jour.</p>
                </div>
                <div v-if="overview.trend.length" class="flex items-end gap-1 overflow-x-auto p-5" style="min-height: 170px">
                    <div v-for="(row, i) in overview.trend" :key="i" class="flex min-w-6 flex-1 flex-col items-center gap-1" :title="`${dayLabel(row.day)} : ${row.visits} passage(s), ${row.consultations} consultation(s)`">
                        <div class="flex w-full items-end justify-center gap-0.5" style="height: 110px">
                            <div class="w-1/2 rounded-t bg-primary-500" :style="{ height: Math.round((row.visits / maxOverviewTrend) * 100) + '%' }"></div>
                            <div class="w-1/2 rounded-t bg-emerald-500" :style="{ height: Math.round((row.consultations / maxOverviewTrend) * 100) + '%' }"></div>
                        </div>
                        <span class="text-[10px] text-slate-400 dark:text-slate-500">{{ dayLabel(row.day) }}</span>
                    </div>
                </div>
                <p v-else class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">Aucune activité sur la période.</p>
            </section>

            <section class="dw-card overflow-hidden">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="font-heading font-bold text-slate-800 dark:text-white">Étudiants les plus actifs</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Total passages + consultations + prêts. Pour le classement par assiduité, voir l’onglet Assiduité.</p>
                </div>
                <div v-if="overview.topStudents.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <Link v-for="(s, i) in overview.topStudents" :key="s.id" :href="route('students.show', s.id)" class="flex items-center gap-4 px-5 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-900">
                        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="medal(i)">{{ i + 1 }}</span>
                        <img v-if="s.photo_url" :src="s.photo_url" :alt="`Photo de ${s.last_name}`" class="h-10 w-9 shrink-0 rounded-md object-cover" />
                        <span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300">{{ s.first_name[0] }}{{ s.last_name[0] }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-slate-700 dark:text-slate-200">{{ s.last_name }} {{ s.first_name }}</span>
                            <span class="block font-mono text-xs text-primary-600 dark:text-primary-400">{{ s.registration_number }}</span>
                        </span>
                        <span class="hidden w-40 sm:block">
                            <span class="block h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><span class="block h-full rounded-full bg-primary-500" :style="{ width: Math.round((s.activity_total / maxStudent) * 100) + '%' }"></span></span>
                        </span>
                        <span class="shrink-0 rounded-full bg-primary-50 px-3 py-1 text-xs font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300">{{ s.activity_total }}</span>
                    </Link>
                </div>
                <p v-else class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">Aucune activité sur la période.</p>
            </section>
        </template>

        <!-- ONGLET : ASSIDUITÉ -->
        <template v-if="tab === 'attendance' && attendance">
            <section class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="dw-card p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Effectif suivi</p>
                    <p class="mt-1 font-heading text-3xl font-bold text-slate-800 dark:text-white">{{ attendance.kpis.cohort }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">étudiants actifs du périmètre</p>
                </div>
                <div class="dw-card p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">Présents</p>
                    <p class="mt-1 font-heading text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ attendance.kpis.present }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">venus au moins une fois</p>
                </div>
                <div class="dw-card p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-red-600 dark:text-red-400">Absents</p>
                    <p class="mt-1 font-heading text-3xl font-bold text-red-600 dark:text-red-400">{{ attendance.kpis.absent }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">jamais venus sur la période</p>
                </div>
                <div class="dw-card p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">Taux de fréquentation</p>
                    <p class="mt-1 font-heading text-3xl font-bold text-primary-600 dark:text-primary-400">{{ attendance.kpis.attendanceRate }}%</p>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-primary-500" :style="{ width: attendance.kpis.attendanceRate + '%' }"></div></div>
                </div>
            </section>

            <section class="mb-5 grid gap-3 sm:grid-cols-3">
                <div class="dw-card flex items-center gap-3 p-4"><span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-300"><AppIcon name="visits" class="h-5 w-5" /></span><div><p class="font-heading text-xl font-bold text-slate-800 dark:text-white">{{ attendance.kpis.totalVisits }}</p><p class="text-xs text-slate-500 dark:text-slate-400">passages enregistrés</p></div></div>
                <div class="dw-card flex items-center gap-3 p-4"><span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300"><AppIcon name="check" class="h-5 w-5" /></span><div><p class="font-heading text-xl font-bold text-slate-800 dark:text-white">{{ attendance.kpis.avgDaysPerPresent }}</p><p class="text-xs text-slate-500 dark:text-slate-400">jours de présence / étudiant présent</p></div></div>
                <div class="dw-card flex items-center gap-3 p-4"><span class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-300"><AppIcon name="clock" class="h-5 w-5" /></span><div><p class="font-heading text-xl font-bold text-slate-800 dark:text-white">{{ attendance.kpis.openDays }}</p><p class="text-xs text-slate-500 dark:text-slate-400">jours d’ouverture de la période</p></div></div>
            </section>

            <section class="dw-card mb-5 overflow-hidden">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="font-heading font-bold text-slate-800 dark:text-white">Présences par {{ granularityWord }}</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Nombre d’étudiants distincts présents sur chaque période.</p>
                </div>
                <div v-if="attendance.trend.length" class="flex items-end gap-1.5 overflow-x-auto p-5" style="min-height: 180px">
                    <div v-for="(row, i) in attendance.trend" :key="i" class="flex min-w-8 flex-1 flex-col items-center gap-1.5" :title="`${row.label} : ${row.present} présent(s)`">
                        <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300">{{ row.present || '' }}</span>
                        <div class="flex w-full items-end" style="height: 120px"><div class="w-full rounded-t bg-primary-500 dark:bg-primary-600" :style="{ height: Math.round((row.present / maxAttendanceTrend) * 100) + '%' }"></div></div>
                        <span class="max-w-full truncate text-[10px] text-slate-400 dark:text-slate-500">{{ row.label }}</span>
                    </div>
                </div>
                <p v-else class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">Aucune présence sur la période.</p>
            </section>

            <section class="dw-card mb-5 overflow-hidden">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="font-heading font-bold text-slate-800 dark:text-white">Assiduité par {{ groupByLabel.toLowerCase() }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="dw-table min-w-[720px] text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3 text-start">{{ groupByLabel }}</th>
                                <th class="px-5 py-3 text-center">Effectif</th>
                                <th class="px-5 py-3 text-center">Présents</th>
                                <th class="px-5 py-3 text-center">Absents</th>
                                <th class="px-5 py-3 text-start">Taux</th>
                                <th class="px-5 py-3 text-center">Jours moyens</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="row in attendance.breakdown" :key="row.label">
                                <td class="px-5 py-3 font-semibold text-slate-700 dark:text-slate-200">{{ row.label }}</td>
                                <td class="px-5 py-3 text-center text-slate-600 dark:text-slate-300">{{ row.cohort }}</td>
                                <td class="px-5 py-3 text-center font-bold text-emerald-600 dark:text-emerald-400">{{ row.present }}</td>
                                <td class="px-5 py-3 text-center font-bold text-red-600 dark:text-red-400">{{ row.absent }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-primary-500" :style="{ width: row.rate + '%' }"></div></div>
                                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300">{{ row.rate }}%</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-center text-slate-600 dark:text-slate-300">{{ row.avgDays }}</td>
                            </tr>
                            <tr v-if="!attendance.breakdown.length"><td colspan="6" class="px-5 py-12 text-center text-slate-500 dark:text-slate-400">Aucun étudiant dans ce périmètre.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dw-card mb-5 overflow-hidden">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="font-heading font-bold text-slate-800 dark:text-white">Étudiants les plus assidus</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Score = jours de présence ×{{ scoreWeights.presence }} + consultations ×{{ scoreWeights.consultation }} + prêts ×{{ scoreWeights.loan }}.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="dw-table min-w-[760px] text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="w-16 px-5 py-3 text-center">Rang</th>
                                <th class="px-5 py-3 text-start">Étudiant</th>
                                <th class="px-5 py-3 text-start">{{ groupByLabel }}</th>
                                <th class="px-5 py-3 text-center">Jours présents</th>
                                <th class="px-5 py-3 text-center">Consult.</th>
                                <th class="px-5 py-3 text-center">Prêts</th>
                                <th class="px-5 py-3 text-center">Score</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="(row, i) in attendance.ranking" :key="row.id">
                                <td class="px-5 py-3 text-center"><span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold" :class="medal(i)">{{ i + 1 }}</span></td>
                                <td class="px-5 py-3">
                                    <Link :href="route('students.show', row.id)" class="flex items-center gap-3">
                                        <img v-if="row.photo_url" :src="row.photo_url" :alt="`Photo de ${row.name}`" class="h-10 w-9 shrink-0 rounded-md border border-gray-200 object-cover dark:border-gray-700" />
                                        <span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300">{{ row.name.slice(0, 2) }}</span>
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold text-slate-700 hover:text-primary-600 dark:text-slate-200">{{ row.name }}</span>
                                            <span class="block font-mono text-xs text-primary-600 dark:text-primary-400">{{ row.registration_number }}</span>
                                        </span>
                                    </Link>
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ row.group }}</td>
                                <td class="px-5 py-3 text-center font-bold text-emerald-600 dark:text-emerald-400">{{ row.daysPresent }}</td>
                                <td class="px-5 py-3 text-center text-slate-600 dark:text-slate-300">{{ row.consultations }}</td>
                                <td class="px-5 py-3 text-center text-slate-600 dark:text-slate-300">{{ row.loans }}</td>
                                <td class="px-5 py-3 text-center"><span class="rounded-full bg-primary-600 px-3 py-1 text-xs font-bold text-white">{{ row.score }}</span></td>
                            </tr>
                            <tr v-if="!attendance.ranking.length"><td colspan="7" class="px-5 py-12 text-center text-slate-500 dark:text-slate-400">Aucune activité sur la période.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="attendance.absentees.length" class="dw-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h2 class="font-heading font-bold text-slate-800 dark:text-white">Étudiants absents sur la période</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Inscrits actifs qui ne sont jamais venus.</p>
                    </div>
                    <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-bold text-red-700 dark:bg-red-950 dark:text-red-300">{{ attendance.absentees.length }} absent(s)</span>
                </div>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(220px,1fr))] gap-3 p-4">
                    <Link v-for="a in attendance.absentees.slice(0, 24)" :key="a.id" :href="route('students.show', a.id)" class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 transition hover:border-primary-200 hover:shadow-sm dark:border-gray-800 dark:bg-gray-950 dark:hover:border-primary-800">
                        <img v-if="a.photo_url" :src="a.photo_url" :alt="`Photo de ${a.name}`" class="h-10 w-9 shrink-0 rounded-md object-cover" />
                        <span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ a.name.slice(0, 2) }}</span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-slate-700 dark:text-slate-200">{{ a.name }}</span>
                            <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ a.group }}</span>
                        </span>
                    </Link>
                </div>
                <p v-if="attendance.absentees.length > 24" class="border-t border-gray-200 px-5 py-3 text-center text-xs text-slate-500 dark:border-gray-800 dark:text-slate-400">
                    … et {{ attendance.absentees.length - 24 }} autre(s). La liste complète figure dans l’export.
                </p>
            </section>
        </template>

        <!-- ONGLET : ABSENCES -->
        <template v-if="tab === 'absences' && absences">
            <section class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="dw-card p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Jours d’ouverture</p>
                    <p class="mt-1 font-heading text-3xl font-bold text-slate-800 dark:text-white">{{ absences.openDays }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">référence de la période</p>
                </div>
                <div class="dw-card p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-red-600 dark:text-red-400">Jamais venus</p>
                    <p class="mt-1 font-heading text-3xl font-bold text-red-600 dark:text-red-400">{{ absences.neverCame }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">aucun passage sur la période</p>
                </div>
                <div class="dw-card p-4 sm:col-span-2">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Étudiants listés</p>
                    <p class="mt-1 font-heading text-3xl font-bold text-slate-800 dark:text-white">{{ absences.students.total }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Jours d’absence = jours d’ouverture − jours de présence. Triés du plus absent au moins absent.
                    </p>
                </div>
            </section>

            <section class="dw-card overflow-hidden">
                <div class="flex flex-col gap-2 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                    <div>
                        <h2 class="font-heading font-bold text-slate-800 dark:text-white">Absences par étudiant</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Un étudiant venu une seule fois reste « présent », mais cumule des jours d’absence.
                        </p>
                    </div>
                    <a :href="exportUrl('xlsx')" class="dw-btn-secondary shrink-0 justify-center">
                        <AppIcon name="download" class="h-4 w-4" /> Exporter
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="dw-table min-w-[820px] text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3 text-start">Étudiant</th>
                                <th class="px-5 py-3 text-start">{{ groupByLabel }}</th>
                                <th class="px-5 py-3 text-center">Jours présents</th>
                                <th class="px-5 py-3 text-center">Jours d’absence</th>
                                <th class="px-5 py-3 text-start">Taux d’absence</th>
                                <th class="px-5 py-3 text-center">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="row in absences.students.data" :key="row.id">
                                <td class="px-5 py-3">
                                    <Link :href="route('students.show', row.id)" class="flex items-center gap-3">
                                        <img v-if="row.photo_url" :src="row.photo_url" :alt="`Photo de ${row.name}`" class="h-10 w-9 shrink-0 rounded-md border border-gray-200 object-cover dark:border-gray-700" />
                                        <span v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ row.name.slice(0, 2) }}</span>
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold text-slate-700 hover:text-primary-600 dark:text-slate-200">{{ row.name }}</span>
                                            <span class="block font-mono text-xs text-primary-600 dark:text-primary-400">{{ row.registration_number }}</span>
                                        </span>
                                    </Link>
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ row.group }}</td>
                                <td class="px-5 py-3 text-center font-bold text-emerald-600 dark:text-emerald-400">{{ row.daysPresent }}</td>
                                <td class="px-5 py-3 text-center font-bold text-red-600 dark:text-red-400">{{ row.absenceDays }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-red-500" :style="{ width: row.absenceRate + '%' }"></div></div>
                                        <span class="text-xs font-bold text-slate-600 dark:text-slate-300">{{ row.absenceRate }}%</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold" :class="row.neverCame ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'">
                                        {{ row.neverCame ? 'Jamais venu' : 'Venu partiellement' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!absences.students.data.length"><td colspan="6" class="px-5 py-12 text-center text-slate-500 dark:text-slate-400">Aucun étudiant ne correspond à ces critères.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="absences.students.links.length > 3" class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Résultats {{ absences.students.from }}–{{ absences.students.to }} sur {{ absences.students.total }}</p>
                    <nav class="flex flex-wrap gap-1">
                        <Link v-for="link in absences.students.links" :key="link.label" :href="link.url || '#'" preserve-scroll v-html="link.label" class="min-w-9 rounded-md px-3 py-2 text-center text-xs font-semibold transition"
                            :class="link.active ? 'bg-primary-600 text-white shadow-sm' : link.url ? 'border border-gray-200 bg-white text-slate-600 hover:border-primary-300 hover:text-primary-600 dark:border-gray-800 dark:bg-gray-950 dark:text-slate-300' : 'pointer-events-none border border-transparent text-slate-300 dark:text-slate-700'" />
                    </nav>
                </div>
            </section>
        </template>

        <!-- ONGLET : DOCUMENTS -->
        <template v-if="tab === 'documents' && documents">
            <section class="dw-card mb-5 overflow-hidden">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="font-heading font-bold text-slate-800 dark:text-white">Ouvrages les plus utilisés</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400"><span class="font-bold text-primary-600">Consultations</span> sur place et <span class="font-bold text-amber-600">prêts</span> à domicile sur la période.</p>
                </div>
                <div v-if="documents.topBooks.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <Link v-for="(book, i) in documents.topBooks" :key="book.id" :href="route('books.show', book.id)" class="flex items-center gap-4 px-5 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-900">
                        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="medal(i)">{{ i + 1 }}</span>
                        <img v-if="book.cover_url" :src="book.cover_url" :alt="`Couverture de ${book.title}`" class="h-14 w-10 shrink-0 rounded border border-gray-200 object-cover dark:border-gray-800" />
                        <span v-else class="flex h-14 w-10 shrink-0 items-center justify-center rounded border border-dashed border-gray-300 text-primary-500 dark:border-gray-700"><AppIcon name="books" class="h-5 w-5" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-slate-700 dark:text-slate-200">{{ book.title }}</span>
                            <span class="mt-1 flex flex-wrap gap-1.5">
                                <span class="rounded bg-primary-50 px-2 py-0.5 text-[11px] font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300">{{ book.consultations }} consultation(s)</span>
                                <span class="rounded bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-700 dark:bg-amber-950 dark:text-amber-300">{{ book.loans }} prêt(s)</span>
                            </span>
                        </span>
                        <span class="hidden w-40 sm:block"><span class="block h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><span class="block h-full rounded-full bg-primary-500" :style="{ width: Math.round((book.total / maxBook) * 100) + '%' }"></span></span></span>
                        <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ book.total }}</span>
                    </Link>
                </div>
                <p v-else class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">Aucun ouvrage utilisé sur la période.</p>
            </section>

            <section class="grid gap-5 lg:grid-cols-2">
                <div class="dw-card overflow-hidden">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-heading font-bold text-slate-800 dark:text-white">Catégories les plus consultées</h2></div>
                    <div v-if="documents.topCategories.length" class="space-y-3 p-5">
                        <div v-for="cat in documents.topCategories" :key="cat.name">
                            <div class="flex items-center justify-between text-sm">
                                <span class="truncate font-semibold text-slate-700 dark:text-slate-200">{{ cat.name }}</span>
                                <span class="ms-3 shrink-0 text-xs font-bold text-slate-500 dark:text-slate-400">{{ cat.total }}</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-emerald-500" :style="{ width: Math.round((cat.total / maxCategory) * 100) + '%' }"></div></div>
                        </div>
                    </div>
                    <p v-else class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">Aucune consultation sur la période.</p>
                </div>

                <div class="dw-card overflow-hidden">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"><h2 class="font-heading font-bold text-slate-800 dark:text-white">État de l’inventaire</h2><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Situation actuelle, toutes périodes confondues.</p></div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        <div v-for="row in documents.inventory" :key="row.status" class="flex items-center justify-between px-5 py-3">
                            <span class="text-sm text-slate-600 dark:text-slate-300">{{ row.label }}</span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ row.total }}</span>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <!-- ONGLET : PRÉSENCES -->
        <template v-if="tab === 'presence' && presence">
            <section class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="dw-card p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">Présents maintenant</p>
                    <p class="mt-1 font-heading text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ presence.stats.openNow }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">sortie pas encore enregistrée</p>
                </div>
                <div class="dw-card p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Entrées aujourd’hui</p>
                    <p class="mt-1 font-heading text-3xl font-bold text-slate-800 dark:text-white">{{ presence.stats.today }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ presence.stats.closedToday }} sortie(s) enregistrée(s)</p>
                </div>
                <div class="dw-card p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">Passages sur la période</p>
                    <p class="mt-1 font-heading text-3xl font-bold text-primary-600 dark:text-primary-400">{{ presence.stats.periodTotal }}</p>
                </div>
                <div class="dw-card p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Étudiants distincts</p>
                    <p class="mt-1 font-heading text-3xl font-bold text-slate-800 dark:text-white">{{ presence.stats.uniqueStudents }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">venus au moins une fois</p>
                </div>
            </section>

            <section class="dw-card overflow-hidden">
                <div class="flex flex-col gap-2 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                    <div>
                        <h2 class="font-heading font-bold text-slate-800 dark:text-white">Registre des passages</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Qui est venu et quand, du plus récent au plus ancien.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ presence.visits.total }} passage(s)</span>
                        <Link :href="route('visits.index')" class="text-xs font-bold text-primary-600 hover:underline dark:text-primary-400">Vue par étudiant →</Link>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="dw-table min-w-[900px] text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3 text-start">N° passage</th>
                                <th class="px-5 py-3 text-start">Étudiant</th>
                                <th class="px-5 py-3 text-start">Entrée</th>
                                <th class="px-5 py-3 text-start">Sortie</th>
                                <th class="px-5 py-3 text-center">Consult.</th>
                                <th class="px-5 py-3 text-center">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="visit in presence.visits.data" :key="visit.id">
                                <td class="px-5 py-3 font-mono text-xs text-primary-600 dark:text-primary-400">{{ visit.visit_number }}</td>
                                <td class="px-5 py-3">
                                    <Link :href="route('students.show', visit.student.id)" class="flex items-center gap-3">
                                        <img v-if="visit.student.photo_url" :src="visit.student.photo_url" :alt="`Photo de ${visit.student.last_name}`" class="h-9 w-8 shrink-0 rounded object-cover" />
                                        <span v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-50 text-[11px] font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300">{{ visit.student.first_name[0] }}{{ visit.student.last_name[0] }}</span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-semibold text-slate-700 hover:text-primary-600 dark:text-slate-200">{{ visit.student.last_name }} {{ visit.student.first_name }}</span>
                                            <span class="block font-mono text-xs text-slate-500 dark:text-slate-400">{{ visit.student.registration_number }}</span>
                                        </span>
                                    </Link>
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">
                                    {{ timeLabel(visit.checked_in_at) }}
                                    <span v-if="visit.checked_in_by" class="block text-xs text-slate-500 dark:text-slate-400">par {{ visit.checked_in_by }}</span>
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300">
                                    {{ timeLabel(visit.checked_out_at) }}
                                    <span v-if="visit.checked_out_by" class="block text-xs text-slate-500 dark:text-slate-400">par {{ visit.checked_out_by }}</span>
                                </td>
                                <td class="px-5 py-3 text-center text-slate-600 dark:text-slate-300">{{ visit.consultations_count }}</td>
                                <td class="px-5 py-3 text-center">
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold" :class="visit.checked_out_at ? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'">
                                        {{ visit.checked_out_at ? 'Sorti' : 'Présent' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!presence.visits.data.length"><td colspan="6" class="px-5 py-12 text-center text-slate-500 dark:text-slate-400">Aucun passage sur la période.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="presence.visits.links.length > 3" class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Résultats {{ presence.visits.from }}–{{ presence.visits.to }} sur {{ presence.visits.total }}</p>
                    <nav class="flex flex-wrap gap-1">
                        <Link v-for="link in presence.visits.links" :key="link.label" :href="link.url || '#'" preserve-scroll v-html="link.label" class="min-w-9 rounded-md px-3 py-2 text-center text-xs font-semibold transition"
                            :class="link.active ? 'bg-primary-600 text-white shadow-sm' : link.url ? 'border border-gray-200 bg-white text-slate-600 hover:border-primary-300 hover:text-primary-600 dark:border-gray-800 dark:bg-gray-950 dark:text-slate-300' : 'pointer-events-none border border-transparent text-slate-300 dark:text-slate-700'" />
                    </nav>
                </div>
            </section>
        </template>
    </AuthenticatedLayout>
</template>
