<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    plate: string;
    caption?: string | null;
}>();

const label = computed(() => {
    const raw = props.plate.trim().toUpperCase();

    if (raw.includes(' ') || raw.length < 5) {
        return raw;
    }

    const grouped = raw.match(/^([A-Z]{3})([0-9]{2}[0-9A-Z]?)$/);

    return grouped ? `${grouped[1]} ${grouped[2]}` : raw;
});
</script>

<template>
    <div
        class="relative flex w-full flex-col items-center justify-center rounded-md bg-[#f5c400] px-6 pb-4 pt-3 text-black shadow-[0_0_0_3px_#111,inset_0_0_0_3px_#f5c400,inset_0_0_0_5px_#111]"
        :aria-label="caption ? `Placa ${plate}, ${caption}` : `Placa ${plate}`"
    >
        <span class="absolute left-2 top-1.5 h-1.5 w-1.5 rounded-full border border-black bg-[#e2b000]" aria-hidden="true" />
        <span class="absolute right-2 top-1.5 h-1.5 w-1.5 rounded-full border border-black bg-[#e2b000]" aria-hidden="true" />
        <span class="absolute bottom-1.5 left-2 h-1.5 w-1.5 rounded-full border border-black bg-[#e2b000]" aria-hidden="true" />
        <span class="absolute bottom-1.5 right-2 h-1.5 w-1.5 rounded-full border border-black bg-[#e2b000]" aria-hidden="true" />
        <span class="font-display text-3xl font-bold uppercase leading-none tracking-[0.16em]">{{ label }}</span>
        <span v-if="caption" class="mt-1 max-w-full truncate text-center text-[11px] font-bold uppercase tracking-[0.22em]">{{ caption }}</span>
    </div>
</template>
