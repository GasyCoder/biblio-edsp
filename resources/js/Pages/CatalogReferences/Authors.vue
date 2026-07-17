<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import InputError from "@/Components/InputError.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
const props = defineProps<{
    authors: { data: any[]; links: any[]; total: number };
    filters: { search: string };
}>();
const page = usePage();
const canCreate = page.props.auth.permissions.includes("authors.create");
const canUpdate = page.props.auth.permissions.includes("authors.update");
const canDelete = page.props.auth.permissions.includes("catalog.manage");
const canBrowseBooks = page.props.auth.permissions.some((permission) =>
    ["books.view", "catalog.view"].includes(permission),
);
const form = useForm({ display_name: "" });
const editing = ref<any | null>(null);
const selected = ref<number[]>([]);
const search = ref(props.filters.search);
const allSelected = computed(
    () =>
        props.authors.data.length > 0 &&
        props.authors.data.every((item) => selected.value.includes(item.id)),
);
const toggleAll = () =>
    (selected.value = allSelected.value
        ? []
        : props.authors.data.map((item) => item.id));
const create = () =>
    form.post(route("authors.store"), { onSuccess: () => form.reset() });
const save = () =>
    editing.value &&
    router.patch(
        route("authors.update", editing.value.id),
        { display_name: editing.value.display_name },
        { onSuccess: () => (editing.value = null) },
    );
const remove = (item: any) =>
    confirm(`Supprimer l’auteur « ${item.display_name} » ?`) &&
    router.delete(route("authors.destroy", item.id));
const removeSelected = () =>
    selected.value.length &&
    confirm(`Supprimer ${selected.value.length} auteur(s) ?`) &&
    router.delete(route("authors.destroy.bulk"), {
        data: { ids: selected.value },
        onSuccess: () => (selected.value = []),
    });
const find = () =>
    router.get(
        route("authors.index"),
        { search: search.value },
        { preserveState: true, replace: true },
    );
