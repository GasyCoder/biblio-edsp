<script setup lang="ts">
import AppIcon from "@/Components/AppIcon.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
const props = defineProps<{ cards: { data: any[] } }>();
const page = usePage();
const selected = ref<number[]>([]);
const previewCard = ref<any | null>(null);
const canUpdate = page.props.auth.permissions.includes("cards.update");
const canDelete = page.props.auth.permissions.includes("cards.manage");
const canPrint = page.props.auth.permissions.includes("cards.print");
const ids = computed(() => props.cards.data.map((card) => card.id));
const allSelected = computed(
    () =>
        ids.value.length > 0 &&
        ids.value.every((id) => selected.value.includes(id)),
);
const toggleAll = () =>
    (selected.value = allSelected.value ? [] : [...ids.value]);
const remove = (card: any) => {
    if (confirm(`Supprimer la carte ${card.card_number} ?`))
        router.delete(route("cards.destroy", card.id));
};
const removeSelected = () => {
    if (
        !selected.value.length ||
        !confirm(
            `Supprimer les ${selected.value.length} cartes sélectionnées ?`,
        )
    )
        return;
    router.delete(route("cards.destroy.bulk"), {
        data: { ids: selected.value },
        onSuccess: () => (selected.value = []),
    });
};
const printSelected = () => {
    if (selected.value.length === 1) {
        previewCard.value = props.cards.data.find(
            (card) => card.id === selected.value[0],
        );
    } else if (selected.value.length > 1) {
        const query = new URLSearchParams(
            selected.value.map((id) => ["ids[]", String(id)]),
        );
        window.open(
            `${route("cards.print.bulk")}?${query}`,
            "_blank",
            "noopener",
        );
    }
};
const previewUrl = computed(() =>
    previewCard.value
        ? route("cards.print", { card: previewCard.value.id, embedded: 1 })
        : "",
);
const printPreview = () =>
    document
        .querySelector<HTMLIFrameElement>("#library-card-preview")
        ?.contentWindow?.print();
