<script setup lang="ts">
import DocumentKindIcon from '@/components/DocumentKindIcon.vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

export type InscriptionDocumentFile = {
    id: string;
    filename: string;
    url: string;
    mime_type?: string | null;
    side?: string | null;
    label?: string | null;
};

export type InscriptionDocumentItem = {
    slug: string;
    name: string;
    id?: string | null;
    status?: string | null;
    document_number?: string | null;
    expiration_date?: string | null;
    files: InscriptionDocumentFile[];
};

const props = defineProps<{
    item: InscriptionDocumentItem;
    capturable?: boolean;
}>();

const emit = defineEmits<{
    capture: [side: string];
}>();

type Slot = {
    key: string;
    label: string;
    file: InscriptionDocumentFile | null;
};

const preview = ref<Slot | null>(null);

const statusLabel: Record<string, string> = {
    valid: 'Vigente',
    expired: 'Vencido',
    pending_review: 'Por verificar',
    revoked: 'Revocado',
    cargado: 'Cargado',
};

const icon = computed(() => {
    const kinds: Record<string, string> = {
        cedula: 'cedula',
        licencia: 'licencia',
        'tarjeta-propiedad': 'tarjeta',
        soat: 'soat',
        tecnomecanica: 'tecnomecanica',
    };

    return kinds[props.item.slug] ?? 'cedula';
});

const twoSided = computed(() => ['cedula', 'licencia', 'tarjeta-propiedad'].includes(props.item.slug));

const slots = computed<Slot[]>(() => {
    if (twoSided.value) {
        return [
            {
                key: 'front',
                label: 'Cara',
                file: props.item.files.find((file) => file.side === 'front') ?? null,
            },
            {
                key: 'back',
                label: 'Respaldo',
                file: props.item.files.find((file) => file.side === 'back') ?? null,
            },
        ];
    }

    if (!props.item.files.length) {
        return [{ key: 'file', label: props.item.name, file: null }];
    }

    return props.item.files.map((file) => ({
        key: file.id,
        label: file.label || props.item.name,
        file,
    }));
});

const isImage = (file: InscriptionDocumentFile) => {
    const mime = file.mime_type ?? '';

    if (mime.startsWith('image/')) {
        return true;
    }

    return /\.(jpe?g|png|gif|webp|bmp)$/i.test(file.filename);
};

const open = (slot: Slot) => {
    if (slot.file) {
        preview.value = slot;
        return;
    }

    if (props.capturable) {
        emit('capture', slot.key);
    }
};

const change = (slot: Slot, event: Event) => {
    event.stopPropagation();
    emit('capture', slot.key);
};

const close = () => {
    preview.value = null;
};

const onKey = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        close();
    }
};

onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <article class="rounded-xl border border-white/10 p-4">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
            <div class="flex items-center gap-3">
                <span class="flex h-14 w-[5.5rem] shrink-0 items-center justify-center overflow-hidden rounded-xl border border-white/10 bg-ink-900/80 p-1">
                    <DocumentKindIcon :kind="icon" />
                </span>
                <div>
                    <p class="font-medium text-cream">{{ item.name }}</p>
                    <p class="text-xs uppercase text-cream/50">
                        {{ item.document_number || 'Sin número' }}
                        · {{ item.expiration_date || 'Sin vencimiento' }}
                        · {{ item.files.length ? (statusLabel[item.status ?? ''] ?? item.status ?? 'Cargado') : 'Sin archivo' }}
                    </p>
                </div>
            </div>
            <slot name="actions" />
        </div>

        <div class="grid gap-3" :class="twoSided ? 'sm:grid-cols-2' : ''">
            <button
                v-for="slot in slots"
                :key="slot.key"
                type="button"
                class="group overflow-hidden rounded-xl border border-white/10 bg-black/20 text-left"
                :disabled="!slot.file && !capturable"
                @click="open(slot)"
            >
                <div class="flex items-center justify-between gap-2 px-3 pt-2">
                    <p class="text-xs uppercase tracking-widest text-cream/50">{{ slot.label }}</p>
                    <span
                        v-if="capturable && slot.file"
                        class="text-[11px] font-medium uppercase tracking-widest text-ember-300"
                        @click="change(slot, $event)"
                    >
                        Cambiar
                    </span>
                </div>
                <div class="flex min-h-36 items-center justify-center p-2">
                    <img
                        v-if="slot.file && isImage(slot.file)"
                        :src="slot.file.url"
                        :alt="slot.label"
                        class="max-h-40 w-full rounded-lg object-contain transition group-hover:scale-[1.02]"
                    />
                    <span v-else-if="slot.file" class="px-3 py-6 text-center text-sm text-ember-300">
                        {{ slot.file.filename }}
                    </span>
                    <span v-else class="flex flex-col items-center gap-2 px-3 py-6 text-center text-sm text-cream/40">
                        <span class="flex h-16 w-24 items-center justify-center overflow-hidden rounded-lg border border-white/10 bg-ink-900/80 p-1">
                            <DocumentKindIcon :kind="icon" />
                        </span>
                        {{ capturable ? `Toca para cargar ${slot.label.toLowerCase()}` : `Sin ${slot.label.toLowerCase()}` }}
                    </span>
                </div>
            </button>
        </div>
    </article>

    <Teleport to="body">
        <div
            v-if="preview?.file"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-black/80 p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="`${item.name} · ${preview.label}`"
            @click.self="close"
        >
            <section class="flex max-h-[94dvh] w-full max-w-5xl flex-col gap-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="font-display text-2xl text-cream">{{ item.name }} · {{ preview.label }}</p>
                    <button type="button" class="btn-ghost" @click="close">Cerrar</button>
                </div>
                <img
                    v-if="isImage(preview.file)"
                    :src="preview.file.url"
                    :alt="preview.label"
                    class="mx-auto max-h-[80dvh] w-full rounded-2xl object-contain"
                />
                <iframe
                    v-else
                    :src="preview.file.url"
                    class="h-[80dvh] w-full rounded-2xl bg-white"
                    :title="preview.file.filename"
                />
            </section>
        </div>
    </Teleport>
</template>
