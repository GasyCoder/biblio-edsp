<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import CameraQrScanner from '@/Components/CameraQrScanner.vue';
import InputError from '@/Components/InputError.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';

type Item = { id: number; returned_at?: string; copy: { inventory_number: string; book: { title: string; cover_url?: string } } };
type Session = { id: number; session_number: string; opened_at: string; closed_at?: string; items: Item[] };
type Visit = { id: number; visit_number: string; checked_in_at: string; consultation_session?: Session };
type Loan = { id: number; loan_number: string; opened_at: string; due_at: string; closed_at?: string; items: Item[] };
type AcademicReference = { id: number; code: string; name: string };
type Activity = { key: string; type: 'entry' | 'exit' | 'consultation' | 'loan' | 'return'; label: string; occurred_at: string; student: Pick<Student, 'registration_number' | 'first_name' | 'last_name'>; book?: string; inventory_number?: string };
type ActiveVisit = { id: number; visit_number: string; checked_in_at: string; student: Student; consultation?: { is_open: boolean; active_copies: number }; loan?: { loan_number: string; active_copies: number; due_at: string } };
type Student = {
    id: number;
    registration_number: string;
    academic_number?: string;
    first_name: string;
    last_name: string;
    status: string;
    level?: string;
    program?: string;
    academic_year?: string;
    photo_url?: string;
    academic_level?: AcademicReference;
    mention?: AcademicReference;
    academic_program?: AcademicReference;
    cards?: { id: number; card_number: string; status: string; expires_at?: string }[];
};

