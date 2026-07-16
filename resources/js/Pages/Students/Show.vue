<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
defineProps<{ student: any; consultedBooks: any; borrowedBooks: any }>();
const page = usePage();
const canUpdate = page.props.auth.permissions.includes("students.update");
const statusLabels: Record<string, string> = {
    active: "Actif",
    inactive: "Inactif",
    suspended: "Suspendu",
    graduated: "Diplômé",
};
</script>
<template>
    <Head
        :title="`${student.last_name} ${student.first_name}`"
    /><AuthenticatedLayout
        ><template #header
            ><div
                class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
            >
                <div>
                    <Link
                        :href="route('students.index')"
                        class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                        >← Retour aux étudiants</Link
                    >
                    <p
                        class="dw-page-kicker mt-3"
                    >
                        Profil étudiant
                    </p>
                    <h1
                        class="dw-page-title"
                    >
                        {{ student.last_name }} {{ student.first_name }}
                    </h1>
                </div>
                <Link
                    v-if="canUpdate"
                    :href="route('students.edit', student.id)"
                    class="inline-flex h-10 items-center gap-2 self-start rounded-md bg-primary-600 px-4 text-sm font-bold text-white"
                    ><AppIcon name="edit" class="h-4 w-4" /> Modifier</Link
                >
            </div></template
        >
        <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="dw-card p-5">
                <img
                    v-if="student.photo_url"
                    :src="student.photo_url"
                    class="mx-auto h-64 w-52 rounded-xl border border-slate-200 object-cover shadow-sm"
                    alt="Photo de profil"
                />
                <div
                    v-else
                    class="mx-auto flex h-64 w-52 flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 text-slate-500 dark:text-slate-400 dark:border-slate-700 dark:bg-slate-900"
                >
                    <AppIcon name="user" class="h-16 w-16" /><span
                        class="mt-3 text-xs font-bold"
                        >Aucune photo</span
                    >
                </div>
                <div class="mt-5 text-center">
                    <p class="font-mono text-sm font-bold text-primary-700">
                        {{ student.registration_number }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{
                            student.academic_number || "Matricule non renseigné"
                        }}
                    </p>
                    <span
                        class="mt-3 inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                        >{{
                            statusLabels[student.status] || student.status
                        }}</span
                    >
                </div>
            </aside>
            <div class="space-y-6">
                <section class="dw-card p-6">
                    <h2 class="font-heading text-lg font-bold text-slate-800">
                        Informations personnelles et académiques
                    </h2>
                    <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <dt
                                class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400"
                            >
                                Date de naissance
                            </dt>
                            <dd class="mt-1 text-slate-700">
                                {{ student.birth_date || "Non renseignée" }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400"
                            >
                                Nationalité
                            </dt>
                            <dd class="mt-1 text-slate-700">
                                {{ student.nationality || "Non renseignée" }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400"
                            >
                                Mention
                            </dt>
                            <dd class="mt-1 text-slate-700">
                                {{ student.mention?.name || "Non renseignée" }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400"
                            >
                                Parcours
                            </dt>
                            <dd class="mt-1 text-slate-700">
                                {{
                                    student.academic_program?.name ||
                                    student.program ||
                                    "Non renseigné"
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400"
                            >
                                Niveau
                            </dt>
                            <dd class="mt-1 text-slate-700">
                                {{
                                    student.academic_level?.name ||
                                    student.level ||
                                    "Non renseigné"
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400"
                            >
                                Année universitaire
                            </dt>
                            <dd class="mt-1 text-slate-700">
                                {{ student.academic_year || "Non renseignée" }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400"
                            >
                                Téléphone
                            </dt>
                            <dd class="mt-1 text-slate-700">
                                {{ student.phone || "Non renseigné" }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400"
                            >
                                E-mail
                            </dt>
                            <dd class="mt-1 text-slate-700">
                                {{ student.email || "Non renseigné" }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt
                                class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400"
                            >
                                Adresse
                            </dt>
                            <dd class="mt-1 text-slate-700">
                                {{ student.address || "Non renseignée" }}
                            </dd>
                        </div>
                    </dl>
                </section>
                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="dw-card overflow-hidden">
                        <div class="border-b border-slate-100 px-5 py-4">
                            <h2 class="font-heading font-bold text-slate-800">
                                Carte de bibliothèque
                            </h2>
                        </div>
                        <div
                            v-if="student.cards.length"
                            class="divide-y divide-slate-100"
                        >
                            <div
                                v-for="card in student.cards"
                                :key="card.id"
                                class="flex items-center gap-3 p-4"
                            >
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="font-mono text-sm font-bold text-primary-700"
                                    >
                                        {{ card.card_number }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        Délivrée le
                                        {{ card.issued_at?.slice(0, 10) }} ·
                                        {{ card.status }}
                                    </p>
                                </div>
                                <a
                                    :href="route('cards.print', card.id)"
                                    target="_blank"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded text-primary-600"
                                    ><AppIcon name="print" class="h-4 w-4"
                                /></a>
                            </div>
                        </div>
                        <p
                            v-else
                            class="p-8 text-center text-sm text-slate-500 dark:text-slate-400"
                        >
                            Aucune carte de bibliothèque.
                        </p>
                    </section>
                    <section class="dw-card p-5">
                        <h2 class="font-heading font-bold text-slate-800">
                            Activité bibliothèque
                        </h2>
                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-primary-50 p-4 dark:bg-primary-950/40">
                                <p class="text-2xl font-bold text-primary-700 dark:text-primary-300">
                                    {{ student.visits.length }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    Présences récentes
                                </p>
                            </div>
                            <div class="rounded-lg bg-emerald-50 p-4 dark:bg-emerald-950/40">
                                <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                                    {{ student.consultation_sessions.length }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    Consultations récentes
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 space-y-2">
                            <div
                                v-for="visit in student.visits.slice(0, 5)"
                                :key="visit.id"
                                class="flex justify-between rounded border border-slate-100 p-2 text-xs"
                            >
                                <span>{{ visit.checked_in_at }}</span
                                ><span>{{
                                    visit.checked_out_at
                                        ? "Sortie enregistrée"
                                        : "Présent"
                                }}</span>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <section class="dw-card overflow-hidden">
                <div class="flex items-center justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-900"><div><p class="dw-page-kicker">Lecture sur place</p><h2 class="mt-1 font-heading text-lg font-bold text-slate-800">Livres consultés</h2><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ consultedBooks.total }} consultation(s) enregistrée(s)</p></div><span class="flex h-11 w-11 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950"><AppIcon name="books" class="h-5 w-5"/></span></div>
                <div v-if="consultedBooks.data.length" class="divide-y divide-gray-200 dark:divide-gray-900">
                    <div v-for="item in consultedBooks.data" :key="item.id" class="flex gap-4 p-4 sm:p-5"><img v-if="item.copy.book.cover_url" :src="item.copy.book.cover_url" :alt="`Couverture de ${item.copy.book.title}`" class="h-20 w-14 shrink-0 rounded object-cover shadow-sm"/><span v-else class="flex h-20 w-14 shrink-0 items-center justify-center rounded border border-dashed border-gray-300 bg-gray-50 text-primary-500 dark:border-gray-800 dark:bg-gray-900"><AppIcon name="books" class="h-6 w-6"/></span><div class="min-w-0 flex-1"><Link :href="route('books.show', item.copy.book.id)" class="line-clamp-2 text-sm font-bold text-slate-700 hover:text-primary-600 dark:text-slate-200">{{ item.copy.book.title }}</Link><p class="mt-1 font-mono text-xs text-primary-600">{{ item.copy.inventory_number }}</p><div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500 dark:text-slate-400"><span>{{ new Date(item.scanned_at).toLocaleString('fr-FR') }}</span><span>·</span><span>{{ item.session.session_number }}</span></div></div><span class="h-fit rounded-full px-2.5 py-1 text-[11px] font-bold" :class="item.returned_at ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'">{{ item.returned_at ? 'Restitué' : 'En consultation' }}</span></div>
                </div><div v-else class="p-10 text-center"><AppIcon name="books" class="mx-auto h-9 w-9 text-slate-300"/><p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Aucun livre consulté.</p></div>
                <div v-if="consultedBooks.links?.length > 3" class="flex flex-wrap justify-center gap-1 border-t border-gray-200 p-4 dark:border-gray-900"><Link v-for="link in consultedBooks.links" :key="link.label" :href="link.url || '#'" class="rounded-md border px-2.5 py-1.5 text-xs font-semibold transition-colors" :class="link.active ? 'border-primary-600 bg-primary-600 text-white shadow-sm' : link.url ? 'border-gray-300 text-slate-600 hover:border-primary-300 hover:text-primary-600 dark:border-gray-800 dark:text-slate-300 dark:hover:border-primary-700 dark:hover:text-primary-400' : 'pointer-events-none border-gray-200 text-slate-400 dark:border-gray-900 dark:text-slate-600'" v-html="link.label"/></div>
            </section>

            <section class="dw-card overflow-hidden">
                <div class="flex items-center justify-between gap-4 border-b border-gray-200 p-5 dark:border-gray-900"><div><p class="text-xs font-bold uppercase tracking-widest text-amber-600">Prêt à domicile</p><h2 class="mt-1 font-heading text-lg font-bold text-slate-800">Livres empruntés</h2><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ borrowedBooks.total }} emprunt(s) enregistré(s)</p></div><span class="flex h-11 w-11 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950"><AppIcon name="loans" class="h-5 w-5"/></span></div>
                <div v-if="borrowedBooks.data.length" class="divide-y divide-gray-200 dark:divide-gray-900">
                    <div v-for="item in borrowedBooks.data" :key="item.id" class="flex gap-4 p-4 sm:p-5"><img v-if="item.copy.book.cover_url" :src="item.copy.book.cover_url" :alt="`Couverture de ${item.copy.book.title}`" class="h-20 w-14 shrink-0 rounded object-cover shadow-sm"/><span v-else class="flex h-20 w-14 shrink-0 items-center justify-center rounded border border-dashed border-gray-300 bg-gray-50 text-amber-500 dark:border-gray-800 dark:bg-gray-900"><AppIcon name="loans" class="h-6 w-6"/></span><div class="min-w-0 flex-1"><Link :href="route('books.show', item.copy.book.id)" class="line-clamp-2 text-sm font-bold text-slate-700 hover:text-primary-600 dark:text-slate-200">{{ item.copy.book.title }}</Link><p class="mt-1 font-mono text-xs text-amber-600">{{ item.copy.inventory_number }}</p><div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500 dark:text-slate-400"><span>Prêté le {{ new Date(item.loaned_at).toLocaleDateString('fr-FR') }}</span><span>·</span><span>Retour prévu {{ new Date(item.loan.due_at).toLocaleDateString('fr-FR') }}</span></div></div><span class="h-fit rounded-full px-2.5 py-1 text-[11px] font-bold" :class="item.returned_at ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : new Date(item.loan.due_at) < new Date() ? 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300'">{{ item.returned_at ? 'Rendu' : new Date(item.loan.due_at) < new Date() ? 'En retard' : 'Emprunté' }}</span></div>
                </div><div v-else class="p-10 text-center"><AppIcon name="loans" class="mx-auto h-9 w-9 text-slate-300"/><p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Aucun livre emprunté.</p></div>
                <div v-if="borrowedBooks.links?.length > 3" class="flex flex-wrap justify-center gap-1 border-t border-gray-200 p-4 dark:border-gray-900"><Link v-for="link in borrowedBooks.links" :key="link.label" :href="link.url || '#'" class="rounded-md border px-2.5 py-1.5 text-xs font-semibold transition-colors" :class="link.active ? 'border-amber-600 bg-amber-600 text-white shadow-sm' : link.url ? 'border-gray-300 text-slate-600 hover:border-amber-400 hover:text-amber-700 dark:border-gray-800 dark:text-slate-300 dark:hover:border-amber-700 dark:hover:text-amber-400' : 'pointer-events-none border-gray-200 text-slate-400 dark:border-gray-900 dark:text-slate-600'" v-html="link.label"/></div>
            </section>
        </div>
        </AuthenticatedLayout
    >
</template>
