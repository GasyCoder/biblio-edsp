<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import InputError from "@/Components/InputError.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
defineProps<{ roles: string[] }>();
const form = useForm({
    name: "",
    email: "",
    role: "secretaire",
    password: "",
    password_confirmation: "",
});
const labels: any = {
    superadmin: "Super administrateur",
    secretaire: "Secrétaire",
    etudiant: "Étudiant",
};
const showPassword = ref(false);
</script>
<template>
    <Head title="Nouvel utilisateur" /><AuthenticatedLayout
        ><template #header
            ><div>
                <Link
                    :href="route('users.index')"
                    class="text-xs font-bold text-primary-600"
                    >← Utilisateurs</Link
                >
                <h1 class="dw-page-title">Nouvel utilisateur</h1>
                <p class="dw-page-description">
                    Créez un compte et attribuez son niveau d’accès.
                </p>
            </div></template
        >
        <form
            class="dw-card mx-auto w-full max-w-5xl p-6"
            @submit.prevent="form.post(route('users.store'))"
        >
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm">Nom complet *</label
                    ><input
                        v-model="form.name"
                        class="dw-field"
                        required
                    /><InputError :message="form.errors.name" />
                </div>
                <div>
                    <label class="mb-2 block text-sm">E-mail *</label
                    ><input
                        v-model="form.email"
                        type="email"
                        class="dw-field"
                        required
                    /><InputError :message="form.errors.email" />
                </div>
                <div class="md:col-span-2">
                    <span class="mb-2 block text-sm">Rôle *</span>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label
                            v-for="role in roles"
                            :key="role"
                            class="flex cursor-pointer items-center gap-3 rounded-lg border p-4 transition"
                            :class="
                                form.role === role
                                    ? 'border-primary-500 bg-primary-50 text-primary-700 ring-1 ring-primary-500 dark:bg-primary-950/40 dark:text-primary-300'
                                    : 'border-gray-200 hover:border-primary-300 dark:border-gray-800 dark:hover:border-primary-700'
                            "
                        >
                            <input
                                v-model="form.role"
                                type="radio"
                                name="role"
                                :value="role"
                                class="h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500"
                            />
                            <span class="font-semibold">{{ labels[role] || role }}</span>
                        </label>
                    </div>
                    <InputError :message="form.errors.role" />
                </div>
                <div>
                    <label class="mb-2 block text-sm">Mot de passe *</label>
                    <div class="relative">
                        <input
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            class="dw-field pe-12"
                            required
                        /><button
                            type="button"
                            class="absolute end-0 top-0 flex h-11 w-11 items-center justify-center text-slate-400 hover:text-primary-600"
                            :title="showPassword ? 'Masquer' : 'Afficher'"
                            @click="showPassword = !showPassword"
                        >
                            <AppIcon
                                :name="showPassword ? 'eye-off' : 'eye'"
                                class="h-5 w-5"
                            />
                        </button>
                    </div>
                    <InputError :message="form.errors.password" />
                </div>
                <div>
                    <label class="mb-2 block text-sm">Confirmation *</label>
                    <div class="relative">
                        <input
                            v-model="form.password_confirmation"
                            :type="showPassword ? 'text' : 'password'"
                            class="dw-field pe-12"
                            required
                        /><button
                            type="button"
                            class="absolute end-0 top-0 flex h-11 w-11 items-center justify-center text-slate-400 hover:text-primary-600"
                            :title="showPassword ? 'Masquer' : 'Afficher'"
                            @click="showPassword = !showPassword"
                        >
                            <AppIcon
                                :name="showPassword ? 'eye-off' : 'eye'"
                                class="h-5 w-5"
                            />
                        </button>
                    </div>
                </div>
            </div>
            <div
                class="mt-6 flex justify-end gap-3 border-t border-gray-200 pt-5 dark:border-gray-900"
            >
                <Link :href="route('users.index')" class="dw-btn-secondary"
                    >Annuler</Link
                ><button class="dw-btn-primary" :disabled="form.processing">
                    Créer le compte
                </button>
            </div>
        </form></AuthenticatedLayout
    >
</template>
