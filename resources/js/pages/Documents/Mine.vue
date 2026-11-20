<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import MemberAvatar from '@/components/MemberAvatar.vue';
import DocumentKindIcon from '@/components/DocumentKindIcon.vue';
import DocumentStepCapture from '@/components/DocumentStepCapture.vue';
import InscriptionDocumentCard from '@/components/InscriptionDocumentCard.vue';
import type { InscriptionDocumentItem } from '@/components/InscriptionDocumentCard.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { PageProps } from '@/types';

type Entry = InscriptionDocumentItem & {
    issue_date?: string | null;
    motorcycle?: { id?: string; plate?: string | null; brand?: string | null } | null;
};

type DocumentKind = {
    id: string;
    slug: string;
    name: string;
    entity_type: string;
    count: number;
    entries: Entry[];
};

const props = defineProps<{
    linked: boolean;
    name?: string | null;
    photo_url?: string | null;
    types: DocumentKind[];
    motorcycles: { id: string; plate: string; brand?: string | null }[];
}>();

const page = usePage<PageProps>();
const captureError = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined;

    return errors?.file ?? errors?.files ?? '';
});
const selectedSlug = ref<string | null>(null);
const selectedEntryId = ref<string | null>(null);
const creating = ref(false);
const selectedKind = computed(() => props.types.find((type) => type.slug === selectedSlug.value) ?? null);
const entry = computed(() => {
    if (creating.value) {
        return null;
    }

    const entries = selectedKind.value?.entries ?? [];

    if (selectedEntryId.value) {
        return entries.find((item) => item.id === selectedEntryId.value) ?? null;
    }

    return entries[0] ?? null;
});
const availableMotorcycles = computed(() => props.motorcycles.filter(
    (moto) => !(selectedKind.value?.entries ?? []).some((item) => item.motorcycle?.id === moto.id),
));

const form = useForm({
    slug: '',
    motorcycle_id: '',
    document_number: '',
    issue_date: '',
    expiration_date: '',
});
const capturing = ref(false);
const capture = ref<{ side: 'front' | 'back' | null; label: string } | null>(null);

const statusLabel: Record<string, string> = {
    valid: 'Vigente',
    expired: 'Vencido',
    pending_review: 'Por verificar',
    revoked: 'Revocado',
};

const kindIcon = (slug: string) => {
    const kinds: Record<string, string> = {
        cedula: 'cedula',
        licencia: 'licencia',
        'tarjeta-propiedad': 'tarjeta',
        soat: 'soat',
        tecnomecanica: 'tecnomecanica',
    };

    return kinds[slug] ?? 'cedula';
};

const summary = (kind: DocumentKind) => {
    if (kind.count > 1) {
        return `${kind.count} registros`;
    }

    const current = kind.entries[0];

    if (! current) {
        return 'Sin archivo';
    }

    const state = statusLabel[current.status ?? ''] ?? 'Cargado';
    const plate = current.motorcycle?.plate ? ` · ${current.motorcycle.plate}` : '';

    return current.expiration_date ? `${state} · ${current.expiration_date}${plate}` : `${state}${plate}`;
};

const needsMotorcycle = computed(() => selectedKind.value?.entity_type === 'motorcycle' && ! entry.value);
const missingMotorcycle = computed(() => needsMotorcycle.value && props.motorcycles.length === 0);
const cardItem = computed<InscriptionDocumentItem | null>(() => {
    if (! selectedKind.value) {
        return null;
    }

    if (entry.value) {
        return entry.value;
    }

    return {
        slug: selectedKind.value.slug,
        name: selectedKind.value.name,
        files: [],
    };
});
const captureFrame = computed(() => (
    ['cedula', 'licencia', 'tarjeta-propiedad'].includes(selectedKind.value?.slug ?? '') ? 'id-card' : 'sheet'
));

