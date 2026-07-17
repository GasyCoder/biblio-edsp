<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import InputError from '@/Components/InputError.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Student = { id: number; registration_number: string; academic_number?: string; last_name: string; first_name: string; photo_url?: string; academic_level?: { name: string }; academic_program?: { name: string } };
const props = defineProps<{ students: Student[] }>();
const search = ref('');
const form = useForm<{ student_ids: number[]; expires_at: string }>({ student_ids: [], expires_at: '' });

const filteredStudents = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('fr');
    if (!term) return props.students;
    return props.students.filter((student) => [student.last_name, student.first_name, student.registration_number, student.academic_number, student.academic_level?.name, student.academic_program?.name]
        .filter(Boolean).join(' ').toLocaleLowerCase('fr').includes(term));
});
const filteredIds = computed(() => filteredStudents.value.map((student) => student.id));
const allFilteredSelected = computed(() => filteredIds.value.length > 0 && filteredIds.value.every((id) => form.student_ids.includes(id)));
const toggleFiltered = () => {
    if (allFilteredSelected.value) form.student_ids = form.student_ids.filter((id) => !filteredIds.value.includes(id));
    else form.student_ids = [...new Set([...form.student_ids, ...filteredIds.value])];
};
const submit = () => form.post(route('cards.store'));
</script>

<template>
    <Head title="Créer des cartes de bibliothèque" />
    <AuthenticatedLayout>
        <template #header><div><Link :href="route('cards.index')" class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">← Retour aux cartes</Link><h1 class="dw-page-title">Créer des cartes de bibliothèque</h1><p class="dw-page-description">Sélectionnez un ou plusieurs étudiants et créez leurs cartes en une seule opération.</p></div></template>

        <form class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]" @submit.prevent="submit">
            <section class="dw-card overflow-hidden">
                <div class="border-b border-gray-200 p-5 dark:border-gray-900 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-heading font-bold text-slate-700">Étudiants sans carte active</h2><p class="mt-1 text-sm text-slate-500">{{ students.length }} étudiant(s) disponible(s)</p></div><button v-if="filteredStudents.length" type="button" class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300" @click="toggleFiltered">{{ allFilteredSelected ? 'Désélectionner les résultats' : 'Sélectionner tous les résultats' }}</button></div>
                    <div class="relative mt-4"><AppIcon name="search" class="absolute start-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500 dark:text-slate-400"/><input v-model="search" class="dw-field ps-11" placeholder="Rechercher par nom, numéro, niveau ou parcours…" /></div>
                    <InputError class="mt-2" :message="form.errors.student_ids || form.errors['student_ids.0']" />
                </div>

                <div v-if="filteredStudents.length" class="grid max-h-[560px] gap-px overflow-y-auto bg-gray-200 dark:bg-gray-900 md:grid-cols-2">
                    <label v-for="student in filteredStudents" :key="student.id" class="flex cursor-pointer items-center gap-3 bg-white p-4 transition hover:bg-primary-50 dark:bg-gray-950 dark:hover:bg-primary-950/30" :class="form.student_ids.includes(student.id) ? '!bg-primary-50 ring-1 ring-inset ring-primary-300 dark:!bg-primary-950/40 dark:ring-primary-800' : ''">
                        <input v-model="form.student_ids" type="checkbox" :value="student.id" class="h-4 w-4 shrink-0 rounded border-gray-300 text-primary-600" />
                        <img loading="lazy" decoding="async" v-if="student.photo_url" :src="student.photo_url" :alt="`Photo de ${student.first_name} ${student.last_name}`" class="h-12 w-10 shrink-0 rounded object-cover" />
                        <span v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300">{{ student.first_name[0] }}{{ student.last_name[0] }}</span>
                        <span class="min-w-0"><strong class="block truncate text-sm text-slate-700 dark:text-slate-200">{{ student.last_name }} {{ student.first_name }}</strong><span class="mt-1 block font-mono text-xs text-primary-600">{{ student.registration_number }}</span><span class="mt-1 block truncate text-xs text-slate-500 dark:text-slate-400">{{ [student.academic_level?.name, student.academic_program?.name].filter(Boolean).join(' · ') || student.academic_number || 'Formation non renseignée' }}</span></span>
                    </label>
                </div>
                <div v-else class="p-12 text-center"><AppIcon name="students" class="mx-auto h-10 w-10 text-slate-300"/><p class="mt-3 text-sm font-bold text-slate-600">Aucun étudiant trouvé</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Modifiez la recherche ou vérifiez que les étudiants ne possèdent pas déjà une carte active.</p></div>
            </section>

            <aside class="dw-card h-fit p-5 xl:sticky xl:top-24">
                <p class="dw-page-kicker">Attribution groupée</p><h2 class="mt-1 font-heading text-lg font-bold text-slate-700">{{ form.student_ids.length }} carte(s)</h2><p class="dw-page-description">Chaque carte utilisera le numéro de bibliothèque de l’étudiant et un QR code unique.</p>
                <div class="mt-5"><label class="mb-2 block text-sm font-semibold">Date d’expiration commune</label><input v-model="form.expires_at" type="date" class="dw-field"/><p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Facultative. Elle sera appliquée à toutes les cartes sélectionnées.</p><InputError class="mt-2" :message="form.errors.expires_at" /></div>
                <div class="mt-6 space-y-3 border-t border-gray-200 pt-5 dark:border-gray-900"><button class="dw-btn-primary w-full justify-center" :disabled="form.processing || !form.student_ids.length">{{ form.processing ? 'Création en cours…' : `Créer ${form.student_ids.length} carte(s)` }}</button><Link :href="route('cards.index')" class="dw-btn-secondary flex w-full justify-center">Annuler</Link></div>
            </aside>
        </form>
    </AuthenticatedLayout>
</template>
