<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const sidebarOpen = ref(false);
const userMenuOpen = ref(false);

const roleLabel = computed(() => {
    const role = page.props.auth.roles[0];
    return role === 'superadmin' ? 'Super administrateur' : role === 'secretaire' ? 'Secrétaire' : role === 'etudiant' ? 'Étudiant' : 'Utilisateur';
});

const initials = computed(() => page.props.auth.user.name.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase());

const can = (permission?: string) => !permission || page.props.auth.permissions.includes(permission);

const menuGroups = computed(() => [
    { label: 'Vue générale', items: [{ label: 'Tableau de bord', icon: 'dashboard', href: route('dashboard'), active: route().current('dashboard') }] },
    { label: 'Opérations', items: [
        { label: 'Comptoir · Scanner', icon: 'scan', href: route('desk.index'), permission: 'cards.scan', active: route().current('desk.*') },
        { label: 'Cartes étudiantes', icon: 'scan', href: route('cards.index'), permission: 'cards.view', active: route().current('cards.*') },
        { label: 'Présences', icon: 'visits', href: null, permission: page.props.auth.roles.includes('etudiant') ? 'visits.view_own' : 'visits.view' },
        { label: 'Prêts et retours', icon: 'loans', href: null, permission: page.props.auth.roles.includes('etudiant') ? 'loans.view_own' : 'loans.view' },
    ] },
    { label: 'Bibliothèque', items: [
        { label: 'Étudiants', icon: 'students', href: route('students.index'), permission: 'students.view', active: route().current('students.*') },
        { label: page.props.auth.roles.includes('etudiant') ? 'Catalogue' : 'Ouvrages', icon: 'books', href: route('books.index'), permission: page.props.auth.roles.includes('etudiant') ? 'catalog.view' : 'books.view', active: route().current('books.*') },
        { label: 'Exemplaires', icon: 'copies', href: route('copies.index'), permission: 'copies.view', active: route().current('copies.*') },
        { label: 'Référentiels', icon: 'settings', href: route('catalog-references.index'), permission: 'categories.view', active: route().current('catalog-references.*') },
        { label: page.props.auth.roles.includes('etudiant') ? 'Mon historique' : 'Rapports', icon: 'reports', href: null, permission: page.props.auth.roles.includes('etudiant') ? 'consultations.view_own' : 'reports.operational' },
    ] },
    { label: 'Administration', items: [
        { label: 'Utilisateurs', icon: 'users', href: null, permission: 'users.manage' },
        { label: 'Paramètres', icon: 'settings', href: null, permission: 'settings.manage' },
    ] },
].map((group) => ({ ...group, items: group.items.filter((item) => can('permission' in item ? item.permission : undefined)) })).filter((group) => group.items.length > 0));
</script>

