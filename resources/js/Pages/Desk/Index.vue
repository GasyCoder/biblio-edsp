<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import CameraQrScanner from "@/Components/CameraQrScanner.vue";
import InputError from "@/Components/InputError.vue";
import Modal from "@/Components/Modal.vue";
import ScanFeedbackCard from "@/Components/ScanFeedbackCard.vue";
import { playBeep, useScanFeedback } from "@/Composables/useScanFeedback";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, nextTick, onMounted, ref } from "vue";

type Item = {
    id: number;
    returned_at?: string;
    scanned_at?: string;
    loaned_at?: string;
    copy: {
        inventory_number: string;
        book: { title: string; cover_url?: string };
    };
};
type Session = {
    id: number;
    session_number: string;
    opened_at: string;
    closed_at?: string;
    items: Item[];
};
type Visit = {
    id: number;
    visit_number: string;
    checked_in_at: string;
    consultation_session?: Session;
};
type Loan = {
    id: number;
    loan_number: string;
    opened_at: string;
    due_at: string;
    closed_at?: string;
    items: Item[];
};
type AcademicReference = { id: number; code: string; name: string };
type Activity = {
    key: string;
    type: "entry" | "exit" | "consultation" | "loan" | "return";
    label: string;
    occurred_at: string;
    student: Pick<Student, "id" | "registration_number" | "first_name" | "last_name">;
    book?: { title: string; cover_url?: string };
    inventory_number?: string;
};
type ActiveVisit = {
    id: number;
    visit_number: string;
    checked_in_at: string;
    student: Student;
    consultation?: { is_open: boolean; active_copies: number };
    loan?: { loan_number: string; active_copies: number; due_at: string };
};
type RecentExit = {
    id: number;
    visit_number: string;
    checked_in_at: string;
    checked_out_at: string;
    consultations_count: number;
    student: Student;
};
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
    cards?: {
        id: number;
        card_number: string;
        status: string;
        expires_at?: string;
    }[];
};

const props = defineProps<{
    query: string;
    mode: "counter" | "entry" | "exit";
    student?: Student;
    visit?: Visit;
    loan?: Loan;
    matches: Student[];
    activeVisits: ActiveVisit[];
    recentExits: RecentExit[];
    recentActivity: Activity[];
    operationSettings: {
        defaultLoanDays: number;
        scannerInactivitySeconds: number;
    };
}>();
const isCounterMode = computed(() => props.mode === "counter");
const modeTitle = computed(() =>
    props.mode === "entry"
        ? "Pointage des entrées"
        : props.mode === "exit"
          ? "Pointage des sorties"
          : "Comptoir de la bibliothèque",
);
const modeOptions = [
    { value: "counter", label: "Comptoir", help: "Consultations, prêts et retours", icon: "scan", iconClass: "" },
    { value: "entry", label: "Entrées", help: "Pointage automatique des arrivées", icon: "login", iconClass: "" },
    { value: "exit", label: "Sorties", help: "Pointage automatique des départs", icon: "logout", iconClass: "" },
] as const;
const modeTabClass = (value: string) => {
    if (value === "entry")
        return props.mode === value
            ? "bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-800"
            : "text-slate-500 hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-950 dark:hover:text-emerald-300";
    if (value === "exit")
        return props.mode === value
            ? "bg-red-50 text-red-700 ring-1 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-800"
            : "text-slate-500 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950 dark:hover:text-red-300";
    return props.mode === value
        ? "bg-primary-50 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950 dark:text-primary-200 dark:ring-primary-800"
        : "text-slate-500 hover:bg-primary-50 hover:text-primary-700 dark:hover:bg-primary-950 dark:hover:text-primary-300";
};
const modeActionClass = computed(() =>
    props.mode === "entry"
        ? "bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-300"
        : props.mode === "exit"
          ? "bg-red-600 hover:bg-red-700 focus:ring-red-300"
          : "bg-primary-600 hover:bg-primary-700 focus:ring-primary-300",
);
const modeSecondaryClass = computed(() =>
    props.mode === "entry"
        ? "border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-950"
        : props.mode === "exit"
          ? "border-red-300 text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950"
          : "border-primary-300 text-primary-600 hover:bg-primary-50 dark:border-primary-800 dark:text-primary-300 dark:hover:bg-primary-950",
);
const { lastScan } = useScanFeedback();
const scan = ref(props.query);
const presenceTab = ref<"present" | "exited">("present");
const scanInput = ref<HTMLInputElement>();
const copyInput = ref<HTMLInputElement>();
const loanCopyInput = ref<HTMLInputElement>();
const copyForm = useForm({ barcode: "" });
const defaultDueDate = () => {
    const date = new Date();
    date.setDate(date.getDate() + props.operationSettings.defaultLoanDays);
    return date.toISOString().slice(0, 10);
};
const loanOpenForm = useForm({ due_at: defaultDueDate() });
const loanCopyForm = useForm({ barcode: "" });
const cameraTarget = ref<"student" | "copy" | "loan-copy" | null>(null);
const completingVisitId = ref<number | null>(null);
const pendingCompletion = ref<ActiveVisit | null>(null);
const session = computed(() => props.visit?.consultation_session);
const activeItems = computed(
    () => session.value?.items.filter((item) => !item.returned_at) ?? [],
);
const activeLoanItems = computed(
    () => props.loan?.items.filter((item) => !item.returned_at) ?? [],
);
const statusLabels: Record<string, string> = {
    active: "Actif",
    inactive: "Inactif",
    suspended: "Suspendu",
    graduated: "Diplômé",
};
const activityStyles: Record<Activity["type"], string> = {
    entry: "bg-emerald-50 text-emerald-600 dark:bg-emerald-950",
    exit: "bg-slate-100 text-slate-500 dark:bg-slate-800",
    consultation: "bg-primary-50 text-primary-600 dark:bg-primary-950",
    loan: "bg-amber-50 text-amber-600 dark:bg-amber-950",
    return: "bg-cyan-50 text-cyan-600 dark:bg-cyan-950",
};
const activityIcon = (type: Activity["type"]) =>
    type === "loan"
        ? "loans"
        : type === "consultation" || type === "return"
          ? "books"
          : "visits";
const presenceDuration = (checkedInAt: string) => {
    const minutes = Math.max(
        0,
        Math.floor((Date.now() - new Date(checkedInAt).getTime()) / 60000),
    );
    return minutes < 60
        ? `${minutes} min`
        : `${Math.floor(minutes / 60)} h ${minutes % 60} min`;
};
const completedDuration = (entry: string, exit: string) => {
    const minutes = Math.max(0, Math.floor((new Date(exit).getTime() - new Date(entry).getTime()) / 60000));
    return minutes < 60 ? `${minutes} min` : `${Math.floor(minutes / 60)} h ${minutes % 60} min`;
};
const timeOnly = (value: string) =>
    new Date(value).toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" });
const shortDateTime = (value: string) =>
    new Date(value).toLocaleString("fr-FR", {
        day: "2-digit",
        month: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
    });
const isOverdue = (dueAt?: string) => {
    if (!dueAt) return false;
    const due = new Date(dueAt);
    due.setHours(23, 59, 59, 999);
    return due.getTime() < Date.now();
};
const overdueDays = (dueAt: string) =>
    Math.max(1, Math.floor((Date.now() - new Date(dueAt).getTime()) / 86400000));
const filteredActivity = computed(() => {
    const ids = new Set(
        (presenceTab.value === "present" ? props.activeVisits : props.recentExits).map(
            (visit) => visit.student.id,
        ),
    );
    return props.recentActivity.filter((activity) => ids.has(activity.student.id));
});
const latestEntries = computed(() =>
    [...props.activeVisits]
        .sort((a, b) => b.checked_in_at.localeCompare(a.checked_in_at))
        .slice(0, 6),
);
const latestExits = computed(() => props.recentExits.slice(0, 6));

const findStudent = () =>
    router.get(
        route("desk.index"),
        isCounterMode.value
            ? { q: scan.value }
            : { q: scan.value, mode: props.mode },
        { preserveState: false, replace: true },
    );
