<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
interface Book {
    id: number;
    title: string;
    cover_url: string | null;
    publication_year: number | null;
    publisher: string | null;
    isbn: string | null;
    copies_count: number;
    category: { name: string } | null;
    authors: { id: number; display_name: string }[];
    copies: { id: number; inventory_number: string; status: string }[];
}
interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}
const props = defineProps<{
    books: {
        data: Book[];
        links: PageLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string;
        category: number | null;
        availability: string;
        year: number | null;
    };
    categories: { id: number; name: string }[];
    years: number[];
}>();
const search = ref(props.filters.search);
const category = ref(props.filters.category ?? "");
const availability = ref(props.filters.availability ?? "");
const year = ref(props.filters.year ?? "");
const selected = ref<number[]>([]);
const page = usePage();
const canCreate = page.props.auth.permissions.includes("books.create");
const canUpdate = page.props.auth.permissions.includes("books.update");
const canDelete = page.props.auth.permissions.includes("catalog.manage");
const pageIds = computed(() => props.books.data.map((book) => book.id));
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
const remove = (book: Book) => {
    if (confirm(`Supprimer « ${book.title} » ?`))
        router.delete(route("books.destroy", book.id));
};
const removeSelected = () => {
    if (
        !selected.value.length ||
        !confirm(
            `Supprimer définitivement les ${selected.value.length} ouvrages sélectionnés ?`,
        )
    )
        return;
    router.delete(route("books.destroy.bulk"), {
        data: { ids: selected.value },
        preserveScroll: true,
        onSuccess: () => {
            selected.value = [];
        },
    });
};
const activeFilterCount = computed(
    () =>
        [category.value, availability.value, year.value].filter(Boolean).length,
);
const submitSearch = () =>
    router.get(
        route("books.index"),
        {
            search: search.value,
            category: category.value,
            availability: availability.value,
            year: year.value,
        },
        { preserveState: true, replace: true },
    );
