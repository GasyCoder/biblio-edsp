<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{ imports: { data: any[] } }>();

const input = ref<HTMLInputElement | null>(null);
const form = useForm<{ files: File[] }>({ files: [] });

const selectFiles = (event: Event) => {
    form.files = Array.from((event.target as HTMLInputElement).files ?? []);
};

const removeFile = (index: number) => {
    form.files.splice(index, 1);
    form.files = [...form.files];
};

const upload = () => {
    form.post(route('book-imports.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            if (input.value) input.value.value = '';
        },
    });
};
</script>

<template>
    <Head title="Imports d’ouvrages" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <Link :href="route('books.index')" class="text-xs font-bold text-primary-600">← Retour aux ouvrages</Link>
                <h1 class="dw-page-title">Importer les ouvrages</h1>
                <p class="dw-page-description">Sélectionnez un ou plusieurs classeurs Excel à analyser avant leur intégration.</p>
            </div>
        </template>

        <div v-if="$page.props.flash?.success" class="mb-5 rounded-md bg-emerald-50 p-4 text-sm font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
            {{ $page.props.flash.success }}
        </div>

        <section class="dw-card p-5 sm:p-6">
            <div class="max-w-3xl">
                <h2 class="font-heading text-base font-bold text-slate-700 dark:text-white">Choisir les fichiers à importer</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Formats acceptés : XLSX, XLS et CSV · 20 Mo maximum par fichier · 20 fichiers maximum.</p>

                <form class="mt-5 space-y-4" @submit.prevent="upload">
                    <input ref="input" type="file" name="files[]" accept=".xlsx,.xls,.csv" multiple class="dw-field block w-full p-2 text-sm" required @change="selectFiles" />
                    <InputError :message="form.errors.files || form.errors['files.0']" />

                    <div v-if="form.files.length" class="rounded-md border border-gray-200 dark:border-gray-900">
                        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-400 dark:border-gray-900">
                            <span>{{ form.files.length }} fichier(s) sélectionné(s)</span>
                            <span>{{ (form.files.reduce((size, file) => size + file.size, 0) / 1048576).toFixed(2) }} Mo</span>
                        </div>
                        <ul class="divide-y divide-gray-200 dark:divide-gray-900">
                            <li v-for="(file, index) in form.files" :key="`${file.name}-${file.lastModified}`" class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                                <span class="min-w-0 truncate font-semibold text-slate-600 dark:text-slate-300">{{ file.name }}</span>
                                <button type="button" class="shrink-0 text-xs font-bold text-red-600 hover:text-red-700" @click="removeFile(index)">Retirer</button>
                            </li>
                        </ul>
                    </div>

                    <div v-if="form.progress" class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-900">
                        <div class="h-full rounded-full bg-primary-600 transition-all" :style="{ width: `${form.progress.percentage}%` }" />
                    </div>

                    <button type="submit" class="dw-btn-primary" :disabled="form.processing || !form.files.length">
                        {{ form.processing ? 'Analyse en cours…' : `Analyser ${form.files.length || ''} fichier${form.files.length > 1 ? 's' : ''}` }}
                    </button>
                </form>
            </div>
        </section>

        <section class="dw-card mt-6 overflow-x-auto">
            <table class="dw-table min-w-[700px] text-sm">
                <thead><tr><th class="p-4 text-start">Fichier importé</th><th class="p-4">Ouvrages</th><th class="p-4">Valides</th><th class="p-4">À corriger</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="item in imports.data" :key="item.id">
                        <td class="p-4 font-semibold">{{ item.original_filename }}</td><td class="p-4 text-center">{{ item.total_rows }}</td><td class="p-4 text-center text-emerald-600">{{ item.valid_rows }}</td><td class="p-4 text-center text-red-600">{{ item.error_rows }}</td><td class="p-4 text-end"><Link :href="route('book-imports.show', item.id)" class="text-xs font-bold text-primary-600">Contrôler</Link></td>
                    </tr>
                    <tr v-if="!imports.data.length"><td colspan="5" class="p-10 text-center text-slate-400">Aucun fichier importé pour le moment.</td></tr>
                </tbody>
            </table>
        </section>
    </AuthenticatedLayout>
</template>
