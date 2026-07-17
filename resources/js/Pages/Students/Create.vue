<script setup lang="ts">
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import AppIcon from "@/Components/AppIcon.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
const props = defineProps<{
    statuses: { value: string; label: string }[];
    academicReferences: any[];
}>();
const form = useForm({
    academic_number: "",
    last_name: "",
    first_name: "",
    gender: "",
    repetition_code: "N",
    birth_date: "",
    nationality: "Malagasy",
    photo: null as File | null,
    mention_id: "",
    program_id: "",
    level_id: "",
    academic_year: "",
    phone: "",
    address: "",
    email: "",
    status: "active",
    restriction_reason: "",
});
const photoPreview = ref<string | null>(null);
const programs = computed(
    () =>
        props.academicReferences.find(
            (item) => item.id === Number(form.mention_id),
        )?.programs ?? [],
);
const levels = computed(
    () =>
        programs.value.find((item: any) => item.id === Number(form.program_id))
            ?.levels ?? [],
);
watch(
    () => form.mention_id,
    () => {
        form.program_id = "";
        form.level_id = "";
    },
);
watch(
    () => form.program_id,
    () => (form.level_id = ""),
);
const submit = () => form.post(route("students.store"));
const selectPhoto = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.photo = file;
    if (photoPreview.value) URL.revokeObjectURL(photoPreview.value);
    photoPreview.value = file ? URL.createObjectURL(file) : null;
};
</script>
<template>
    <Head title="Nouvel étudiant" /><AuthenticatedLayout
        ><template #header
            ><div>
                <Link
                    :href="route('students.index')"
                    class="text-xs font-bold text-primary-600 hover:text-primary-700"
                    >← Retour aux étudiants</Link
                >
                <h1 class="dw-page-title mt-2">
                    Inscrire un étudiant
                </h1>
                <p class="dw-page-description">
                    Le numéro de bibliothèque sera généré automatiquement à
                    l’enregistrement.
                </p>
            </div></template
        >
        <form
            class="dw-card w-full p-5 sm:p-7"
            @submit.prevent="submit"
        >
            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Photo de profil</label
                    >
                    <div
                        class="flex flex-col gap-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 sm:flex-row sm:items-center dark:border-slate-700 dark:bg-slate-900/50"
                    >
                        <img loading="lazy" decoding="async"
                            v-if="photoPreview"
                            :src="photoPreview"
                            class="h-32 w-28 rounded-lg border border-slate-200 object-cover shadow-sm"
                            alt="Aperçu de la photo"
                        />
                        <div
                            v-else
                            class="flex h-32 w-28 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-300 dark:border-slate-700 dark:bg-slate-950"
                        >
                            <AppIcon name="user" class="h-12 w-12" />
                        </div>
                        <div>
                            <p
                                class="text-sm font-bold text-slate-700 dark:text-slate-200"
                            >
                                Photo utilisée sur la carte de bibliothèque
                            </p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">
                                Importez une photo d’identité ou une photo
                                scannée. JPG, PNG ou WebP, maximum 4 Mo.
                            </p>
                            <label
                                for="student-photo"
                                class="mt-3 inline-flex cursor-pointer items-center rounded-md bg-primary-600 px-4 py-2 text-xs font-bold text-white hover:bg-primary-700"
                                >Choisir une photo</label
                            ><input
                                id="student-photo"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                @change="selectPhoto"
                            />
                            <p
                                v-if="form.photo"
                                class="mt-2 max-w-sm truncate text-xs font-semibold text-emerald-600"
                            >
                                {{ form.photo.name }}
                            </p>
                            <InputError
                                class="mt-2"
                                :message="form.errors.photo"
                            />
                        </div>
                    </div>
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Nom *</label
                    ><input
                        v-model="form.last_name"
                        class="dw-field"
                        required
                    /><InputError
                        class="mt-2"
                        :message="form.errors.last_name"
                    />
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Prénom *</label
                    ><input
                        v-model="form.first_name"
                        class="dw-field"
                        required
                    /><InputError
                        class="mt-2"
                        :message="form.errors.first_name"
                    />
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Matricule académique</label
                    ><input
                        v-model="form.academic_number"
                        class="dw-field"
                    /><InputError
                        class="mt-2"
                        :message="form.errors.academic_number"
                    />
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Genre</label
                    ><select v-model="form.gender" class="dw-field">
                        <option value="">Non renseigné</option>
                        <option value="female">Féminin</option>
                        <option value="male">Masculin</option>
                        <option value="other">Autre</option>
                    </select>
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Code redoublement</label
                    ><select v-model="form.repetition_code" class="dw-field">
                        <option value="N">N — Nouveau</option>
                        <option value="R">R — Redoublant</option>
                        <option value="T">T — Transfert</option>
                    </select>
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Date de naissance</label
                    ><input
                        v-model="form.birth_date"
                        type="date"
                        class="dw-field"
                    />
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Nationalité</label
                    ><input v-model="form.nationality" class="dw-field" />
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Année universitaire</label
                    ><input
                        v-model="form.academic_year"
                        class="dw-field"
                        placeholder="2026-2027"
                    />
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Mention</label
                    ><select v-model="form.mention_id" class="dw-field">
                        <option value="">Sélectionner</option>
                        <option
                            v-for="item in academicReferences"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Parcours</label
                    ><select
                        v-model="form.program_id"
                        class="dw-field"
                        :disabled="!form.mention_id"
                    >
                        <option value="">Sélectionner</option>
                        <option
                            v-for="item in programs"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option></select
                    ><InputError :message="form.errors.program_id" />
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Niveau</label
                    ><select
                        v-model="form.level_id"
                        class="dw-field"
                        :disabled="!form.program_id"
                    >
                        <option value="">Sélectionner</option>
                        <option
                            v-for="item in levels"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option></select
                    ><InputError :message="form.errors.level_id" />
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Téléphone</label
                    ><input v-model="form.phone" class="dw-field" />
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >E-mail</label
                    ><input
                        v-model="form.email"
                        type="email"
                        class="dw-field"
                    />
                </div>
                <div class="md:col-span-2">
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Adresse</label
                    ><textarea
                        v-model="form.address"
                        rows="3"
                        class="dw-field"
                    ></textarea>
                </div>
                <div>
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Statut *</label
                    ><select v-model="form.status" class="dw-field" required>
                        <option
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>
                <div v-if="form.status === 'suspended'">
                    <label
                        class="mb-2 block text-sm font-semibold text-slate-700"
                        >Motif de suspension *</label
                    ><input
                        v-model="form.restriction_reason"
                        class="dw-field"
                    /><InputError
                        class="mt-2"
                        :message="form.errors.restriction_reason"
                    />
                </div>
            </div>
            <div
                class="mt-7 flex justify-end gap-3 border-t border-slate-100 pt-5"
            >
                <Link
                    :href="route('students.index')"
                    class="inline-flex h-11 items-center rounded-md border border-slate-200 px-5 text-sm font-bold text-slate-600 hover:bg-slate-50"
                    >Annuler</Link
                ><button
                    :disabled="form.processing"
                    class="h-11 rounded-md bg-primary-600 px-5 text-sm font-bold text-white hover:bg-primary-700 disabled:opacity-60"
                >
                    Enregistrer l’étudiant
                </button>
            </div>
        </form></AuthenticatedLayout
    >
</template>
