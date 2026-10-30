<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
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
        { href: `/app/${slug.value}/people`, label: 'Personas', show: can('people.view') },
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
</script>

<template>
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,_#1A2332,_#0B0F14_55%)]">
        <header class="sticky top-0 z-20 border-b border-white/10 bg-ink-900/80 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                <div>
                    <p class="font-display text-xs uppercase tracking-[0.25em] text-ember-400">
                        {{ page.props.current_role?.name ?? 'AMM' }}
                    </p>
                    <h1 class="font-display text-lg leading-none">{{ organization?.name ?? 'Plataforma' }}</h1>
                </div>
                <div class="flex items-center gap-3 text-sm">
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
                        :class="{ 'bg-ember-500/15 text-ember-400': page.url === item.href || page.url.startsWith(item.href + '/') && item.href !== `/app/${organization?.slug}` }"
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

        <nav class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-4 gap-1 border-t border-white/10 bg-ink-900/95 p-2 md:hidden">
            <Link
                v-for="item in items.slice(0, 4)"
                :key="item.href"
                :href="item.href"
                class="rounded-xl px-2 py-2 text-center text-[11px] text-cream/70"
            >
                {{ item.label }}
            </Link>
        </nav>
    </div>
</template>
