<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";

interface Student {
    id: number;
    registration_number: string;
    academic_number: string | null;
    last_name: string;
    first_name: string;
    photo_url: string | null;
    level: string | null;
    program: string | null;
    status: string;
}
interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}
const props = defineProps<{
    students: {
        data: Student[];
        links: PageLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { search: string };
}>();
const search = ref(props.filters.search);
const selected = ref<number[]>([]);
const page = usePage();
const canCreate = page.props.auth.permissions.includes("students.manage");
const canImport = page.props.auth.permissions.includes("imports.view");
const canUpdate = page.props.auth.permissions.includes("students.update");
const canDelete = page.props.auth.permissions.includes("students.manage");
const pageIds = computed(() =>
    props.students.data.map((student) => student.id),
);
const allSelected = computed(
    () =>
        pageIds.value.length > 0 &&
        pageIds.value.every((id) => selected.value.includes(id)),
);
const toggleAll = () => {
    if (allSelected.value) {
        selected.value = selected.value.filter(
            (id) => !pageIds.value.includes(id),
        );
    } else {
        selected.value = [...new Set([...selected.value, ...pageIds.value])];
    }
};
const remove = (student: Student) => {
    if (confirm(`Supprimer ${student.last_name} ${student.first_name} ?`))
        router.delete(route("students.destroy", student.id));
};
const removeSelected = () => {
    if (
        !selected.value.length ||
        !confirm(
            `Supprimer définitivement les ${selected.value.length} étudiants sélectionnés ?`,
        )
    )
        return;
    router.delete(route("students.destroy.bulk"), {
        data: { ids: selected.value },
        preserveScroll: true,
        onSuccess: () => {
            selected.value = [];
        },
    });
};
const submitSearch = () =>
    router.get(
        route("students.index"),
        { search: search.value },
        { preserveState: true, replace: true },
    );
const statusLabel: Record<string, string> = {
    active: "Actif",
    inactive: "Inactif",
    suspended: "Suspendu",
    graduated: "Diplômé",
};
</script>

<template>
    <Head title="Étudiants" />
    <AuthenticatedLayout>
        <template #header
            ><div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
            >
                <div>
                    <p
                        class="dw-page-kicker"
                    >
                        Référentiel
                    </p>
                    <h1 class="dw-page-title">
                        Gestion des étudiants
                    </h1>
                    <p class="dw-page-description">
                        Recherchez par matricule, numéro de bibliothèque, nom ou prénom.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        v-if="canImport"
                        :href="route('student-exports.xlsx')"
                        class="inline-flex h-10 items-center justify-center rounded-md border border-slate-200 px-4 text-sm font-bold text-slate-600"
                        >Exporter Excel</a
                    ><Link
                        v-if="canImport"
                        :href="route('student-imports.index')"
                        class="inline-flex h-10 items-center justify-center rounded-md border border-primary-200 px-4 text-sm font-bold text-primary-600"
                        >Importer</Link
                    ><Link
                        v-if="canCreate"
                        :href="route('students.create')"
                        class="inline-flex h-10 items-center justify-center rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700"
                        >Ajouter un étudiant</Link
                    >
                </div>
            </div></template
        >

        <div
            v-if="$page.props.flash?.success"
            class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.errors?.student"
            class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
        >
            {{ $page.props.errors.student }}
        </div>
        <div
            v-if="selected.length"
            class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-primary-200 bg-primary-50 p-3 dark:border-primary-800 dark:bg-primary-950/40"
        >
            <span
                class="text-sm font-bold text-primary-700 dark:text-primary-300"
                >{{ selected.length }} étudiant(s) sélectionné(s)</span
            >
            <button
                v-if="canDelete"
                class="inline-flex h-9 items-center gap-2 rounded-md bg-red-600 px-3 text-xs font-bold text-white"
                @click="removeSelected"
            >
                <AppIcon name="trash" class="h-4 w-4" /> Supprimer
            </button>
            <button
                class="ms-auto text-xs font-bold text-slate-500"
                @click="selected = []"
            >
                Annuler la sélection
            </button>
        </div>
        <section class="dw-card overflow-hidden">
            <div class="border-b border-slate-100 p-4 sm:p-5">
                <form
                    class="flex max-w-xl gap-2"
                    @submit.prevent="submitSearch"
                >
                    <div class="relative flex-1">
                        <AppIcon
                            name="search"
                            class="absolute start-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500 dark:text-slate-400"
                        /><input
                            v-model="search"
                            class="dw-field ps-11"
                            placeholder="Rechercher un étudiant…"
                        />
                    </div>
                    <button
                        class="rounded-md bg-slate-800 px-4 text-sm font-bold text-white hover:bg-slate-900"
                    >
                        Rechercher
                    </button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="dw-table min-w-[950px] text-start text-sm">
                    <thead
                        class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400"
                    >
                        <tr>
                            <th class="w-12 px-4 py-3 text-center">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-primary-600"
                                    :checked="allSelected"
                                    aria-label="Tout sélectionner"
                                    @change="toggleAll"
                                />
                            </th>
                            <th class="px-5 py-3 text-start">Étudiant</th>
                            <th class="px-5 py-3 text-start">N° bibliothèque</th>
                            <th class="px-5 py-3 text-start">Matricule</th>
                            <th class="px-5 py-3 text-start">
                                Niveau / parcours
                            </th>
                            <th class="px-5 py-3 text-start">Statut</th>
                            <th class="px-5 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="student in students.data"
                            :key="student.id"
                            :class="
                                selected.includes(student.id)
                                    ? 'bg-primary-50/60 dark:bg-primary-950/20'
                                    : 'hover:bg-slate-50/70'
                            "
                        >
                            <td class="px-4 py-4 text-center">
                                <input
                                    v-model="selected"
                                    type="checkbox"
                                    :value="student.id"
                                    class="h-4 w-4 rounded border-slate-300 text-primary-600"
                                    :aria-label="`Sélectionner ${student.last_name} ${student.first_name}`"
                                />
                            </td>
                            <td class="px-5 py-4 font-semibold text-slate-700">
                                <div class="flex items-center gap-3">
                                    <img loading="lazy" decoding="async"
                                        v-if="student.photo_url"
                                        :src="student.photo_url"
                                        class="h-11 w-9 rounded object-cover"
                                        alt="Photo"
                                    />
                                    <div
                                        v-else
                                        class="flex h-11 w-9 items-center justify-center rounded bg-slate-100"
                                    >
                                        <AppIcon
                                            name="user"
                                            class="h-4 w-4 text-slate-500 dark:text-slate-400"
                                        />
                                    </div>
                                    <span
                                        >{{ student.last_name }}
                                        {{ student.first_name }}</span
                                    >
                                </div>
                            </td>
                            <td
                                class="px-5 py-4 font-mono text-xs text-primary-700"
                            >
                                {{ student.registration_number }}
                            </td>
                            <td class="px-5 py-4 text-slate-500">
                                {{ student.academic_number || "—" }}
                            </td>
                            <td class="px-5 py-4 text-slate-500">
                                {{
                                    [student.level, student.program]
                                        .filter(Boolean)
                                        .join(" · ") || "—"
                                }}
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    :class="
                                        student.status === 'active'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : student.status === 'suspended'
                                              ? 'bg-red-50 text-red-700'
                                              : 'bg-slate-100 text-slate-600'
                                    "
                                    class="rounded-full px-2.5 py-1 text-xs font-bold"
                                    >{{ statusLabel[student.status] }}</span
                                >
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-center gap-1">
                                    <Link
                                        :href="
                                            route('students.show', student.id)
                                        "
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-md text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-950"
                                        title="Voir le profil"
                                        aria-label="Voir le profil étudiant"
                                        ><AppIcon name="eye" class="h-4 w-4"
                                    /></Link>
                                    <Link
                                        v-if="canUpdate"
                                        :href="
                                            route('students.edit', student.id)
                                        "
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-md text-amber-600 transition-colors hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950"
                                        title="Modifier"
                                        aria-label="Modifier l’étudiant"
                                        ><AppIcon
                                            name="edit"
                                            class="h-4 w-4" /></Link
                                    ><button
                                        v-if="canDelete"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-md text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950"
                                        title="Supprimer"
                                        aria-label="Supprimer l’étudiant"
                                        @click="remove(student)"
                                    >
                                        <AppIcon name="trash" class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!students.data.length">
                            <td
                                colspan="7"
                                class="px-5 py-16 text-center text-sm text-slate-500 dark:text-slate-400"
                            >
                                Aucun étudiant trouvé.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="students.total"
                class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Résultats {{ students.from }}–{{ students.to }} sur
                    {{ students.total }}
                </p>
                <div class="flex flex-wrap gap-1">
                    <template v-for="link in students.links" :key="link.label"
                        ><Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            preserve-state
                            :class="
                                link.active
                                    ? 'border border-primary-600 bg-primary-600 text-white shadow-sm'
                                    : 'border border-gray-300 bg-white text-slate-600 hover:border-primary-300 hover:text-primary-600 dark:border-gray-800 dark:bg-gray-950 dark:text-slate-300 dark:hover:border-primary-700 dark:hover:text-primary-400'
                            "
                            class="min-w-9 rounded-md px-3 py-2 text-center text-xs font-semibold transition-colors"
                            v-html="link.label" /><span
                            v-else
                            class="min-w-9 rounded-md border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-slate-400 dark:border-gray-900 dark:text-slate-600"
                            v-html="link.label"
                    /></template>
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
