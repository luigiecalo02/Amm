<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{ documents: any[] }>();
</script>

<template>
    <AppLayout>
        <h1 class="font-display text-3xl">Vencimientos</h1>
        <p class="mt-1 text-sm text-cream/60">Documentos que vencen en los próximos 45 días.</p>
        <div class="mt-4 space-y-3">
            <article v-for="doc in documents" :key="doc.id" class="panel p-4">
                <p class="font-medium">{{ doc.type?.name }}</p>
                <p class="text-sm text-cream/60">{{ doc.person?.first_name }} {{ doc.person?.last_name }} {{ doc.motorcycle?.plate }}</p>
                <p class="mt-2 text-ember-400">Vence {{ doc.expiration_date }}</p>
            </article>
            <p v-if="!documents.length" class="text-cream/50">No hay vencimientos próximos.</p>
        </div>
    </AppLayout>
</template>
