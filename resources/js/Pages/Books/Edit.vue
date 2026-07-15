<script setup lang="ts">
import InputError from "@/Components/InputError.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
const props = defineProps<{ book: any; categories: any[] }>();
const form = useForm({
    _method: "patch",
    category_id: props.book.category_id ?? "",
    title: props.book.title,
    subtitle: props.book.subtitle ?? "",
    cover: null as File | null,
    remove_cover: false,
    authors: props.book.authors.map((a: any) => a.display_name),
    publication_year: props.book.publication_year ?? "",
    publisher: props.book.publisher ?? "",
    isbn: props.book.isbn ?? "",
    language: props.book.language ?? "",
    edition: props.book.edition ?? "",
    summary: props.book.summary ?? "",
});
const addAuthor = () => form.authors.push("");
const removeAuthor = (i: number) => form.authors.splice(i, 1);
const selectCover = (event: Event) => {
    form.cover = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (form.cover) form.remove_cover = false;
};
</script>
<template>
    <Head title="Modifier un ouvrage" /><AuthenticatedLayout
        ><template #header
            ><div>
                <Link
                    :href="route('books.index')"
                    class="text-xs font-bold text-primary-600"
                    >← Retour au catalogue</Link
                >
                <h1 class="mt-2 font-heading text-2xl font-bold text-slate-800">
                    Modifier l’ouvrage
                </h1>
            </div></template
        >
        <form
            class="dw-card mx-auto max-w-5xl p-6"
            @submit.prevent="form.post(route('books.update', book.id), { forceFormData: true })"
        >
            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold"
                        >Titre *</label
                    ><input
                        v-model="form.title"
                        class="dw-field"
                        required
                    /><InputError :message="form.errors.title" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold"
                        >Sous-titre</label
                    ><input v-model="form.subtitle" class="dw-field" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold">Image de couverture</label>
                    <div v-if="book.cover_url && !form.remove_cover" class="mb-3 flex items-center gap-4"><img :src="book.cover_url" :alt="`Couverture de ${book.title}`" class="h-32 w-24 rounded-md border border-slate-200 object-cover"/><button type="button" class="text-xs font-bold text-red-600" @click="form.remove_cover = true; form.cover = null">Supprimer la couverture</button></div>
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="dw-field file:me-3 file:rounded file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-primary-700" @change="selectCover" />
                    <p class="mt-1 text-xs text-slate-400">Une nouvelle image remplacera la couverture actuelle. Maximum 4 Mo.</p>
                    <InputError :message="form.errors.cover" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >Catégorie</label
                    ><select v-model="form.category_id" class="dw-field">
                        <option value="">Non classé</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold">ISBN</label
                    ><input v-model="form.isbn" class="dw-field" />
                </div>
                <div class="md:col-span-2">
                    <div class="mb-2 flex justify-between">
                        <label class="text-sm font-semibold">Auteur(s) *</label
                        ><button
                            type="button"
                            class="text-xs font-bold text-primary-600"
                            @click="addAuthor"
                        >
                            + Ajouter
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="(_, i) in form.authors"
                            :key="i"
                            class="flex gap-2"
                        >
                            <input
                                v-model="form.authors[i]"
                                class="dw-field"
                                required
                            /><button
                                type="button"
                                class="h-12 w-12 text-red-500"
                                :disabled="form.authors.length === 1"
                                @click="removeAuthor(i)"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                    <InputError :message="form.errors.authors" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >Maison d’édition</label
                    ><input v-model="form.publisher" class="dw-field" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold">Année</label
                    ><input
                        v-model="form.publication_year"
                        type="number"
                        class="dw-field"
                    />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >Édition</label
                    ><input v-model="form.edition" class="dw-field" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >Langue</label
                    ><input v-model="form.language" class="dw-field" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold"
                        >Résumé</label
                    ><textarea
                        v-model="form.summary"
                        rows="5"
                        class="w-full rounded-md border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                    ></textarea>
                </div>
            </div>
            <div
                class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-5"
            >
                <Link
                    :href="route('books.index')"
                    class="rounded-md border border-slate-200 px-5 py-2.5 text-sm font-bold"
                    >Annuler</Link
                ><button
                    class="rounded-md bg-primary-600 px-5 py-2.5 text-sm font-bold text-white"
                >
                    Enregistrer
                </button>
            </div>
        </form></AuthenticatedLayout
    >
</template>
