<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = withDefaults(defineProps<{
    label?: string;
    currentUrl?: string | null;
    alt?: string;
    placeholder?: string | null;
    shape?: 'portrait' | 'wide' | 'banner';
    canEdit?: boolean;
    processing?: boolean;
    error?: string;
}>(), {
    label: '',
    currentUrl: null,
    alt: 'Foto',
    placeholder: null,
    shape: 'portrait',
    canEdit: false,
    processing: false,
    error: '',
});

const emit = defineEmits<{
    save: [file: File];
}>();

const input = ref<HTMLInputElement | null>(null);
const file = ref<File | null>(null);
const preview = ref<string | null>(null);
const dragging = ref(false);
const shown = computed(() => preview.value || props.currentUrl || '');

const clear = () => {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    preview.value = null;
    file.value = null;

    if (input.value) {
        input.value.value = '';
    }
};

const choose = (next: File | null) => {
    if (! next || ! next.type.startsWith('image/')) {
        return;
    }

    clear();
    file.value = next;
    preview.value = URL.createObjectURL(next);
};

const onInput = (event: Event) => {
    choose((event.target as HTMLInputElement).files?.[0] ?? null);
};

const onDrop = (event: DragEvent) => {
    dragging.value = false;
    choose(event.dataTransfer?.files?.[0] ?? null);
};

const save = () => {
    if (file.value) {
        emit('save', file.value);
    }
};

watch(() => props.processing, (processing, wasProcessing) => {
    if (wasProcessing && ! processing && ! props.error) {
        clear();
    }
});

onBeforeUnmount(clear);
</script>

<template>
    <div class="space-y-3">
        <p v-if="label" class="font-medium text-cream">{{ label }}</p>
        <button
            type="button"
            class="group relative flex w-full items-center justify-center overflow-hidden rounded-2xl border bg-ink-900/50"
            :class="[
                shape === 'portrait' ? 'mx-auto aspect-square max-w-56' : '',
                shape === 'wide' ? 'aspect-[16/10]' : '',
                shape === 'banner' ? 'aspect-[2.4/1] min-h-40 md:min-h-52' : '',
                canEdit
                    ? (dragging ? 'border-dashed border-ember-400 bg-ember-500/10' : 'border-dashed border-white/15')
                    : 'border-solid border-white/10',
                canEdit ? 'cursor-pointer' : 'cursor-default disabled:opacity-100',
            ]"
            :disabled="!canEdit"
            @click="canEdit && input?.click()"
            @dragover.prevent="dragging = canEdit"
            @dragleave="dragging = false"
            @drop.prevent="canEdit && onDrop($event)"
        >
            <img
                v-if="shown"
                :src="shown"
                :alt="alt"
                class="h-full w-full object-cover"
            />
            <img
                v-else-if="placeholder"
                :src="placeholder"
                :alt="alt"
                class="h-full w-full object-cover"
            />
            <span v-else class="px-4 text-center text-sm text-cream/50">
                {{ canEdit ? 'Haz clic o suelta la foto aquí' : 'Sin foto' }}
            </span>
            <slot name="overlay" />
            <span
                v-if="canEdit"
                class="absolute inset-x-0 bottom-0 z-10 bg-ink-950/75 px-3 py-2 text-xs text-cream opacity-0 transition group-hover:opacity-100"
            >
                {{ shown ? 'Cambiar foto' : 'Elegir foto' }}
            </span>
        </button>
        <input ref="input" type="file" accept="image/*" class="hidden" @change="onInput" />
        <p v-if="file" class="truncate text-xs text-cream/50">{{ file.name }}</p>
        <p v-if="error" class="text-sm text-red-300">{{ error }}</p>
        <div v-if="canEdit && file" class="flex flex-wrap gap-2">
            <button type="button" class="btn-primary" :disabled="processing" @click="save">
                {{ processing ? 'Guardando…' : 'Guardar foto' }}
            </button>
            <button type="button" class="btn-ghost" :disabled="processing" @click="clear">Quitar selección</button>
        </div>
    </div>
</template>