const lastIdentify = { code: "", at: 0 };
const identifyFromCard = () => {
    const value = scan.value.trim();
    if (!value) return;

    // Une douchette ou la caméra continue peut relire le même code deux fois de suite.
    const now = Date.now();
    if (value === lastIdentify.code && now - lastIdentify.at < 3000) {
        scan.value = "";
        return;
    }
    lastIdentify.code = value;
    lastIdentify.at = now;

    router.post(
        route("desk.identify"),
        { code: value, mode: props.mode },
        {
            preserveScroll: false,
            onSuccess: () => (scan.value = ""),
            onError: () => nextTick(() => scanInput.value?.select()),
            onFinish: () => nextTick(() => scanInput.value?.focus()),
        },
    );
};
const looksLikeCardCode = (value: string) =>
    /^(?:BIB-)?\d{2}-\d+$/i.test(value.trim()) ||
    /^EDSP:CARD:/i.test(value.trim());
const looksLikeCopyCode = (value: string) => /^EDSP:COPY:/i.test(value.trim());
const looksLikeName = (value: string) =>
    /^[\p{L}][\p{L}\s'’.-]*$/u.test(value.trim());
const identifyOrSearch = () => {
    // Un livre scanné dans le champ carte pendant qu'une présence est ouverte
    // rejoint directement la consultation de l'étudiant affiché.
    if (isCounterMode.value && looksLikeCopyCode(scan.value)) {
        if (props.visit) {
            copyForm.barcode = scan.value.trim();
            scan.value = "";
            addCopy();
        } else {
            identifyFromCard();
        }
        return;
    }

    if (isCounterMode.value)
        return looksLikeCardCode(scan.value)
            ? identifyFromCard()
            : findStudent();

    return looksLikeName(scan.value) ? findStudent() : identifyFromCard();
};
const pendingCardSwitch = ref<string | null>(null);
const cancelCardSwitch = () => {
    pendingCardSwitch.value = null;
    nextTick(() => (copyInput.value ?? loanCopyInput.value)?.focus());
};
const confirmCardSwitch = () => {
    if (!pendingCardSwitch.value) return;
    scan.value = pendingCardSwitch.value;
    pendingCardSwitch.value = null;
    identifyFromCard();
};
const selectMatch = (match: Student) => {
    if (isCounterMode.value) {
        router.get(route("desk.index"), { q: match.registration_number });
        return;
    }

    scan.value = "";
    router.post(
        route("desk.identify"),
        { code: match.registration_number, mode: props.mode },
        { onFinish: () => nextTick(() => scanInput.value?.focus()) },
    );
};
const post = (url: string) => router.post(url, {}, { preserveScroll: true });
const openPresence = (presence: ActiveVisit) =>
    router.get(route("desk.index"), {
        q: presence.student.registration_number,
    });
const completePresence = (presence: ActiveVisit) => {
    pendingCompletion.value = presence;
};
const confirmCompletion = () => {
    if (!pendingCompletion.value) return;

    completingVisitId.value = pendingCompletion.value.id;
    router.post(
        route("desk.visits.complete", pendingCompletion.value.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => (pendingCompletion.value = null),
            onFinish: () => (completingVisitId.value = null),
        },
    );
};
const duplicateGuard = () => {
    const seen = { value: "", at: 0 };
    return (value: string) => {
        const now = Date.now();
        if (value === seen.value && now - seen.at < 3000) return true;
        seen.value = value;
        seen.at = now;
        return false;
    };
};
const isDuplicateCopyScan = duplicateGuard();
const isDuplicateLoanScan = duplicateGuard();
const addCopy = () => {
    if (!props.visit) return;
    const value = copyForm.barcode.trim();
    if (!value || copyForm.processing) return;
    if (looksLikeCardCode(value)) {
        playBeep("warning");
        pendingCardSwitch.value = value;
        copyForm.reset();
        return;
    }
    // Une douchette ou la caméra continue peut relire le même code deux fois de suite.
    if (isDuplicateCopyScan(value)) {
        copyForm.reset();
        return;
    }

    const url =
        session.value && !session.value.closed_at
            ? route("desk.consultations.copies.store", session.value.id)
            : route("desk.visits.copies.store", props.visit.id);
    copyForm.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            playBeep("success");
            copyForm.reset();
        },
        onError: () => {
            playBeep("error");
            nextTick(() => copyInput.value?.select());
        },
        onFinish: () => nextTick(() => copyInput.value?.focus()),
    });
};
const openLoan = () => {
    if (!props.student) return;
    loanOpenForm.post(route("desk.loans.open", props.student.id), {
        preserveScroll: true,
    });
};
const addLoanCopy = () => {
    if (!props.loan) return;
    const value = loanCopyForm.barcode.trim();
    if (!value || loanCopyForm.processing) return;
    if (looksLikeCardCode(value)) {
        playBeep("warning");
        pendingCardSwitch.value = value;
        loanCopyForm.reset("barcode");
        return;
    }
    if (isDuplicateLoanScan(value)) {
        loanCopyForm.reset("barcode");
        return;
    }

    loanCopyForm.post(route("desk.loans.copies.store", props.loan.id), {
        preserveScroll: true,
        onSuccess: () => {
            playBeep("success");
            loanCopyForm.reset("barcode");
        },
        onError: () => {
            playBeep("error");
            nextTick(() => loanCopyInput.value?.select());
        },
        onFinish: () => nextTick(() => loanCopyInput.value?.focus()),
    });
};
const useCameraResult = (value: string) => {
    const target = cameraTarget.value;
    if (target === "student") {
        // En mode pointage, la caméra reste ouverte pour scanner l'étudiant suivant.
        if (isCounterMode.value) cameraTarget.value = null;
        scan.value = value;
        identifyFromCard();
        return;
    }
    if (target === "copy") {
        if (copyForm.processing) return;
        copyForm.barcode = value;
        addCopy();
        return;
    }
    if (target === "loan-copy") {
        if (loanCopyForm.processing) return;
        loanCopyForm.barcode = value;
        addLoanCopy();
    }
};

onMounted(() => {
    scanInput.value?.focus();
    // Le champ peut encore contenir le code précédent (q en URL) :
    // tout sélectionner pour que le scan suivant l'écrase.
    if (scan.value) scanInput.value?.select();
});
</script>

