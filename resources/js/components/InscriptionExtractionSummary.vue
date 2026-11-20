<script setup lang="ts">
import { ref } from 'vue';

export type ReviewChoice = {
    source: 'registered' | 'front' | 'back';
    label: string;
    value: string;
};

export type ReviewField = {
    key: string;
    label: string;
    registered: string | null;
    front: string | null;
    back: string | null;
    status: 'match' | 'mismatch' | 'unread' | 'kept';
    choices?: ReviewChoice[];
    kept_source?: string | null;
    kept_value?: string | null;
};

export type ExtractionReview = {
    step: string;
    label: string;
    group: string;
    group_label: string;
    has_mate: boolean;
    both_sides: boolean;
    matches_registered: boolean | null;
    headline: string;
    fields: ReviewField[];
    extracted: { key: string; label: string; value: string }[];
    extracted_front?: { key: string; label: string; value: string }[];
    extracted_back?: { key: string; label: string; value: string }[];
};

defineProps<{
    review: ExtractionReview;
    saving?: boolean;
}>();

const emit = defineEmits<{
    keep: [payload: { step: string; key: string; source: 'registered' | 'front' | 'back' | 'manual'; value?: string }];
}>();

const editing = ref<string | null>(null);
const correctAll = ref(false);
const manual = ref<Record<string, string>>({});

const statusLabel: Record<ReviewField['status'], string> = {
    match: 'Coincide',
    mismatch: 'No coincide',
    unread: 'Sin lectura',
    kept: 'Corregido',
};

const statusTone = (status: ReviewField['status']) => {
    if (status === 'match' || status === 'kept') {
        return 'text-emerald-200';
    }
    if (status === 'mismatch') {
        return 'text-red-200';
    }
    return 'text-cream/50';
};

const choose = (field: ReviewField, source: ReviewChoice['source'], step: string) => {
    editing.value = null;
    emit('keep', { step, key: field.key, source });
};

const bannerTone = (review: ExtractionReview) => {
    if (review.matches_registered === true) {
        return 'border-emerald-400/30 bg-emerald-500/10 text-emerald-100';
    }
    if (review.matches_registered === false) {
        return 'border-red-400/30 bg-red-500/10 text-red-100';
    }
    return 'border-amber-400/30 bg-amber-500/10 text-amber-100';
};

const display = (value: string | null | undefined) => value && value !== '' ? value : '—';

const detected = (field: ReviewField) => {
    if (field.front && field.back && field.front !== field.back) {
        return `Frente: ${field.front} · Reverso: ${field.back}`;
    }

    return display(field.front || field.back);
};

const openEditor = (field: ReviewField) => {
    manual.value[field.key] = field.kept_value || field.front || field.back || field.registered || '';
    editing.value = field.key;
    correctAll.value = false;
};

const openAll = (fields: ReviewField[]) => {
    correctAll.value = true;
    editing.value = null;
    fields.forEach((field) => {
        manual.value[field.key] = field.kept_value || field.front || field.back || field.registered || '';
    });
};

const saveManual = (field: ReviewField, step: string) => {
    const value = (manual.value[field.key] ?? '').trim();

    if (value === '') {
        return;
    }

    emit('keep', { step, key: field.key, source: 'manual', value });

    if (! correctAll.value) {
        editing.value = null;
    }
};

const isEditing = (field: ReviewField) => correctAll.value || editing.value === field.key;

const counts = (fields: ReviewField[]) => ({
    ok: fields.filter((field) => field.status === 'match' || field.status === 'kept').length,
    review: fields.filter((field) => field.status === 'mismatch').length,
    unread: fields.filter((field) => field.status === 'unread').length,
});
</script>

