<script setup lang="ts">
export type ReviewField = {
    key: string;
    label: string;
    registered: string | null;
    front: string | null;
    back: string | null;
    status: 'match' | 'mismatch' | 'unread';
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
}>();

const statusLabel: Record<ReviewField['status'], string> = {
    match: 'Coincide',
    mismatch: 'No coincide',
    unread: 'Sin lectura',
};

const statusTone = (status: ReviewField['status']) => {
    if (status === 'match') {
        return 'text-emerald-200';
    }
    if (status === 'mismatch') {
        return 'text-red-200';
    }
    return 'text-cream/50';
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

const display = (value: string | null) => value && value !== '' ? value : '—';
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

        <div v-if="review.fields.length" class="overflow-x-auto rounded-xl border border-white/10">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-black/20 text-xs uppercase tracking-widest text-cream/45">
                    <tr>
                        <th class="px-3 py-2 font-medium">Dato</th>
                        <th class="px-3 py-2 font-medium">Registrado</th>
                        <th v-if="review.has_mate" class="px-3 py-2 font-medium">Frente</th>
                        <th v-if="review.has_mate" class="px-3 py-2 font-medium">Reverso</th>
                        <th v-else class="px-3 py-2 font-medium">Leído</th>
                        <th class="px-3 py-2 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="field in review.fields" :key="field.key" class="border-t border-white/10">
                        <td class="px-3 py-2 text-cream/70">{{ field.label }}</td>
                        <td class="px-3 py-2">{{ display(field.registered) }}</td>
                        <td v-if="review.has_mate" class="px-3 py-2">{{ display(field.front) }}</td>
                        <td v-if="review.has_mate" class="px-3 py-2">{{ display(field.back) }}</td>
                        <td v-else class="px-3 py-2">{{ display(field.front) }}</td>
                        <td class="px-3 py-2 font-medium" :class="statusTone(field.status)">
                            {{ statusLabel[field.status] }}
                        </td>
                    </tr>
                </tbody>
            </table>
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
