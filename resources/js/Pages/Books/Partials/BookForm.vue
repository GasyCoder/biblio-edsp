<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import InputError from "@/Components/InputError.vue";
import { Link, useForm } from "@inertiajs/vue3";
import { computed, onBeforeUnmount, ref } from "vue";

const props = defineProps<{
    categories: { id: number; name: string }[];
    aiAvailable: boolean;
    book?: any;
}>();

const editing = computed(() => Boolean(props.book));
const form = useForm({
    ...(editing.value ? { _method: "patch" } : {}),
    category_id: props.book?.category_id ?? "",
    title: props.book?.title ?? "",
    subtitle: props.book?.subtitle ?? "",
    cover: null as File | null,
    remove_cover: false,
    authors: props.book?.authors?.map((author: any) => author.display_name) ?? [
        "",
    ],
    publication_year: props.book?.publication_year ?? "",
    publisher: props.book?.publisher ?? "",
    isbn: props.book?.isbn ?? "",
    language: props.book?.language ?? "Français",
    edition: props.book?.edition ?? "",
    summary: props.book?.summary ?? "",
});

const fileInput = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(props.book?.cover_url ?? null);
const dragging = ref(false);
const aiPrompt = ref("");
const aiSteps = ref(4);
const aiSeed = ref<number | "">("");
const generating = ref(false);
const aiError = ref("");
const generatedImage = ref<string | null>(null);
const generatedSeed = ref<number | null>(null);
let objectUrl: string | null = null;

const categoryName = computed(
    () =>
        props.categories.find((item) => item.id === Number(form.category_id))
            ?.name ?? "",
);
const promptLength = computed(() => aiPrompt.value.length);
const composePrompt = () => {
    const authors = form.authors.filter(Boolean).join(", ");
    aiPrompt.value = [
        `Couverture verticale professionnelle pour l’ouvrage universitaire « ${form.title || "Titre de l’ouvrage"} »`,
        authors ? `écrit par ${authors}` : "",
        categoryName.value ? `dans le domaine ${categoryName.value}` : "",
        "style éditorial sobre, contemporain et institutionnel, adapté à une bibliothèque universitaire malgache, composition lisible, sans logo inventé ni code-barres",
    ]
        .filter(Boolean)
        .join(", ");
};
const setCover = (file?: File | null) => {
    if (!file) return;
    if (
        !file.type.match(/^image\/(jpeg|png|webp)$/) ||
        file.size > 4 * 1024 * 1024
    ) {
        form.setError(
            "cover",
            "Choisissez une image JPG, PNG ou WebP de 4 Mo maximum.",
        );
        return;
    }
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = URL.createObjectURL(file);
    preview.value = objectUrl;
    form.cover = file;
    form.remove_cover = false;
    form.clearErrors("cover");
    generatedImage.value = null;
    generatedSeed.value = null;
};
const selectCover = (event: Event) =>
    setCover((event.target as HTMLInputElement).files?.[0]);
const dropCover = (event: DragEvent) => {
    dragging.value = false;
    setCover(event.dataTransfer?.files?.[0]);
};
const removeCover = () => {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = null;
    preview.value = null;
    form.cover = null;
    form.remove_cover = true;
    generatedImage.value = null;
    generatedSeed.value = null;
    if (fileInput.value) fileInput.value.value = "";
};
const dataUrlToFile = async (dataUrl: string) => {
    const blob = await (await fetch(dataUrl)).blob();
    return new File([blob], `couverture-${Date.now()}.jpg`, {
        type: "image/jpeg",
    });
};
const generateCover = async () => {
    aiError.value = "";
    if (!aiPrompt.value.trim()) {
        aiError.value = "Décrivez l’image à générer.";
        return;
    }
    if (promptLength.value > 2048) {
        aiError.value = "La description ne peut pas dépasser 2 048 caractères.";
        return;
    }
    generating.value = true;
    try {
        const response = await fetch(route("ai.images.generate"), {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN":
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? "",
            },
            body: JSON.stringify({
                prompt: aiPrompt.value,
                steps: aiSteps.value,
                seed: aiSeed.value === "" ? null : aiSeed.value,
            }),
        });
        const payload = await response.json();
        if (!response.ok)
            throw new Error(
                payload.errors?.prompt?.[0] ??
                    payload.message ??
                    "Génération impossible.",
            );
        setCover(await dataUrlToFile(payload.data.image));
        generatedImage.value = payload.data.image;
        generatedSeed.value = payload.data.seed;
        aiSeed.value = payload.data.seed;
    } catch (error) {
        aiError.value =
            error instanceof Error ? error.message : "Génération impossible.";
    } finally {
        generating.value = false;
    }
};
const downloadGeneratedImage = () => {
    if (!generatedImage.value) return;
    const link = document.createElement("a");
    link.href = generatedImage.value;
    link.download = `couverture-${form.title || "ouvrage"}.jpg`.replace(
        /[^a-zA-Z0-9._-]+/g,
        "-",
    );
    link.click();
};
const addAuthor = () => form.authors.push("");
const removeAuthor = (index: number) =>
    form.authors.length > 1 && form.authors.splice(index, 1);
