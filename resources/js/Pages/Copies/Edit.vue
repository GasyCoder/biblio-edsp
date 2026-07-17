<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import InputError from "@/Components/InputError.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
const props = defineProps<{
    copy: any;
    locations: any[];
    conditions: any[];
    statuses: any[];
}>();
const form = useForm({
    location_id: props.copy.location_id ?? "",
    condition: props.copy.condition,
    status: props.copy.status,
    notes: props.copy.notes ?? "",
});

const locationTypeLabel = (type: string) =>
    type === "cabinet"
        ? "Armoire"
        : type === "shelf"
          ? "Étagère"
          : "Autre emplacement";
</script>
<template>
    <Head title="Modifier un exemplaire" /><AuthenticatedLayout
        ><template #header
            ><div>
                <Link
                    :href="route('copies.index')"
                    class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                    >← Retour aux exemplaires</Link
                >
                <h1 class="dw-page-title mt-2">
                    Modifier {{ copy.inventory_number }}
                </h1>
                <p class="dw-page-description">{{ copy.book.title }}</p>
            </div></template
        >
        <form
            class="dw-card grid w-full gap-5 p-5 sm:p-7 md:grid-cols-2"
            @submit.prevent="form.patch(route('copies.update', copy.id))"
        >
            <fieldset class="md:col-span-2">
                <legend
                    class="text-sm font-semibold text-slate-700 dark:text-slate-200"
                >
                    Emplacement physique
                </legend>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Sélectionnez l’armoire, l’étagère ou laissez l’exemplaire
                    sans affectation.
                </p>
                <div
                    class="mt-4 grid max-h-80 gap-3 overflow-y-auto pe-1 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <label
                        class="relative flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition"
                        :class="
                            form.location_id === ''
                                ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-500/15 dark:bg-primary-950/40'
                                : 'border-gray-200 bg-white hover:border-primary-300 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:hover:border-primary-700 dark:hover:bg-gray-900'
                        "
                    >
                        <input
                            v-model="form.location_id"
                            type="radio"
                            value=""
                            class="h-4 w-4 shrink-0 border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-gray-100 text-slate-500 dark:bg-gray-900 dark:text-slate-300"
                            ><AppIcon name="close" class="h-4 w-4"
                        /></span>
                        <span class="min-w-0"
                            ><strong
                                class="block text-sm text-slate-700 dark:text-white"
                                >Non affecté</strong
                            ><span
                                class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400"
                                >À ranger ultérieurement</span
                            ></span
                        >
                    </label>

                    <label
                        v-for="location in locations"
                        :key="location.id"
                        class="relative flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition"
                        :class="
                            form.location_id === location.id
                                ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-500/15 dark:bg-primary-950/40'
                                : 'border-gray-200 bg-white hover:border-primary-300 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:hover:border-primary-700 dark:hover:bg-gray-900'
                        "
                    >
                        <input
                            v-model="form.location_id"
                            type="radio"
                            :value="location.id"
                            class="h-4 w-4 shrink-0 border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-300"
                            ><AppIcon
                                :name="
                                    location.type === 'shelf'
                                        ? 'books'
                                        : 'copies'
                                "
                                class="h-5 w-5"
                        /></span>
                        <span class="min-w-0"
                            ><span
                                class="block text-[11px] font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400"
                                >{{ locationTypeLabel(location.type) }}</span
                            ><strong
                                class="mt-0.5 block truncate text-sm text-slate-700 dark:text-white"
                                >{{ location.name }}</strong
                            ><span
                                class="mt-0.5 block font-mono text-xs text-slate-500 dark:text-slate-400"
                                >{{ location.code }}</span
                            ></span
                        >
                        <AppIcon
                            v-if="form.location_id === location.id"
                            name="check"
                            class="ms-auto h-5 w-5 shrink-0 text-primary-600"
                        />
                    </label>
                </div>
                <InputError class="mt-2" :message="form.errors.location_id" />
            </fieldset>
            <div>
                <label class="mb-2 block text-sm font-semibold"
                    >État physique</label
                ><select v-model="form.condition" class="dw-field">
                    <option
                        v-for="item in conditions"
                        :key="item.value"
                        :value="item.value"
                    >
                        {{ item.label }}
                    </option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-semibold">Statut</label
                ><select v-model="form.status" class="dw-field">
                    <option
                        v-for="item in statuses"
                        :key="item.value"
                        :value="item.value"
                    >
                        {{ item.label }}
                    </option></select
                ><InputError class="mt-2" :message="form.errors.status" />
            </div>
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-semibold">Notes</label
                ><textarea
                    v-model="form.notes"
                    rows="4"
                    class="dw-field"
                ></textarea>
            </div>
            <div
                class="md:col-span-2 flex justify-end gap-3 border-t border-slate-100 pt-5"
            >
                <Link :href="route('copies.index')" class="dw-btn-secondary"
                    >Annuler</Link
                ><button class="dw-btn-primary">Enregistrer</button>
            </div>
        </form></AuthenticatedLayout
    >
</template>
