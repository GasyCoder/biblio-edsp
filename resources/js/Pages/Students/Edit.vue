<script setup lang="ts">
import InputError from "@/Components/InputError.vue";
import AppIcon from "@/Components/AppIcon.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
const props = defineProps<{
    student: any;
    statuses: any[];
    academicReferences: any[];
}>();
const form = useForm({
    _method: "patch",
    academic_number: props.student.academic_number ?? "",
    last_name: props.student.last_name,
    first_name: props.student.first_name,
    gender: props.student.gender ?? "",
    repetition_code: props.student.repetition_code ?? "N",
    birth_date: props.student.birth_date ?? "",
    nationality: props.student.nationality ?? "",
    photo: null as File | null,
    remove_photo: false,
    mention_id: props.student.mention_id ?? "",
    program_id: props.student.program_id ?? "",
    level_id: props.student.level_id ?? "",
    academic_year: props.student.academic_year ?? "",
    phone: props.student.phone ?? "",
    address: props.student.address ?? "",
    email: props.student.email ?? "",
    status: props.student.status,
    restriction_reason: props.student.restriction_reason ?? "",
});
const photoPreview = ref<string | null>(props.student.photo_url ?? null);
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
const selectPhoto = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.photo = file;
    form.remove_photo = false;
    if (photoPreview.value?.startsWith("blob:"))
        URL.revokeObjectURL(photoPreview.value);
    photoPreview.value = file
        ? URL.createObjectURL(file)
        : props.student.photo_url;
};
const removePhoto = () => {
    form.remove_photo = true;
    form.photo = null;
    photoPreview.value = null;
};
</script>
<template>
    <Head title="Modifier un étudiant" /><AuthenticatedLayout
        ><template #header
            ><div>
                <Link
                    :href="route('students.index')"
                    class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                    >← Retour aux étudiants</Link
                >
                <h1 class="dw-page-title mt-2">
                    Modifier {{ student.registration_number }}
                </h1>
            </div></template
        >
        <form
            class="dw-card w-full p-5 sm:p-7"
            @submit.prevent="
                form.post(route('students.update', student.id), {
                    forceFormData: true,
                })
            "
        >
            <div class="grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold"
                        >Photo de profil</label
                    >
                    <div
                        class="flex flex-col gap-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 sm:flex-row sm:items-center dark:border-slate-700 dark:bg-slate-900/50"
                    >
                        <img
                            v-if="photoPreview"
                            :src="photoPreview"
                            class="h-32 w-28 rounded-lg border border-slate-200 object-cover shadow-sm"
                            alt="Photo de l’étudiant"
                        />
                        <div
                            v-else
                            class="flex h-32 w-28 items-center justify-center rounded-lg border bg-white text-slate-300 dark:border-slate-700 dark:bg-slate-950"
                        >
                            <AppIcon name="user" class="h-12 w-12" />
                        </div>
                        <div>
                            <p
                                class="text-sm font-bold text-slate-700 dark:text-slate-200"
                            >
                                Photo utilisée sur la carte de bibliothèque
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Vous pouvez ajouter ou remplacer la photo
                                actuelle.
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <label
                                    for="student-photo-edit"
                                    class="inline-flex cursor-pointer items-center rounded-md bg-primary-600 px-4 py-2 text-xs font-bold text-white"
                                    >{{
                                        photoPreview
                                            ? "Remplacer la photo"
                                            : "Ajouter une photo"
                                    }}</label
                                ><button
                                    v-if="photoPreview"
                                    type="button"
                                    class="rounded-md border border-red-200 px-4 py-2 text-xs font-bold text-red-600"
                                    @click="removePhoto"
                                >
                                    Supprimer
                                </button>
                            </div>
                            <input
                                id="student-photo-edit"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                @change="selectPhoto"
                            />
                            <p
                                v-if="form.photo"
                                class="mt-2 text-xs font-semibold text-emerald-600"
                            >
                                {{ form.photo.name }}
                            </p>
                            <InputError :message="form.errors.photo" />
                        </div>
                    </div>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold">Nom *</label
                    ><input
                        v-model="form.last_name"
                        class="dw-field"
                        required
                    /><InputError :message="form.errors.last_name" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >Prénom *</label
                    ><input
                        v-model="form.first_name"
                        class="dw-field"
                        required
                    />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >Matricule académique</label
                    ><input
                        v-model="form.academic_number"
                        class="dw-field"
                    /><InputError :message="form.errors.academic_number" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold">Genre</label
                    ><select v-model="form.gender" class="dw-field">
                        <option value="">Non renseigné</option>
                        <option value="female">Féminin</option>
                        <option value="male">Masculin</option>
                        <option value="other">Autre</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >Code redoublement</label
                    ><select v-model="form.repetition_code" class="dw-field">
                        <option value="N">N — Nouveau</option>
                        <option value="R">R — Redoublant</option>
                        <option value="T">T — Transfert</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >Date de naissance</label
                    ><input
                        v-model="form.birth_date"
                        type="date"
                        class="dw-field"
                    />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >Nationalité</label
                    ><input v-model="form.nationality" class="dw-field" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >Téléphone</label
                    ><input v-model="form.phone" class="dw-field" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
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
                    <label class="mb-2 block text-sm font-semibold"
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
                        </option>
                    </select>
                    <InputError :message="form.errors.program_id" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
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
                        </option>
                    </select>
                    <InputError :message="form.errors.level_id" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >Année universitaire</label
                    ><input v-model="form.academic_year" class="dw-field" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >E-mail</label
                    ><input
                        v-model="form.email"
                        type="email"
                        class="dw-field"
                    />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold"
                        >Adresse</label
                    ><textarea
                        v-model="form.address"
                        rows="3"
                        class="w-full rounded-md border border-slate-300 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                    ></textarea>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-semibold"
                        >Statut</label
                    ><select v-model="form.status" class="dw-field">
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
                    <label class="mb-2 block text-sm font-semibold"
                        >Motif de suspension</label
                    ><input
                        v-model="form.restriction_reason"
                        class="dw-field"
                    /><InputError :message="form.errors.restriction_reason" />
                </div>
            </div>
            <div
                class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-5"
            >
                <Link
                    :href="route('students.index')"
                    class="dw-btn-secondary"
                    >Annuler</Link
                ><button
                    class="dw-btn-primary"
                >
                    Enregistrer
                </button>
            </div>
        </form></AuthenticatedLayout
    >
</template>