const resetFilters = () => {
    search.value = "";
    category.value = "";
    availability.value = "";
    year.value = "";
    submitSearch();
};
</script>
<template>
    <Head title="Catalogue" /><AuthenticatedLayout
        ><template #header
            ><div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
            >
                <div>
                    <p class="dw-page-kicker">Catalogue</p>
                    <h1 class="dw-page-title">Ouvrages</h1>
                    <p class="dw-page-description">
                        Titres bibliographiques et disponibilité des
                        exemplaires.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        v-if="
                            $page.props.auth.permissions.includes(
                                'imports.view',
                            )
                        "
                        :href="route('book-exports.xlsx')"
                        class="dw-btn-secondary"
                        >Exporter Excel</a
                    ><Link
                        v-if="
                            $page.props.auth.permissions.includes(
                                'imports.view',
                            )
                        "
                        :href="route('book-imports.index')"
                        class="inline-flex h-10 items-center rounded-md border border-primary-200 bg-white px-4 text-sm font-semibold text-primary-600 transition-colors hover:border-primary-400 hover:bg-primary-50 dark:border-primary-800 dark:bg-gray-950 dark:text-primary-300 dark:hover:bg-primary-950"
                        >Importer</Link
                    ><Link
                        v-if="canCreate"
                        :href="route('books.create')"
                        class="inline-flex h-10 items-center justify-center rounded-md bg-primary-600 px-4 text-sm font-bold text-white hover:bg-primary-700"
                        >Ajouter un ouvrage</Link
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
            v-if="$page.props.errors?.book"
            class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
        >
            {{ $page.props.errors.book }}
        </div>
        <div
            v-if="selected.length"
            class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-primary-200 bg-primary-50 p-3 dark:border-primary-800 dark:bg-primary-950/40"
        >
            <span
                class="text-sm font-bold text-primary-700 dark:text-primary-300"
                >{{ selected.length }} ouvrage(s) sélectionné(s)</span
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
            <div
                class="border-b border-gray-200 p-4 dark:border-gray-800 sm:p-5"
            >
                <form
                    class="grid gap-3 lg:grid-cols-[minmax(280px,1fr)_220px_200px_150px_auto]"
                    @submit.prevent="submitSearch"
                >
                    <div class="relative flex-1">
                        <AppIcon
                            name="search"
                            class="absolute start-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500 dark:text-slate-400"
                        /><input
                            v-model="search"
                            class="dw-field ps-11"
                            placeholder="Titre, auteur, ISBN ou n° d’enregistrement…"
                        />
                    </div>
                    <select
                        v-model="category"
                        class="dw-field"
                        aria-label="Filtrer par catégorie"
                    >
                        <option value="">Toutes les catégories</option>
                        <option
                            v-for="item in categories"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option>
                    </select>
                    <select
                        v-model="availability"
                        class="dw-field"
                        aria-label="Filtrer par disponibilité"
                    >
                        <option value="">Toutes les disponibilités</option>
                        <option value="available">Disponible</option>
                        <option value="in_consultation">En consultation</option>
                        <option value="borrowed">Emprunté</option>
                        <option value="no_copies">Sans exemplaire</option>
                    </select>
                    <select
                        v-model="year"
                        class="dw-field"
                        aria-label="Filtrer par année"
                    >
                        <option value="">Toutes les années</option>
                        <option v-for="item in years" :key="item" :value="item">
                            {{ item }}
                        </option>
                    </select>
                    <button class="dw-btn-primary justify-center">
                        Filtrer
                    </button>
                </form>
                <div
                    v-if="activeFilterCount || search"
                    class="mt-3 flex flex-wrap items-center gap-2"
                >
                    <span class="text-xs font-semibold text-slate-500"
                        >{{ activeFilterCount }} filtre(s) actif(s)</span
                    >
                    <button
                        type="button"
                        class="text-xs font-bold text-primary-600 hover:text-primary-700"
                        @click="resetFilters"
                    >
                        Réinitialiser tous les filtres
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="dw-table min-w-[1200px] text-sm">
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
                            <th class="px-5 py-3 text-start">
                                Titre et auteur
                            </th>
                            <th class="px-5 py-3 text-start">Catégorie</th>
                            <th class="px-5 py-3 text-start">Édition</th>
                            <th class="px-5 py-3 text-start">
                                N° d’enregistrement
                            </th>
                            <th class="px-5 py-3 text-start">ISBN</th>
                            <th class="px-5 py-3 text-center">Ex.</th>
                            <th class="px-5 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="book in books.data"
                            :key="book.id"
                            :class="
                                selected.includes(book.id)
                                    ? 'bg-primary-50/60 dark:bg-primary-950/20'
                                    : 'hover:bg-slate-50/70'
                            "
                        >
                            <td class="px-4 py-4 text-center">
                                <input
                                    v-model="selected"
                                    type="checkbox"
                                    :value="book.id"
                                    class="h-4 w-4 rounded border-slate-300 text-primary-600"
                                    :aria-label="`Sélectionner ${book.title}`"
                                />
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="book.cover_url"
                                        :src="book.cover_url"
                                        :alt="`Couverture de ${book.title}`"
                                        class="h-16 w-12 shrink-0 rounded border border-slate-200 object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-16 w-12 shrink-0 items-center justify-center rounded border border-dashed border-slate-300 bg-slate-50 text-slate-300"
                                    >
                                        <AppIcon name="books" class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <Link
                                            :href="route('books.show', book.id)"
                                            class="font-semibold text-slate-700 hover:text-primary-600"
                                            >{{ book.title }}</Link
                                        >
                                        <p
                                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                book.authors
                                                    .map((a) => a.display_name)
                                                    .join(", ")
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-slate-500">
                                {{ book.category?.name || "Non classé" }}
                            </td>
                            <td class="px-5 py-4 text-slate-500">
                                {{
                                    [book.publisher, book.publication_year]
                                        .filter(Boolean)
                                        .join(" · ") || "—"
                                }}
                            </td>
                            <td class="px-5 py-4">
                                <div
                                    v-if="book.copies.length"
                                    class="flex max-w-xs flex-wrap gap-1"
                                >
                                    <span
                                        v-for="copy in book.copies"
                                        :key="copy.id"
                                        class="rounded bg-primary-50 px-2 py-1 font-mono text-[11px] font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                                        >{{ copy.inventory_number }}</span
                                    >
                                </div>
                                <span
                                    v-else
                                    class="text-slate-500 dark:text-slate-400"
                                    >Aucun</span
                                >
                            </td>
                            <td
                                class="px-5 py-4 font-mono text-xs text-slate-500"
                            >
                                {{ book.isbn || "—" }}
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span
                                    class="rounded-full bg-primary-50 px-2.5 py-1 text-xs font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300"
                                    >{{ book.copies_count }}</span
                                >
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex justify-center gap-1">
                                    <Link
                                        :href="route('books.show', book.id)"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-md text-primary-600 transition-colors hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-950"
                                        title="Voir les détails"
                                        aria-label="Voir les détails de l’ouvrage"
                                        ><AppIcon name="eye" class="h-4 w-4"
                                    /></Link>
                                    <Link
                                        v-if="canUpdate"
                                        :href="route('books.edit', book.id)"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-md text-amber-600 transition-colors hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950"
                                        title="Modifier"
                                        aria-label="Modifier l’ouvrage"
                                        ><AppIcon
                                            name="edit"
                                            class="h-4 w-4" /></Link
                                    ><button
                                        v-if="canDelete"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-md text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950"
                                        title="Supprimer"
                                        aria-label="Supprimer l’ouvrage"
                                        @click="remove(book)"
                                    >
                                        <AppIcon name="trash" class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!books.data.length">
                            <td
                                colspan="8"
                                class="px-5 py-16 text-center text-slate-500 dark:text-slate-400"
                            >
                                Aucun ouvrage trouvé.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-if="books.total"
                class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Résultats {{ books.from }}–{{ books.to }} sur
                    {{ books.total }}
                </p>
                <div class="flex flex-wrap gap-1">
                    <template v-for="link in books.links" :key="link.label"
                        ><Link
                            v-if="link.url"
                            :href="link.url"
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
            </div></section
    ></AuthenticatedLayout>
</template>
