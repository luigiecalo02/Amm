<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import MemberAvatar from '@/components/MemberAvatar.vue';
import DocumentKindIcon from '@/components/DocumentKindIcon.vue';
import InscriptionDocumentCard from '@/components/InscriptionDocumentCard.vue';
import type { InscriptionDocumentItem } from '@/components/InscriptionDocumentCard.vue';
import { computed, ref } from 'vue';

type Member = {
    id: string;
    name: string;
    document_number?: string | null;
    photo_url?: string | null;
    documents: Array<InscriptionDocumentItem & {
        issue_date?: string | null;
        metadata?: Record<string, unknown>;
        motorcycle?: { plate?: string | null; brand?: string | null } | null;
    }>;
};

type TypeEntry = Member['documents'][number] & {
    member: Pick<Member, 'id' | 'name' | 'document_number' | 'photo_url'>;
};

type DocumentKind = {
    id: string;
    slug: string;
    name: string;
    entity_type: string;
    count: number;
    entries: TypeEntry[];
};

const props = defineProps<{
    members: Member[];
    types: DocumentKind[];
    canDownload: boolean;
}>();

const view = ref<'members' | 'types'>('members');
const selectedMemberId = ref<string | null>(null);
const selectedTypeSlug = ref<string | null>(null);
const selectedEntryId = ref<string | null>(null);
const memberQuery = ref('');

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

const filteredMembers = computed(() => {
    const needle = memberQuery.value.trim().toLowerCase();

    if (! needle) {
        return props.members;
    }

    return props.members.filter((member) => (
        member.name.toLowerCase().includes(needle)
        || (member.document_number ?? '').toLowerCase().includes(needle)
    ));
});

const selectedMember = computed(() => props.members.find((member) => member.id === selectedMemberId.value) ?? null);
const selectedKind = computed(() => props.types.find((type) => type.slug === selectedTypeSlug.value) ?? null);
const selectedEntry = computed(() => selectedKind.value?.entries.find((entry) => entry.id === selectedEntryId.value) ?? null);

const openMembers = () => {
    view.value = 'members';
    selectedTypeSlug.value = null;
    selectedEntryId.value = null;
};

const openTypes = () => {
    view.value = 'types';
    selectedMemberId.value = null;
};

const openMember = (id: string) => {
    selectedMemberId.value = id;
};

const openKind = (slug: string) => {
    selectedTypeSlug.value = slug;
    selectedEntryId.value = null;
};

const openEntry = (id: string) => {
    selectedEntryId.value = id;
};

const shown = (value: unknown) => {
    if (value == null || value === '') {
        return '—';
    }

    return String(value);
};

const entryFields = (entry: TypeEntry) => {
    const fields = [
        { label: 'Integrante', value: entry.member.name },
        { label: 'Cédula', value: shown(entry.member.document_number) },
        { label: 'Número del documento', value: shown(entry.document_number) },
        { label: 'Expedición', value: shown(entry.issue_date) },
        { label: 'Vencimiento', value: shown(entry.expiration_date) },
        { label: 'Estado', value: statusLabel[entry.status ?? ''] ?? shown(entry.status) },
    ];

    if (entry.motorcycle?.plate || entry.motorcycle?.brand) {
        fields.push({
            label: 'Vehículo',
            value: [entry.motorcycle.brand, entry.motorcycle.plate].filter(Boolean).join(' · '),
        });
    }

    return fields;
};
</script>

