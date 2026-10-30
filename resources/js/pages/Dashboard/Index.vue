<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { useOrganization } from '@/composables/useOrganization';
import { usePermissions } from '@/composables/usePermissions';

defineProps<{
    stats: { members: number; people: number; motorcycles: number; expiring: number };
    expiringDocuments: any[];
    recentMembers: any[];
}>();

const { clubPath } = useOrganization();
const { can } = usePermissions();
</script>

<template>
    <AppLayout>
        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="item in [
                { label: 'Miembros activos', value: stats.members },
                { label: 'Personas', value: stats.people },
                { label: 'Motocicletas', value: stats.motorcycles },
                { label: 'Por vencer', value: stats.expiring },
            ]" :key="item.label" class="panel p-5">
                <p class="text-xs uppercase tracking-widest text-cream/50">{{ item.label }}</p>
                <p class="mt-2 font-display text-4xl text-ember-400">{{ item.value }}</p>
            </article>
        </section>

        <section class="mt-6 grid gap-4 lg:grid-cols-2">
            <div class="panel p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-xl">Vencimientos</h2>
                    <Link v-if="can('reports.view')" :href="clubPath('documents/expiring')" class="text-sm text-ember-400">Ver todos</Link>
                </div>
                <div v-if="!expiringDocuments.length" class="text-sm text-cream/50">No hay documentos por vencer en 30 días.</div>
                <ul class="space-y-3">
                    <li v-for="doc in expiringDocuments" :key="doc.id" class="flex items-center justify-between rounded-xl bg-ink-900/70 px-3 py-2">
                        <div>
                            <p class="font-medium">{{ doc.type?.name }}</p>
                            <p class="text-xs text-cream/50">{{ doc.person?.first_name }} {{ doc.person?.last_name }} {{ doc.motorcycle?.plate }}</p>
                        </div>
                        <span class="text-sm text-ember-400">{{ doc.expiration_date }}</span>
                    </li>
                </ul>
            </div>
            <div class="panel p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-xl">Últimos miembros</h2>
                    <Link v-if="can('people.create')" :href="clubPath('people/create')" class="btn-primary">Inscribir</Link>
                </div>
                <ul class="space-y-3">
                    <li v-for="member in recentMembers" :key="member.id" class="rounded-xl bg-ink-900/70 px-3 py-2">
                        <p class="font-medium">{{ member.person?.first_name }} {{ member.person?.last_name }}</p>
                        <p class="text-xs text-cream/50">{{ member.membership_number || 'Sin número' }}</p>
                    </li>
                </ul>
            </div>
        </section>
    </AppLayout>
</template>
