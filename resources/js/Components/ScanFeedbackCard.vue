<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import type { LastScan, ScanStatus } from '@/Composables/useScanFeedback';
import { computed } from 'vue';

const props = defineProps<{ scan: LastScan }>();

type Style = { card: string; icon: string; badge: string; label: string; hint: string; iconName: string };

const styles: Record<ScanStatus, Style> = {
    entry: {
        card: 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950',
        icon: 'bg-emerald-600 text-white',
        badge: 'bg-white text-emerald-700 dark:bg-emerald-900 dark:text-emerald-200',
        label: 'Entrée enregistrée',
        hint: 'La présence est ouverte.',
        iconName: 'login',
    },
    exit: {
        card: 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950',
        icon: 'bg-emerald-600 text-white',
        badge: 'bg-white text-emerald-700 dark:bg-emerald-900 dark:text-emerald-200',
        label: 'Sortie enregistrée',
        hint: 'La présence est clôturée. Les prêts à domicile restent ouverts.',
        iconName: 'logout',
    },
    already_present: {
        card: 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950',
        icon: 'bg-amber-500 text-white',
        badge: 'bg-white text-amber-700 dark:bg-amber-900 dark:text-amber-200',
        label: 'Déjà présent(e)',
        hint: 'Aucun doublon créé : l’entrée était déjà enregistrée.',
        iconName: 'check',
    },
    no_visit: {
        card: 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950',
        icon: 'bg-amber-500 text-white',
        badge: 'bg-white text-amber-700 dark:bg-amber-900 dark:text-amber-200',
        label: 'Aucune présence ouverte',
        hint: 'Aucun pointage modifié : l’entrée n’avait pas été enregistrée.',
        iconName: 'clock',
    },
    unknown: {
        card: 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950',
        icon: 'bg-red-600 text-white',
        badge: 'bg-white text-red-700 dark:bg-red-900 dark:text-red-200',
        label: 'Carte inconnue ou inactive',
        hint: 'Vérifiez le code présenté ou recherchez l’étudiant par son nom.',
        iconName: 'close',
    },
};

const style = computed(() => styles[props.scan.status] ?? styles.unknown);
const scanTime = computed(() =>
    new Date(props.scan.scanned_at).toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
    }),
);
</script>

<template>
    <div
        class="flex flex-col gap-4 rounded-lg border p-5 shadow-sm sm:flex-row sm:items-center"
        :class="style.card"
        role="status"
        aria-live="polite"
    >
        <span
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full shadow-sm"
            :class="style.icon"
        >
            <AppIcon :name="style.iconName" class="h-6 w-6" />
        </span>
        <template v-if="scan.student">
            <img
                v-if="scan.student.photo_url"
                :src="scan.student.photo_url"
                :alt="`Photo de ${scan.student.first_name} ${scan.student.last_name}`"
                class="h-16 w-14 shrink-0 rounded-md border border-white/70 object-cover shadow-sm dark:border-slate-700"
            />
            <span
                v-else
                class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white text-sm font-bold text-slate-700 shadow-sm dark:bg-slate-900 dark:text-slate-200"
                >{{ scan.student.first_name[0] }}{{ scan.student.last_name[0] }}</span
            >
        </template>
        <div class="min-w-0 flex-1">
            <p class="text-lg font-bold text-slate-800 dark:text-white">
                <template v-if="scan.student">{{ scan.student.last_name }} {{ scan.student.first_name }}</template>
                <template v-else>Code non reconnu</template>
            </p>
            <p v-if="scan.student" class="mt-0.5 font-mono text-xs font-semibold text-slate-600 dark:text-slate-300">
                {{ scan.student.registration_number }}
            </p>
            <p v-else-if="scan.code" class="mt-0.5 break-all font-mono text-xs font-semibold text-slate-600 dark:text-slate-300">
                {{ scan.code }}
            </p>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">{{ style.hint }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-2 sm:flex-col sm:items-end">
            <span class="rounded-full px-3 py-1 text-xs font-bold shadow-sm" :class="style.badge">{{ style.label }}</span>
            <span class="flex items-center gap-1 text-xs font-semibold text-slate-600 dark:text-slate-300">
                <AppIcon name="clock" class="h-3.5 w-3.5" />{{ scanTime }}
            </span>
        </div>
    </div>
</template>
