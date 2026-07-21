<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import CameraQrScanner from '@/Components/CameraQrScanner.vue';
import InputError from '@/Components/InputError.vue';
import ScanFeedbackCard from '@/Components/ScanFeedbackCard.vue';
import { useScanFeedback } from '@/Composables/useScanFeedback';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';

type Student = { registration_number: string; first_name: string; last_name: string; photo_url?: string };
type RecentScan = { id: number; scanned_at: string; student: Student };

const props = defineProps<{ mode: 'entry' | 'exit'; recentScans: RecentScan[] }>();
const input = ref<HTMLInputElement>();
const cameraOpen = ref(false);
const form = useForm({ code: '' });
const isEntry = computed(() => props.mode === 'entry');
const { lastScan } = useScanFeedback();

const lastSubmit = { code: '', at: 0 };
const submit = () => {
    const value = form.code.trim();
    if (!value || form.processing) return;

    // Une douchette ou la caméra continue peut relire le même code deux fois de suite.
    const now = Date.now();
    if (value === lastSubmit.code && now - lastSubmit.at < 3000) {
        form.reset();
        return;
    }
    lastSubmit.code = value;
    lastSubmit.at = now;

    form.post(route('attendance.scan', props.mode), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => nextTick(() => input.value?.select()),
        onFinish: () => nextTick(() => input.value?.focus()),
    });
};

const cameraResult = (code: string) => {
    form.code = code;
    submit();
};

onMounted(() => input.value?.focus());
</script>

<template>
    <Head :title="isEntry ? 'Poste d’entrée' : 'Poste de sortie'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="dw-page-kicker">Pointage autonome</p>
                    <h1 class="dw-page-title">{{ isEntry ? 'Poste d’entrée' : 'Poste de sortie' }}</h1>
                    <p class="dw-page-description">Un seul QR de carte. Ce poste enregistre uniquement {{ isEntry ? 'les entrées' : 'les sorties' }}.</p>
                </div>
                <div class="flex gap-2 rounded-lg bg-slate-100 p-1 dark:bg-slate-900">
                    <Link :href="route('attendance.station', 'entry')" class="rounded-md px-4 py-2 text-sm font-bold" :class="isEntry ? 'bg-white text-emerald-700 shadow-sm dark:bg-slate-800 dark:text-emerald-300' : 'text-slate-500'">Entrée</Link>
                    <Link :href="route('attendance.station', 'exit')" class="rounded-md px-4 py-2 text-sm font-bold" :class="!isEntry ? 'bg-white text-rose-700 shadow-sm dark:bg-slate-800 dark:text-rose-300' : 'text-slate-500'">Sortie</Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <ScanFeedbackCard v-if="lastScan" :scan="lastScan" />
            <div v-if="$page.props.flash?.success && !lastScan" class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.info && !lastScan" class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm font-semibold text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200">{{ $page.props.flash.info }}</div>

            <section class="dw-card overflow-hidden">
                <div class="flex flex-col items-center px-6 py-10 text-center sm:py-14">
                    <span class="flex h-20 w-20 items-center justify-center rounded-2xl" :class="isEntry ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950' : 'bg-rose-50 text-rose-600 dark:bg-rose-950'">
                        <AppIcon :name="isEntry ? 'login' : 'logout'" class="h-10 w-10" />
                    </span>
                    <h2 class="mt-6 font-heading text-2xl font-bold text-slate-800 dark:text-white">Présentez votre carte de bibliothèque</h2>
                    <p class="mt-2 max-w-xl text-sm text-slate-500 dark:text-slate-400">Le lecteur USB peut scanner directement. Appuyez sur « Caméra » uniquement si vous utilisez la caméra de l’appareil.</p>

                    <form class="mt-8 flex w-full max-w-3xl flex-col gap-3 sm:flex-row" @submit.prevent="submit">
                        <div class="relative flex-1">
                            <AppIcon name="scan" class="absolute start-4 top-1/2 h-5 w-5 -translate-y-1/2 text-primary-500" />
                            <input ref="input" v-model="form.code" class="dw-field h-14 ps-12 text-lg font-semibold" placeholder="Scannez le QR de la carte…" autocomplete="off" />
                        </div>
                        <button type="button" class="dw-btn-secondary h-14 justify-center px-6" @click="cameraOpen = true"><AppIcon name="scan" class="h-5 w-5" /> Caméra</button>
                        <button class="h-14 rounded-md px-7 text-sm font-bold text-white shadow-sm" :class="isEntry ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700'" :disabled="form.processing">{{ form.processing ? 'Pointage…' : (isEntry ? 'Enregistrer l’entrée' : 'Enregistrer la sortie') }}</button>
                    </form>
                    <InputError class="mt-3" :message="form.errors.code" />
                </div>
            </section>

            <section class="dw-card overflow-hidden">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <p class="text-xs font-bold uppercase tracking-widest text-primary-600">Derniers pointages</p>
                    <h2 class="mt-1 font-heading text-lg font-bold text-slate-800 dark:text-white">{{ isEntry ? 'Entrées récentes' : 'Sorties récentes' }}</h2>
                </div>
                <div v-if="recentScans.length" class="divide-y divide-gray-200 dark:divide-gray-800">
                    <div v-for="scan in recentScans" :key="scan.id" class="flex items-center gap-4 px-5 py-4">
                        <img v-if="scan.student.photo_url" :src="scan.student.photo_url" class="h-11 w-10 rounded-md object-cover" alt="" />
                        <span v-else class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300">{{ scan.student.first_name[0] }}{{ scan.student.last_name[0] }}</span>
                        <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold text-slate-700 dark:text-slate-200">{{ scan.student.last_name }} {{ scan.student.first_name }}</p><p class="font-mono text-xs text-primary-600">{{ scan.student.registration_number }}</p></div>
                        <time class="text-xs text-slate-500">{{ new Date(scan.scanned_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}</time>
                    </div>
                </div>
                <p v-else class="p-8 text-center text-sm text-slate-500">Aucun pointage récent sur ce poste.</p>
            </section>
        </div>

        <CameraQrScanner :open="cameraOpen" title="Scanner la carte de bibliothèque" help="Présentez le QR unique de la carte devant la caméra." :continuous="true" @detected="cameraResult" @close="cameraOpen = false" />
    </AuthenticatedLayout>
</template>
