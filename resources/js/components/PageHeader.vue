<script setup lang="ts">
defineProps<{
    kicker?: string;
    title: string;
    description?: string;
}>();
</script>

<template>
    <header class="mb-4">
        <p v-if="kicker" class="text-xs uppercase tracking-[0.25em] text-ember-400">{{ kicker }}</p>
        <h1 class="font-display text-3xl">{{ title }}</h1>
        <p v-if="description" class="mt-1 text-sm text-cream/60">{{ description }}</p>
    </header>
</template>
