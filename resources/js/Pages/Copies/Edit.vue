<script setup lang="ts">
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
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-semibold"
                    >Emplacement</label
                ><select v-model="form.location_id" class="dw-field">
                    <option value="">Non défini</option>
                    <option
                        v-for="location in locations"
                        :key="location.id"
                        :value="location.id"
                    >
                        {{ location.name }} · {{ location.code }}
                    </option>
                </select>
            </div>
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