<template>
    <AppLayout>
        <PageHeader
            kicker="Archivo del club"
            title="Documentos"
            description="Consulta por integrante o por tipo de documento. Toca una foto o una licencia para abrir el archivo."
        />

        <div class="mb-5 grid grid-cols-2 gap-2">
            <button
                type="button"
                class="rounded-xl border px-3 py-2 text-sm transition"
                :class="view === 'members'
                    ? 'border-ember-400 bg-ember-500/15 text-ember-200'
                    : 'border-white/10 bg-white/5 text-cream/60 hover:border-white/25'"
                @click="openMembers"
            >
                Por integrante
            </button>
            <button
                type="button"
                class="rounded-xl border px-3 py-2 text-sm transition"
                :class="view === 'types'
                    ? 'border-ember-400 bg-ember-500/15 text-ember-200'
                    : 'border-white/10 bg-white/5 text-cream/60 hover:border-white/25'"
                @click="openTypes"
            >
                Por documento
            </button>
        </div>

        <section v-if="view === 'members'">
            <div v-if="!selectedMember" class="space-y-3">
                <input v-model="memberQuery" class="field" placeholder="Buscar integrante por nombre o cédula" />
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="member in filteredMembers"
                        :key="member.id"
                        type="button"
                        class="panel flex items-center gap-3 p-3 text-left hover:border-ember-400/40"
                        @click="openMember(member.id)"
                    >
                        <MemberAvatar :src="member.photo_url" :alt="member.name" class="h-14 w-14 shrink-0" />
                        <span class="min-w-0">
                            <span class="block truncate font-medium text-cream">{{ member.name }}</span>
                            <span class="block text-xs text-cream/50">
                                {{ member.document_number || 'Sin cédula' }}
                                · {{ member.documents.length }} documento{{ member.documents.length === 1 ? '' : 's' }}
                            </span>
                        </span>
                    </button>
                </div>
                <p v-if="!filteredMembers.length" class="text-sm text-cream/50">No hay integrantes con ese nombre.</p>
            </div>

            <div v-else class="space-y-4">
                <button type="button" class="text-sm text-cream/60 hover:text-cream" @click="selectedMemberId = null">
                    ← Todos los integrantes
                </button>
                <div class="flex items-center gap-3">
                    <MemberAvatar :src="selectedMember.photo_url" :alt="selectedMember.name" class="h-16 w-16" />
                    <div>
                        <p class="font-display text-2xl">{{ selectedMember.name }}</p>
                        <p class="text-sm text-cream/50">{{ selectedMember.document_number || 'Sin cédula' }}</p>
                    </div>
                </div>
                <p v-if="!selectedMember.documents.length" class="text-sm text-cream/50">Este integrante aún no tiene documentos.</p>
                <InscriptionDocumentCard
                    v-for="item in selectedMember.documents"
                    :key="item.id ?? item.slug"
                    :item="item"
                />
            </div>
        </section>

        <section v-else>
            <div v-if="!selectedKind" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="kind in types"
                    :key="kind.id"
                    type="button"
                    class="panel flex items-center gap-3 p-4 text-left hover:border-ember-400/40"
                    @click="openKind(kind.slug)"
                >
                    <span class="flex h-14 w-[5.5rem] shrink-0 items-center justify-center overflow-hidden rounded-xl border border-white/10 bg-ink-900/80 p-1">
                        <DocumentKindIcon :kind="kindIcon(kind.slug)" />
                    </span>
                    <span>
                        <span class="block font-medium text-cream">{{ kind.name }}</span>
                        <span class="block text-xs text-cream/50">
                            {{ kind.count }} {{ kind.count === 1 ? 'integrante' : 'integrantes' }}
                        </span>
                    </span>
                </button>
            </div>

            <div v-else-if="!selectedEntry" class="space-y-3">
                <button type="button" class="text-sm text-cream/60 hover:text-cream" @click="selectedTypeSlug = null">
                    ← Todos los documentos
                </button>
                <h2 class="font-display text-2xl">{{ selectedKind.name }}</h2>
                <p v-if="!selectedKind.entries.length" class="text-sm text-cream/50">Nadie tiene este documento todavía.</p>
                <button
                    v-for="entry in selectedKind.entries"
                    :key="entry.id ?? entry.member.id"
                    type="button"
                    class="panel flex w-full items-center gap-3 p-3 text-left hover:border-ember-400/40"
                    @click="openEntry(entry.id ?? entry.member.id)"
                >
                    <MemberAvatar :src="entry.member.photo_url" :alt="entry.member.name" class="h-12 w-12 shrink-0" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium text-cream">{{ entry.member.name }}</span>
                        <span class="block text-xs text-cream/50">
                            {{ entry.document_number || 'Sin número' }}
                            · {{ entry.expiration_date || 'Sin vencimiento' }}
                            · {{ statusLabel[entry.status ?? ''] ?? entry.status ?? 'Sin estado' }}
                        </span>
                    </span>
                </button>
            </div>

            <div v-else class="space-y-4">
                <button type="button" class="text-sm text-cream/60 hover:text-cream" @click="selectedEntryId = null">
                    ← {{ selectedKind.name }}
                </button>
                <div class="flex items-center gap-3">
                    <MemberAvatar :src="selectedEntry.member.photo_url" :alt="selectedEntry.member.name" class="h-14 w-14" />
                    <div>
                        <p class="font-display text-2xl">{{ selectedEntry.member.name }}</p>
                        <p class="text-sm text-cream/50">{{ selectedKind.name }}</p>
                    </div>
                </div>
                <section class="panel p-5">
                    <h3 class="font-display text-xl">Datos del documento</h3>
                    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div v-for="field in entryFields(selectedEntry)" :key="field.label">
                            <dt class="text-xs uppercase tracking-widest text-cream/45">{{ field.label }}</dt>
                            <dd class="mt-1 text-sm text-cream/80">{{ field.value }}</dd>
                        </div>
                    </dl>
                </section>
                <InscriptionDocumentCard :item="selectedEntry" />
            </div>
        </section>
    </AppLayout>
</template>
