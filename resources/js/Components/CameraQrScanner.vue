<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import { BrowserQRCodeReader, type IScannerControls } from '@zxing/browser';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = withDefaults(defineProps<{
    open: boolean;
    title?: string;
    help?: string;
    continuous?: boolean;
    inactivitySeconds?: number;
}>(), {
    title: 'Scanner un QR code',
    help: 'Placez le QR code au centre du cadre.',
    continuous: false,
    inactivitySeconds: 40,
});

const emit = defineEmits<{
    close: [];
    detected: [value: string];
}>();

const video = ref<HTMLVideoElement>();
const error = ref('');
const starting = ref(false);
const remainingSeconds = ref(props.inactivitySeconds);
const lastScanned = ref('');
let controls: IScannerControls | undefined;
let detected = false;
let lastDetectedAt = 0;
let inactivityTimer: ReturnType<typeof setInterval> | undefined;

const clearInactivityTimer = () => {
    if (inactivityTimer) clearInterval(inactivityTimer);
    inactivityTimer = undefined;
};

const resetInactivityTimer = () => {
    if (!props.continuous) return;
    clearInactivityTimer();
    remainingSeconds.value = props.inactivitySeconds;
    inactivityTimer = setInterval(() => {
        remainingSeconds.value -= 1;
        if (remainingSeconds.value <= 0) close();
    }, 1000);
};

const stop = () => {
    clearInactivityTimer();
    controls?.stop();
    controls = undefined;
    if (video.value?.srcObject) {
        for (const track of (video.value.srcObject as MediaStream).getTracks()) track.stop();
        video.value.srcObject = null;
    }
};

const close = () => {
    stop();
    emit('close');
};

const start = async () => {
    stop();
    error.value = '';
    detected = false;
    lastScanned.value = '';
    lastDetectedAt = 0;
    starting.value = true;
    await nextTick();

    if (!navigator.mediaDevices?.getUserMedia) {
        error.value = 'La caméra est indisponible. Utilisez HTTPS/localhost ou une douchette USB.';
        starting.value = false;
        return;
    }

    try {
        const reader = new BrowserQRCodeReader(undefined, { delayBetweenScanAttempts: 150 });
        controls = await reader.decodeFromConstraints(
            { video: { facingMode: { ideal: 'environment' } }, audio: false },
            video.value!,
            (result) => {
                const value = result?.getText().trim();
                if (!value) return;
                const now = Date.now();
                if (props.continuous) {
                    if (value === lastScanned.value && now - lastDetectedAt < 3000) return;
                    lastScanned.value = value;
                    lastDetectedAt = now;
                    resetInactivityTimer();
                    emit('detected', value);
                    return;
                }
                if (detected) return;
                detected = true;
                stop();
                emit('detected', value);
            },
        );
        resetInactivityTimer();
    } catch (exception) {
        const name = exception instanceof DOMException ? exception.name : '';
        error.value = name === 'NotAllowedError'
            ? 'Accès à la caméra refusé. Autorisez la caméra dans les réglages du navigateur.'
            : name === 'NotFoundError'
                ? 'Aucune caméra n’a été détectée sur cet appareil.'
                : 'Impossible de démarrer la caméra. Vérifiez les autorisations et réessayez.';
    } finally {
        starting.value = false;
    }
};

watch(() => props.open, (open) => open ? start() : stop());
onBeforeUnmount(stop);
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" :aria-label="title" @click.self="close">
            <section class="w-full max-w-lg overflow-hidden rounded-xl border border-slate-700 bg-slate-900 text-white shadow-2xl">
                <header class="flex items-start justify-between gap-4 border-b border-slate-700 px-5 py-4">
                    <div>
                        <h2 class="font-heading text-lg font-bold">{{ title }}</h2>
                        <p class="mt-1 text-sm text-slate-300">{{ help }}</p>
                    </div>
                    <button type="button" class="rounded-md p-2 text-slate-300 hover:bg-white/10 hover:text-white" aria-label="Fermer le scanner" @click="close">
                        <AppIcon name="close" class="h-5 w-5" />
                    </button>
                </header>

                <div class="p-5">
                    <div class="relative aspect-square overflow-hidden rounded-lg bg-black sm:aspect-[4/3]">
                        <video ref="video" class="h-full w-full object-cover" muted playsinline />
                        <div v-if="!error" class="pointer-events-none absolute inset-[12%] rounded-xl border-2 border-white/90 shadow-[0_0_0_999px_rgba(2,6,23,0.42)]">
                            <span class="absolute -left-0.5 -top-0.5 h-8 w-8 border-l-4 border-t-4 border-primary-400" />
                            <span class="absolute -right-0.5 -top-0.5 h-8 w-8 border-r-4 border-t-4 border-primary-400" />
                            <span class="absolute -bottom-0.5 -left-0.5 h-8 w-8 border-b-4 border-l-4 border-primary-400" />
                            <span class="absolute -bottom-0.5 -right-0.5 h-8 w-8 border-b-4 border-r-4 border-primary-400" />
                        </div>
                        <div v-if="starting" class="absolute inset-0 flex items-center justify-center bg-slate-950/70 text-sm font-semibold">Démarrage de la caméra…</div>
                        <div v-if="error" class="absolute inset-0 flex flex-col items-center justify-center p-7 text-center">
                            <AppIcon name="scan" class="h-10 w-10 text-red-400" />
                            <p class="mt-4 text-sm font-semibold text-red-200">{{ error }}</p>
                            <button type="button" class="mt-5 rounded-md bg-white px-4 py-2 text-sm font-bold text-slate-900" @click="start">Réessayer</button>
                        </div>
                    </div>
                    <div v-if="continuous" class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <span v-if="lastScanned" class="inline-flex items-center gap-1.5 font-bold text-emerald-400"><AppIcon name="check" class="h-4 w-4" /> Code lu, présentez le livre suivant</span>
                        <span v-else class="text-slate-300">Le lecteur reste ouvert pour scanner plusieurs livres.</span>
                        <span class="ms-auto tabular-nums text-slate-400">Fermeture dans {{ remainingSeconds }} s</span>
                    </div>
                    <p class="mt-3 text-center text-xs text-slate-400">La caméra sert uniquement à lire le code. Aucune image n’est conservée.</p>
                </div>
            </section>
        </div>
    </Teleport>
</template>
