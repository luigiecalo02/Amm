<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { useWebPush } from '@/composables/useWebPush';
import type { PageProps } from '@/types';

const props = defineProps<{
    documents: boolean;
    birthdays: boolean;
}>();

const page = usePage<PageProps>();
const push = useWebPush(page.props.vapidPublicKey ?? null);
const form = useForm({
    documents: props.documents,
    birthdays: props.birthdays,
});

const persist = () => {
    form.put(`/app/${page.props.organization?.slug}/avisos-personales`, {
        preserveScroll: true,
        preserveState: true,
    });
};

const toggle = (key: 'documents' | 'birthdays') => {
    if (form.processing) {
        return;
    }

    form[key] = !form[key];
    persist();
};

const toggleDevice = () => {
    if (push.enabling.value || push.blocked.value) {
        return;
    }

    if (push.enabled.value) {
        push.disable();
        return;
    }

    push.enable();
};
</script>

<template>
    <div class="space-y-3">
        <button
            type="button"
            role="switch"
            class="flex w-full items-center justify-between gap-4 rounded-2xl border px-4 py-4 text-left transition"
            :class="form.documents
                ? 'border-ember-400/50 bg-ember-500/10'
                : 'border-white/10 bg-ink-900/50 hover:border-white/25'"
            :aria-checked="form.documents"
            :disabled="form.processing"
            @click="toggle('documents')"
        >
            <span>
                <span class="block font-medium text-cream">Vencimientos</span>
                <span class="mt-1 block text-xs text-cream/50">Licencia, SOAT y técnico-mecánica, cuando te correspondan.</span>
            </span>
            <span class="flex shrink-0 items-center gap-3">
                <span class="text-xs uppercase tracking-widest" :class="form.documents ? 'text-ember-200' : 'text-cream/40'">
                    {{ form.documents ? 'Activos' : 'Apagados' }}
                </span>
                <span class="relative inline-flex h-7 w-12">
                    <span class="absolute inset-0 rounded-full transition" :class="form.documents ? 'bg-ember-500' : 'bg-white/15'"></span>
                    <span class="absolute top-0.5 h-6 w-6 rounded-full bg-cream transition" :class="form.documents ? 'left-5' : 'left-0.5'"></span>
                </span>
            </span>
        </button>

        <button
            type="button"
            role="switch"
            class="flex w-full items-center justify-between gap-4 rounded-2xl border px-4 py-4 text-left transition"
            :class="form.birthdays
                ? 'border-ember-400/50 bg-ember-500/10'
                : 'border-white/10 bg-ink-900/50 hover:border-white/25'"
            :aria-checked="form.birthdays"
            :disabled="form.processing"
            @click="toggle('birthdays')"
        >
            <span>
                <span class="block font-medium text-cream">Cumpleaños</span>
                <span class="mt-1 block text-xs text-cream/50">Solo si formas parte de la directiva que los recibe.</span>
            </span>
            <span class="flex shrink-0 items-center gap-3">
                <span class="text-xs uppercase tracking-widest" :class="form.birthdays ? 'text-ember-200' : 'text-cream/40'">
                    {{ form.birthdays ? 'Activos' : 'Apagados' }}
                </span>
                <span class="relative inline-flex h-7 w-12">
                    <span class="absolute inset-0 rounded-full transition" :class="form.birthdays ? 'bg-ember-500' : 'bg-white/15'"></span>
                    <span class="absolute top-0.5 h-6 w-6 rounded-full bg-cream transition" :class="form.birthdays ? 'left-5' : 'left-0.5'"></span>
                </span>
            </span>
        </button>

        <button
            v-if="push.supported.value"
            type="button"
            role="switch"
            class="flex w-full items-center justify-between gap-4 rounded-2xl border px-4 py-4 text-left transition"
            :class="push.enabled.value
                ? 'border-ember-400/50 bg-ember-500/10'
                : 'border-white/10 bg-ink-900/50 hover:border-white/25'"
            :aria-checked="push.enabled.value"
            :disabled="push.enabling.value || push.blocked.value"
            @click="toggleDevice"
        >
            <span>
                <span class="block font-medium text-cream">Avisos en este dispositivo</span>
                <span class="mt-1 block text-xs text-cream/50">
                    {{ push.blocked.value
                        ? 'El navegador bloqueó los avisos. Actívalos en la configuración del sitio.'
                        : 'Notificaciones emergentes aunque no tengas la app abierta.' }}
                </span>
            </span>
            <span class="flex shrink-0 items-center gap-3">
                <span class="text-xs uppercase tracking-widest" :class="push.enabled.value ? 'text-ember-200' : 'text-cream/40'">
                    {{ push.enabled.value ? 'Activos' : 'Apagados' }}
                </span>
                <span class="relative inline-flex h-7 w-12">
                    <span class="absolute inset-0 rounded-full transition" :class="push.enabled.value ? 'bg-ember-500' : 'bg-white/15'"></span>
                    <span class="absolute top-0.5 h-6 w-6 rounded-full bg-cream transition" :class="push.enabled.value ? 'left-5' : 'left-0.5'"></span>
                </span>
            </span>
        </button>
    </div>
</template>
