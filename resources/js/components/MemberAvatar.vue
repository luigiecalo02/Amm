<script setup lang="ts">
withDefaults(defineProps<{
    src?: string | null;
    alt?: string;
}>(), {
    alt: 'Foto de perfil',
});
</script>

<template>
    <img
        :src="src || '/images/member-placeholder.svg'"
        :alt="alt"
        class="rounded-full object-cover ring-1 ring-white/15"
    />
</template>
