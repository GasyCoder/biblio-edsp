<script setup lang="ts">
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import BookForm from "@/Pages/Books/Partials/BookForm.vue";
import { Head, Link } from "@inertiajs/vue3";

defineProps<{
    categories: { id: number; name: string }[];
    aiAvailable: boolean;
}>();
</script>

<template>
    <Head title="Nouvel ouvrage" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <Link
                    :href="route('books.index')"
                    class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >← Retour au catalogue</Link
                >
                <p class="dw-page-kicker mt-3">Catalogue</p>
                <h1 class="dw-page-title">Ajouter un ouvrage</h1>
                <p class="dw-page-description">
                    Créez sa fiche bibliographique, ajoutez sa couverture puis
                    enregistrez ses exemplaires physiques.
                </p>
            </div>
        </template>
        <BookForm :categories="categories" :ai-available="aiAvailable" />
    </AuthenticatedLayout>
</template>
