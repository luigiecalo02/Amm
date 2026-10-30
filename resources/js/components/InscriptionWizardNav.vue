<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
    token: string;
    current: 'basics' | 'documents' | 'form';
    basicsDone?: boolean;
}>();

const items = computed(() => [
    {
        key: 'basics' as const,
        step: 1,
        label: 'Datos básicos',
        href: `/i/${props.token}/datos`,
        enabled: true,
    },
    {
        key: 'documents' as const,
        step: 2,
        label: 'Documentos',
        href: `/i/${props.token}`,
        enabled: Boolean(props.basicsDone),
        optional: true,
    },
    {
        key: 'form' as const,
        step: 3,
        label: 'Formulario',
        href: `/i/${props.token}/formulario`,
        enabled: Boolean(props.basicsDone),
    },
]);

const tone = (key: typeof props.current, enabled: boolean) => {
    if (key === props.current) {
        return 'border-ember-400 bg-ember-500/15 text-ember-200';
    }
    if (enabled) {
        return 'border-white/15 bg-white/5 text-cream/80 hover:border-white/30';
    }
    return 'cursor-not-allowed border-white/5 bg-white/[0.03] text-cream/30';
};
</script>

<template>
    <nav class="mb-6" aria-label="Pasos de inscripción">
        <ol class="grid gap-2 sm:grid-cols-3">
            <li v-for="item in items" :key="item.key">
                <Link
                    v-if="item.enabled"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-2xl border px-3 py-3 transition"
                    :class="tone(item.key, true)"
                    :aria-current="item.key === current ? 'step' : undefined"
                >
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-current text-sm font-semibold">
                        {{ item.step }}
                    </span>
                    <span>
                        <span class="block text-sm font-medium">{{ item.label }}</span>
                        <span v-if="item.optional" class="block text-[11px] text-cream/45">Opcional</span>
                    </span>
                </Link>
                <div
                    v-else
                    class="flex items-center gap-3 rounded-2xl border px-3 py-3"
                    :class="tone(item.key, false)"
                >
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-current text-sm font-semibold">
                        {{ item.step }}
                    </span>
                    <span>
                        <span class="block text-sm font-medium">{{ item.label }}</span>
                        <span v-if="item.optional" class="block text-[11px] text-cream/30">Opcional</span>
                    </span>
                </div>
            </li>
        </ol>
    </nav>
</template>