const statusLabels: Record<string, string> = {
    active: "Active",
    suspended: "Suspendue",
    expired: "Expirée",
    replaced: "Remplacée",
};
</script>
<template>
    <Head title="Cartes de bibliothèque" /><AuthenticatedLayout
        ><template #header
            ><div class="flex items-end justify-between">
                <div>
                    <p
                        class="text-xs font-bold uppercase tracking-widest text-primary-600"
                    >
                        Identification bibliothèque
                    </p>
                    <h1
                        class="mt-1 font-heading text-2xl font-bold text-slate-800"
                    >
                        Cartes de bibliothèque
                    </h1>
                    <p class="mt-2 text-sm text-slate-500">
                        Sélectionnez plusieurs cartes pour effectuer une
                        suppression groupée.
                    </p>
                </div>
                <Link
                    :href="route('cards.create')"
                    class="rounded-md bg-primary-600 px-4 py-2.5 text-sm font-bold text-white"
                    >Créer une carte</Link
                >
            </div></template
        >
        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-md bg-emerald-50 p-3 text-sm text-emerald-700"
        >
            {{ $page.props.flash.success }}
        </div>
        <div
            v-if="selected.length"
            class="mb-4 flex items-center gap-3 rounded-lg border border-primary-200 bg-primary-50 p-3 dark:border-primary-800 dark:bg-primary-950/30"
        >
            <span class="text-sm font-bold text-primary-700"
                >{{ selected.length }} sélectionnée(s)</span
            ><button
                v-if="canPrint"
                class="inline-flex h-9 items-center gap-2 rounded-md bg-primary-600 px-3 text-xs font-bold text-white"
                @click="printSelected"
            >
                <AppIcon name="print" class="h-4 w-4" /> Imprimer</button
            ><button
                v-if="canDelete"
                class="inline-flex h-9 items-center gap-2 rounded-md bg-red-600 px-3 text-xs font-bold text-white"
                @click="removeSelected"
            >
                <AppIcon name="trash" class="h-4 w-4" /> Supprimer</button
            ><button
                class="ms-auto text-xs font-bold text-slate-500"
                @click="selected = []"
            >
                Annuler
            </button>
        </div>
        <section class="dw-card overflow-x-auto">
            <table class="w-full min-w-[950px] text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-400">
                    <tr>
                        <th class="w-12 p-4 text-center">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                class="h-4 w-4 rounded text-primary-600"
                                aria-label="Tout sélectionner"
                                @change="toggleAll"
                            />
                        </th>
                        <th class="p-4 text-start">Titulaire</th>
                        <th class="p-4 text-start">N° carte bibliothèque</th>
                        <th class="p-4 text-start">Statut</th>
                        <th class="p-4 text-start">Expiration</th>
                        <th class="p-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr
                        v-for="card in cards.data"
                        :key="card.id"
                        :class="
                            selected.includes(card.id)
                                ? 'bg-primary-50/60 dark:bg-primary-950/20'
                                : ''
                        "
                    >
                        <td class="p-4 text-center">
                            <input
                                v-model="selected"
                                type="checkbox"
                                :value="card.id"
                                class="h-4 w-4 rounded text-primary-600"
                                :aria-label="`Sélectionner ${card.card_number}`"
                            />
                        </td>
                        <td class="p-4">
                            <div class="flex items-center gap-3">
                                <img
                                    v-if="card.student.photo_url"
                                    :src="card.student.photo_url"
                                    class="h-10 w-9 rounded object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-10 w-9 items-center justify-center rounded bg-slate-100"
                                >
                                    <AppIcon
                                        name="user"
                                        class="h-4 w-4 text-slate-400"
                                    />
                                </div>
                                <span class="font-semibold"
                                    >{{ card.student.last_name }}
                                    {{ card.student.first_name }}</span
                                >
                            </div>
                        </td>
                        <td
                            class="p-4 font-mono text-xs font-bold text-primary-700"
                        >
                            {{ card.card_number }}
                        </td>
                        <td class="p-4">
                            {{ statusLabels[card.status] || card.status }}
                        </td>
                        <td class="p-4 text-slate-500">
                            {{ card.expires_at?.slice(0, 10) || "Sans limite" }}
                        </td>
                        <td class="p-4">
                            <div class="flex justify-center gap-1">
                                <button
                                    v-if="canPrint"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md text-primary-600 hover:bg-primary-50"
                                    title="Imprimer"
                                    aria-label="Imprimer"
                                    @click="previewCard = card"
                                >
                                    <AppIcon
                                        name="print"
                                        class="h-4 w-4"
                                    /></button
                                ><Link
                                    v-if="canUpdate"
                                    :href="route('cards.edit', card.id)"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md text-amber-600 hover:bg-amber-50"
                                    title="Modifier"
                                    aria-label="Modifier"
                                    ><AppIcon
                                        name="edit"
                                        class="h-4 w-4" /></Link
                                ><button
                                    v-if="canDelete"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md text-red-600 hover:bg-red-50"
                                    title="Supprimer"
                                    aria-label="Supprimer"
                                    @click="remove(card)"
                                >
                                    <AppIcon name="trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!cards.data.length">
                        <td colspan="7" class="p-12 text-center text-slate-400">
                            Aucune carte de bibliothèque créée.
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
        <Teleport to="body"
            ><div
                v-if="previewCard"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/70 p-4"
                role="dialog"
                aria-modal="true"
                @click.self="previewCard = null"
            >
                <div
                    class="w-full max-w-2xl overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-slate-900"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-700"
                    >
                        <div>
                            <h2
                                class="font-heading font-bold text-slate-800 dark:text-white"
                            >
                                Carte de bibliothèque
                            </h2>
                            <p class="mt-1 font-mono text-xs text-primary-600">
                                {{ previewCard.card_number }}
                            </p>
                        </div>
                        <button
                            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-500"
                            aria-label="Fermer"
                            @click="previewCard = null"
                        >
                            <AppIcon name="close" class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="bg-slate-100 p-4 dark:bg-slate-950">
                        <iframe
                            id="library-card-preview"
                            :src="previewUrl"
                            class="h-[310px] w-full rounded-md bg-white"
                            title="Aperçu de la carte de bibliothèque"
                        ></iframe>
                    </div>
                    <div
                        class="flex justify-end gap-3 border-t border-slate-200 p-4 dark:border-slate-700"
                    >
                        <button
                            class="rounded-md border px-4 py-2 text-sm font-bold"
                            @click="previewCard = null"
                        >
                            Fermer</button
                        ><button
                            class="inline-flex items-center gap-2 rounded-md bg-primary-600 px-4 py-2 text-sm font-bold text-white"
                            @click="printPreview"
                        >
                            <AppIcon name="print" class="h-4 w-4" /> Imprimer
                        </button>
                    </div>
                </div>
            </div></Teleport
        ></AuthenticatedLayout
    >
</template>