const props = defineProps<{ query: string; student?: Student; visit?: Visit; loan?: Loan; matches: Student[]; activeVisits: ActiveVisit[]; recentActivity: Activity[]; operationSettings: { defaultLoanDays: number; scannerInactivitySeconds: number } }>();
const scan = ref(props.query);
const scanInput = ref<HTMLInputElement>();
const copyInput = ref<HTMLInputElement>();
const copyForm = useForm({ barcode: '' });
const defaultDueDate = () => { const date = new Date(); date.setDate(date.getDate() + props.operationSettings.defaultLoanDays); return date.toISOString().slice(0, 10); };
const loanOpenForm = useForm({ due_at: defaultDueDate() });
const loanCopyForm = useForm({ barcode: '' });
const cameraTarget = ref<'student' | 'copy' | 'loan-copy' | null>(null);
const session = computed(() => props.visit?.consultation_session);
const activeItems = computed(() => session.value?.items.filter((item) => !item.returned_at) ?? []);
const activeLoanItems = computed(() => props.loan?.items.filter((item) => !item.returned_at) ?? []);
const statusLabels: Record<string, string> = { active: 'Actif', inactive: 'Inactif', suspended: 'Suspendu', graduated: 'Diplômé' };
const activityStyles: Record<Activity['type'], string> = { entry: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950', exit: 'bg-slate-100 text-slate-500 dark:bg-slate-800', consultation: 'bg-primary-50 text-primary-600 dark:bg-primary-950', loan: 'bg-amber-50 text-amber-600 dark:bg-amber-950', return: 'bg-cyan-50 text-cyan-600 dark:bg-cyan-950' };
const activityIcon = (type: Activity['type']) => type === 'loan' ? 'loans' : type === 'consultation' || type === 'return' ? 'books' : 'visits';
const presenceDuration = (checkedInAt: string) => {
    const minutes = Math.max(0, Math.floor((Date.now() - new Date(checkedInAt).getTime()) / 60000));
    return minutes < 60 ? `${minutes} min` : `${Math.floor(minutes / 60)} h ${minutes % 60} min`;
};

const findStudent = () => router.get(route('desk.index'), { q: scan.value }, { preserveState: false, replace: true });
const post = (url: string) => router.post(url, {}, { preserveScroll: true });
const addCopy = () => {
    if (!session.value) return;
    copyForm.post(route('desk.consultations.copies.store', session.value.id), {
        preserveScroll: true,
        onSuccess: () => { copyForm.reset(); nextTick(() => copyInput.value?.focus()); },
    });
};
const openLoan = () => {
    if (!props.student) return;
    loanOpenForm.post(route('desk.loans.open', props.student.id), { preserveScroll: true });
};
const addLoanCopy = () => {
    if (!props.loan) return;
    loanCopyForm.post(route('desk.loans.copies.store', props.loan.id), {
        preserveScroll: true,
        onSuccess: () => { loanCopyForm.reset('barcode'); nextTick(() => copyInput.value?.focus()); },
    });
};
const useCameraResult = (value: string) => {
    const target = cameraTarget.value;
    if (target === 'student') {
        cameraTarget.value = null;
        scan.value = value;
        findStudent();
        return;
    }
    if (target === 'copy') {
        if (copyForm.processing) return;
        copyForm.barcode = value;
        addCopy();
        return;
    }
    if (target === 'loan-copy') {
        if (loanCopyForm.processing) return;
        loanCopyForm.barcode = value;
        addLoanCopy();
    }
};

onMounted(() => scanInput.value?.focus());
</script>

<template>
    <Head title="Comptoir de la bibliothèque" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <p class="dw-page-kicker">Opération rapide</p>
                <h1 class="dw-page-title">Comptoir de la bibliothèque</h1>
                <p class="dw-page-description">Scannez d’abord la carte de bibliothèque pour identifier l’étudiant.</p>
            </div>
        </template>

        <div v-if="$page.props.flash?.success" class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">{{ $page.props.flash.success }}</div>

        <section class="dw-card p-5 sm:p-6">
            <form class="flex flex-col gap-3 sm:flex-row" @submit.prevent="findStudent">
                <div class="relative flex-1">
                    <AppIcon name="scan" class="absolute start-4 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-500" />
                    <input ref="scanInput" v-model="scan" class="dw-field ps-12 text-base" placeholder="Carte BIB-26-001, 26-001, matricule ou nom…" autocomplete="off" />
                </div>
                <button type="button" class="flex h-11 items-center justify-center gap-2 rounded-md border border-primary-300 px-5 text-sm font-semibold text-primary-600 transition-colors hover:bg-primary-50 dark:border-primary-800 dark:text-primary-300 dark:hover:bg-primary-950" @click="cameraTarget = 'student'">
                    <AppIcon name="scan" class="h-5 w-5" /> Scanner la carte
                </button>
                <button class="h-11 rounded-md bg-primary-600 px-6 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-700">Identifier</button>
            </form>
            <InputError class="mt-2" :message="$page.props.errors?.student" />
        </section>

        <details class="dw-card group mt-6 overflow-hidden" open>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 marker:hidden sm:px-6">
                <div class="flex items-center gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300"><AppIcon name="visits" class="h-5 w-5" /></span>
                    <div><p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Suivi en temps réel</p><h2 class="mt-1 font-heading text-lg font-bold text-slate-800">Présences en cours</h2><p class="mt-1 text-xs text-slate-500">Étudiants entrés dans la bibliothèque et dont la sortie n’est pas encore enregistrée.</p></div>
                </div>
                <div class="flex shrink-0 items-center gap-3"><span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ activeVisits.length }} présent(s)</span><AppIcon name="chevron-down" class="h-5 w-5 text-slate-500 dark:text-slate-400 transition group-open:rotate-180" /></div>
            </summary>
            <div class="border-t border-gray-200 dark:border-gray-900">
                <div v-if="activeVisits.length" class="grid gap-px bg-gray-200 dark:bg-gray-900 md:grid-cols-2 2xl:grid-cols-3">
                    <button v-for="presence in activeVisits" :key="presence.id" class="flex min-w-0 items-start gap-4 bg-white p-5 text-start transition hover:bg-primary-50 dark:bg-gray-950 dark:hover:bg-primary-950/30" @click="router.get(route('desk.index'), { q: presence.student.registration_number })">
                        <img v-if="presence.student.photo_url" :src="presence.student.photo_url" :alt="`Photo de ${presence.student.first_name} ${presence.student.last_name}`" class="h-14 w-12 shrink-0 rounded-md object-cover" />
                        <span v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300">{{ presence.student.first_name[0] }}{{ presence.student.last_name[0] }}</span>
                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold text-slate-700 dark:text-slate-200">{{ presence.student.last_name }} {{ presence.student.first_name }}</span><span class="mt-1 block font-mono text-xs text-primary-600">{{ presence.student.registration_number }}</span><span class="mt-2 flex flex-wrap gap-1.5"><span class="rounded bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Depuis {{ presenceDuration(presence.checked_in_at) }}</span><span v-if="presence.consultation?.is_open" class="rounded bg-primary-50 px-2 py-1 text-[11px] font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300">{{ presence.consultation.active_copies }} en consultation</span><span v-if="presence.loan" class="rounded bg-amber-50 px-2 py-1 text-[11px] font-bold text-amber-700 dark:bg-amber-950 dark:text-amber-300">{{ presence.loan.active_copies }} en prêt</span></span></span>
                        <AppIcon name="chevron-down" class="mt-1 h-4 w-4 -rotate-90 text-slate-500 dark:text-slate-400" />
                    </button>
                </div>
                <div v-else class="p-10 text-center"><span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-500 dark:bg-emerald-950"><AppIcon name="visits" class="h-6 w-6" /></span><p class="mt-3 text-sm font-bold text-slate-600">Aucune présence ouverte</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Les étudiants apparaîtront ici après l’enregistrement de leur entrée.</p></div>
            </div>
        </details>

        <section v-if="student" class="mt-6 grid gap-6 xl:grid-cols-[360px_1fr]">
            <article class="dw-card self-start overflow-hidden xl:sticky xl:top-24">
                <div class="relative min-h-64 overflow-hidden p-6 text-white">
                    <img src="/images/desk/student-profile-cover.png" alt="" class="absolute inset-0 h-full w-full object-cover" />
                    <div class="absolute inset-0 bg-gradient-to-b from-slate-950/25 via-primary-950/55 to-primary-950/95" />
                    <div class="relative z-10 flex min-h-52 flex-col justify-end">
                        <img v-if="student.photo_url" :src="student.photo_url" :alt="`Photo de ${student.first_name} ${student.last_name}`" class="mb-5 h-28 w-24 rounded-lg border-2 border-white/70 object-cover shadow-xl" />
                        <div v-else class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-white/15 text-2xl font-bold ring-1 ring-white/30 backdrop-blur-sm">{{ student.first_name[0] }}{{ student.last_name[0] }}</div>
                        <h2 class="font-heading text-xl font-bold !text-white [text-shadow:0_2px_5px_rgba(0,0,0,0.9)]">{{ student.last_name }} {{ student.first_name }}</h2>
                        <p class="mt-1 font-mono text-sm font-bold !text-white [text-shadow:0_1px_4px_rgba(0,0,0,0.9)]">{{ student.registration_number }}</p>
                    </div>
                </div>
                <div class="space-y-4 p-5 text-sm">
                    <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Matricule</span><strong>{{ student.academic_number || '—' }}</strong></div>
                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Mention</span><strong class="text-end">{{ student.mention?.name || '—' }}</strong></div>
                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Parcours</span><strong class="text-end">{{ student.academic_program?.name || student.program || '—' }}</strong></div>
                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Niveau</span><strong class="text-end">{{ student.academic_level?.name || student.level || '—' }}</strong></div>
                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Année universitaire</span><strong class="text-end">{{ student.academic_year || '—' }}</strong></div>
                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Carte physique</span><strong class="text-end text-xs" :class="student.cards?.length ? 'text-emerald-600' : 'text-amber-600'">{{ student.cards?.length ? 'Active' : 'Non délivrée' }}</strong></div>
                    <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Statut</span><span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="student.status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'">{{ statusLabels[student.status] || student.status }}</span></div>
                </div>
            </article>

            <div class="space-y-6">
                <article v-if="!visit" class="dw-card p-6 text-center sm:p-10">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-900/30 dark:text-primary-300"><AppIcon name="visits" class="h-7 w-7" /></div>
                    <h2 class="mt-4 font-heading text-lg font-bold text-slate-800">Aucune présence ouverte</h2>
                    <p class="dw-page-description">Enregistrez l’entrée pour commencer les opérations.</p>
                    <button class="mt-6 rounded-md bg-primary-600 px-6 py-3 text-sm font-bold text-white" @click="post(route('desk.check-in', student.id))">Enregistrer l’entrée</button>
                </article>

                <template v-else>
                    <article class="dw-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                        <div><p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Présence en cours</p><h2 class="mt-1 font-heading font-bold text-slate-800">{{ visit.visit_number }}</h2><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Entrée : {{ new Date(visit.checked_in_at).toLocaleString('fr-FR') }}</p></div>
                        <button v-if="session?.closed_at || !session" class="ms-auto rounded-md border border-red-200 px-4 py-2.5 text-sm font-bold text-red-600 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-950" @click="post(route('desk.check-out', visit.id))">Enregistrer la sortie</button>
                    </article>

                    <article v-if="!session" class="dw-card p-6 text-center">
                        <h2 class="font-heading text-lg font-bold text-slate-800">Consultation sur place</h2>
                        <p class="dw-page-description">Ouvrez une session avant de scanner les exemplaires.</p>
                        <button class="mt-5 rounded-md bg-primary-600 px-5 py-2.5 text-sm font-bold text-white" @click="post(route('desk.consultations.open', visit.id))">Ouvrir la consultation</button>
                    </article>

                    <article v-else class="dw-card overflow-hidden">
                        <div class="border-b border-slate-100 p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="dw-page-kicker">Session {{ session.session_number }}</p><h2 class="mt-1 font-heading font-bold text-slate-800">Exemplaires consultés</h2></div><span class="rounded-full px-3 py-1 text-xs font-bold" :class="session.closed_at ? 'bg-slate-100 text-slate-500' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'">{{ session.closed_at ? 'Clôturée' : `${activeItems.length} à restituer` }}</span></div>
                            <form v-if="!session.closed_at" class="mt-5 flex flex-col gap-2 sm:flex-row" @submit.prevent="addCopy"><input ref="copyInput" v-model="copyForm.barcode" class="dw-field flex-1" placeholder="Scanner le code de l’exemplaire…" autocomplete="off" /><button type="button" class="flex h-11 items-center justify-center gap-2 rounded-md border border-primary-300 px-4 text-sm font-semibold text-primary-600 transition-colors hover:bg-primary-50 dark:border-primary-800 dark:text-primary-300 dark:hover:bg-primary-950" @click="cameraTarget = 'copy'"><AppIcon name="scan" class="h-5 w-5" /> Caméra</button><button class="h-11 rounded-md bg-slate-800 px-5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 dark:bg-primary-600 dark:hover:bg-primary-700">Ajouter</button></form>
                            <InputError class="mt-2" :message="copyForm.errors.barcode || $page.props.errors?.copy" />
                        </div>
                        <div v-if="session.items.length" class="divide-y divide-slate-100">
                            <div v-for="item in session.items" :key="item.id" class="flex items-center gap-4 p-4 sm:px-5"><img v-if="item.copy.book.cover_url" :src="item.copy.book.cover_url" :alt="`Couverture de ${item.copy.book.title}`" class="h-14 w-10 shrink-0 rounded border border-slate-200 object-cover shadow-sm dark:border-slate-700"/><div v-else class="flex h-14 w-10 shrink-0 items-center justify-center rounded border border-dashed border-slate-300 bg-slate-50 text-primary-500 dark:border-slate-700 dark:bg-slate-900"><AppIcon name="books" class="h-5 w-5"/></div><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-700">{{ item.copy.book.title }}</p><p class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400">{{ item.copy.inventory_number }}</p></div><span v-if="item.returned_at" class="ms-auto text-xs font-bold text-emerald-600">Restitué</span><button v-else class="ms-auto rounded-md border border-primary-200 px-3 py-2 text-xs font-bold text-primary-600 dark:border-primary-800" @click="post(route('desk.consultations.copies.return', item.id))">Restituer</button></div>
                        </div>
                        <p v-else class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">Aucun exemplaire scanné.</p>
                        <div v-if="!session.closed_at" class="border-t border-slate-100 p-5 text-end"><p v-if="activeItems.length" class="mb-3 text-xs text-slate-500">La clôture rendra automatiquement disponible {{ activeItems.length }} exemplaire(s).</p><button class="dw-btn-primary" @click="post(route('desk.consultations.close', session.id))">Clôturer et libérer les exemplaires</button></div>
                        <div v-else class="flex flex-col gap-3 border-t border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"><p class="text-sm text-slate-500">L’étudiant veut consulter d’autres livres ? Ouvrez une nouvelle session sans enregistrer une nouvelle entrée.</p><button class="shrink-0 rounded-md bg-primary-600 px-5 py-2.5 text-sm font-bold text-white" @click="post(route('desk.consultations.open', visit.id))">Nouvelle consultation</button></div>
                    </article>

                    <article class="dw-card overflow-hidden">
                        <div class="border-b border-slate-100 p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div><p class="text-xs font-bold uppercase tracking-widest text-amber-600">Prêt à domicile</p><h2 class="mt-1 font-heading font-bold text-slate-800">Livres emportés par l’étudiant</h2></div>
                                <span v-if="loan" class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700 dark:bg-amber-950 dark:text-amber-300">{{ activeLoanItems.length }} en prêt</span>
                            </div>

                            <form v-if="!loan" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="openLoan">
                                <div class="flex-1"><label class="mb-2 block text-xs font-bold uppercase text-slate-500 dark:text-slate-400">Date limite de retour</label><input v-model="loanOpenForm.due_at" type="date" class="dw-field" required /></div>
                                <button class="h-11 rounded-md bg-amber-600 px-5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-amber-700">Ouvrir un prêt</button>
                            </form>
                            <InputError class="mt-2" :message="loanOpenForm.errors.due_at || $page.props.errors?.loan" />

                            <template v-if="loan">
                                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500 dark:text-slate-400"><span>{{ loan.loan_number }}</span><span>Retour avant le {{ new Date(loan.due_at).toLocaleDateString('fr-FR') }}</span></div>
                                <form class="mt-5 flex flex-col gap-2 sm:flex-row" @submit.prevent="addLoanCopy">
                                    <input v-model="loanCopyForm.barcode" class="dw-field flex-1" placeholder="Scanner l’exemplaire à prêter…" autocomplete="off" />
                                    <button type="button" class="flex h-11 items-center justify-center gap-2 rounded-md border border-amber-300 px-4 text-sm font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-800 dark:text-amber-300 dark:hover:bg-amber-950" @click="cameraTarget = 'loan-copy'"><AppIcon name="scan" class="h-5 w-5" /> Caméra</button>
                                    <button class="h-11 rounded-md bg-amber-600 px-5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-amber-700">Prêter</button>
                                </form>
                                <InputError class="mt-2" :message="loanCopyForm.errors.barcode || $page.props.errors?.loan_copy" />
                            </template>
                        </div>
                        <div v-if="loan?.items.length" class="divide-y divide-slate-100">
                            <div v-for="item in loan.items" :key="item.id" class="flex items-center gap-4 p-4 sm:px-5"><img v-if="item.copy.book.cover_url" :src="item.copy.book.cover_url" :alt="`Couverture de ${item.copy.book.title}`" class="h-14 w-10 shrink-0 rounded border border-slate-200 object-cover shadow-sm dark:border-slate-700"/><div v-else class="flex h-14 w-10 shrink-0 items-center justify-center rounded border border-dashed border-slate-300 bg-slate-50 text-amber-500 dark:border-slate-700 dark:bg-slate-900"><AppIcon name="loans" class="h-5 w-5"/></div><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-700">{{ item.copy.book.title }}</p><p class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400">{{ item.copy.inventory_number }}</p></div><span v-if="item.returned_at" class="ms-auto text-xs font-bold text-emerald-600">Rendu</span><button v-else class="ms-auto rounded-md border border-amber-200 px-3 py-2 text-xs font-bold text-amber-700 dark:border-amber-800" @click="post(route('desk.loans.copies.return', item.id))">Enregistrer le retour</button></div>
                        </div>
                        <div v-if="loan" class="border-t border-slate-100 p-5 text-end"><button :disabled="activeLoanItems.length > 0" class="rounded-md bg-slate-700 px-5 py-2.5 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40" @click="post(route('desk.loans.close', loan.id))">Clôturer le prêt</button></div>
                    </article>
                </template>
            </div>
        </section>

        <section v-else-if="query && matches.length" class="dw-card mt-6 overflow-hidden">
            <div class="border-b border-slate-100 p-5"><h2 class="font-heading font-bold text-slate-800">Plusieurs étudiants correspondent</h2><p class="mt-1 text-sm text-slate-500">Sélectionnez la bonne fiche à partir de son numéro.</p></div>
            <div class="divide-y divide-slate-100"><button v-for="match in matches" :key="match.id" class="flex w-full items-center gap-4 p-4 text-start transition hover:bg-slate-50 sm:px-5" @click="router.get(route('desk.index'), { q: match.registration_number })"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">{{ match.first_name[0] }}{{ match.last_name[0] }}</span><span class="min-w-0"><strong class="block truncate text-sm text-slate-700">{{ match.last_name }} {{ match.first_name }}</strong><span class="mt-1 block font-mono text-xs text-slate-500 dark:text-slate-400">{{ match.registration_number }} · {{ match.academic_number || 'sans matricule' }}</span></span><span class="ms-auto hidden text-xs text-slate-500 dark:text-slate-400 sm:block">{{ [match.level, match.program].filter(Boolean).join(' · ') }}</span></button></div>
        </section>
        <section v-else-if="query" class="dw-card mt-6 p-10 text-center"><p class="font-heading font-bold text-slate-800">Aucun étudiant trouvé</p><p class="dw-page-description">Vérifiez le code ou utilisez une recherche par nom.</p></section>

        <details class="dw-card group mt-6 overflow-hidden">
            <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 p-5 marker:hidden dark:border-slate-800">
                <div><p class="dw-page-kicker">Activité du comptoir</p><h2 class="mt-1 font-heading text-lg font-bold text-slate-800">Historique récent des scans</h2></div>
                <span class="flex items-center gap-3"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500 dark:bg-slate-800">{{ recentActivity.length }} dernières opérations</span><AppIcon name="chevron-down" class="h-5 w-5 text-slate-500 dark:text-slate-400 transition group-open:rotate-180" /></span>
            </summary>
            <div v-if="recentActivity.length" class="divide-y divide-slate-100 border-t border-slate-100 dark:divide-slate-800 dark:border-slate-800">
                <div v-for="activity in recentActivity" :key="activity.key" class="grid gap-3 p-4 sm:grid-cols-[44px_minmax(180px,0.8fr)_minmax(220px,1.4fr)_auto] sm:items-center sm:px-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg" :class="activityStyles[activity.type]"><AppIcon :name="activityIcon(activity.type)" class="h-5 w-5" /></span>
                    <div class="min-w-0"><p class="text-sm font-bold text-slate-700">{{ activity.label }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ new Date(activity.occurred_at).toLocaleString('fr-FR') }}</p></div>
                    <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-700">{{ activity.student.last_name }} {{ activity.student.first_name }}</p><p class="mt-1 font-mono text-xs text-primary-600">{{ activity.student.registration_number }}</p></div>
                    <div v-if="activity.book" class="min-w-0 sm:max-w-80 sm:text-end"><p class="truncate text-sm text-slate-600">{{ activity.book }}</p><p class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400">{{ activity.inventory_number }}</p></div>
                </div>
            </div>
            <div v-else class="p-10 text-center"><AppIcon name="scan" class="mx-auto h-9 w-9 text-slate-300"/><p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Aucune opération de scan enregistrée pour le moment.</p></div>
        </details>

        <CameraQrScanner
            :open="cameraTarget !== null"
            :continuous="cameraTarget === 'copy' || cameraTarget === 'loan-copy'"
            :inactivity-seconds="operationSettings.scannerInactivitySeconds"
            :title="cameraTarget === 'copy' ? 'Scanner un livre pour consultation' : cameraTarget === 'loan-copy' ? 'Scanner un livre à prêter' : 'Scanner une carte de bibliothèque'"
            :help="cameraTarget === 'copy' || cameraTarget === 'loan-copy' ? 'Présentez devant la caméra l’étiquette QR collée sur le livre.' : 'Présentez devant la caméra le QR de la carte de bibliothèque.'"
            @close="cameraTarget = null"
            @detected="useCameraResult"
        />
    </AuthenticatedLayout>
</template>
