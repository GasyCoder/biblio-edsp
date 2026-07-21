import { usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

export type ScanStatus = 'entry' | 'exit' | 'already_present' | 'no_visit' | 'unknown';

export type LastScan = {
    status: ScanStatus;
    code?: string | null;
    scanned_at: string;
    student?: {
        id: number;
        first_name: string;
        last_name: string;
        registration_number: string;
        photo_url?: string | null;
    } | null;
};

let audioContext: AudioContext | undefined;

const ensureContext = (): AudioContext | undefined => {
    if (typeof window === 'undefined') return undefined;
    try {
        audioContext ??= new AudioContext();
        if (audioContext.state === 'suspended') void audioContext.resume();
        return audioContext;
    } catch {
        // Environnement sans audio : le retour reste visuel.
        return undefined;
    }
};

// Les navigateurs ne laissent démarrer l'audio qu'après un geste utilisateur ;
// les bips étant joués dans des callbacks asynchrones (après la réponse HTTP),
// on déverrouille le contexte dès la première interaction (clic, touche ou douchette).
if (typeof window !== 'undefined') {
    const unlock = () => void ensureContext();
    window.addEventListener('pointerdown', unlock, { once: true });
    window.addEventListener('keydown', unlock, { once: true });
}

const tone = (ctx: AudioContext, frequency: number, delay: number, duration: number) => {
    const start = ctx.currentTime + delay;
    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();
    oscillator.type = 'sine';
    oscillator.frequency.value = frequency;
    gain.gain.setValueAtTime(0.15, start);
    gain.gain.exponentialRampToValueAtTime(0.001, start + duration);
    oscillator.connect(gain).connect(ctx.destination);
    oscillator.start(start);
    oscillator.stop(start + duration);
};

export const playBeep = (kind: 'success' | 'warning' | 'error') => {
    const ctx = ensureContext();
    if (!ctx) return;

    const schedule = () => {
        try {
            if (kind === 'success') {
                tone(ctx, 880, 0, 0.15);
            } else if (kind === 'error') {
                tone(ctx, 220, 0, 0.35);
            } else {
                tone(ctx, 523, 0, 0.1);
                tone(ctx, 523, 0.16, 0.1);
            }
        } catch {
            // Environnement sans audio : le retour reste visuel.
        }
    };

    if (ctx.state === 'suspended') {
        void ctx.resume().then(schedule).catch(() => {});
    } else {
        schedule();
    }
};

const playFeedback = (status: ScanStatus) =>
    playBeep(
        status === 'entry' || status === 'exit'
            ? 'success'
            : status === 'unknown'
              ? 'error'
              : 'warning',
    );

export function useScanFeedback() {
    const page = usePage();
    const lastScan = computed(
        () => (page.props.flash as { lastScan?: LastScan | null } | undefined)?.lastScan ?? null,
    );

    watch(lastScan, (scanned) => {
        if (scanned) playFeedback(scanned.status);
    });

    return { lastScan };
}