<template>
    <div class="space-y-4">
        <div>
            <p class="text-xs uppercase tracking-widest text-ember-400">Resumen · {{ review.group_label }}</p>
            <p class="mt-1 font-display text-2xl">{{ review.label }}</p>
        </div>

        <p class="rounded-xl border px-4 py-3 text-sm" :class="bannerTone(review)">
            {{ review.headline }}
        </p>

        <div v-if="review.extracted.length" class="rounded-xl border border-ember-400/20 bg-ember-500/5 p-4">
            <p class="text-xs uppercase tracking-widest text-ember-400">Datos leídos</p>
            <ul class="mt-2 grid gap-1 text-sm sm:grid-cols-2">
                <li v-for="row in review.extracted" :key="row.key">
                    <span class="text-cream/50">{{ row.label }}:</span>
                    <span class="ml-1 font-medium">{{ row.value }}</span>
                </li>
            </ul>
        </div>
        <p v-else class="rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-cream/60">
            No salió texto de estas fotos. El resumen de abajo es lo que escribiste al inicio, no lo que se leyó del documento.
        </p>

        <div v-if="review.fields.length">
            <div class="mb-1.5 flex items-center justify-between gap-2 text-xs text-cream/50">
                <p>{{ counts(review.fields).ok }} correctos · {{ counts(review.fields).review }} por revisar · {{ counts(review.fields).unread }} sin lectura</p>
                <button type="button" class="hover:text-cream disabled:opacity-50" :disabled="saving" @click="openAll(review.fields)">
                    Corregir todos
                </button>
            </div>

            <div class="divide-y divide-white/10 rounded-xl border border-white/10">
                <div class="hidden grid-cols-[9.5rem_1fr_1fr_4rem] gap-3 px-3 py-1.5 text-[11px] text-cream/40 sm:grid">
                    <span></span>
                    <span>En el documento</span>
                    <span>En el sistema</span>
                    <span></span>
                </div>
                <article
                    v-for="field in review.fields"
                    :key="field.key"
                    class="grid grid-cols-2 gap-x-4 gap-y-1 px-3 py-2 sm:grid-cols-[9.5rem_1fr_1fr_4rem] sm:items-start"
                >
                    <div class="col-span-2 sm:col-span-1">
                        <p class="text-sm text-cream">{{ field.label }}</p>
                        <p class="text-[11px]" :class="statusTone(field.status)">{{ statusLabel[field.status] }}</p>
                    </div>
                    <template v-if="isEditing(field)">
                        <div class="col-span-2 flex flex-wrap items-center gap-2 sm:col-span-3">
                            <input v-model="manual[field.key]" class="field min-w-36 flex-1 px-2 py-1 text-sm" :disabled="saving" />
                            <button
                                type="button"
                                class="text-xs font-semibold text-ember-300 hover:text-ember-200 disabled:opacity-50"
                                :disabled="saving || !(manual[field.key] ?? '').trim()"
                                @click="saveManual(field, review.step)"
                            >
                                Guardar
                            </button>
                            <button v-if="!correctAll" type="button" class="text-xs text-cream/50 hover:text-cream" :disabled="saving" @click="editing = null">Cancelar</button>
                        </div>
                    </template>
                    <template v-else>
                        <div>
                            <p class="text-[11px] text-cream/40 sm:hidden">En el documento</p>
                            <p class="text-sm font-medium text-cream">{{ detected(field) }}</p>
                            <div class="mt-0.5 flex flex-wrap gap-x-2">
                                <button
                                    v-if="field.status === 'mismatch' && field.front"
                                    type="button"
                                    class="text-xs text-ember-300 hover:text-ember-200 disabled:opacity-50"
                                    :disabled="saving"
                                    @click="choose(field, 'front', review.step)"
                                >
                                    {{ field.back && field.back !== field.front ? 'Usar frente' : 'Usar este' }}
                                </button>
                                <button
                                    v-if="field.status === 'mismatch' && field.back && (!field.front || field.back !== field.front)"
                                    type="button"
                                    class="text-xs text-ember-300 hover:text-ember-200 disabled:opacity-50"
                                    :disabled="saving"
                                    @click="choose(field, 'back', review.step)"
                                >
                                    {{ field.front ? 'Usar reverso' : 'Usar este' }}
                                </button>
                            </div>
                        </div>
                        <div>
                            <p class="text-[11px] text-cream/40 sm:hidden">En el sistema</p>
                            <p class="text-sm text-cream/80">{{ display(field.registered) }}</p>
                            <button
                                v-if="field.registered && field.status === 'mismatch'"
                                type="button"
                                class="mt-0.5 text-xs text-cream/50 hover:text-cream disabled:opacity-50"
                                :disabled="saving"
                                @click="choose(field, 'registered', review.step)"
                            >
                                Dejar este
                            </button>
                        </div>
                        <button
                            type="button"
                            class="col-span-2 text-left text-xs text-cream/50 hover:text-cream disabled:opacity-50 sm:col-span-1 sm:text-right"
                            :disabled="saving"
                            @click="openEditor(field)"
                        >
                            {{ field.front || field.back ? 'Cambiar' : 'Escribir' }}
                        </button>
                    </template>
                </article>
            </div>
            <button v-if="correctAll" type="button" class="mt-1.5 text-xs text-cream/50 hover:text-cream" :disabled="saving" @click="correctAll = false">
                Cerrar correcciones
            </button>
        </div>

        <div v-if="review.has_mate && (review.extracted_front?.length || review.extracted_back?.length)" class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-white/10 p-3">
                <p class="text-xs uppercase tracking-widest text-cream/50">Frente</p>
                <ul v-if="review.extracted_front?.length" class="mt-2 space-y-1 text-sm">
                    <li v-for="row in review.extracted_front" :key="row.key">
                        <span class="text-cream/50">{{ row.label }}:</span> {{ row.value }}
                    </li>
                </ul>
                <p v-else class="mt-2 text-sm text-cream/40">Sin lectura en el frente.</p>
            </div>
            <div class="rounded-xl border border-white/10 p-3">
                <p class="text-xs uppercase tracking-widest text-cream/50">Reverso</p>
                <ul v-if="review.extracted_back?.length" class="mt-2 space-y-1 text-sm">
                    <li v-for="row in review.extracted_back" :key="row.key">
                        <span class="text-cream/50">{{ row.label }}:</span> {{ row.value }}
                    </li>
                </ul>
                <p v-else class="mt-2 text-sm text-cream/40">Sin lectura en el reverso.</p>
            </div>
        </div>
    </div>
</template>
