<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import InputError from '@/Components/InputError.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';

type Item = { id: number; returned_at?: string; copy: { inventory_number: string; book: { title: string } } };
type Session = { id: number; session_number: string; opened_at: string; closed_at?: string; items: Item[] };
type Visit = { id: number; visit_number: string; checked_in_at: string; consultation_session?: Session };
type Student = { id: number; registration_number: string; academic_number?: string; first_name: string; last_name: string; status: string; level?: string; program?: string };

const props = defineProps<{ query: string; student?: Student; visit?: Visit; matches: Student[] }>();
const scan = ref(props.query);
const scanInput = ref<HTMLInputElement>();
const copyInput = ref<HTMLInputElement>();
const copyForm = useForm({ barcode: '' });
const session = computed(() => props.visit?.consultation_session);
const activeItems = computed(() => session.value?.items.filter((item) => !item.returned_at) ?? []);

const findStudent = () => router.get(route('desk.index'), { q: scan.value }, { preserveState: false, replace: true });
const post = (url: string) => router.post(url, {}, { preserveScroll: true });
const addCopy = () => {
    if (!session.value) return;
    copyForm.post(route('desk.consultations.copies.store', session.value.id), {
        preserveScroll: true,
        onSuccess: () => { copyForm.reset(); nextTick(() => copyInput.value?.focus()); },
    });
};

onMounted(() => scanInput.value?.focus());
</script>

