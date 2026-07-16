<script setup lang="ts">
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import BookForm from "@/Pages/Books/Partials/BookForm.vue";
import { Head, Link } from "@inertiajs/vue3";

defineProps<{
    book: any;
    categories: { id: number; name: string }[];
    aiAvailable: boolean;
}>();
</script>

<template>
    <Head :title="`Modifier ${book.title}`" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <Link
                    :href="route('books.show', book.id)"
                    class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400"
                    >← Retour à la fiche</Link
                >
                <p class="dw-page-kicker mt-3">Catalogue</p>
                <h1 class="dw-page-title">Modifier l’ouvrage</h1>
                <p class="dw-page-description">
                    Mettez à jour les informations bibliographiques et la
                    couverture.
                </p>
            </div>
        </template>
        <BookForm
            :book="book"
            :categories="categories"
            :ai-available="aiAvailable"
        />
    </AuthenticatedLayout>
</template>