</script>
<template>
    <Head title="Auteurs" /><AuthenticatedLayout
        ><template #header
            ><div>
                <Link
                    :href="route('catalog-references.index')"
                    class="text-xs font-bold text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                    >← Référentiels</Link
                >
                <h1 class="dw-page-title">Auteurs</h1>
                <p class="dw-page-description">
                    Noms d’auteurs utilisés dans les notices bibliographiques.
                </p>
            </div></template
        >
        <div
            v-if="$page.props.flash?.success"
            class="mb-5 rounded-md bg-emerald-50 p-4 text-sm text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
        >
            {{ $page.props.flash.success }}
        </div>
        <InputError class="mb-4" :message="$page.props.errors?.author" />
        <div class="grid gap-6 xl:grid-cols-[360px_1fr]">
            <section v-if="canCreate" class="dw-card self-start p-5">
                <h2 class="text-lg font-bold">Nouvel auteur</h2>
                <form class="mt-5 space-y-4" @submit.prevent="create">
                    <div>
                        <label class="mb-2 block text-sm">Nom complet *</label
                        ><input
                            v-model="form.display_name"
                            class="dw-field"
                            placeholder="Ex. Jean Touchard"
                            required
                        /><InputError :message="form.errors.display_name" />
                    </div>
                    <button class="dw-btn-primary w-full">
                        Ajouter l’auteur
                    </button>
                </form>
            </section>
            <section class="dw-card overflow-hidden">
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-5 dark:border-gray-900"
                >
                    <div>
                        <h2 class="text-lg font-bold">Liste des auteurs</h2>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ authors.total }} résultat(s)
                        </p>
                    </div>
                    <button
                        v-if="selected.length"
                        class="dw-btn-secondary text-red-600"
                        @click="removeSelected"
                    >
                        <AppIcon name="trash" class="h-4 w-4" /> Supprimer ({{
                            selected.length
                        }})
                    </button>
                </div>
                <form
                    class="flex gap-2 border-b border-gray-200 p-4 dark:border-gray-900"
                    @submit.prevent="find"
                >
                    <div class="relative flex-1">
                        <AppIcon
                            name="search"
                            class="absolute start-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500 dark:text-slate-400"
                        /><input
                            v-model="search"
                            class="dw-field ps-10"
                            placeholder="Rechercher un auteur…"
                        />
                    </div>
                    <button class="dw-btn-primary">Rechercher</button>
                </form>
                <div class="overflow-x-auto">
                    <table class="dw-table min-w-[650px]">
                        <thead>
                            <tr>
                                <th v-if="canDelete" class="w-12 p-4">
                                    <input
                                        type="checkbox"
                                        :checked="allSelected"
                                        @change="toggleAll"
                                    />
                                </th>
                                <th class="p-4 text-start">Nom d’auteur</th>
                                <th class="p-4 text-center">Ouvrages</th>
                                <th class="p-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in authors.data" :key="item.id">
                                <td v-if="canDelete" class="p-4 text-center">
                                    <input
                                        v-model="selected"
                                        type="checkbox"
                                        :value="item.id"
                                    />
                                </td>
                                <td class="p-4">
                                    <input
                                        v-if="editing?.id === item.id"
                                        v-model="editing.display_name"
                                        class="dw-field h-9"
                                    /><Link
                                        v-if="
                                            canBrowseBooks &&
                                            editing?.id !== item.id
                                        "
                                        :href="
                                            route('books.index', {
                                                author: item.id,
                                            })
                                        "
                                        :title="`Voir les ouvrages de ${item.display_name}`"
                                        class="font-bold text-slate-700 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                                        >{{ item.display_name }}</Link
                                    ><strong
                                        v-else-if="editing?.id !== item.id"
                                        class="text-slate-700 dark:text-white"
                                        >{{ item.display_name }}</strong
                                    >
                                </td>
                                <td class="p-4 text-center">
                                    <Link
                                        v-if="canBrowseBooks"
                                        :href="
                                            route('books.index', {
                                                author: item.id,
                                            })
                                        "
                                        :aria-label="`Voir les ${item.books_count} ouvrages de ${item.display_name}`"
                                        class="inline-flex min-w-9 items-center justify-center rounded-full bg-primary-50 px-2.5 py-1 text-xs font-bold text-primary-700 transition hover:bg-primary-100 dark:bg-primary-950 dark:text-primary-300 dark:hover:bg-primary-900"
                                        >{{ item.books_count }}</Link
                                    >
                                    <span v-else>{{ item.books_count }}</span>
                                </td>
                                <td class="p-4">
                                    <div class="flex justify-center gap-1">
                                        <template v-if="editing?.id === item.id"
                                            ><button
                                                class="h-9 w-9 text-emerald-600"
                                                @click="save"
                                            >
                                                <AppIcon
                                                    name="check"
                                                    class="mx-auto h-4 w-4"
                                                /></button
                                            ><button
                                                class="h-9 w-9 text-slate-500 dark:text-slate-400"
                                                @click="editing = null"
                                            >
                                                <AppIcon
                                                    name="close"
                                                    class="mx-auto h-4 w-4"
                                                /></button></template
                                        ><template v-else
                                            ><button
                                                v-if="canUpdate"
                                                class="h-9 w-9 text-amber-600"
                                                @click="editing = { ...item }"
                                            >
                                                <AppIcon
                                                    name="edit"
                                                    class="mx-auto h-4 w-4"
                                                /></button
                                            ><button
                                                v-if="canDelete"
                                                class="h-9 w-9 text-red-600"
                                                @click="remove(item)"
                                            >
                                                <AppIcon
                                                    name="trash"
                                                    class="mx-auto h-4 w-4"
                                                /></button
                                        ></template>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!authors.data.length">
                                <td
                                    :colspan="canDelete ? 4 : 3"
                                    class="p-10 text-center text-slate-500 dark:text-slate-400"
                                >
                                    Aucun auteur trouvé.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-if="authors.links.length > 3"
                    class="flex flex-wrap justify-center gap-1 border-t border-gray-200 p-4 dark:border-gray-900"
                >
                    <Link
                        v-for="link in authors.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        v-html="link.label"
                        class="rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
                        :class="
                            link.active
                                ? 'bg-primary-600 text-white shadow-sm'
                                : link.url
                                  ? 'text-slate-600 hover:bg-gray-100 hover:text-primary-600 dark:text-slate-300 dark:hover:bg-gray-900 dark:hover:text-primary-400'
                                  : 'pointer-events-none text-slate-400 dark:text-slate-600'
                        "
                    />
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
