<script setup lang="ts">
defineProps<{
    label: string;
    span?: boolean;
}>();
</script>

<template>
    <label class="block text-sm" :class="{ 'md:col-span-2': span }">
        <span class="mb-1 block text-cream/70">{{ label }}</span>
        <slot />
    </label>
</template>
