<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import InputError from "@/Components/InputError.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed, nextTick, ref } from "vue";

type Book = {
    id: number;
    title: string;
    cover_url?: string;
    copies_count: number;
    category?: { name: string };
    authors: { display_name: string }[];
};
type Location = { id: number; code: string; type: string; name: string };

const props = defineProps<{
    books: Book[];
    locations: Location[];
    conditions: { value: string; label: string }[];
}>();

const form = useForm({
    book_id: "" as number | "",
    location_id: "" as number | "",
    condition: "good",
    barcode_symbology: "qr",
    notes: "",
});
const bookQuery = ref("");
const bookSearchInput = ref<HTMLInputElement>();
const selectedBook = computed(() =>
    props.books.find((book) => book.id === form.book_id),
);
const filteredBooks = computed(() => {
    const query = bookQuery.value.trim().toLocaleLowerCase("fr");
    const books = query
        ? props.books.filter((book) =>
              [
                  book.title,
                  book.category?.name,
                  ...book.authors.map((author) => author.display_name),
              ]
                  .filter(Boolean)
                  .some((value) =>
                      value!.toLocaleLowerCase("fr").includes(query),
                  ),
          )
        : props.books;
    return books.slice(0, 20);
});

const selectBook = (book: Book) => {
    form.book_id = book.id;
    bookQuery.value = "";
};
const changeBook = () => {
    form.book_id = "";
    nextTick(() => bookSearchInput.value?.focus());
};
const locationTypeLabel = (type: string) =>
    type === "cabinet" ? "Armoire" : type === "shelf" ? "Étagère" : "Autre";
</script>