const submit = () =>
    form.post(
        editing.value
            ? route("books.update", props.book.id)
            : route("books.store"),
        { forceFormData: true },
    );
onBeforeUnmount(() => objectUrl && URL.revokeObjectURL(objectUrl));
</script>

<template>
    <form
        class="grid w-full gap-6 xl:grid-cols-[minmax(0,1fr)_380px]"
        @submit.prevent="submit"
    >
        <div class="space-y-6">
            <section class="dw-card p-5 sm:p-6">
                <div
                    class="mb-5 border-b border-gray-200 pb-4 dark:border-gray-800"
                >
                    <h2
                        class="font-heading text-base font-bold text-slate-800 dark:text-white"
                    >
                        Informations bibliographiques
                    </h2>
                    <p class="mt-1 text-xs text-slate-400">
                        Les informations principales affichées dans le
                        catalogue.
                    </p>
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-semibold"
                            >Titre *</label
                        ><input
                            v-model="form.title"
                            class="dw-field"
                            required
                            placeholder="Titre complet de l’ouvrage"
                        /><InputError
                            class="mt-1"
                            :message="form.errors.title"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-semibold"
                            >Sous-titre</label
                        ><input
                            v-model="form.subtitle"
                            class="dw-field"
                            placeholder="Sous-titre facultatif"
                        />
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
                            </option></select
                        ><InputError :message="form.errors.category_id" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold"
                            >ISBN</label
                        ><input
                            v-model="form.isbn"
                            class="dw-field"
                            placeholder="978-…"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-sm font-semibold"
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
                                    :placeholder="`Nom de l’auteur ${index + 1}`"
                                    required
                                /><button
                                    type="button"
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-md border border-gray-200 text-red-500 disabled:opacity-30 dark:border-gray-800"
                                    :disabled="form.authors.length === 1"
                                    title="Retirer"
                                    @click="removeAuthor(index)"
                                >
                                    ×
                                </button>
                            </div>
                        </div>
                        <InputError
                            class="mt-1"
                            :message="form.errors.authors"
                        />
                    </div>
                </div>
            </section>

            <section class="dw-card p-5 sm:p-6">
                <div
                    class="mb-5 border-b border-gray-200 pb-4 dark:border-gray-800"
                >
                    <h2
                        class="font-heading text-base font-bold text-slate-800 dark:text-white"
                    >
                        Publication et description
                    </h2>
                    <p class="mt-1 text-xs text-slate-400">
                        Informations complémentaires utiles à la recherche.
                    </p>
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-semibold"
                            >Maison d’édition</label
                        ><input v-model="form.publisher" class="dw-field" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold"
                            >Année de publication</label
                        ><input
                            v-model="form.publication_year"
                            type="number"
                            min="1000"
                            class="dw-field"
                        />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold"
                            >Édition</label
                        ><input
                            v-model="form.edition"
                            class="dw-field"
                            placeholder="Ex. 2e édition"
                        />
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
                            rows="6"
                            class="dw-field min-h-36 resize-y"
                            placeholder="Résumé ou description de l’ouvrage…"
                        ></textarea>
                    </div>
                </div>
            </section>
        </div>

        <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
            <section class="dw-card overflow-hidden">
                <div
                    class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"
                >
                    <h2
                        class="font-heading text-base font-bold text-slate-800 dark:text-white"
                    >
                        Couverture
                    </h2>
                    <p class="mt-1 text-xs text-slate-400">
                        Format vertical recommandé.
                    </p>
                </div>
                <div class="p-5">
                    <div
                        v-if="preview"
                        class="relative mx-auto mb-4 aspect-[2/3] max-h-[390px] overflow-hidden rounded-lg border border-gray-200 bg-slate-50 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    >
                        <img
                            :src="preview"
                            alt="Aperçu de la couverture"
                            class="h-full w-full object-cover"
                        /><button
                            type="button"
                            class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-red-600 shadow dark:bg-gray-950"
                            title="Supprimer"
                            @click="removeCover"
                        >
                            <AppIcon name="trash" class="h-4 w-4" />
                        </button>
                    </div>
                    <button
                        v-else
                        type="button"
                        class="flex aspect-[2/3] max-h-[360px] w-full flex-col items-center justify-center rounded-lg border-2 border-dashed p-6 text-center transition"
                        :class="
                            dragging
                                ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/30'
                                : 'border-gray-300 bg-slate-50 hover:border-primary-400 dark:border-gray-700 dark:bg-gray-900'
                        "
                        @click="fileInput?.click()"
                        @dragenter.prevent="dragging = true"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="dropCover"
                    >
                        <span
                            class="flex h-14 w-14 items-center justify-center rounded-full bg-white text-primary-600 shadow-sm dark:bg-gray-950"
                            ><AppIcon name="books" class="h-7 w-7" /></span
                        ><strong
                            class="mt-4 text-sm text-slate-700 dark:text-slate-200"
                            >Déposez la couverture ici</strong
                        ><span class="mt-1 text-xs text-slate-400"
                            >ou cliquez pour parcourir</span
                        ><span
                            class="mt-3 text-[10px] uppercase tracking-wide text-slate-400"
                            >JPG, PNG, WebP · 4 Mo max.</span
                        >
                    </button>
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="hidden"
                        @change="selectCover"
                    />
                    <button
                        v-if="preview"
                        type="button"
                        class="dw-btn-secondary mt-3 w-full justify-center"
                        @click="fileInput?.click()"
                    >
                        Remplacer l’image
                    </button>
                    <InputError class="mt-2" :message="form.errors.cover" />
                </div>
            </section>

            <section class="dw-card overflow-hidden">
                <div
                    class="bg-gradient-to-br from-primary-600 to-primary-700 px-5 py-4 text-white"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15"
                            >✦</span
                        >
                        <h2 class="font-heading text-sm font-bold !text-white">
                            Créer avec l’IA
                        </h2>
                    </div>
                    <p class="mt-2 text-xs !text-white/85">
                        Générez une proposition depuis le titre, les auteurs et
                        la catégorie.
                    </p>
                </div>
                <div class="p-5">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label
                            class="block text-xs font-bold text-slate-600 dark:text-slate-300"
                            >Description de l’image *</label
                        >
                        <span
                            class="text-[10px] font-semibold"
                            :class="
                                promptLength > 2048
                                    ? 'text-red-600'
                                    : 'text-slate-400'
                            "
                            >{{ promptLength }} / 2 048</span
                        >
                    </div>
                    <textarea
                        v-model="aiPrompt"
                        class="dw-field min-h-28 resize-y"
                        maxlength="2048"
                        placeholder="Décrivez précisément la couverture à générer…"
                    ></textarea>
                    <button
                        type="button"
                        class="mt-2 text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400"
                        @click="composePrompt"
                    >
                        Composer depuis la fiche de l’ouvrage
                    </button>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="mb-1.5 block text-[11px] font-bold text-slate-500"
                                >Étapes</label
                            ><select v-model="aiSteps" class="dw-field">
                                <option
                                    v-for="step in 8"
                                    :key="step"
                                    :value="step"
                                >
                                    {{ step }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block text-[11px] font-bold text-slate-500"
                                >Seed facultatif</label
                            ><input
                                v-model.number="aiSeed"
                                type="number"
                                min="0"
                                max="2147483647"
                                class="dw-field"
                                placeholder="Aléatoire"
                            />
                        </div>
                    </div>
                    <p
                        v-if="aiError"
                        role="alert"
                        class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300"
                    >
                        {{ aiError }}
                    </p>
                    <button
                        type="button"
                        class="dw-btn-primary mt-3 w-full justify-center disabled:!bg-slate-500 disabled:!text-white disabled:!opacity-100 dark:disabled:!bg-slate-700"
                        :disabled="
                            !aiAvailable ||
                            generating ||
                            !aiPrompt.trim() ||
                            promptLength > 2048
                        "
                        @click="generateCover"
                    >
                        <span
                            v-if="generating"
                            class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                        ></span
                        >{{
                            generating
                                ? "Génération en cours…"
                                : generatedImage
                                  ? "✦ Relancer la génération"
                                  : "✦ Générer l’image"
                        }}
                    </button>
                    <div
                        v-if="generatedImage"
                        class="mt-3 flex items-center gap-2"
                    >
                        <button
                            type="button"
                            class="dw-btn-secondary flex-1 justify-center"
                            @click="downloadGeneratedImage"
                        >
                            <AppIcon name="download" class="h-4 w-4" />
                            Télécharger
                        </button>
                        <span
                            v-if="generatedSeed !== null"
                            class="rounded-md bg-slate-100 px-2 py-2 font-mono text-[10px] text-slate-500 dark:bg-gray-900 dark:text-slate-400"
                            >Seed {{ generatedSeed }}</span
                        >
                    </div>
                    <p
                        v-if="!aiAvailable"
                        class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-center text-[11px] font-semibold leading-4 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                    >
                        Configurez Cloudflare Workers AI dans le fichier .env
                        pour activer cette option.
                    </p>
                </div>
            </section>

            <div class="flex gap-3">
                <Link
                    :href="route('books.index')"
                    class="dw-btn-secondary flex-1 justify-center"
                    >Annuler</Link
                ><button
                    class="dw-btn-primary flex-1 justify-center"
                    :disabled="form.processing"
                >
                    {{
                        form.processing
                            ? "Enregistrement…"
                            : editing
                              ? "Enregistrer"
                              : "Créer l’ouvrage"
                    }}
                </button>
            </div>
        </aside>
    </form>
</template>
