<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import InputError from '@/Components/InputError.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{ canResetPassword?: boolean; status?: string }>();

const showPassword = ref(false);
const form = useForm({ email: '', password: '', remember: false });

const submit = () => {
    form.post(route('login'), { onFinish: () => form.reset('password') });
};
</script>

<template>
    <Head title="Connexion" />

    <main class="relative flex min-h-screen bg-white transition-colors dark:bg-slate-900">
        <div class="absolute end-5 top-5 z-20 rounded-full bg-white/80 shadow-sm backdrop-blur dark:bg-slate-900/80 lg:end-7 lg:top-7"><ThemeToggle /></div>
        <section class="flex w-full flex-col lg:w-[46%]">
            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-6 py-10 sm:px-8 lg:py-14">
                <div class="mb-12 flex items-center gap-3">
                    <ApplicationLogo class="h-11 w-11 text-primary-600" />
                    <div>
                        <p class="font-heading text-lg font-bold leading-tight text-slate-800">Bibliothèque EDSP</p>
                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-slate-400">Université de Mahajanga</p>
                    </div>
                </div>

                <div class="mb-7">
                    <p class="mb-2 text-xs font-bold uppercase tracking-[0.18em] text-primary-600">Espace sécurisé</p>
                    <h1 class="font-heading text-3xl font-bold tracking-tight text-slate-800">Heureux de vous revoir</h1>
                    <p class="mt-3 text-sm leading-6 text-slate-500">Connectez-vous pour accéder à la gestion interne de la bibliothèque.</p>
                </div>

                <div v-if="status" class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ status }}</div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Adresse e-mail</label>
                        <input id="email" v-model="form.email" type="email" class="dw-field" placeholder="nom@edsp.mg" required autofocus autocomplete="username" />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label for="password" class="text-sm font-semibold text-slate-700">Mot de passe</label>
                            <Link v-if="canResetPassword" :href="route('password.request')" class="text-xs font-semibold text-primary-600 hover:text-primary-700">Mot de passe oublié ?</Link>
                        </div>
                        <div class="relative">
                            <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" class="dw-field pe-12" placeholder="Votre mot de passe" required autocomplete="current-password" />
                            <button type="button" class="absolute inset-y-0 end-0 flex w-12 items-center justify-center text-slate-400 hover:text-primary-600" :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'" @click="showPassword = !showPassword">
                                <AppIcon :name="showPassword ? 'eye-off' : 'eye'" class="h-5 w-5" />
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <label class="flex cursor-pointer items-center gap-3 text-sm text-slate-600">
                        <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                        Garder ma session ouverte
                    </label>

                    <button type="submit" class="flex h-12 w-full items-center justify-center rounded-md bg-primary-600 px-5 text-sm font-bold text-white shadow-sm transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing">
                        <span v-if="form.processing" class="me-2 h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        Se connecter
                    </button>
                </form>

                <p class="mt-7 rounded-md bg-slate-50 px-4 py-3 text-center text-xs leading-5 text-slate-500">Les comptes sont attribués par l’administration de l’EDSP. Contactez le responsable si vous ne disposez pas encore d’un accès.</p>
            </div>

            <footer class="mx-auto w-full max-w-md px-6 pb-8 text-xs text-slate-400 sm:px-8">© {{ new Date().getFullYear() }} EDSP — Gestion interne de la bibliothèque</footer>
        </section>

        <aside class="relative hidden flex-1 overflow-hidden bg-slate-950 lg:flex lg:items-center lg:justify-center">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_80%_20%,rgba(121,139,255,0.3),transparent_35%),radial-gradient(circle_at_20%_85%,rgba(23,107,83,0.28),transparent_32%)]"></div>
            <div class="absolute inset-0 opacity-[0.08] [background-image:linear-gradient(rgba(255,255,255,.35)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.35)_1px,transparent_1px)] [background-size:42px_42px]"></div>
            <div class="relative z-10 max-w-xl px-12 text-white">
                <div class="mb-8 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/15 backdrop-blur"><AppIcon name="book-open" class="h-8 w-8 text-primary-300" /></div>
                <h2 class="font-heading text-4xl font-bold leading-tight">Le savoir, accessible.<br />La gestion, simplifiée.</h2>
                <p class="mt-5 max-w-lg text-base leading-7 text-slate-300">Une plateforme unique pour le catalogue, le pointage, la consultation sur place et le suivi des prêts de la bibliothèque EDSP.</p>
                <div class="mt-10 grid grid-cols-3 gap-4">
                    <div class="rounded-lg border border-white/10 bg-white/[0.06] p-4 backdrop-blur"><AppIcon name="scan" class="mb-3 h-6 w-6 text-primary-300"/><p class="text-sm font-semibold">Scan rapide</p></div>
                    <div class="rounded-lg border border-white/10 bg-white/[0.06] p-4 backdrop-blur"><AppIcon name="books" class="mb-3 h-6 w-6 text-emerald-300"/><p class="text-sm font-semibold">Catalogue fiable</p></div>
                    <div class="rounded-lg border border-white/10 bg-white/[0.06] p-4 backdrop-blur"><AppIcon name="reports" class="mb-3 h-6 w-6 text-amber-300"/><p class="text-sm font-semibold">Suivi précis</p></div>
                </div>
            </div>
        </aside>
    </main>
</template>
