<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
const props = defineProps<{
    card: any;
    statuses: { value: string; label: string }[];
}>();
const form = useForm({
    status: props.card.status,
    expires_at: props.card.expires_at?.slice(0, 10) ?? "",
});
</script>
<template>
    <Head title="Modifier la carte de bibliothèque" /><AuthenticatedLayout
        ><template #header
            ><div>
                <Link
                    :href="route('cards.index')"
                    class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                    >← Retour aux cartes</Link
                >
                <h1 class="dw-page-title mt-2">
                    Modifier {{ card.card_number }}
                </h1>
                <p class="dw-page-description">
                    Le titulaire et le numéro de carte ne peuvent pas être
                    modifiés.
                </p>
            </div></template
        >
        <form
            class="dw-card mx-auto max-w-3xl p-6"
            @submit.prevent="form.patch(route('cards.update', card.id))"
        >
            <div
                class="mb-6 flex items-center gap-4 rounded-lg bg-slate-50 p-4 dark:bg-slate-900"
            >
                <img
                    v-if="card.student.photo_url"
                    :src="card.student.photo_url"
                    class="h-20 w-16 rounded object-cover"
                />
                <div
                    v-else
                    class="flex h-20 w-16 items-center justify-center rounded bg-white"
                >
                    <AppIcon name="user" class="h-7 w-7 text-slate-300" />
                </div>
                <div>
                    <p class="font-bold text-slate-800">
                        {{ card.student.last_name }}
                        {{ card.student.first_name }}
                    </p>
                    <p class="mt-1 font-mono text-xs text-primary-600">
                        {{ card.student.registration_number }}
                    </p>
                </div>
            </div>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >Statut</label
                    ><select v-model="form.status" class="dw-field">
                        <option
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option></select
                    ><InputError :message="form.errors.status" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >Date d’expiration</label
                    ><input
                        v-model="form.expires_at"
                        type="date"
                        class="dw-field"
                    /><InputError :message="form.errors.expires_at" />
                </div>
            </div>
            <div
                class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-5"
            >
                <Link
                    :href="route('cards.index')"
                    class="dw-btn-secondary"
                    >Annuler</Link
                ><button
                    class="dw-btn-primary"
                >
                    Enregistrer
                </button>
            </div>
        </form></AuthenticatedLayout
    >
</template>