<template>
    <Head title="Nouvel exemplaire" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <Link
                    :href="route('copies.index')"
                    class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >← Retour aux exemplaires</Link
                >
                <p class="dw-page-kicker mt-4">Inventaire physique</p>
                <h1 class="dw-page-title">Enregistrer un exemplaire</h1>
                <p class="dw-page-description">
                    Choisissez l’ouvrage et son emplacement. Le numéro
                    d’enregistrement et le QR code seront générés
                    automatiquement.
                </p>
            </div>
        </template>

        <form
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]"
            @submit.prevent="form.post(route('copies.store'))"
        >
            <div class="space-y-6">
                <section class="dw-card overflow-hidden">
                    <div
                        class="flex items-center gap-4 border-b border-gray-200 p-5 dark:border-gray-800"
                    >
                        <span
                            class="flex h-11 w-11 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-300"
                            ><AppIcon name="books" class="h-5 w-5"
                        /></span>
                        <div>
                            <h2
                                class="font-heading font-bold text-slate-800 dark:text-white"
                            >
                                Choisir l’ouvrage
                            </h2>
                            <p
                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                            >
                                Recherchez par titre, auteur ou catégorie.
                            </p>
                        </div>
                    </div>
                    <div class="p-5 sm:p-6">
                        <div
                            v-if="selectedBook"
                            class="flex flex-col gap-4 rounded-xl border border-primary-200 bg-primary-50/60 p-4 sm:flex-row sm:items-center dark:border-primary-800 dark:bg-primary-950/30"
                        >
                            <img loading="lazy" decoding="async"
                                v-if="selectedBook.cover_url"
                                :src="selectedBook.cover_url"
                                :alt="`Couverture de ${selectedBook.title}`"
                                class="h-24 w-16 shrink-0 rounded-md object-cover shadow-sm"
                            />
                            <span
                                v-else
                                class="flex h-24 w-16 shrink-0 items-center justify-center rounded-md border border-dashed border-primary-300 bg-white text-primary-500 dark:border-primary-800 dark:bg-gray-950"
                                ><AppIcon name="books" class="h-7 w-7"
                            /></span>
                            <div class="min-w-0 flex-1">
                                <span
                                    class="text-xs font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400"
                                    >Ouvrage sélectionné</span
                                >
                                <h3
                                    class="mt-1 font-heading text-lg font-bold text-slate-800 dark:text-white"
                                >
                                    {{ selectedBook.title }}
                                </h3>
                                <p
                                    class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        selectedBook.authors
                                            .map(
                                                (author) => author.display_name,
                                            )
                                            .join(", ") ||
                                        "Auteur non renseigné"
                                    }}
                                </p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <span
                                        v-if="selectedBook.category"
                                        class="rounded bg-white px-2 py-1 text-xs font-semibold text-slate-600 dark:bg-gray-900 dark:text-slate-300"
                                        >{{ selectedBook.category.name }}</span
                                    ><span
                                        class="rounded bg-white px-2 py-1 text-xs font-semibold text-slate-600 dark:bg-gray-900 dark:text-slate-300"
                                        >{{
                                            selectedBook.copies_count
                                        }}
                                        exemplaire(s)</span
                                    >
                                </div>
                            </div>
                            <button
                                type="button"
                                class="dw-btn-secondary shrink-0 justify-center"
                                @click="changeBook"
                            >
                                <AppIcon name="search" class="h-4 w-4" />Changer
                            </button>
                        </div>
                        <div v-else>
                            <div class="relative">
                                <AppIcon
                                    name="search"
                                    class="absolute start-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                                /><input
                                    ref="bookSearchInput"
                                    v-model="bookQuery"
                                    class="dw-field h-12 ps-12"
                                    placeholder="Saisir un titre, un auteur ou une catégorie…"
                                    autocomplete="off"
                                    autofocus
                                />
                            </div>
                            <div
                                class="mt-3 max-h-96 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-800"
                            >
                                <button
                                    v-for="book in filteredBooks"
                                    :key="book.id"
                                    type="button"
                                    class="flex w-full items-center gap-4 border-b border-gray-100 p-3 text-start transition last:border-0 hover:bg-primary-50 dark:border-gray-900 dark:hover:bg-primary-950/30"
                                    @click="selectBook(book)"
                                >
                                    <img loading="lazy" decoding="async"
                                        v-if="book.cover_url"
                                        :src="book.cover_url"
                                        alt=""
                                        class="h-16 w-11 shrink-0 rounded object-cover"
                                    /><span
                                        v-else
                                        class="flex h-16 w-11 shrink-0 items-center justify-center rounded border border-dashed border-gray-300 text-primary-500 dark:border-gray-700"
                                        ><AppIcon name="books" class="h-5 w-5"
                                    /></span>
                                    <span class="min-w-0 flex-1"
                                        ><strong
                                            class="block truncate text-sm text-slate-700 dark:text-slate-200"
                                            >{{ book.title }}</strong
                                        ><span
                                            class="mt-1 block truncate text-xs text-slate-500 dark:text-slate-400"
                                            >{{
                                                book.authors
                                                    .map(
                                                        (author) =>
                                                            author.display_name,
                                                    )
                                                    .join(", ") ||
                                                "Auteur non renseigné"
                                            }}</span
                                        ><span
                                            v-if="book.category"
                                            class="mt-1 block text-[11px] font-semibold text-primary-600 dark:text-primary-400"
                                            >{{ book.category.name }}</span
                                        ></span
                                    ><span
                                        class="shrink-0 rounded-full bg-gray-100 px-2 py-1 text-[11px] font-bold text-slate-500 dark:bg-gray-900 dark:text-slate-400"
                                        >{{ book.copies_count }}</span
                                    >
                                </button>
                                <div
                                    v-if="!filteredBooks.length"
                                    class="p-8 text-center"
                                >
                                    <AppIcon
                                        name="search"
                                        class="mx-auto h-8 w-8 text-slate-300"
                                    />
                                    <p
                                        class="mt-3 text-sm font-semibold text-slate-600 dark:text-slate-300"
                                    >
                                        Aucun ouvrage trouvé
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        Essayez un autre titre, auteur ou
                                        catégorie.
                                    </p>
                                </div>
                            </div>
                            <p
                                v-if="
                                    !bookQuery &&
                                    books.length > filteredBooks.length
                                "
                                class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                            >
                                Les 20 premiers ouvrages sont affichés. Utilisez
                                la recherche pour affiner la liste.
                            </p>
                        </div>
                        <InputError
                            class="mt-2"
                            :message="form.errors.book_id"
                        />
                    </div>
                </section>

                <section class="dw-card overflow-hidden">
                    <div
                        class="flex items-center gap-4 border-b border-gray-200 p-5 dark:border-gray-800"
                    >
                        <span
                            class="flex h-11 w-11 items-center justify-center rounded-lg bg-cyan-50 text-cyan-600 dark:bg-cyan-950 dark:text-cyan-300"
                            ><AppIcon name="copies" class="h-5 w-5"
                        /></span>
                        <div>
                            <h2
                                class="font-heading font-bold text-slate-800 dark:text-white"
                            >
                                Emplacement physique
                            </h2>
                            <p
                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                            >
                                Indiquez où l’exemplaire sera rangé.
                            </p>
                        </div>
                    </div>
                    <fieldset
                        class="grid max-h-80 gap-3 overflow-y-auto p-5 sm:grid-cols-2 lg:grid-cols-3 sm:p-6"
                    >
                        <label
                            class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition"
                            :class="
                                form.location_id === ''
                                    ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-500/15 dark:bg-primary-950/40'
                                    : 'border-gray-200 hover:border-primary-300 dark:border-gray-800 dark:hover:border-primary-700'
                            "
                            ><input
                                v-model="form.location_id"
                                type="radio"
                                value=""
                                class="h-4 w-4 text-primary-600"
                            /><span
                                ><strong
                                    class="block text-sm text-slate-700 dark:text-white"
                                    >Non affecté</strong
                                ><span
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                    >À ranger plus tard</span
                                ></span
                            ></label
                        >
                        <label
                            v-for="location in locations"
                            :key="location.id"
                            class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition"
                            :class="
                                form.location_id === location.id
                                    ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-500/15 dark:bg-primary-950/40'
                                    : 'border-gray-200 hover:border-primary-300 dark:border-gray-800 dark:hover:border-primary-700'
                            "
                            ><input
                                v-model="form.location_id"
                                type="radio"
                                :value="location.id"
                                class="h-4 w-4 shrink-0 text-primary-600"
                            /><span class="min-w-0"
                                ><span
                                    class="block text-[11px] font-bold uppercase text-primary-600 dark:text-primary-400"
                                    >{{
                                        locationTypeLabel(location.type)
                                    }}</span
                                ><strong
                                    class="block truncate text-sm text-slate-700 dark:text-white"
                                    >{{ location.name }}</strong
                                ><span
                                    class="font-mono text-xs text-slate-500 dark:text-slate-400"
                                    >{{ location.code }}</span
                                ></span
                            ></label
                        >
                    </fieldset>
                    <InputError
                        class="px-6 pb-4"
                        :message="form.errors.location_id"
                    />
                </section>
            </div>

            <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
                <section class="dw-card overflow-hidden">
                    <div
                        class="border-b border-gray-200 p-5 dark:border-gray-800"
                    >
                        <h2
                            class="font-heading font-bold text-slate-800 dark:text-white"
                        >
                            Caractéristiques
                        </h2>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            État et identification de l’exemplaire.
                        </p>
                    </div>
                    <div class="space-y-5 p-5">
                        <fieldset>
                            <legend
                                class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200"
                            >
                                État physique
                            </legend>
                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    v-for="item in conditions"
                                    :key="item.value"
                                    class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2.5 text-sm transition"
                                    :class="
                                        form.condition === item.value
                                            ? 'border-primary-500 bg-primary-50 font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300'
                                            : 'border-gray-200 text-slate-600 dark:border-gray-800 dark:text-slate-300'
                                    "
                                    ><input
                                        v-model="form.condition"
                                        type="radio"
                                        :value="item.value"
                                        class="h-4 w-4 text-primary-600"
                                    />{{ item.label }}</label
                                >
                            </div>
                            <InputError
                                class="mt-2"
                                :message="form.errors.condition"
                            />
                        </fieldset>
                        <fieldset>
                            <legend
                                class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200"
                            >
                                Code de l’étiquette
                            </legend>
                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    v-for="item in [
                                        {
                                            value: 'qr',
                                            label: 'QR code',
                                            icon: 'scan',
                                        },
                                        {
                                            value: 'code128',
                                            label: 'Code 128',
                                            icon: 'copies',
                                        },
                                    ]"
                                    :key="item.value"
                                    class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border p-4 text-center transition"
                                    :class="
                                        form.barcode_symbology === item.value
                                            ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300'
                                            : 'border-gray-200 text-slate-500 dark:border-gray-800 dark:text-slate-400'
                                    "
                                    ><input
                                        v-model="form.barcode_symbology"
                                        type="radio"
                                        :value="item.value"
                                        class="sr-only"
                                    /><AppIcon
                                        :name="item.icon"
                                        class="h-6 w-6"
                                    /><span class="text-xs font-bold">{{
                                        item.label
                                    }}</span></label
                                >
                            </div>
                        </fieldset>
                        <div>
                            <label
                                class="mb-2 block text-sm font-semibold text-slate-700 dark:text-slate-200"
                                >Notes internes</label
                            ><textarea
                                v-model="form.notes"
                                rows="4"
                                class="dw-field"
                                placeholder="Observation facultative sur cet exemplaire…"
                            ></textarea
                            ><InputError
                                class="mt-2"
                                :message="form.errors.notes"
                            />
                        </div>
                    </div>
                </section>
                <div class="dw-card p-4">
                    <div class="flex flex-col gap-3">
                        <button
                            class="dw-btn-primary h-11 justify-center"
                            :disabled="form.processing || !form.book_id"
                        >
                            <span
                                v-if="form.processing"
                                class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                            ></span
                            ><AppIcon v-else name="check" class="h-5 w-5" />{{
                                form.processing
                                    ? "Enregistrement…"
                                    : "Enregistrer l’exemplaire"
                            }}</button
                        ><Link
                            :href="route('copies.index')"
                            class="dw-btn-secondary justify-center"
                            >Annuler</Link
                        >
                    </div>
                    <p
                        class="mt-3 text-center text-xs text-slate-500 dark:text-slate-400"
                    >
                        Le numéro d’inventaire sera attribué automatiquement.
                    </p>
                </div>
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
