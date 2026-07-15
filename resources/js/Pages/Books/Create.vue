<script setup lang="ts">
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
defineProps<{ categories: { id: number; name: string }[] }>();
const form = useForm({
    category_id: "",
    title: "",
    subtitle: "",
    cover: null as File | null,
    authors: [""],
    publication_year: "",
    publisher: "",
    isbn: "",
    language: "Français",
    edition: "",
    summary: "",
});
const addAuthor = () => form.authors.push("");
const removeAuthor = (index: number) =>
    form.authors.length > 1 && form.authors.splice(index, 1);
const submit = () => form.post(route("books.store"));
const selectCover = (event: Event) => {
    form.cover = (event.target as HTMLInputElement).files?.[0] ?? null;
};
</script>
<template>
    <Head title="Nouvel ouvrage" /><AuthenticatedLayout
        ><template #header
            ><div>
                <Link
                    :href="route('books.index')"
                    class="text-xs font-bold text-primary-600"
                    >← Retour au catalogue</Link
                >
                <h1 class="mt-2 font-heading text-2xl font-bold text-slate-800">
                    Ajouter un ouvrage
                </h1>
                <p class="mt-2 text-sm text-slate-500">
                    Cette fiche bibliographique pourra ensuite recevoir
                    plusieurs exemplaires physiques.
                </p>
            </div></template
        >
        <form
            class="dw-card mx-auto max-w-5xl p-5 sm:p-7"
            @submit.prevent="submit"
        >
            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Titre *</label
                    ><input
                        v-model="form.title"
                        class="dw-field"
                        required
                    /><InputError class="mt-2" :message="form.errors.title" />
                </div>
                <div class="md:col-span-2">
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Sous-titre</label
                    ><input v-model="form.subtitle" class="dw-field" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold text-slate-700">Image de couverture</label>
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="dw-field file:me-3 file:rounded file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-primary-700" @change="selectCover" />
                    <p class="mt-1 text-xs text-slate-400">JPG, PNG ou WebP, maximum 4 Mo.</p>
                    <InputError class="mt-2" :message="form.errors.cover" />
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
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
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >ISBN</label
                    ><input v-model="form.isbn" class="dw-field" />
                </div>
                <div class="md:col-span-2">
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-sm font-semibold text-slate-700"
                            >Auteur(s) *</label
                        ><button
                            type="button"
                            class="text-xs font-bold text-primary-600"
                            @click="addAuthor"
                        >
                            + Ajouter un auteur
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="(_, index) in form.authors"
                            :key="index"
                            class="flex gap-2"
                        >
                            <input
                                v-model="form.authors[index]"
                                class="dw-field"
                                :placeholder="`Auteur ${index + 1}`"
                                required
                            /><button
                                type="button"
                                class="rounded-md border border-slate-200 px-3 text-slate-400 hover:text-red-500 disabled:opacity-30"
                                :disabled="form.authors.length === 1"
                                @click="removeAuthor(index)"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.authors" />
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Maison d’édition</label
                    ><input v-model="form.publisher" class="dw-field" />
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Année de publication</label
                    ><input
                        v-model="form.publication_year"
                        type="number"
                        min="1000"
                        class="dw-field"
                    />
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Édition</label
                    ><input v-model="form.edition" class="dw-field" />
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Langue</label
                    ><input v-model="form.language" class="dw-field" />
                </div>
                <div class="md:col-span-2">
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Résumé</label
                    ><textarea
                        v-model="form.summary"
                        rows="5"
                        class="w-full rounded-md border border-slate-300 px-4 py-3 text-sm focus:border-primary-500 focus:ring-primary-100"
                    ></textarea>
                </div>
            </div>
            <div
                class="mt-7 flex justify-end gap-3 border-t border-slate-100 pt-5"
            >
                <Link
                    :href="route('books.index')"
                    class="inline-flex h-11 items-center rounded-md border border-slate-200 px-5 text-sm font-bold text-slate-600"
                    >Annuler</Link
                ><button
                    :disabled="form.processing"
                    class="h-11 rounded-md bg-primary-600 px-5 text-sm font-bold text-white disabled:opacity-60"
                >
                    Enregistrer l’ouvrage
                </button>
            </div>
        </form></AuthenticatedLayout
    >
</template>
