<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import NotificationBell from '@/components/NotificationBell.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useOrganization } from '@/composables/useOrganization';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const { can } = usePermissions();
const { organization, slug } = useOrganization();
const flash = computed(() => page.props.flash);

const items = computed(() => {
    if (!slug.value) return [];

    return [
        { href: `/app/${slug.value}`, label: 'Club', show: true },
        { href: `/app/${slug.value}/perfil`, label: 'Mi perfil', show: true },
        { href: `/app/${slug.value}/mis-documentos`, label: 'Mis documentos', show: true },
        { href: `/app/${slug.value}/people`, label: 'Compañeros de ruta', show: can('people.view') },
        { href: `/app/${slug.value}/members`, label: 'Miembros', show: can('members.view') },
        { href: `/app/${slug.value}/motorcycles`, label: 'Motos', show: can('motorcycles.view') },
        { href: `/app/${slug.value}/documents`, label: 'Documentos', show: can('documents.view') },
        { href: `/app/${slug.value}/documents/expiring`, label: 'Vencimientos', show: can('reports.view') },
        { href: `/app/${slug.value}/inscripciones`, label: 'Inscripciones', show: can('inscriptions.view') },
        { href: `/app/${slug.value}/users`, label: 'Usuarios', show: can('users.view') },
        { href: `/app/${slug.value}/roles`, label: 'Roles', show: can('roles.view') },
        { href: `/app/${slug.value}/settings`, label: 'Ajustes', show: can('organization.view'), key: 'settings' },
    ].filter((item) => item.show);
});

const menuOpen = ref(false);
const mobileShortcuts = computed(() => {
    const visible = items.value;

    if (visible.length <= 4) {
        return visible;
    }

    const companions = visible.find((item) => item.href.endsWith('/people'));
    const club = visible.find((item) => item.href === `/app/${slug.value}`);
    const profile = visible.find((item) => item.href.endsWith('/perfil'));
    const documents = visible.find((item) => item.href.endsWith('/mis-documentos'));

    return [profile, documents, companions ?? club].filter((item) => item !== undefined);
});
const showMenuButton = computed(() => items.value.length > 3);
const mobileColumns = computed(() => {
    const count = showMenuButton.value ? 4 : Math.min(Math.max(items.value.length, 1), 4);

    return ['grid-cols-1', 'grid-cols-2', 'grid-cols-3', 'grid-cols-4'][count - 1];
});

const isCurrent = (href: string) => {
    const clubHome = `/app/${slug.value}`;

    if (href === clubHome) {
        return page.url === href || page.url === `${href}/`;
    }

    return page.url === href || page.url.startsWith(`${href}/`);
};

watch(() => page.url, () => {
    menuOpen.value = false;
});
</script>

<template>
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,_#1A2332,_#0B0F14_55%)]">
        <header class="app-header sticky top-0 z-20 border-b border-white/10 bg-ink-900/80 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                <div>
                    <p class="font-display text-xs uppercase tracking-[0.25em] text-ember-400">
                        {{ page.props.current_role?.name ?? 'AMM' }}
                    </p>
                    <h1 class="font-display text-lg leading-none">{{ organization?.name ?? 'Plataforma' }}</h1>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <NotificationBell v-if="page.props.auth.user" />
                    <Link v-if="page.props.can_switch_clubs" href="/organizations" class="text-cream/60 hover:text-cream">Clubes</Link>
                    <form method="post" action="/logout">
                        <input type="hidden" name="_token" :value="page.props.csrf_token || ''" />
                    </form>
                    <Link href="/logout" method="post" as="button" class="text-cream/60 hover:text-cream">Salir</Link>
                </div>
            </div>
        </header>

        <div class="mx-auto flex max-w-6xl gap-6 px-4 py-6 pb-24 md:pb-8">
            <aside class="hidden w-56 shrink-0 md:block">
                <nav class="panel sticky top-24 space-y-1 p-3">
                    <Link
                        v-for="item in items"
                        :key="item.key || item.href"
                        :href="item.href"
                        class="block rounded-xl px-3 py-2 text-sm text-cream/70 hover:bg-white/5 hover:text-cream"
                        :class="{ 'bg-ember-500/15 text-ember-400': isCurrent(item.href) }"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </aside>

            <main class="min-w-0 flex-1 space-y-4">
                <div v-if="flash?.success" class="rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                    {{ flash.success }}
                </div>
                <div v-if="flash?.error" class="rounded-xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                    {{ flash.error }}
                </div>
                <slot />
            </main>
        </div>

        <nav
            class="fixed inset-x-0 bottom-0 z-20 grid items-stretch gap-1 border-t border-white/10 bg-ink-900/95 p-2 md:hidden"
            :class="mobileColumns"
        >
            <button
                v-if="showMenuButton"
                type="button"
                class="flex h-11 items-center justify-center rounded-xl px-1 text-center text-[11px] leading-tight text-cream/70"
                :class="{ 'bg-ember-500/15 text-ember-300': menuOpen }"
                @click="menuOpen = !menuOpen"
            >
                Menú
            </button>
            <Link
                v-for="item in mobileShortcuts"
                :key="item.href"
                :href="item.href"
                class="flex h-11 items-center justify-center rounded-xl px-1 text-center text-[11px] leading-tight text-cream/70"
                :class="{ 'bg-ember-500/15 text-ember-300': isCurrent(item.href) }"
            >
                {{ item.label }}
            </Link>
        </nav>

        <div v-if="menuOpen" class="fixed inset-0 z-30 md:hidden">
            <button type="button" class="absolute inset-0 bg-black/70" aria-label="Cerrar menú" @click="menuOpen = false" />
            <nav class="absolute inset-x-0 bottom-0 max-h-[80dvh] overflow-y-auto rounded-t-3xl border border-white/10 bg-ink-900 p-4 pb-8">
                <div class="mb-3 flex items-center justify-between">
                    <p class="font-display text-lg">Menú</p>
                    <button type="button" class="btn-ghost" @click="menuOpen = false">Cerrar</button>
                </div>
                <div v-for="item in items" :key="`mobile-${item.key || item.href}`" @click="menuOpen = false">
                    <Link
                        :href="item.href"
                        class="block rounded-xl px-3 py-3 text-sm text-cream/80 hover:bg-white/5"
                        :class="{ 'bg-ember-500/15 text-ember-300': isCurrent(item.href) }"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </nav>
        </div>
    </div>
</template>
