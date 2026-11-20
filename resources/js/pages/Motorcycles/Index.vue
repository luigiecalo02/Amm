<script setup lang="ts">
import LicensePlateCard from '@/components/LicensePlateCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    motorcycles: { data: any[] };
}>();
</script>

<template>
    <AppLayout>
        <h1 class="font-display text-3xl">Motocicletas</h1>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <article v-for="moto in motorcycles.data" :key="moto.id">
                <LicensePlateCard
                    :plate="moto.plate"
                    :caption="[moto.person?.first_name, moto.person?.last_name].filter(Boolean).join(' ')"
                    :riders="moto.riders"
                />
            </article>
        </div>
    </AppLayout>
</template>
