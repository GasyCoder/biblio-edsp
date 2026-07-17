<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import InputError from "@/Components/InputError.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, useForm } from "@inertiajs/vue3";
import { onBeforeUnmount, ref } from "vue";

const props = defineProps<{ settings: Record<string, any> }>();
const form = useForm({
    _method: "patch",
    library_name: props.settings.library_name,
    institution_name: props.settings.institution_name,
    contact_email: props.settings.contact_email,
    contact_phone: props.settings.contact_phone,
    default_loan_days: props.settings.default_loan_days,
    max_books_per_loan: props.settings.max_books_per_loan,
    scanner_inactivity_seconds: props.settings.scanner_inactivity_seconds,
    card_validity_months: props.settings.card_validity_months,
    logo: null as File | null,
    favicon: null as File | null,
    remove_logo: false,
    remove_favicon: false,
});

const logoPreview = ref<string | null>(props.settings.logo_url);
const faviconPreview = ref<string | null>(props.settings.favicon_url);
const temporaryUrls: string[] = [];

const selectAsset = (event: Event, type: "logo" | "favicon") => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form[type] = file;
    if (!file) return;

    const url = URL.createObjectURL(file);
    temporaryUrls.push(url);
    if (type === "logo") {
        logoPreview.value = url;
        form.remove_logo = false;
    } else {
        faviconPreview.value = url;
        form.remove_favicon = false;
    }
};

const removeAsset = (type: "logo" | "favicon") => {
    form[type] = null;
    if (type === "logo") {
        logoPreview.value = null;
        form.remove_logo = true;
    } else {
        faviconPreview.value = null;
        form.remove_favicon = true;
    }
};

const submit = () =>
    form.post(route("settings.update"), {
        preserveScroll: true,
        forceFormData: true,
    });
onBeforeUnmount(() => temporaryUrls.forEach((url) => URL.revokeObjectURL(url)));
</script>

