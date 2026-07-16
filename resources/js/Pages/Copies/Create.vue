<script setup lang="ts">
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
defineProps<{ books: any[]; locations: any[]; conditions: any[] }>();
const form = useForm({
    book_id: "",
    location_id: "",
    condition: "good",
    barcode_symbology: "code128",
    notes: "",
});
</script>
<template>
    <Head title="Nouvel exemplaire" /><AuthenticatedLayout
        ><template #header
            ><div>
                <Link
                    :href="route('copies.index')"
                    class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                    >← Retour</Link
                >
                <h1 class="dw-page-title mt-2">Enregistrer un exemplaire</h1>
                <p class="dw-page-description">
                    Le numéro d’inventaire et le code scannable seront générés
                    automatiquement.
                </p>
            </div></template
        >
        <form
            class="dw-card grid w-full gap-5 p-5 sm:p-7 md:grid-cols-2"
            @submit.prevent="form.post(route('copies.store'))"
        >
            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-semibold">Ouvrage *</label
                ><select v-model="form.book_id" class="dw-field" required>
                    <option value="">Sélectionner</option>
                    <option
                        v-for="book in books"
                        :key="book.id"
                        :value="book.id"
                    >
                        {{ book.title }}
                    </option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-semibold"
                    >Emplacement</label
                ><select v-model="form.location_id" class="dw-field">
                    <option value="">Non défini</option>
                    <option
                        v-for="location in locations"
                        :key="location.id"
                        :value="location.id"
                    >
                        {{ location.code }} · {{ location.name }}
                    </option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-semibold">État</label
                ><select v-model="form.condition" class="dw-field">
                    <option
                        v-for="condition in conditions"
                        :key="condition.value"
                        :value="condition.value"
                    >
                        {{ condition.label }}
                    </option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-semibold"
                    >Type de code</label
                ><select v-model="form.barcode_symbology" class="dw-field">
                    <option value="code128">Code 128</option>
                    <option value="qr">QR code</option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-semibold">Notes</label
                ><input v-model="form.notes" class="dw-field" />
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
