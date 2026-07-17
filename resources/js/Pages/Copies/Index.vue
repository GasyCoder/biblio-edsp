<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";

const props = defineProps<{
    copies: {
        data: any[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string;
        status: string;
        condition: string;
        location: string;
    };
    locations: { id: number; code: string; name: string }[];
    conditionLabels: Record<string, string>;
    statusLabels: Record<string, string>;
}>();

const page = usePage();
const selected = ref<number[]>([]);
const previewCopy = ref<any | null>(null);
const search = ref(props.filters.search);
const status = ref(props.filters.status);
const condition = ref(props.filters.condition);
const location = ref(props.filters.location);
const canUpdate = page.props.auth.permissions.includes("copies.update");
const canDelete = page.props.auth.permissions.includes("catalog.manage");
const canPrint = page.props.auth.permissions.includes("copies.print");
const canUseDesk = page.props.auth.permissions.includes("cards.scan");
const pageIds = computed(() => props.copies.data.map((copy) => copy.id));
const allSelected = computed(
    () =>
        pageIds.value.length > 0 &&
        pageIds.value.every((id) => selected.value.includes(id)),
);
const activeOperations = computed(
    () =>
        props.copies.data.filter((copy) =>
            ["in_consultation", "borrowed"].includes(copy.status),
        ).length,
);
const activeFilterCount = computed(
    () =>
        [status.value, condition.value, location.value].filter(Boolean).length,
);

const applyFilters = () =>
    router.get(
        route("copies.index"),
        {
            search: search.value,
            status: status.value,
            condition: condition.value,
            location: location.value,
        },
        { preserveState: true, replace: true },
    );

const resetFilters = () => {
    search.value = "";
    status.value = "";
    condition.value = "";
    location.value = "";
    applyFilters();
};

const operationStudent = (copy: any) =>
    copy.active_consultation_items?.[0]?.session?.student ??
    copy.active_loan_items?.[0]?.loan?.student ??
    null;

const statusClass: Record<string, string> = {
    available:
        "bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300",
    in_consultation:
        "bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
    borrowed: "bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300",
    damaged: "bg-orange-50 text-orange-700",
    lost: "bg-red-50 text-red-700",
    archived: "bg-slate-100 text-slate-600",
};

const toggleAll = () => {
    selected.value = allSelected.value ? [] : [...pageIds.value];
};

const remove = (copy: any) => {
    if (confirm(`Supprimer définitivement ${copy.inventory_number} ?`)) {
        router.delete(route("copies.destroy", copy.id));
    }
};

const printSelected = () => {
    if (selected.value.length === 1) {
        previewCopy.value = props.copies.data.find(
            (copy) => copy.id === selected.value[0],
        );
        return;
    }
    if (selected.value.length > 1) {
        const query = new URLSearchParams(
            selected.value.map((id) => ["ids[]", String(id)]),
        );
        window.open(
            `${route("copies.print.bulk")}?${query}`,
            "_blank",
            "noopener",
        );
    }
};

const downloadPdf = (ids: number[]) => {
    const query = new URLSearchParams(ids.map((id) => ["ids[]", String(id)]));
    window.location.href = `${route("copies.print.pdf")}?${query}`;
};

const removeSelected = () => {
    if (
        !selected.value.length ||
        !confirm(
            `Supprimer définitivement les ${selected.value.length} exemplaires sélectionnés ?`,
        )
    )
        return;
    router.delete(route("copies.destroy.bulk"), {
        data: { ids: selected.value },
        preserveScroll: true,
        onSuccess: () => {
            selected.value = [];
        },
    });
};

const previewUrl = computed(() =>
    previewCopy.value
        ? route("copies.print", {
              copy: previewCopy.value.id,
              qr: 1,
              embedded: 1,
          })
        : "",
);
const printPreview = () =>
    document
        .querySelector<HTMLIFrameElement>("#copy-qr-preview")
        ?.contentWindow?.print();
</script>

<template>
    <Head title="Inventaire physique" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="dw-page-kicker">Inventaire</p>
                    <h1 class="dw-page-title">Inventaire physique</h1>
                    <p class="dw-page-description">
                        Sélectionnez un ou plusieurs exemplaires pour imprimer
                        leurs QR codes ou les supprimer.
                    </p>
                </div>
                <Link
                    :href="route('copies.create')"
                    class="shrink-0 rounded-md bg-primary-600 px-4 py-2.5 text-sm font-bold text-white"
                    >Nouvel exemplaire</Link
                >
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.errors?.copy"
            class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
        >
            {{ $page.props.errors.copy }}
        </div>

        <section class="dw-card mb-5 overflow-hidden">
            <div
                class="flex flex-col gap-2 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <div>
                    <h2
                        class="font-heading font-bold text-slate-800 dark:text-white"
                    >
                        Rechercher dans l’inventaire
                    </h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Retrouvez rapidement un exemplaire par numéro, ouvrage,
                        auteur ou emplacement.
                    </p>
                </div>
                <span
                    class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                    >{{ copies.total }} exemplaire(s)</span
                >
            </div>
            <form
                class="grid gap-3 p-4 sm:p-5 lg:grid-cols-2 2xl:grid-cols-[minmax(280px,1fr)_190px_170px_210px_auto]"
                @submit.prevent="applyFilters"
            >
                <div class="relative lg:col-span-2 2xl:col-span-1">
                    <AppIcon
                        name="search"
                        class="absolute start-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                    /><input
                        v-model="search"
                        class="dw-field ps-11"
                        placeholder="N° d’enregistrement, titre, auteur, code…"
                    />
                </div>
                <select
                    v-model="status"
                    class="dw-field"
                    aria-label="Filtrer par statut"
                >
                    <option value="">Tous les statuts</option>
                    <option
                        v-for="(label, value) in statusLabels"
                        :key="value"
                        :value="value"
                    >
                        {{ label }}
                    </option>
                </select>
                <select
                    v-model="condition"
                    class="dw-field"
                    aria-label="Filtrer par état"
                >
                    <option value="">Tous les états</option>
                    <option
                        v-for="(label, value) in conditionLabels"
                        :key="value"
                        :value="value"
                    >
                        {{ label }}
                    </option>
                </select>
                <select
                    v-model="location"
                    class="dw-field"
                    aria-label="Filtrer par emplacement"
                >
                    <option value="">Tous les emplacements</option>
                    <option value="unassigned">Non affecté</option>
                    <option
                        v-for="item in locations"
                        :key="item.id"
                        :value="String(item.id)"
                    >
                        {{ item.name }} · {{ item.code }}
                    </option>
                </select>
                <button class="dw-btn-primary justify-center">
                    <AppIcon name="search" class="h-4 w-4" />Filtrer
                </button>
            </form>
            <div
                v-if="activeFilterCount || search"
                class="flex flex-wrap items-center gap-3 border-t border-gray-200 px-5 py-3 dark:border-gray-800"
            >
                <span
                    class="rounded-full bg-primary-50 px-2.5 py-1 text-xs font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300"
                    >{{ activeFilterCount + (search ? 1 : 0) }} filtre(s)
                    actif(s)</span
                ><button
                    type="button"
                    class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    @click="resetFilters"
                >
                    Réinitialiser les filtres
                </button>
            </div>
        </section>

        <div
            v-if="activeOperations"
            class="mb-4 flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <strong
                    >{{ activeOperations }} opération(s) active(s) sur cette
                    page.</strong
                >
                <p class="mt-1 text-xs opacity-80">
                    Ouvrez l’étudiant au comptoir pour clôturer une consultation
                    ou enregistrer un retour de prêt.
                </p>
            </div>
            <Link
                :href="route('desk.index')"
                class="shrink-0 text-xs font-bold text-amber-800 underline underline-offset-4 dark:text-amber-200"
                >Ouvrir le comptoir</Link
            >
        </div>

        <div
            v-if="selected.length"
            class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-primary-200 bg-primary-50 p-3 dark:border-primary-800 dark:bg-primary-950/40"
        >
            <span
                class="text-sm font-bold text-primary-700 dark:text-primary-300"
                >{{ selected.length }} sélectionné(s)</span
            >
            <button
                v-if="canPrint"
                class="inline-flex h-9 items-center gap-2 rounded-md bg-primary-600 px-3 text-xs font-bold text-white"
                @click="printSelected"
            >
                <AppIcon name="print" class="h-4 w-4" /> QR code{{
                    selected.length > 1 ? "s" : ""
                }}
            </button>
            <button
                v-if="canPrint"
                class="inline-flex h-9 items-center gap-2 rounded-md border border-primary-300 bg-white px-3 text-xs font-bold text-primary-700 dark:border-primary-700 dark:bg-slate-900 dark:text-primary-300"
                @click="downloadPdf(selected)"
            >
                PDF
            </button>
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

        <section class="dw-card overflow-x-auto">
            <table class="dw-table min-w-[1100px] text-sm">
                <thead
                    class="bg-slate-50 text-xs uppercase text-slate-500 dark:text-slate-400"
                >
                    <tr>
                        <th class="w-12 p-4 text-center">
                            <input
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-primary-600"
                                :checked="allSelected"
                                aria-label="Tout sélectionner"
                                @change="toggleAll"
                            />
                        </th>
                        <th class="p-4 text-start">N° d’enregistrement</th>
                        <th class="p-4 text-start">Ouvrage</th>
                        <th class="p-4 text-start">Emplacement</th>
                        <th class="p-4 text-start">État</th>
                        <th class="p-4 text-start">Statut</th>
                        <th class="w-36 p-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr
                        v-for="copy in copies.data"
                        :key="copy.id"
                        :class="
                            selected.includes(copy.id)
                                ? 'bg-primary-50/60 dark:bg-primary-950/20'
                                : ''
                        "
                    >
                        <td class="p-4 text-center">
                            <input
                                v-model="selected"
                                type="checkbox"
                                :value="copy.id"
                                class="h-4 w-4 rounded border-slate-300 text-primary-600"
                                :aria-label="`Sélectionner ${copy.inventory_number}`"
                            />
                        </td>
                        <td class="p-4 font-mono font-bold text-primary-700">
                            {{ copy.inventory_number }}
                        </td>
                        <td class="p-4">
                            <div class="flex items-center gap-3">
                                <img loading="lazy" decoding="async"
                                    v-if="copy.book.cover_url"
                                    :src="copy.book.cover_url"
                                    :alt="`Couverture de ${copy.book.title}`"
                                    class="h-14 w-10 shrink-0 rounded border border-gray-200 object-cover shadow-sm dark:border-gray-800"
                                />
                                <span
                                    v-else
                                    class="flex h-14 w-10 shrink-0 items-center justify-center rounded border border-dashed border-gray-300 bg-gray-50 text-primary-500 dark:border-gray-800 dark:bg-gray-900"
                                    ><AppIcon name="books" class="h-5 w-5"
                                /></span>
                                <span
                                    class="line-clamp-2 font-semibold text-slate-700 dark:text-slate-200"
                                    >{{ copy.book.title }}</span
                                >
                            </div>
                        </td>
                        <td class="p-4">
                            {{
                                copy.location
                                    ? `${copy.location.name} · ${copy.location.code}`
                                    : "Non affecté"
                            }}
                        </td>
                        <td class="p-4">
                            {{ conditionLabels[copy.condition] }}
                        </td>
                        <td class="p-4">
                            <span
                                :class="statusClass[copy.status]"
                                class="rounded-full px-2.5 py-1 text-xs font-bold"
                                >{{ statusLabels[copy.status] }}</span
                            ><Link
                                v-if="canUseDesk && operationStudent(copy)"
                                :href="
                                    route('desk.index', {
                                        q: operationStudent(copy)
                                            .registration_number,
                                    })
                                "
                                class="mt-2 block text-xs font-bold text-primary-600 hover:underline"
                                >Gérer au comptoir →</Link
                            >
                        </td>
                        <td class="p-3">
                            <div class="flex justify-center gap-1">
                                <button
                                    v-if="canPrint"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/30"
                                    title="Afficher le QR code"
                                    aria-label="Afficher le QR code"
                                    @click="previewCopy = copy"
                                >
                                    <AppIcon name="print" class="h-4 w-4" />
                                </button>
                                <Link
                                    v-if="canUpdate"
                                    :href="route('copies.edit', copy.id)"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950"
                                    title="Modifier"
                                    aria-label="Modifier l’exemplaire"
                                    ><AppIcon name="edit" class="h-4 w-4"
                                /></Link>
                                <button
                                    v-if="canDelete"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                                    title="Supprimer"
                                    aria-label="Supprimer l’exemplaire"
                                    @click="remove(copy)"
                                >
                                    <AppIcon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!copies.data.length">
                        <td
                            colspan="7"
                            class="p-12 text-center text-slate-500 dark:text-slate-400"
                        >
                            {{
                                activeFilterCount || search
                                    ? "Aucun exemplaire ne correspond aux filtres."
                                    : "Aucun exemplaire enregistré."
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <div
            v-if="copies.links.length > 3"
            class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Affichage de {{ copies.from }} à {{ copies.to }} sur
                {{ copies.total }} exemplaire(s)
            </p>
            <nav
                class="flex flex-wrap gap-1"
                aria-label="Pagination de l’inventaire"
            >
                <Link
                    v-for="link in copies.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    preserve-scroll
                    v-html="link.label"
                    class="rounded-md px-3 py-2 text-xs font-semibold transition"
                    :class="
                        link.active
                            ? 'bg-primary-600 text-white shadow-sm'
                            : link.url
                              ? 'border border-gray-200 bg-white text-slate-600 hover:border-primary-300 hover:text-primary-600 dark:border-gray-800 dark:bg-gray-950 dark:text-slate-300'
                              : 'pointer-events-none border border-transparent text-slate-300 dark:text-slate-700'
                    "
                />
            </nav>
        </div>

        <Teleport to="body">
            <div
                v-if="previewCopy"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/70 p-4"
                role="dialog"
                aria-modal="true"
                @click.self="previewCopy = null"
            >
                <div
                    class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-slate-900"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700"
                    >
                        <div>
                            <h2
                                class="font-heading font-bold text-slate-800 dark:text-white"
                            >
                                QR code de l’exemplaire
                            </h2>
                            <p class="mt-1 font-mono text-xs text-primary-600">
                                {{ previewCopy.inventory_number }}
                            </p>
                        </div>
                        <button
                            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                            aria-label="Fermer"
                            @click="previewCopy = null"
                        >
                            <AppIcon name="close" class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="bg-slate-100 p-4 dark:bg-slate-950">
                        <iframe
                            id="copy-qr-preview"
                            :src="previewUrl"
                            class="h-[190px] w-full rounded-md bg-white"
                            title="Aperçu de l’étiquette QR"
                        ></iframe>
                        <p class="mt-2 text-center text-xs text-slate-500">
                            Étiquette autocollante 63,5 × 33,9 mm
                        </p>
                    </div>
                    <div
                        class="flex flex-wrap justify-end gap-3 border-t border-slate-200 p-4 dark:border-slate-700"
                    >
                        <button
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-600"
                            @click="previewCopy = null"
                        >
                            Fermer</button
                        ><button
                            class="rounded-md border border-primary-300 px-4 py-2 text-sm font-bold text-primary-700 dark:border-primary-700 dark:text-primary-300"
                            @click="downloadPdf([previewCopy.id])"
                        >
                            Télécharger PDF</button
                        ><button
                            class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-4 py-2 text-sm font-bold text-white"
                            @click="printPreview"
                        >
                            <AppIcon name="print" class="h-4 w-4" /> Imprimer
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