<template>
    <Head title="Paramètres" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="dw-page-kicker">Configuration</p>
                <h1 class="dw-page-title">Paramètres</h1>
                <p class="dw-page-description">
                    Personnalisez l’identité visuelle et les règles
                    opérationnelles de la bibliothèque.
                </p>
            </div>
        </template>

        <div
            v-if="$page.props.flash?.success"
            class="mb-5 rounded-md bg-emerald-50 p-4 text-sm font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
        >
            {{ $page.props.flash.success }}
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="dw-card overflow-hidden">
                <div
                    class="flex gap-4 border-b border-gray-200 p-5 dark:border-gray-900"
                >
                    <span
                        class="flex h-11 w-11 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950"
                        ><AppIcon name="books" class="h-5 w-5"
                    /></span>
                    <div>
                        <h2
                            class="font-heading font-bold text-slate-700 dark:text-white"
                        >
                            Logo et favicon
                        </h2>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            Identité affichée dans l’application et l’onglet du
                            navigateur.
                        </p>
                    </div>
                </div>
                <div class="grid gap-6 p-5 lg:grid-cols-2">
                    <div
                        class="rounded-lg border border-gray-200 p-5 dark:border-gray-800"
                    >
                        <div class="flex items-center gap-5">
                            <div
                                class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
                            >
                                <img loading="lazy" decoding="async"
                                    v-if="logoPreview"
                                    :src="logoPreview"
                                    alt="Aperçu du logo"
                                    class="h-full w-full object-contain p-2"
                                />
                                <AppIcon
                                    v-else
                                    name="books"
                                    class="h-9 w-9 text-primary-500"
                                />
                            </div>
                            <div class="min-w-0">
                                <h3
                                    class="font-semibold text-slate-700 dark:text-white"
                                >
                                    Logo principal
                                </h3>
                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    PNG, JPG, WEBP ou SVG · 4 Mo maximum. Format
                                    carré recommandé.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <label class="dw-btn-secondary cursor-pointer"
                                ><AppIcon
                                    name="upload"
                                    class="h-4 w-4" />Choisir un logo<input
                                    type="file"
                                    class="sr-only"
                                    accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                    @change="selectAsset($event, 'logo')"
                            /></label>
                            <button
                                v-if="logoPreview"
                                type="button"
                                class="rounded-md px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                                @click="removeAsset('logo')"
                            >
                                Réinitialiser
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.logo" />
                    </div>

                    <div
                        class="rounded-lg border border-gray-200 p-5 dark:border-gray-800"
                    >
                        <div class="flex items-center gap-5">
                            <div
                                class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
                            >
                                <img loading="lazy" decoding="async"
                                    v-if="faviconPreview"
                                    :src="faviconPreview"
                                    alt="Aperçu du favicon"
                                    class="h-12 w-12 object-contain"
                                />
                                <span
                                    v-else
                                    class="text-xl font-bold text-primary-600"
                                    >ED</span
                                >
                            </div>
                            <div class="min-w-0">
                                <h3
                                    class="font-semibold text-slate-700 dark:text-white"
                                >
                                    Favicon
                                </h3>
                                <p
                                    class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    PNG, ICO ou SVG · 1 Mo maximum. Dimensions
                                    conseillées : 32 × 32 ou 64 × 64 px.
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <label class="dw-btn-secondary cursor-pointer"
                                ><AppIcon
                                    name="upload"
                                    class="h-4 w-4" />Choisir un favicon<input
                                    type="file"
                                    class="sr-only"
                                    accept="image/png,image/x-icon,image/svg+xml,.ico"
                                    @change="selectAsset($event, 'favicon')"
                            /></label>
                            <button
                                v-if="faviconPreview"
                                type="button"
                                class="rounded-md px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                                @click="removeAsset('favicon')"
                            >
                                Réinitialiser
                            </button>
                        </div>
                        <InputError
                            class="mt-2"
                            :message="form.errors.favicon"
                        />
                    </div>
                </div>
            </section>

            <section class="dw-card overflow-hidden">
                <div
                    class="flex gap-4 border-b border-gray-200 p-5 dark:border-gray-900"
                >
                    <span
                        class="flex h-11 w-11 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950"
                        ><AppIcon name="settings" class="h-5 w-5"
                    /></span>
                    <div>
                        <h2
                            class="font-heading font-bold text-slate-700 dark:text-white"
                        >
                            Identité de la bibliothèque
                        </h2>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            Informations utilisées dans l’administration et les
                            documents.
                        </p>
                    </div>
                </div>
                <div class="grid gap-5 p-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm"
                            >Nom de la bibliothèque *</label
                        ><input
                            v-model="form.library_name"
                            class="dw-field"
                        /><InputError :message="form.errors.library_name" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm">Établissement *</label
                        ><input
                            v-model="form.institution_name"
                            class="dw-field"
                        /><InputError :message="form.errors.institution_name" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm"
                            >E-mail de contact</label
                        ><input
                            v-model="form.contact_email"
                            type="email"
                            class="dw-field"
                        /><InputError :message="form.errors.contact_email" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm">Téléphone</label
                        ><input
                            v-model="form.contact_phone"
                            class="dw-field"
                        /><InputError :message="form.errors.contact_phone" />
                    </div>
                </div>
            </section>

            <section class="dw-card overflow-hidden">
                <div
                    class="flex gap-4 border-b border-gray-200 p-5 dark:border-gray-900"
                >
                    <span
                        class="flex h-11 w-11 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950"
                        ><AppIcon name="loans" class="h-5 w-5"
                    /></span>
                    <div>
                        <h2
                            class="font-heading font-bold text-slate-700 dark:text-white"
                        >
                            Règles opérationnelles
                        </h2>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            Ces valeurs sont appliquées immédiatement au
                            comptoir.
                        </p>
                    </div>
                </div>
                <div class="grid gap-5 p-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm"
                            >Durée de prêt par défaut</label
                        >
                        <div class="relative">
                            <input
                                v-model="form.default_loan_days"
                                type="number"
                                min="1"
                                max="90"
                                class="dw-field pe-16"
                            /><span
                                class="absolute end-4 top-3 text-xs text-slate-500"
                                >jours</span
                            >
                        </div>
                        <InputError :message="form.errors.default_loan_days" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm"
                            >Livres maximum par prêt</label
                        ><input
                            v-model="form.max_books_per_loan"
                            type="number"
                            min="1"
                            max="20"
                            class="dw-field"
                        /><InputError
                            :message="form.errors.max_books_per_loan"
                        />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm"
                            >Fermeture du scanner après inactivité</label
                        >
                        <div class="relative">
                            <input
                                v-model="form.scanner_inactivity_seconds"
                                type="number"
                                min="10"
                                max="180"
                                class="dw-field pe-20"
                            /><span
                                class="absolute end-4 top-3 text-xs text-slate-500"
                                >secondes</span
                            >
                        </div>
                        <InputError
                            :message="form.errors.scanner_inactivity_seconds"
                        />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm"
                            >Validité d’une carte</label
                        >
                        <div class="relative">
                            <input
                                v-model="form.card_validity_months"
                                type="number"
                                min="1"
                                max="60"
                                class="dw-field pe-16"
                            /><span
                                class="absolute end-4 top-3 text-xs text-slate-500"
                                >mois</span
                            >
                        </div>
                        <InputError
                            :message="form.errors.card_validity_months"
                        />
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <button class="dw-btn-primary" :disabled="form.processing">
                    {{
                        form.processing
                            ? "Enregistrement…"
                            : "Enregistrer les paramètres"
                    }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
