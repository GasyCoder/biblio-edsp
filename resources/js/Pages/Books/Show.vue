<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps<{ book: any }>();
const page = usePage();
const canUpdate = page.props.auth.permissions.includes('books.update');
const statusLabels: Record<string, string> = { available: 'Disponible', in_consultation: 'En consultation', borrowed: 'Emprunté', damaged: 'Endommagé', lost: 'Perdu', archived: 'Archivé' };
</script>

<template>
    <Head :title="book.title" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div><Link :href="route('books.index')" class="text-xs font-bold text-primary-600">← Retour au catalogue</Link><p class="mt-3 text-xs font-bold uppercase tracking-widest text-primary-600">Fiche bibliographique</p><h1 class="mt-1 font-heading text-2xl font-bold text-slate-800">{{ book.title }}</h1></div>
                <Link v-if="canUpdate" :href="route('books.edit', book.id)" class="inline-flex h-10 items-center gap-2 self-start rounded-md bg-primary-600 px-4 text-sm font-bold text-white"><AppIcon name="edit" class="h-4 w-4" /> Modifier</Link>
            </div>
        </template>

        <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="dw-card p-5">
                <img v-if="book.cover_url" :src="book.cover_url" :alt="`Couverture de ${book.title}`" class="mx-auto aspect-[2/3] w-full max-w-56 rounded-lg border border-slate-200 object-cover shadow-sm" />
                <div v-else class="mx-auto flex aspect-[2/3] w-full max-w-56 flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 text-center text-slate-400 dark:border-slate-700 dark:bg-slate-900"><AppIcon name="books" class="h-12 w-12"/><span class="mt-3 text-xs font-bold">Aucune couverture</span></div>
                <dl class="mt-5 space-y-3 border-t border-slate-100 pt-5 text-sm"><div><dt class="text-xs font-bold uppercase text-slate-400">Catégorie</dt><dd class="mt-1 font-semibold text-slate-700">{{ book.category?.name || 'Non classé' }}</dd></div><div><dt class="text-xs font-bold uppercase text-slate-400">ISBN</dt><dd class="mt-1 font-mono text-slate-700">{{ book.isbn || 'Non renseigné' }}</dd></div><div><dt class="text-xs font-bold uppercase text-slate-400">Langue</dt><dd class="mt-1 text-slate-700">{{ book.language || 'Non renseignée' }}</dd></div></dl>
            </aside>

            <div class="space-y-6">
                <section class="dw-card p-6"><h2 class="font-heading text-lg font-bold text-slate-800">Informations bibliographiques</h2><dl class="mt-5 grid gap-5 sm:grid-cols-2"><div class="sm:col-span-2"><dt class="text-xs font-bold uppercase text-slate-400">Titre complet</dt><dd class="mt-1 font-semibold text-slate-700">{{ book.title }}<span v-if="book.subtitle" class="font-normal text-slate-500"> : {{ book.subtitle }}</span></dd></div><div><dt class="text-xs font-bold uppercase text-slate-400">Auteur(s)</dt><dd class="mt-1 text-slate-700">{{ book.authors.map((author:any) => author.display_name).join(', ') || 'Non renseigné' }}</dd></div><div><dt class="text-xs font-bold uppercase text-slate-400">Édition</dt><dd class="mt-1 text-slate-700">{{ book.edition || 'Non renseignée' }}</dd></div><div><dt class="text-xs font-bold uppercase text-slate-400">Maison d’édition</dt><dd class="mt-1 text-slate-700">{{ book.publisher || 'Non renseignée' }}</dd></div><div><dt class="text-xs font-bold uppercase text-slate-400">Année de publication</dt><dd class="mt-1 text-slate-700">{{ book.publication_year || 'Non renseignée' }}</dd></div><div v-if="book.summary" class="sm:col-span-2"><dt class="text-xs font-bold uppercase text-slate-400">Résumé</dt><dd class="mt-2 whitespace-pre-line leading-6 text-slate-600">{{ book.summary }}</dd></div></dl></section>

                <section class="dw-card overflow-hidden"><div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="font-heading font-bold text-slate-800">Exemplaires physiques</h2><p class="mt-1 text-xs text-slate-400">{{ book.copies.length }} exemplaire(s) enregistré(s)</p></div><Link :href="route('copies.index')" class="text-xs font-bold text-primary-600">Voir l’inventaire</Link></div><div class="overflow-x-auto"><table class="w-full min-w-[650px] text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-400"><tr><th class="px-5 py-3 text-start">N° d’enregistrement</th><th class="px-5 py-3 text-start">Emplacement</th><th class="px-5 py-3 text-start">Statut</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="copy in book.copies" :key="copy.id"><td class="px-5 py-3 font-mono font-bold text-primary-700">{{ copy.inventory_number }}</td><td class="px-5 py-3 text-slate-500">{{ copy.location ? `${copy.location.name} · ${copy.location.code}` : 'Non affecté' }}</td><td class="px-5 py-3 text-slate-600">{{ statusLabels[copy.status] || copy.status }}</td></tr><tr v-if="!book.copies.length"><td colspan="3" class="px-5 py-10 text-center text-slate-400">Aucun exemplaire physique.</td></tr></tbody></table></div></section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
