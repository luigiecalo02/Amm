<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{ members: { data: any[] } }>();
</script>

<template>
    <AppLayout>
        <h1 class="font-display text-3xl">Membresías</h1>
        <div class="mt-4 space-y-3">
            <article v-for="member in members.data" :key="member.id" class="panel flex items-center justify-between p-4">
                <div>
                    <p class="font-medium">{{ member.person?.first_name }} {{ member.person?.last_name }}</p>
                    <p class="text-sm text-cream/50">{{ member.membership_number || 'Sin número' }}</p>
                </div>
                <span class="rounded-full bg-ember-500/15 px-3 py-1 text-xs uppercase text-ember-400">{{ member.status }}</span>
            </article>
        </div>
    </AppLayout>
</template>