<template>
    <Head title="Comptoir de la bibliothèque" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="text-xs font-bold uppercase tracking-widest text-primary-600">Opération rapide</p>
                <h1 class="mt-1 font-heading text-2xl font-bold text-slate-800">Comptoir de la bibliothèque</h1>
                <p class="mt-2 text-sm text-slate-500">Scannez une carte ou recherchez par matricule, numéro ou nom.</p>
            </div>
        </template>

        <div v-if="$page.props.flash?.success" class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">{{ $page.props.flash.success }}</div>

        <section class="dw-card p-5 sm:p-6">
            <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="findStudent">
                <div class="relative flex-1">
                    <AppIcon name="scan" class="absolute start-4 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-500" />
                    <input ref="scanInput" v-model="scan" class="dw-field ps-12 text-base" placeholder="Scannez la carte ou saisissez une recherche…" autocomplete="off" />
                </div>
                <button class="h-12 rounded-md bg-primary-600 px-6 text-sm font-bold text-white hover:bg-primary-700">Identifier</button>
            </form>
            <InputError class="mt-2" :message="$page.props.errors?.student" />
        </section>

        <section v-if="student" class="mt-6 grid gap-6 xl:grid-cols-[360px_1fr]">
            <article class="dw-card overflow-hidden">
                <div class="bg-gradient-to-br from-primary-600 to-primary-800 p-6 text-white">
                    <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-white/15 text-2xl font-bold ring-1 ring-white/20">{{ student.first_name[0] }}{{ student.last_name[0] }}</div>
                    <h2 class="font-heading text-xl font-bold">{{ student.last_name }} {{ student.first_name }}</h2>
                    <p class="mt-1 font-mono text-xs text-primary-100">{{ student.registration_number }}</p>
                </div>
                <div class="space-y-4 p-5 text-sm">
                    <div class="flex justify-between"><span class="text-slate-400">Matricule</span><strong>{{ student.academic_number || '—' }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-400">Formation</span><strong>{{ [student.level, student.program].filter(Boolean).join(' · ') || '—' }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-400">Statut</span><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ student.status }}</span></div>
                </div>
            </article>

            <div class="space-y-6">
                <article v-if="!visit" class="dw-card p-6 text-center sm:p-10">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-300"><AppIcon name="visits" class="h-7 w-7" /></div>
                    <h2 class="mt-4 font-heading text-lg font-bold text-slate-800">Aucune présence ouverte</h2>
                    <p class="mt-2 text-sm text-slate-500">Enregistrez l’entrée pour commencer les opérations.</p>
                    <button class="mt-6 rounded-md bg-primary-600 px-6 py-3 text-sm font-bold text-white" @click="post(route('desk.check-in', student.id))">Enregistrer l’entrée</button>
                </article>

                <template v-else>
                    <article class="dw-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                        <div><p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Présence en cours</p><h2 class="mt-1 font-heading font-bold text-slate-800">{{ visit.visit_number }}</h2><p class="mt-1 text-xs text-slate-400">Entrée : {{ new Date(visit.checked_in_at).toLocaleString('fr-FR') }}</p></div>
                        <button v-if="session?.closed_at || !session" class="ms-auto rounded-md border border-red-200 px-4 py-2.5 text-sm font-bold text-red-600 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-950" @click="post(route('desk.check-out', visit.id))">Enregistrer la sortie</button>
                    </article>

                    <article v-if="!session" class="dw-card p-6 text-center">
                        <h2 class="font-heading text-lg font-bold text-slate-800">Consultation sur place</h2>
                        <p class="mt-2 text-sm text-slate-500">Ouvrez une session avant de scanner les exemplaires.</p>
                        <button class="mt-5 rounded-md bg-primary-600 px-5 py-2.5 text-sm font-bold text-white" @click="post(route('desk.consultations.open', visit.id))">Ouvrir la consultation</button>
                    </article>

                    <article v-else class="dw-card overflow-hidden">
                        <div class="border-b border-slate-100 p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-widest text-primary-600">Session {{ session.session_number }}</p><h2 class="mt-1 font-heading font-bold text-slate-800">Exemplaires consultés</h2></div><span class="rounded-full px-3 py-1 text-xs font-bold" :class="session.closed_at ? 'bg-slate-100 text-slate-500' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'">{{ session.closed_at ? 'Clôturée' : `${activeItems.length} à restituer` }}</span></div>
                            <form v-if="!session.closed_at" class="mt-5 flex gap-2" @submit.prevent="addCopy"><input ref="copyInput" v-model="copyForm.barcode" class="dw-field" placeholder="Scanner le code de l’exemplaire…" autocomplete="off" /><button class="rounded-md bg-slate-800 px-5 text-sm font-bold text-white dark:bg-primary-600">Ajouter</button></form>
                            <InputError class="mt-2" :message="copyForm.errors.barcode || $page.props.errors?.copy" />
                        </div>
                        <div v-if="session.items.length" class="divide-y divide-slate-100">
                            <div v-for="item in session.items" :key="item.id" class="flex items-center gap-4 p-4 sm:px-5"><AppIcon name="books" class="h-5 w-5 shrink-0 text-primary-500"/><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-700">{{ item.copy.book.title }}</p><p class="mt-1 font-mono text-xs text-slate-400">{{ item.copy.inventory_number }}</p></div><span v-if="item.returned_at" class="ms-auto text-xs font-bold text-emerald-600">Restitué</span><button v-else class="ms-auto rounded-md border border-primary-200 px-3 py-2 text-xs font-bold text-primary-600 dark:border-primary-800" @click="post(route('desk.consultations.copies.return', item.id))">Restituer</button></div>
                        </div>
                        <p v-else class="p-10 text-center text-sm text-slate-400">Aucun exemplaire scanné.</p>
                        <div v-if="!session.closed_at" class="border-t border-slate-100 p-5 text-end"><button :disabled="activeItems.length > 0" class="rounded-md bg-primary-600 px-5 py-2.5 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40" @click="post(route('desk.consultations.close', session.id))">Clôturer la session</button></div>
                    </article>
                </template>
            </div>
        </section>

        <section v-else-if="query && matches.length" class="dw-card mt-6 overflow-hidden">
            <div class="border-b border-slate-100 p-5"><h2 class="font-heading font-bold text-slate-800">Plusieurs étudiants correspondent</h2><p class="mt-1 text-sm text-slate-500">Sélectionnez la bonne fiche à partir de son numéro.</p></div>
            <div class="divide-y divide-slate-100"><button v-for="match in matches" :key="match.id" class="flex w-full items-center gap-4 p-4 text-start transition hover:bg-slate-50 sm:px-5" @click="router.get(route('desk.index'), { q: match.registration_number })"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">{{ match.first_name[0] }}{{ match.last_name[0] }}</span><span class="min-w-0"><strong class="block truncate text-sm text-slate-700">{{ match.last_name }} {{ match.first_name }}</strong><span class="mt-1 block font-mono text-xs text-slate-400">{{ match.registration_number }} · {{ match.academic_number || 'sans matricule' }}</span></span><span class="ms-auto hidden text-xs text-slate-400 sm:block">{{ [match.level, match.program].filter(Boolean).join(' · ') }}</span></button></div>
        </section>
        <section v-else-if="query" class="dw-card mt-6 p-10 text-center"><p class="font-heading font-bold text-slate-800">Aucun étudiant trouvé</p><p class="mt-2 text-sm text-slate-500">Vérifiez le code ou utilisez une recherche par nom.</p></section>
    </AuthenticatedLayout>
</template>
