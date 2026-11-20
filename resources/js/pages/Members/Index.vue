<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { useOrganization } from '@/composables/useOrganization';

defineProps<{ members: { data: any[] } }>();

const { clubPath } = useOrganization();
const statusLabel: Record<string, string> = {
    pending: 'Pendiente',
    active: 'Activo',
    inactive: 'Inactivo',
    suspended: 'Suspendido',
};
</script>

<template>
    <AppLayout>
        <h1 class="font-display text-3xl">Membresías</h1>
        <div class="mt-4 space-y-3">
            <article v-for="member in members.data" :key="member.id" class="panel flex items-center justify-between p-4">
                <div>
                    <Link
                        v-if="member.person?.id"
                        :href="clubPath(`people/${member.person.id}`)"
                        class="font-medium hover:text-ember-300"
                    >
                        {{ member.person?.first_name }} {{ member.person?.last_name }}
                    </Link>
                    <p v-else class="font-medium">{{ member.person?.first_name }} {{ member.person?.last_name }}</p>
                    <p class="text-sm text-cream/50">{{ member.membership_number || 'Sin número' }}</p>
                </div>
                <span class="rounded-full bg-ember-500/15 px-3 py-1 text-xs uppercase text-ember-400">
                    {{ statusLabel[member.status] ?? member.status }}
                </span>
            </article>
        </div>
    </AppLayout>
</template>