const fill = (kind: DocumentKind, current: Entry | null) => {
    const free = props.motorcycles.filter(
        (moto) => !kind.entries.some((item) => item.motorcycle?.id === moto.id),
    );

    form.slug = kind.slug;
    form.motorcycle_id = current ? '' : (free.length === 1 ? free[0].id : '');
    form.document_number = current?.document_number ?? '';
    form.issue_date = current?.issue_date ?? '';
    form.expiration_date = current?.expiration_date ?? '';
    form.clearErrors();
};

const openKind = (kind: DocumentKind) => {
    creating.value = false;
    selectedSlug.value = kind.slug;
    selectedEntryId.value = kind.entries[0]?.id ?? null;
    fill(kind, kind.entries[0] ?? null);
};

const chooseEntry = (item: Entry) => {
    if (! selectedKind.value) {
        return;
    }

    creating.value = false;
    selectedEntryId.value = item.id ?? null;
    fill(selectedKind.value, item);
};

const startAnother = () => {
    if (! selectedKind.value) {
        return;
    }

    creating.value = true;
    selectedEntryId.value = null;
    fill(selectedKind.value, null);
};

const openCapture = (side: string) => {
    if (missingMotorcycle.value || capturing.value) {
        return;
    }

    capture.value = {
        side: side === 'front' || side === 'back' ? side : null,
        label: side === 'front' ? 'Cara' : side === 'back' ? 'Respaldo' : (selectedKind.value?.name ?? 'Documento'),
    };
};

const closeCapture = () => {
    if (! capturing.value) {
        capture.value = null;
    }
};

const submitCapture = (file: File) => {
    const slug = page.props.organization?.slug;
    const data = {
        slug: form.slug,
        motorcycle_id: form.motorcycle_id,
        document_number: form.document_number,
        issue_date: form.issue_date,
        expiration_date: form.expiration_date,
        file,
        side: capture.value?.side ?? '',
    };
    const options = {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            creating.value = false;
            capture.value = null;
        },
        onFinish: () => {
            capturing.value = false;
        },
    };

    capturing.value = true;

    if (entry.value?.id) {
        router.put(`/app/${slug}/mis-documentos/${entry.value.id}`, data, options);
        return;
    }

    router.post(`/app/${slug}/mis-documentos`, data, options);
};

const save = () => {
    const slug = page.props.organization?.slug;
    const options = { preserveScroll: true, preserveState: true };

    if (entry.value?.id) {
        form.put(`/app/${slug}/mis-documentos/${entry.value.id}`, options);
        return;
    }

    form.post(`/app/${slug}/mis-documentos`, options);
};
</script>