<template>
    <Head :title="modeTitle" />
    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <p class="dw-page-kicker">Opération rapide</p>
                    <h1 class="dw-page-title">{{ modeTitle }}</h1>
                    <p class="dw-page-description">
                        <template v-if="mode === 'entry'">Scannez chaque carte pour enregistrer immédiatement l’entrée.</template>
                        <template v-else-if="mode === 'exit'">Scannez chaque carte pour enregistrer immédiatement la sortie.</template>
                        <template v-else>Scannez la carte : l’étudiant est identifié et son entrée est enregistrée automatiquement si nécessaire.</template>
                    </p>
                </div>
                <Link
                    v-if="student"
                    :href="route('desk.index')"
                    class="dw-btn-secondary shrink-0 justify-center"
                >
                    <AppIcon name="chevron-down" class="h-4 w-4 rotate-90" />
                    Retour au comptoir
                </Link>
            </div>
        </template>

        <ScanFeedbackCard v-if="lastScan" :scan="lastScan" class="mb-5" />
        <div
            v-if="$page.props.flash?.success && !lastScan"
            class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="$page.props.flash?.info && !lastScan"
            class="mb-5 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-semibold text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300"
        >
            {{ $page.props.flash.info }}
        </div>

        <nav class="mb-5 grid gap-2 rounded-lg border border-gray-200 bg-white p-2 shadow-sm sm:grid-cols-3 dark:border-gray-800 dark:bg-gray-950" aria-label="Mode du comptoir">
            <Link
                v-for="option in modeOptions"
                :key="option.value"
                :href="route('desk.index', option.value === 'counter' ? {} : { mode: option.value })"
                class="flex items-center gap-3 rounded-md px-4 py-3 transition"
                :class="modeTabClass(option.value)"
            >
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-white shadow-sm dark:bg-slate-900"><AppIcon :name="option.icon" class="h-4 w-4 transition-transform" :class="option.iconClass" /></span>
                <span><strong class="block text-sm">{{ option.label }}</strong><small class="mt-0.5 block text-xs opacity-75">{{ option.help }}</small></span>
            </Link>
        </nav>

        <section class="dw-card p-5 sm:p-6">
            <form
                class="flex flex-col gap-3 sm:flex-row"
                @submit.prevent="identifyOrSearch"
            >
                <div class="relative flex-1">
                    <AppIcon
                        name="scan"
                        class="absolute start-4 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-500"
                    />
                    <input
                        ref="scanInput"
                        v-model="scan"
                        class="dw-field ps-12 text-base"
                        :placeholder="isCounterMode ? 'Carte BIB-26-001, 26-001, matricule ou nom…' : 'Scannez la carte BIB… ou tapez un nom si carte oubliée'"
                        autocomplete="off"
                        @focus="scanInput?.select()"
                    />
                </div>
                <button
                    type="button"
                    class="flex h-11 items-center justify-center gap-2 rounded-md border px-5 text-sm font-semibold transition-colors"
                    :class="modeSecondaryClass"
                    @click="cameraTarget = 'student'"
                >
                    <AppIcon name="scan" class="h-5 w-5" /> Scanner la carte
                </button>
                <button
                    class="h-11 rounded-md px-6 text-sm font-semibold text-white shadow-sm transition-colors focus:outline-none focus:ring-4"
                    :class="modeActionClass"
                >
                    {{ mode === 'entry' ? 'Enregistrer l’entrée' : mode === 'exit' ? 'Enregistrer la sortie' : 'Identifier' }}
                </button>
            </form>
            <InputError class="mt-2" :message="$page.props.errors?.student" />
        </section>

        <template v-if="!isCounterMode && !query">
            <section class="mt-6 grid gap-6 lg:grid-cols-[300px_1fr]">
                <article
                    class="dw-card flex flex-col justify-center gap-3 p-6 text-center"
                >
                    <span
                        class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl"
                        :class="
                            mode === 'entry'
                                ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300'
                                : 'bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-300'
                        "
                    >
                        <AppIcon
                            :name="mode === 'entry' ? 'login' : 'logout'"
                            class="h-6 w-6"
                        />
                    </span>
                    <p
                        class="font-heading text-4xl font-bold text-slate-800 dark:text-white"
                    >
                        {{ mode === 'entry' ? activeVisits.length : recentExits.length }}
                    </p>
                    <p
                        class="text-sm font-semibold text-slate-600 dark:text-slate-300"
                    >
                        {{ mode === 'entry' ? 'présent(s) actuellement' : 'sortie(s) aujourd’hui' }}
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{
                            mode === 'entry'
                                ? 'Étudiants dont la sortie n’est pas encore pointée.'
                                : `${activeVisits.length} étudiant(s) encore présent(s).`
                        }}
                    </p>
                </article>

                <article class="dw-card overflow-hidden">
                    <div
                        class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"
                    >
                        <p class="dw-page-kicker">Derniers pointages</p>
                        <h2
                            class="mt-1 font-heading text-lg font-bold text-slate-800 dark:text-white"
                        >
                            {{ mode === 'entry' ? 'Dernières entrées' : 'Dernières sorties' }}
                        </h2>
                    </div>
                    <div
                        v-if="mode === 'entry' && latestEntries.length"
                        class="divide-y divide-gray-200 dark:divide-gray-800"
                    >
                        <div
                            v-for="presence in latestEntries"
                            :key="presence.id"
                            class="flex items-center gap-4 px-5 py-3.5"
                        >
                            <img loading="lazy" decoding="async"
                                v-if="presence.student.photo_url"
                                :src="presence.student.photo_url"
                                :alt="`Photo de ${presence.student.first_name} ${presence.student.last_name}`"
                                class="h-11 w-10 shrink-0 rounded-md border border-gray-200 object-cover dark:border-gray-700"
                            />
                            <span
                                v-else
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                >{{ presence.student.first_name[0]
                                }}{{ presence.student.last_name[0] }}</span
                            >
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-bold text-slate-700 dark:text-slate-200"
                                >
                                    {{ presence.student.last_name }}
                                    {{ presence.student.first_name }}
                                </p>
                                <p
                                    class="mt-0.5 font-mono text-xs text-primary-600"
                                >
                                    {{ presence.student.registration_number }}
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <span
                                    class="rounded bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                    >Entré(e) à
                                    {{ timeOnly(presence.checked_in_at) }}</span
                                >
                                <span
                                    class="text-[11px] font-semibold text-slate-500 dark:text-slate-400"
                                    >il y a
                                    {{ presenceDuration(presence.checked_in_at) }}</span
                                >
                            </div>
                        </div>
                    </div>
                    <div
                        v-else-if="mode === 'exit' && latestExits.length"
                        class="divide-y divide-gray-200 dark:divide-gray-800"
                    >
                        <div
                            v-for="visit in latestExits"
                            :key="visit.id"
                            class="flex items-center gap-4 px-5 py-3.5"
                        >
                            <img loading="lazy" decoding="async"
                                v-if="visit.student.photo_url"
                                :src="visit.student.photo_url"
                                :alt="`Photo de ${visit.student.first_name} ${visit.student.last_name}`"
                                class="h-11 w-10 shrink-0 rounded-md border border-gray-200 object-cover dark:border-gray-700"
                            />
                            <span
                                v-else
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-xs font-bold text-red-700 dark:bg-red-950 dark:text-red-300"
                                >{{ visit.student.first_name[0]
                                }}{{ visit.student.last_name[0] }}</span
                            >
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-bold text-slate-700 dark:text-slate-200"
                                >
                                    {{ visit.student.last_name }}
                                    {{ visit.student.first_name }}
                                </p>
                                <p
                                    class="mt-0.5 font-mono text-xs text-primary-600"
                                >
                                    {{ visit.student.registration_number }}
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <span
                                    class="rounded bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                    >{{ timeOnly(visit.checked_in_at) }} →
                                    {{ timeOnly(visit.checked_out_at) }}</span
                                >
                                <span
                                    class="text-[11px] font-semibold text-slate-500 dark:text-slate-400"
                                    >{{
                                        completedDuration(
                                            visit.checked_in_at,
                                            visit.checked_out_at,
                                        )
                                    }}
                                    sur place</span
                                >
                            </div>
                        </div>
                    </div>
                    <p
                        v-else
                        class="p-8 text-center text-sm text-slate-500 dark:text-slate-400"
                    >
                        {{
                            mode === 'entry'
                                ? 'Aucun étudiant présent pour le moment. Scannez la première carte.'
                                : 'Aucune sortie enregistrée aujourd’hui.'
                        }}
                    </p>
                </article>
            </section>
        </template>

        <details
            v-if="!student && isCounterMode"
            class="dw-card group mt-6 overflow-hidden"
            open
        >
            <summary
                class="flex cursor-pointer list-none flex-col items-stretch justify-between gap-4 p-5 marker:hidden sm:flex-row sm:items-center sm:px-6"
            >
                <div class="flex items-center gap-4">
                    <span
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300"
                        ><AppIcon name="visits" class="h-5 w-5"
                    /></span>
                    <div>
                        <p
                            class="text-xs font-bold uppercase tracking-widest text-emerald-600"
                        >
                            Suivi en temps réel
                        </p>
                        <h2
                            class="mt-1 font-heading text-lg font-bold text-slate-800"
                        >
                            Présences en cours
                        </h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Étudiants entrés dans la bibliothèque et dont la
                            sortie n’est pas encore enregistrée.
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center justify-between gap-3 sm:justify-end">
                    <span
                        class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                        >{{ presenceTab === 'present' ? `${activeVisits.length} présent(s)` : `${recentExits.length} sortie(s)` }}</span
                    ><AppIcon
                        name="chevron-down"
                        class="h-5 w-5 text-slate-500 dark:text-slate-400 transition group-open:rotate-180"
                    />
                </div>
            </summary>
            <div class="border-t border-gray-200 bg-slate-50/80 p-3 dark:border-gray-900 dark:bg-slate-900/40 sm:p-4">
                <div class="mb-4 flex w-full gap-1 rounded-lg border border-gray-200 bg-white p-1 dark:border-gray-800 dark:bg-gray-950 sm:w-fit">
                    <button type="button" class="flex flex-1 items-center justify-center gap-2 rounded-md px-4 py-2 text-xs font-bold transition sm:flex-none" :class="presenceTab === 'present' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-900'" @click="presenceTab = 'present'">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>Présents
                        <span class="rounded-full bg-white px-2 py-0.5 text-[10px] shadow-sm dark:bg-slate-900">{{ activeVisits.length }}</span>
                    </button>
                    <button type="button" class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-md px-4 py-2 text-xs font-bold transition sm:flex-none" :class="presenceTab === 'exited' ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' : 'text-slate-500 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950 dark:hover:text-red-300'" @click="presenceTab = 'exited'">
                        <AppIcon name="logout" class="h-3.5 w-3.5" />Sorties récentes
                        <span class="rounded-full bg-white px-2 py-0.5 text-[10px] shadow-sm dark:bg-slate-900">{{ recentExits.length }}</span>
                    </button>
                </div>
                <div
                    v-if="presenceTab === 'present' && activeVisits.length"
                    class="grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] gap-3"
                >
                    <div
                        v-for="presence in activeVisits"
                        :key="presence.id"
                        class="group/presence flex min-w-0 items-start gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-primary-200 hover:shadow-md dark:border-gray-800 dark:bg-gray-950 dark:hover:border-primary-800 dark:hover:bg-primary-950/20 sm:p-5"
                    >
                        <button
                            type="button"
                            class="flex min-w-0 flex-1 cursor-pointer items-start gap-4 text-start"
                            :title="`Ouvrir la fiche de ${presence.student.first_name} ${presence.student.last_name}`"
                            @click="openPresence(presence)"
                        >
                            <img loading="lazy" decoding="async"
                                v-if="presence.student.photo_url"
                                :src="presence.student.photo_url"
                                :alt="`Photo de ${presence.student.first_name} ${presence.student.last_name}`"
                                class="h-14 w-12 shrink-0 rounded-md border border-gray-200 object-cover shadow-sm dark:border-gray-700"
                            />
                            <span
                                v-else
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300"
                                >{{ presence.student.first_name[0]
                                }}{{ presence.student.last_name[0] }}</span
                            >
                            <span class="min-w-0 flex-1"
                                ><span
                                    class="block truncate text-sm font-bold text-slate-700 dark:text-slate-200"
                                    >{{ presence.student.last_name }}
                                    {{ presence.student.first_name }}</span
                                ><span
                                    class="mt-1 block font-mono text-xs text-primary-600"
                                    >{{
                                        presence.student.registration_number
                                    }}</span
                                ><span class="mt-2 flex flex-wrap gap-1.5"
                                    ><span
                                        class="rounded bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                        >Depuis
                                        {{
                                            presenceDuration(presence.checked_in_at)
                                        }}</span
                                    ><span
                                        v-if="presence.consultation?.is_open"
                                        class="rounded bg-primary-50 px-2 py-1 text-[11px] font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300"
                                        >{{
                                            presence.consultation.active_copies
                                        }}
                                        en consultation</span
                                    ><span
                                        v-if="presence.loan && presence.loan.active_copies > 0"
                                        class="rounded px-2 py-1 text-[11px] font-bold"
                                        :class="
                                            isOverdue(presence.loan.due_at)
                                                ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300'
                                                : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                                        "
                                        >{{ presence.loan.active_copies }} en
                                        prêt{{
                                            isOverdue(presence.loan.due_at)
                                                ? " · en retard"
                                                : ""
                                        }}</span
                                    ></span
                                ></span
                            >
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-10 shrink-0 cursor-pointer items-center gap-2 rounded-md border border-red-200 px-3 text-xs font-bold text-red-600 transition hover:bg-red-50 disabled:cursor-wait disabled:opacity-60 dark:border-red-900 dark:hover:bg-red-950"
                            :disabled="completingVisitId === presence.id"
                            :title="
                                presence.consultation?.active_copies
                                    ? 'Terminer la consultation et enregistrer la sortie'
                                    : 'Enregistrer la sortie'
                            "
                            @click="completePresence(presence)"
                        >
                            <AppIcon name="logout" class="h-4 w-4" />
                            <span class="hidden xl:inline">{{
                                completingVisitId === presence.id
                                    ? "Traitement…"
                                    : "Sortie"
                            }}</span>
                        </button>
                        <AppIcon
                            name="chevron-down"
                            class="mt-1 h-4 w-4 shrink-0 -rotate-90 text-slate-400 transition-transform group-hover/presence:translate-x-0.5 group-hover/presence:text-primary-500"
                        />
                    </div>
                </div>
                <div v-else-if="presenceTab === 'exited' && recentExits.length" class="grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] gap-3">
                    <Link
                        v-for="visit in recentExits"
                        :key="visit.id"
                        :href="route('students.show', visit.student.id)"
                        class="group flex min-w-0 items-center gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-primary-200 hover:shadow-md dark:border-gray-800 dark:bg-gray-950 dark:hover:border-primary-800"
                    >
                        <img v-if="visit.student.photo_url" :src="visit.student.photo_url" :alt="`Photo de ${visit.student.first_name} ${visit.student.last_name}`" class="h-14 w-12 shrink-0 rounded-md border border-gray-200 object-cover dark:border-gray-700" />
                        <span v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ visit.student.first_name[0] }}{{ visit.student.last_name[0] }}</span>
                        <span class="min-w-0 flex-1">
                            <strong class="block truncate text-sm text-slate-700 dark:text-slate-200">{{ visit.student.last_name }} {{ visit.student.first_name }}</strong>
                            <span class="mt-1 block font-mono text-xs text-primary-600">{{ visit.student.registration_number }}</span>
                            <span class="mt-2 flex flex-wrap gap-1.5 text-[11px] font-bold">
                                <span class="rounded bg-slate-100 px-2 py-1 text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ timeOnly(visit.checked_in_at) }} → {{ timeOnly(visit.checked_out_at) }}</span>
                                <span class="rounded bg-cyan-50 px-2 py-1 text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300">{{ completedDuration(visit.checked_in_at, visit.checked_out_at) }}</span>
                                <span v-if="visit.consultations_count" class="rounded bg-primary-50 px-2 py-1 text-primary-700 dark:bg-primary-950 dark:text-primary-300">{{ visit.consultations_count }} consultation(s)</span>
                            </span>
                        </span>
                        <AppIcon name="chevron-down" class="h-4 w-4 -rotate-90 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-primary-500" />
                    </Link>
                </div>
                <div v-else class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-950">
                    <span
                        class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-500 dark:bg-emerald-950"
                        ><AppIcon name="visits" class="h-6 w-6"
                    /></span>
                    <p class="mt-3 text-sm font-bold text-slate-600">
                        {{ presenceTab === 'present' ? 'Aucune présence ouverte' : 'Aucune sortie enregistrée aujourd’hui' }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ presenceTab === 'present' ? 'Les étudiants apparaîtront ici après l’enregistrement de leur entrée.' : 'Les étudiants apparaîtront ici après leur pointage de sortie.' }}
                    </p>
                </div>
            </div>
        </details>

        <section
            v-if="student && isCounterMode"
            class="mt-6 grid gap-6 xl:grid-cols-[360px_1fr]"
        >
            <article
                class="dw-card self-start overflow-hidden xl:sticky xl:top-24"
            >
                <div class="relative min-h-64 overflow-hidden p-6 text-white">
                    <img loading="lazy" decoding="async"
                        src="/images/desk/student-profile-cover.png"
                        alt=""
                        class="absolute inset-0 h-full w-full object-cover"
                    />
                    <div
                        class="absolute inset-0 bg-gradient-to-b from-slate-950/25 via-primary-950/55 to-primary-950/95"
                    />
                    <div
                        class="relative z-10 flex min-h-52 flex-col justify-end"
                    >
                        <img loading="lazy" decoding="async"
                            v-if="student.photo_url"
                            :src="student.photo_url"
                            :alt="`Photo de ${student.first_name} ${student.last_name}`"
                            class="mb-5 h-28 w-24 rounded-lg border-2 border-white/70 object-cover shadow-xl"
                        />
                        <div
                            v-else
                            class="mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-white/15 text-2xl font-bold ring-1 ring-white/30 backdrop-blur-sm"
                        >
                            {{ student.first_name[0]
                            }}{{ student.last_name[0] }}
                        </div>
                        <h2
                            class="font-heading text-xl font-bold !text-white [text-shadow:0_2px_5px_rgba(0,0,0,0.9)]"
                        >
                            {{ student.last_name }} {{ student.first_name }}
                        </h2>
                        <p
                            class="mt-1 font-mono text-sm font-bold !text-white [text-shadow:0_1px_4px_rgba(0,0,0,0.9)]"
                        >
                            {{ student.registration_number }}
                        </p>
                    </div>
                </div>
                <div class="space-y-4 p-5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400"
                            >Matricule</span
                        ><strong>{{ student.academic_number || "—" }}</strong>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500 dark:text-slate-400"
                            >Mention</span
                        ><strong class="text-end">{{
                            student.mention?.name || "—"
                        }}</strong>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500 dark:text-slate-400"
                            >Parcours</span
                        ><strong class="text-end">{{
                            student.academic_program?.name ||
                            student.program ||
                            "—"
                        }}</strong>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500 dark:text-slate-400"
                            >Niveau</span
                        ><strong class="text-end">{{
                            student.academic_level?.name || student.level || "—"
                        }}</strong>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500 dark:text-slate-400"
                            >Année universitaire</span
                        ><strong class="text-end">{{
                            student.academic_year || "—"
                        }}</strong>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-slate-500 dark:text-slate-400"
                            >Carte physique</span
                        ><strong
                            class="text-end text-xs"
                            :class="
                                student.cards?.length
                                    ? 'text-emerald-600'
                                    : 'text-amber-600'
                            "
                            >{{
                                student.cards?.length
                                    ? "Active"
                                    : "Non délivrée"
                            }}</strong
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 dark:text-slate-400"
                            >Statut</span
                        ><span
                            class="rounded-full px-2.5 py-1 text-xs font-bold"
                            :class="
                                student.status === 'active'
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                    : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                            "
                            >{{
                                statusLabels[student.status] || student.status
                            }}</span
                        >
                    </div>
                </div>
            </article>

            <div class="space-y-6">
                <article v-if="!visit" class="dw-card overflow-hidden">
                    <div
                        class="border-b border-gray-200 bg-gradient-to-r from-emerald-50 to-white p-6 dark:border-gray-800 dark:from-emerald-950/50 dark:to-gray-950 sm:p-8"
                    >
                        <div
                            class="flex flex-col gap-5 sm:flex-row sm:items-center"
                        >
                            <span
                                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 ring-8 ring-white/70 dark:bg-emerald-950 dark:text-emerald-300 dark:ring-gray-950/40"
                                ><AppIcon name="check" class="h-7 w-7"
                            /></span>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-600 dark:text-emerald-400"
                                >
                                    Étudiant identifié
                                </p>
                                <h2
                                    class="mt-1 font-heading text-xl font-bold text-slate-800 dark:text-white"
                                >
                                    Prêt à enregistrer l’entrée
                                </h2>
                                <p
                                    class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    La carte est valide. Confirmez l’entrée pour
                                    ouvrir la présence et accéder aux
                                    consultations ou aux prêts.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div
                        class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between sm:px-8"
                    >
                        <div
                            class="flex items-center gap-3 text-sm text-slate-500 dark:text-slate-400"
                        >
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-md bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-300"
                                ><AppIcon name="visits" class="h-4 w-4" /></span
                            ><span
                                >L’heure d’entrée sera enregistrée
                                automatiquement.</span
                            >
                        </div>
                        <button
                            class="dw-btn-primary h-11 shrink-0 justify-center px-6"
                            @click="post(route('desk.check-in', student.id))"
                        >
                            <AppIcon name="visits" class="h-5 w-5" />Enregistrer
                            l’entrée
                        </button>
                    </div>
                </article>

                <template v-else>
                    <article
                        class="dw-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center"
                    >
                        <div>
                            <p
                                class="text-xs font-bold uppercase tracking-widest text-emerald-600"
                            >
                                Présence en cours
                            </p>
                            <h2
                                class="mt-1 font-heading font-bold text-slate-800"
                            >
                                {{ visit.visit_number }}
                            </h2>
                            <p
                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                            >
                                Entrée :
                                {{
                                    new Date(
                                        visit.checked_in_at,
                                    ).toLocaleString("fr-FR")
                                }}
                            </p>
                        </div>
                        <button
                            v-if="session?.closed_at || !session"
                            class="ms-auto rounded-md border border-red-200 px-4 py-2.5 text-sm font-bold text-red-600 hover:bg-red-50 dark:border-red-900 dark:hover:bg-red-950"
                            @click="post(route('desk.check-out', visit.id))"
                        >
                            Enregistrer la sortie
                        </button>
                        <p
                            v-else
                            class="ms-auto max-w-56 text-end text-xs text-slate-500 dark:text-slate-400"
                        >
                            Clôturez la consultation en cours pour pouvoir
                            enregistrer la sortie.
                        </p>
                    </article>

                    <article v-if="!session" class="dw-card overflow-hidden">
                        <div class="p-5">
                            <p class="dw-page-kicker">Consultation sur place</p>
                            <h2
                                class="mt-1 font-heading font-bold text-slate-800"
                            >
                                Exemplaires consultés
                            </h2>
                            <p
                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                            >
                                Scannez le premier livre : la session de
                                consultation s’ouvre automatiquement.
                            </p>
                            <form
                                class="mt-5 flex flex-col gap-2 sm:flex-row"
                                @submit.prevent="addCopy"
                            >
                                <input
                                    ref="copyInput"
                                    v-model="copyForm.barcode"
                                    class="dw-field flex-1"
                                    placeholder="Scanner le code de l’exemplaire…"
                                    autocomplete="off"
                                /><button
                                    type="button"
                                    class="flex h-11 items-center justify-center gap-2 rounded-md border border-primary-300 px-4 text-sm font-semibold text-primary-600 transition-colors hover:bg-primary-50 dark:border-primary-800 dark:text-primary-300 dark:hover:bg-primary-950"
                                    @click="cameraTarget = 'copy'"
                                >
                                    <AppIcon name="scan" class="h-5 w-5" />
                                    Caméra</button
                                ><button
                                    class="h-11 rounded-md bg-slate-800 px-5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 dark:bg-primary-600 dark:hover:bg-primary-700"
                                >
                                    Ajouter
                                </button>
                            </form>
                            <InputError
                                class="mt-2"
                                :message="
                                    copyForm.errors.barcode ||
                                    $page.props.errors?.copy
                                "
                            />
                        </div>
                    </article>

                    <article v-else class="dw-card overflow-hidden">
                        <div class="border-b border-slate-100 p-5">
                            <div
                                class="flex flex-wrap items-center justify-between gap-3"
                            >
                                <div>
                                    <p class="dw-page-kicker">
                                        Session {{ session.session_number }}
                                    </p>
                                    <h2
                                        class="mt-1 font-heading font-bold text-slate-800"
                                    >
                                        Exemplaires consultés
                                    </h2>
                                </div>
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-bold"
                                    :class="
                                        session.closed_at
                                            ? 'bg-slate-100 text-slate-500'
                                            : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                    "
                                    >{{
                                        session.closed_at
                                            ? "Clôturée"
                                            : `${activeItems.length} à restituer`
                                    }}</span
                                >
                            </div>
                            <form
                                v-if="!session.closed_at"
                                class="mt-5 flex flex-col gap-2 sm:flex-row"
                                @submit.prevent="addCopy"
                            >
                                <input
                                    ref="copyInput"
                                    v-model="copyForm.barcode"
                                    class="dw-field flex-1"
                                    placeholder="Scanner le code de l’exemplaire…"
                                    autocomplete="off"
                                /><button
                                    type="button"
                                    class="flex h-11 items-center justify-center gap-2 rounded-md border border-primary-300 px-4 text-sm font-semibold text-primary-600 transition-colors hover:bg-primary-50 dark:border-primary-800 dark:text-primary-300 dark:hover:bg-primary-950"
                                    @click="cameraTarget = 'copy'"
                                >
                                    <AppIcon name="scan" class="h-5 w-5" />
                                    Caméra</button
                                ><button
                                    class="h-11 rounded-md bg-slate-800 px-5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 dark:bg-primary-600 dark:hover:bg-primary-700"
                                >
                                    Ajouter
                                </button>
                            </form>
                            <InputError
                                class="mt-2"
                                :message="
                                    copyForm.errors.barcode ||
                                    $page.props.errors?.copy
                                "
                            />
                        </div>
                        <div
                            v-if="session.items.length"
                            class="divide-y divide-slate-100"
                        >
                            <div
                                v-for="item in session.items"
                                :key="item.id"
                                class="flex items-center gap-4 p-4 sm:px-5"
                            >
                                <img loading="lazy" decoding="async"
                                    v-if="item.copy.book.cover_url"
                                    :src="item.copy.book.cover_url"
                                    :alt="`Couverture de ${item.copy.book.title}`"
                                    class="h-14 w-10 shrink-0 rounded border border-slate-200 object-cover shadow-sm dark:border-slate-700"
                                />
                                <div
                                    v-else
                                    class="flex h-14 w-10 shrink-0 items-center justify-center rounded border border-dashed border-slate-300 bg-slate-50 text-primary-500 dark:border-slate-700 dark:bg-slate-900"
                                >
                                    <AppIcon name="books" class="h-5 w-5" />
                                </div>
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-semibold text-slate-700"
                                    >
                                        {{ item.copy.book.title }}
                                    </p>
                                    <p
                                        class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ item.copy.inventory_number
                                        }}<template v-if="item.scanned_at">
                                            · scanné à
                                            {{ timeOnly(item.scanned_at) }}</template
                                        >
                                    </p>
                                </div>
                                <span
                                    v-if="item.returned_at"
                                    class="ms-auto text-xs font-bold text-emerald-600"
                                    >Restitué à
                                    {{ timeOnly(item.returned_at) }}</span
                                ><button
                                    v-else
                                    class="ms-auto rounded-md border border-primary-200 px-3 py-2 text-xs font-bold text-primary-600 dark:border-primary-800"
                                    @click="
                                        post(
                                            route(
                                                'desk.consultations.copies.return',
                                                item.id,
                                            ),
                                        )
                                    "
                                >
                                    Restituer
                                </button>
                            </div>
                        </div>
                        <p
                            v-else
                            class="p-10 text-center text-sm text-slate-500 dark:text-slate-400"
                        >
                            Aucun exemplaire scanné.
                        </p>
                        <div
                            v-if="!session.closed_at"
                            class="border-t border-slate-100 p-5 text-end"
                        >
                            <p
                                v-if="activeItems.length"
                                class="mb-3 text-xs text-slate-500"
                            >
                                La clôture rendra automatiquement disponible
                                {{ activeItems.length }} exemplaire(s).
                            </p>
                            <button
                                class="dw-btn-primary"
                                @click="
                                    post(
                                        route(
                                            'desk.consultations.close',
                                            session.id,
                                        ),
                                    )
                                "
                            >
                                Clôturer et libérer les exemplaires
                            </button>
                        </div>
                        <div
                            v-else
                            class="border-t border-slate-100 p-5 dark:border-slate-800"
                        >
                            <p class="text-sm text-slate-500">
                                L’étudiant veut consulter d’autres livres ?
                                Scannez un exemplaire : une nouvelle session
                                s’ouvre automatiquement, sans nouvelle entrée.
                            </p>
                            <form
                                class="mt-4 flex flex-col gap-2 sm:flex-row"
                                @submit.prevent="addCopy"
                            >
                                <input
                                    ref="copyInput"
                                    v-model="copyForm.barcode"
                                    class="dw-field flex-1"
                                    placeholder="Scanner le code de l’exemplaire…"
                                    autocomplete="off"
                                /><button
                                    type="button"
                                    class="flex h-11 items-center justify-center gap-2 rounded-md border border-primary-300 px-4 text-sm font-semibold text-primary-600 transition-colors hover:bg-primary-50 dark:border-primary-800 dark:text-primary-300 dark:hover:bg-primary-950"
                                    @click="cameraTarget = 'copy'"
                                >
                                    <AppIcon name="scan" class="h-5 w-5" />
                                    Caméra</button
                                ><button
                                    class="h-11 rounded-md bg-primary-600 px-5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary-700"
                                >
                                    Nouvelle consultation
                                </button>
                            </form>
                            <InputError
                                class="mt-2"
                                :message="
                                    copyForm.errors.barcode ||
                                    $page.props.errors?.copy
                                "
                            />
                        </div>
                    </article>

                    <article class="dw-card overflow-hidden">
                        <div class="border-b border-slate-100 p-5">
                            <div
                                class="flex flex-wrap items-center justify-between gap-3"
                            >
                                <div>
                                    <p
                                        class="text-xs font-bold uppercase tracking-widest text-amber-600"
                                    >
                                        Prêt à domicile
                                    </p>
                                    <h2
                                        class="mt-1 font-heading font-bold text-slate-800"
                                    >
                                        Livres emportés par l’étudiant
                                    </h2>
                                </div>
                                <span
                                    v-if="loan"
                                    class="rounded-full px-3 py-1 text-xs font-bold"
                                    :class="
                                        isOverdue(loan.due_at) &&
                                        activeLoanItems.length
                                            ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300'
                                            : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                                    "
                                    >{{ activeLoanItems.length }} en prêt{{
                                        isOverdue(loan.due_at) &&
                                        activeLoanItems.length
                                            ? " · en retard"
                                            : ""
                                    }}</span
                                >
                            </div>

                            <form
                                v-if="!loan"
                                class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end"
                                @submit.prevent="openLoan"
                            >
                                <div class="flex-1">
                                    <label
                                        class="mb-2 block text-xs font-bold uppercase text-slate-500 dark:text-slate-400"
                                        >Date limite de retour</label
                                    ><input
                                        v-model="loanOpenForm.due_at"
                                        type="date"
                                        class="dw-field"
                                        required
                                    />
                                </div>
                                <button
                                    class="h-11 rounded-md bg-amber-600 px-5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-amber-700"
                                >
                                    Ouvrir un prêt
                                </button>
                            </form>
                            <InputError
                                class="mt-2"
                                :message="
                                    loanOpenForm.errors.due_at ||
                                    $page.props.errors?.loan
                                "
                            />

                            <template v-if="loan">
                                <div
                                    class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    <span>{{ loan.loan_number }}</span
                                    ><span
                                        :class="
                                            isOverdue(loan.due_at)
                                                ? 'font-bold text-red-600 dark:text-red-400'
                                                : ''
                                        "
                                        >Retour avant le
                                        {{
                                            new Date(
                                                loan.due_at,
                                            ).toLocaleDateString("fr-FR")
                                        }}</span
                                    ><span
                                        v-if="isOverdue(loan.due_at) && activeLoanItems.length"
                                        class="rounded bg-red-50 px-2 py-1 text-[11px] font-bold text-red-700 dark:bg-red-950 dark:text-red-300"
                                        >En retard de
                                        {{ overdueDays(loan.due_at) }}
                                        jour(s)</span
                                    >
                                </div>
                                <form
                                    class="mt-5 flex flex-col gap-2 sm:flex-row"
                                    @submit.prevent="addLoanCopy"
                                >
                                    <input
                                        ref="loanCopyInput"
                                        v-model="loanCopyForm.barcode"
                                        class="dw-field flex-1"
                                        placeholder="Scanner l’exemplaire à prêter…"
                                        autocomplete="off"
                                    />
                                    <button
                                        type="button"
                                        class="flex h-11 items-center justify-center gap-2 rounded-md border border-amber-300 px-4 text-sm font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-800 dark:text-amber-300 dark:hover:bg-amber-950"
                                        @click="cameraTarget = 'loan-copy'"
                                    >
                                        <AppIcon name="scan" class="h-5 w-5" />
                                        Caméra
                                    </button>
                                    <button
                                        class="h-11 rounded-md bg-amber-600 px-5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-amber-700"
                                    >
                                        Prêter
                                    </button>
                                </form>
                                <InputError
                                    class="mt-2"
                                    :message="
                                        loanCopyForm.errors.barcode ||
                                        $page.props.errors?.loan_copy
                                    "
                                />
                            </template>
                        </div>
                        <div
                            v-if="loan?.items.length"
                            class="divide-y divide-slate-100"
                        >
                            <div
                                v-for="item in loan.items"
                                :key="item.id"
                                class="flex items-center gap-4 p-4 sm:px-5"
                            >
                                <img loading="lazy" decoding="async"
                                    v-if="item.copy.book.cover_url"
                                    :src="item.copy.book.cover_url"
                                    :alt="`Couverture de ${item.copy.book.title}`"
                                    class="h-14 w-10 shrink-0 rounded border border-slate-200 object-cover shadow-sm dark:border-slate-700"
                                />
                                <div
                                    v-else
                                    class="flex h-14 w-10 shrink-0 items-center justify-center rounded border border-dashed border-slate-300 bg-slate-50 text-amber-500 dark:border-slate-700 dark:bg-slate-900"
                                >
                                    <AppIcon name="loans" class="h-5 w-5" />
                                </div>
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-semibold text-slate-700"
                                    >
                                        {{ item.copy.book.title }}
                                    </p>
                                    <p
                                        class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ item.copy.inventory_number
                                        }}<template v-if="item.loaned_at">
                                            · prêté le
                                            {{ shortDateTime(item.loaned_at) }}</template
                                        >
                                    </p>
                                </div>
                                <span
                                    v-if="item.returned_at"
                                    class="ms-auto text-xs font-bold text-emerald-600"
                                    >Rendu le
                                    {{ shortDateTime(item.returned_at) }}</span
                                ><button
                                    v-else
                                    class="ms-auto rounded-md border border-amber-200 px-3 py-2 text-xs font-bold text-amber-700 dark:border-amber-800"
                                    @click="
                                        post(
                                            route(
                                                'desk.loans.copies.return',
                                                item.id,
                                            ),
                                        )
                                    "
                                >
                                    Enregistrer le retour
                                </button>
                            </div>
                        </div>
                        <div
                            v-if="loan"
                            class="border-t border-slate-100 p-5 text-end"
                        >
                            <button
                                :disabled="activeLoanItems.length > 0"
                                class="rounded-md bg-slate-700 px-5 py-2.5 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40"
                                @click="
                                    post(route('desk.loans.close', loan.id))
                                "
                            >
                                Clôturer le prêt
                            </button>
                        </div>
                    </article>
                </template>
            </div>
        </section>

        <section
            v-else-if="query && matches.length"
            class="dw-card mt-6 overflow-hidden"
        >
            <div class="border-b border-slate-100 p-5">
                <h2 class="font-heading font-bold text-slate-800">
                    {{ isCounterMode ? 'Plusieurs étudiants correspondent' : 'Carte oubliée ? Résultats de la recherche' }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{
                        isCounterMode
                            ? 'Sélectionnez la bonne fiche à partir de son numéro.'
                            : `Sélectionnez l’étudiant pour ${mode === 'entry' ? 'enregistrer directement son entrée' : 'enregistrer directement sa sortie'}.`
                    }}
                </p>
            </div>
            <div class="divide-y divide-slate-100">
                <button
                    v-for="match in matches"
                    :key="match.id"
                    class="flex w-full items-center gap-4 p-4 text-start transition hover:bg-slate-50 sm:px-5"
                    @click="selectMatch(match)"
                >
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                        >{{ match.first_name[0] }}{{ match.last_name[0] }}</span
                    ><span class="min-w-0"
                        ><strong class="block truncate text-sm text-slate-700"
                            >{{ match.last_name }}
                            {{ match.first_name }}</strong
                        ><span
                            class="mt-1 block font-mono text-xs text-slate-500 dark:text-slate-400"
                            >{{ match.registration_number }} ·
                            {{
                                match.academic_number || "sans matricule"
                            }}</span
                        ></span
                    ><span class="ms-auto flex shrink-0 flex-col items-end gap-2">
                        <span
                            v-if="!isCounterMode"
                            class="rounded-md px-3 py-2 text-xs font-bold text-white shadow-sm"
                            :class="mode === 'entry' ? 'bg-emerald-600' : 'bg-red-600'"
                            >{{ mode === 'entry' ? 'Enregistrer l’entrée' : 'Enregistrer la sortie' }}</span
                        >
                        <span
                            class="hidden text-xs text-slate-500 dark:text-slate-400 sm:block"
                            >{{
                                [match.level, match.program]
                                    .filter(Boolean)
                                    .join(" · ")
                            }}</span
                        >
                    </span>
                </button>
            </div>
        </section>
        <section v-else-if="query" class="dw-card mt-6 p-10 text-center">
            <p class="font-heading font-bold text-slate-800">
                Aucun étudiant trouvé
            </p>
            <p class="dw-page-description">
                Vérifiez le code ou utilisez une recherche par nom.
            </p>
        </section>

        <details v-if="!student && isCounterMode" class="dw-card group mt-6 overflow-hidden">
            <summary
                class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 p-5 marker:hidden dark:border-slate-800"
            >
                <div>
                    <p class="dw-page-kicker">Activité du comptoir</p>
                    <h2
                        class="mt-1 font-heading text-lg font-bold text-slate-800"
                    >
                        {{ presenceTab === 'present' ? 'Activité des étudiants présents' : 'Activité des sorties récentes' }}
                    </h2>
                </div>
                <span class="flex items-center gap-3"
                    ><span
                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500 dark:bg-slate-800"
                        >{{ filteredActivity.length }} opération(s)</span
                    ><AppIcon
                        name="chevron-down"
                        class="h-5 w-5 text-slate-500 dark:text-slate-400 transition group-open:rotate-180"
                /></span>
            </summary>
            <div
                v-if="filteredActivity.length"
                class="divide-y divide-slate-100 border-t border-slate-100 dark:divide-slate-800 dark:border-slate-800"
            >
                <div
                    v-for="activity in filteredActivity"
                    :key="activity.key"
                    class="grid gap-3 p-4 sm:grid-cols-[44px_minmax(180px,0.8fr)_minmax(220px,1.4fr)_auto] sm:items-center sm:px-5"
                >
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-lg"
                        :class="activityStyles[activity.type]"
                        ><AppIcon
                            :name="activityIcon(activity.type)"
                            class="h-5 w-5"
                    /></span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-700">
                            {{ activity.label }}
                        </p>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{
                                new Date(activity.occurred_at).toLocaleString(
                                    "fr-FR",
                                )
                            }}
                        </p>
                    </div>
                    <div class="min-w-0">
                        <p
                            class="truncate text-sm font-semibold text-slate-700"
                        >
                            {{ activity.student.last_name }}
                            {{ activity.student.first_name }}
                        </p>
                        <p class="mt-1 font-mono text-xs text-primary-600">
                            {{ activity.student.registration_number }}
                        </p>
                    </div>
                    <div
                        v-if="activity.book"
                        class="flex min-w-0 items-center gap-3 sm:max-w-80"
                    >
                        <img loading="lazy" decoding="async"
                            v-if="activity.book.cover_url"
                            :src="activity.book.cover_url"
                            :alt="`Couverture de ${activity.book.title}`"
                            class="h-12 w-9 shrink-0 rounded object-cover"
                        /><span
                            v-else
                            class="flex h-12 w-9 shrink-0 items-center justify-center rounded border border-dashed border-gray-300 text-primary-500 dark:border-gray-800"
                            ><AppIcon name="books" class="h-4 w-4"
                        /></span>
                        <div class="min-w-0 sm:text-end">
                            <p
                                class="truncate text-sm text-slate-600 dark:text-slate-300"
                            >
                                {{ activity.book.title }}
                            </p>
                            <p
                                class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ activity.inventory_number }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="p-10 text-center">
                <AppIcon name="scan" class="mx-auto h-9 w-9 text-slate-300" />
                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                    Aucune opération récente pour les étudiants de cet onglet.
                </p>
            </div>
        </details>

        <Modal
            :show="pendingCompletion !== null"
            max-width="md"
            :closeable="completingVisitId === null"
            @close="pendingCompletion = null"
        >
            <div v-if="pendingCompletion" class="overflow-hidden">
                <div
                    class="flex items-start gap-4 border-b border-gray-200 p-5 sm:p-6 dark:border-gray-800"
                >
                    <span
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600 ring-8 ring-red-50/60 dark:bg-red-950 dark:text-red-300 dark:ring-red-950/40"
                    >
                        <AppIcon name="logout" class="h-6 w-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-xs font-bold uppercase tracking-[0.16em] text-red-600 dark:text-red-400"
                        >
                            Fin de présence
                        </p>
                        <h2
                            class="mt-1 font-heading text-xl font-bold text-slate-800 dark:text-white"
                        >
                            Enregistrer la sortie ?
                        </h2>
                        <p
                            class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                        >
                            Vérifiez les opérations qui seront effectuées avant
                            de confirmer.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-2 text-slate-400 transition hover:bg-gray-100 hover:text-slate-700 dark:hover:bg-gray-900 dark:hover:text-white"
                        aria-label="Fermer"
                        :disabled="completingVisitId !== null"
                        @click="pendingCompletion = null"
                    >
                        <AppIcon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-5 p-5 sm:p-6">
                    <div
                        class="flex items-center gap-4 rounded-lg bg-gray-50 p-4 dark:bg-gray-900"
                    >
                        <img loading="lazy" decoding="async"
                            v-if="pendingCompletion.student.photo_url"
                            :src="pendingCompletion.student.photo_url"
                            :alt="`Photo de ${pendingCompletion.student.first_name} ${pendingCompletion.student.last_name}`"
                            class="h-14 w-12 rounded-md object-cover"
                        />
                        <span
                            v-else
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300"
                            >{{ pendingCompletion.student.first_name[0]
                            }}{{ pendingCompletion.student.last_name[0] }}</span
                        >
                        <div class="min-w-0">
                            <p
                                class="truncate font-bold text-slate-800 dark:text-white"
                            >
                                {{ pendingCompletion.student.last_name }}
                                {{ pendingCompletion.student.first_name }}
                            </p>
                            <p
                                class="mt-1 font-mono text-xs text-primary-600 dark:text-primary-400"
                            >
                                {{
                                    pendingCompletion.student
                                        .registration_number
                                }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex gap-3">
                            <span
                                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300"
                                ><AppIcon name="check" class="h-3.5 w-3.5"
                            /></span>
                            <div>
                                <p
                                    class="text-sm font-semibold text-slate-700 dark:text-slate-200"
                                >
                                    Enregistrer l’heure de sortie
                                </p>
                                <p
                                    class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    La présence en cours sera définitivement
                                    clôturée.
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="pendingCompletion.consultation?.is_open"
                            class="flex gap-3"
                        >
                            <span
                                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-950 dark:text-primary-300"
                                ><AppIcon name="books" class="h-3.5 w-3.5"
                            /></span>
                            <div>
                                <p
                                    class="text-sm font-semibold text-slate-700 dark:text-slate-200"
                                >
                                    Terminer la consultation
                                </p>
                                <p
                                    class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        pendingCompletion.consultation
                                            .active_copies
                                    }}
                                    exemplaire(s) seront restitués et remis en
                                    disponibilité.
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="pendingCompletion.loan && pendingCompletion.loan.active_copies > 0"
                            class="flex gap-3"
                        >
                            <span
                                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-300"
                                ><AppIcon name="loans" class="h-3.5 w-3.5"
                            /></span>
                            <div>
                                <p
                                    class="text-sm font-semibold text-slate-700 dark:text-slate-200"
                                >
                                    Conserver le prêt à domicile ouvert
                                </p>
                                <p
                                    class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ pendingCompletion.loan.active_copies }}
                                    exemplaire(s) restent attribués jusqu’à leur
                                    retour.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="flex flex-col-reverse gap-3 border-t border-gray-200 bg-gray-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6 dark:border-gray-800 dark:bg-gray-900/60"
                >
                    <button
                        type="button"
                        class="dw-btn-secondary justify-center"
                        :disabled="completingVisitId !== null"
                        @click="pendingCompletion = null"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-red-600 px-5 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 disabled:cursor-wait disabled:opacity-60"
                        :disabled="completingVisitId !== null"
                        @click="confirmCompletion"
                    >
                        <span
                            v-if="completingVisitId !== null"
                            class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                        ></span
                        ><AppIcon v-else name="logout" class="h-4 w-4" />{{
                            completingVisitId !== null
                                ? "Traitement…"
                                : "Confirmer la sortie"
                        }}
                    </button>
                </div>
            </div>
        </Modal>

        <Modal
            :show="pendingCardSwitch !== null"
            max-width="md"
            @close="cancelCardSwitch"
        >
            <div class="p-5 sm:p-6">
                <div class="flex items-start gap-4">
                    <span
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600 ring-8 ring-amber-50/60 dark:bg-amber-950 dark:text-amber-300 dark:ring-amber-950/40"
                    >
                        <AppIcon name="students" class="h-6 w-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-xs font-bold uppercase tracking-[0.16em] text-amber-600 dark:text-amber-400"
                        >
                            Carte d’étudiant détectée
                        </p>
                        <h2
                            class="mt-1 font-heading text-xl font-bold text-slate-800 dark:text-white"
                        >
                            Changer d’étudiant ?
                        </h2>
                        <p
                            class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
                        >
                            Le code scanné est une carte de bibliothèque, pas
                            l’étiquette d’un exemplaire. Voulez-vous ouvrir la
                            fiche de cet étudiant ?
                        </p>
                        <p
                            class="mt-2 break-all font-mono text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ pendingCardSwitch }}
                        </p>
                    </div>
                </div>
                <div
                    class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                >
                    <button
                        type="button"
                        class="dw-btn-secondary justify-center"
                        @click="cancelCardSwitch"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        class="dw-btn-primary justify-center"
                        @click="confirmCardSwitch"
                    >
                        <AppIcon name="students" class="h-4 w-4" />
                        Ouvrir la fiche de l’étudiant
                    </button>
                </div>
            </div>
        </Modal>

        <CameraQrScanner
            :open="cameraTarget !== null"
            :continuous="
                cameraTarget === 'copy' ||
                cameraTarget === 'loan-copy' ||
                (cameraTarget === 'student' && !isCounterMode)
            "
            :inactivity-seconds="operationSettings.scannerInactivitySeconds"
            :title="
                cameraTarget === 'copy'
                    ? 'Scanner un livre pour consultation'
                    : cameraTarget === 'loan-copy'
                      ? 'Scanner un livre à prêter'
                      : 'Scanner une carte de bibliothèque'
            "
            :help="
                cameraTarget === 'copy' || cameraTarget === 'loan-copy'
                    ? 'Présentez devant la caméra l’étiquette QR collée sur le livre.'
                    : 'Présentez devant la caméra le QR de la carte de bibliothèque.'
            "
            @close="cameraTarget = null"
            @detected="useCameraResult"
        />
    </AuthenticatedLayout>
</template>
