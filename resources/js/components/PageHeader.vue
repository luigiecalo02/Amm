<script setup lang="ts">
defineProps<{
    kicker?: string;
    title: string;
    description?: string;
}>();
</script>

<template>
    <header class="mb-4 flex items-center gap-4">
        <div v-if="$slots.leading" class="shrink-0">
            <slot name="leading" />
        </div>
        <div class="min-w-0 flex-1">
            <p v-if="kicker" class="text-xs uppercase tracking-[0.25em] text-ember-400">{{ kicker }}</p>
            <h1 class="font-display text-3xl">{{ title }}</h1>
            <p v-if="description" class="mt-1 text-sm text-cream/60">{{ description }}</p>
        </div>
    </header>
</template>