<template>
    <AppLayout>
        <PageHeader
            kicker="Tu archivo"
            title="Mis documentos"
            description="Estos son tus documentos. Abre uno para corregir los datos o actualizar el archivo."
        >
            <template #leading>
                <MemberAvatar :src="photo_url" :alt="name || 'Foto de perfil'" class="h-20 w-20" />
            </template>
        </PageHeader>

        <p v-if="!linked" class="text-sm text-cream/60">
            Tu usuario no está ligado a un integrante de este club.
        </p>

        <section v-else>
            <div v-if="!selectedKind" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="kind in types"
                    :key="kind.id"
                    type="button"
                    class="panel flex items-center gap-3 p-4 text-left hover:border-ember-400/40"
                    @click="openKind(kind)"
                >
                    <span class="flex h-14 w-[5.5rem] shrink-0 items-center justify-center overflow-hidden rounded-xl border border-white/10 bg-ink-900/80 p-1">
                        <DocumentKindIcon :kind="kindIcon(kind.slug)" />
                    </span>
                    <span>
                        <span class="block font-medium text-cream">{{ kind.name }}</span>
                        <span class="block text-xs text-cream/50">{{ summary(kind) }}</span>
                    </span>
                </button>
            </div>

            <div v-else class="space-y-4">
                <button type="button" class="text-sm text-cream/60 hover:text-cream" @click="selectedSlug = null">
                    ← Mis documentos
                </button>
                <h2 class="font-display text-2xl">{{ selectedKind.name }}</h2>
                <div v-if="selectedKind.entries.length > 1" class="grid gap-2 sm:grid-cols-2">
                    <button
                        v-for="item in selectedKind.entries"
                        :key="item.id ?? item.slug"
                        type="button"
                        class="panel p-3 text-left text-sm hover:border-ember-400/40"
                        :class="{ 'border-ember-400/50': item.id === entry?.id }"
                        @click="chooseEntry(item)"
                    >
                        <span class="block font-medium text-cream">{{ item.motorcycle?.plate || item.document_number || 'Sin número' }}</span>
                        <span class="block text-xs text-cream/50">{{ item.expiration_date || 'Sin vencimiento' }}</span>
                    </button>
                </div>
                <button
                    v-if="entry && selectedKind.entity_type === 'motorcycle' && availableMotorcycles.length"
                    type="button"
                    class="text-sm text-ember-400 hover:text-cream"
                    @click="startAnother"
                >
                    Registrar en otra moto
                </button>
                <p class="text-sm text-cream/60">Toca cara o respaldo para tomar la foto o adjuntar el archivo, como en la inscripción.</p>
                <InscriptionDocumentCard
                    v-if="cardItem"
                    :item="cardItem"
                    capturable
                    @capture="openCapture"
                />

                <form class="panel grid gap-3 p-5 md:grid-cols-2" @submit.prevent="save">
                    <h3 class="font-display text-xl md:col-span-2">Datos del documento</h3>
                    <label class="block text-sm text-cream/70">
                        Número
                        <input v-model="form.document_number" class="field mt-1" />
                    </label>
                    <label v-if="needsMotorcycle && availableMotorcycles.length > 1" class="block text-sm text-cream/70">
                        Moto
                        <select v-model="form.motorcycle_id" class="field mt-1" required>
                            <option value="" disabled>Elige la moto</option>
                            <option v-for="moto in availableMotorcycles" :key="moto.id" :value="moto.id">
                                {{ moto.plate }} · {{ moto.brand }}
                            </option>
                        </select>
                    </label>
                    <p v-if="missingMotorcycle" class="text-sm text-red-300 md:col-span-2">
                        Este documento es de la moto y todavía no tienes una registrada.
                    </p>
                    <label class="block text-sm text-cream/70">
                        Expedición
                        <input v-model="form.issue_date" type="date" class="field mt-1" />
                    </label>
                    <label class="block text-sm text-cream/70">
                        Vencimiento
                        <input v-model="form.expiration_date" type="date" class="field mt-1" />
                    </label>
                    <p v-if="form.errors.expiration_date" class="text-sm text-red-300 md:col-span-2">{{ form.errors.expiration_date }}</p>
                    <p v-if="captureError" class="text-sm text-red-300 md:col-span-2">{{ captureError }}</p>
                    <button class="btn-primary md:col-span-2" :disabled="form.processing || missingMotorcycle">Guardar datos</button>
                </form>
            </div>
        </section>

        <Teleport to="body">
            <div
                v-if="capture && selectedKind"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-3 sm:items-center sm:p-6"
                role="dialog"
                aria-modal="true"
                @click.self="closeCapture"
            >
                <section class="panel max-h-[92dvh] w-full max-w-2xl overflow-y-auto p-5">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="font-display text-2xl">{{ selectedKind.name }} · {{ capture.label }}</p>
                            <p class="mt-1 text-sm text-cream/60">Toma la foto o adjunta el archivo de este lado.</p>
                        </div>
                        <button type="button" class="btn-ghost shrink-0" :disabled="capturing" @click="closeCapture">Cerrar</button>
                    </div>
                    <p v-if="captureError" class="mb-3 rounded-xl border border-red-400/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">
                        {{ captureError }}
                    </p>
                    <DocumentStepCapture
                        :key="`${selectedKind.slug}-${capture.side ?? 'file'}`"
                        :camera="true"
                        :analyze="false"
                        :processing="capturing"
                        :icon="kindIcon(selectedKind.slug)"
                        :frame="captureFrame"
                        :label="`${selectedKind.name} · ${capture.label}`"
                        @submit="submitCapture"
                    />
                </section>
            </div>
        </Teleport>
    </AppLayout>
</template>