<template>
    <div class="min-h-screen bg-slate-50 transition-colors dark:bg-slate-950">
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-sm xl:hidden" @click="sidebarOpen = false"></div>

        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 start-0 z-50 flex w-72 flex-col border-e border-slate-200 bg-white transition duration-300 dark:border-slate-800 dark:bg-slate-900 xl:translate-x-0">
            <div class="flex h-16 items-center border-b border-slate-200 px-6">
                <Link :href="route('dashboard')" class="flex items-center gap-3 rounded-md">
                    <ApplicationLogo class="h-9 w-9 text-primary-600" />
                    <div><p class="font-heading text-base font-bold leading-tight text-slate-800">Bibliothèque EDSP</p><p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Administration</p></div>
                </Link>
                <button class="ms-auto rounded-md p-2 text-slate-400 hover:bg-slate-100 xl:hidden" aria-label="Fermer le menu" @click="sidebarOpen = false"><span class="text-xl">×</span></button>
            </div>

            <nav class="flex-1 overflow-y-auto px-4 py-5">
                <div v-for="group in menuGroups" :key="group.label" class="mb-6">
                    <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">{{ group.label }}</p>
                    <ul class="space-y-1">
                        <li v-for="item in group.items" :key="item.label">
                            <Link v-if="item.href" :href="item.href" :class="item.active ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-50 hover:text-primary-600'" class="flex h-11 items-center gap-3 rounded-md px-3 text-sm font-semibold transition" @click="sidebarOpen = false">
                                <AppIcon :name="item.icon" class="h-5 w-5 shrink-0"/><span>{{ item.label }}</span>
                            </Link>
                            <div v-else class="flex h-11 cursor-not-allowed items-center gap-3 rounded-md px-3 text-sm font-medium text-slate-400" :title="`${item.label} sera disponible dans la prochaine phase`">
                                <AppIcon :name="item.icon" class="h-5 w-5 shrink-0"/><span>{{ item.label }}</span><span class="ms-auto rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold uppercase text-slate-400">Bientôt</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="border-t border-slate-200 p-4">
                <div class="rounded-md bg-slate-50 p-3"><p class="text-xs font-semibold text-slate-600">Besoin d’aide ?</p><p class="mt-1 text-[11px] leading-4 text-slate-400">Contactez le super administrateur de la bibliothèque.</p></div>
            </div>
        </aside>

        <div class="min-h-screen transition-all duration-300 xl:ps-72">
            <header class="fixed inset-x-0 top-0 z-30 h-16 border-b border-slate-200 bg-white/95 backdrop-blur xl:start-72">
                <div class="flex h-full items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <button class="rounded-md p-2 text-slate-500 hover:bg-slate-100 xl:hidden" aria-label="Ouvrir le menu" @click="sidebarOpen = true"><AppIcon name="menu" class="h-6 w-6"/></button>
                    <div class="hidden max-w-md flex-1 items-center sm:flex">
                        <AppIcon name="search" class="h-5 w-5 text-slate-400"/><span class="ms-3 text-sm text-slate-400">Recherche rapide dans la bibliothèque…</span><kbd class="ms-auto rounded border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] text-slate-400">Ctrl K</kbd>
                    </div>
                    <div class="ms-auto flex items-center gap-2">
                        <ThemeToggle />
                        <button class="relative rounded-full p-2.5 text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" aria-label="Notifications"><AppIcon name="bell" class="h-5 w-5"/><span class="absolute end-2 top-2 h-2 w-2 rounded-full border-2 border-white bg-primary-500 dark:border-slate-900"></span></button>
                        <div class="relative">
                            <button class="flex items-center gap-3 rounded-md p-1.5 hover:bg-slate-50" @click="userMenuOpen = !userMenuOpen">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700">{{ initials }}</span>
                                <span class="hidden text-start md:block"><span class="block max-w-36 truncate text-sm font-semibold text-slate-700">{{ $page.props.auth.user.name }}</span><span class="block text-[11px] text-slate-400">{{ roleLabel }}</span></span>
                                <AppIcon name="chevron-down" class="hidden h-4 w-4 text-slate-400 md:block"/>
                            </button>
                            <div v-if="userMenuOpen" class="absolute end-0 mt-2 w-56 overflow-hidden rounded-md border border-slate-200 bg-white py-2 shadow-lg">
                                <div class="border-b border-slate-100 px-4 py-2 md:hidden"><p class="truncate text-sm font-semibold text-slate-700">{{ $page.props.auth.user.name }}</p><p class="text-xs text-slate-400">{{ roleLabel }}</p></div>
                                <Link :href="route('profile.edit')" class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary-600"><AppIcon name="user" class="h-4 w-4"/>Mon profil</Link>
                                <Link :href="route('logout')" method="post" as="button" class="flex w-full items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50"><AppIcon name="logout" class="h-4 w-4"/>Se déconnecter</Link>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="px-4 pb-10 pt-24 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-[1500px]">
                    <div v-if="$slots.header" class="mb-7"><slot name="header" /></div>
                    <slot />
                </div>
            </main>
            <footer class="border-t border-slate-200 bg-white px-6 py-4 text-center text-xs text-slate-400">© {{ new Date().getFullYear() }} EDSP — Université de Mahajanga</footer>
        </div>
    </div>
</template>
