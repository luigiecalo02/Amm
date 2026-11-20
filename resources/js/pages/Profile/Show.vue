<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PersonFichaCover from '@/components/PersonFichaCover.vue';
import PersonProfileSummary from '@/components/PersonProfileSummary.vue';
import { useOrganization } from '@/composables/useOrganization';

defineProps<{
    linked: boolean;
    name: string;
    photo_url?: string | null;
    person?: Record<string, any> | null;
    form: Record<string, any> | null;
    pilots?: { id: string; name: string; document_type?: string | null; document_number?: string | null; phone?: string | null; email?: string | null }[];
    pilotChoices?: { id: string; name: string }[];
    memberActive?: boolean;
    canUpdateProfile?: boolean;
    canUpdateSummary?: boolean;
    canUpdateMotorcycle?: boolean;
}>();
const { clubPath } = useOrganization();
</script>

<template>
    <AppLayout>
        <PersonFichaCover
            v-if="linked && person"
            :person="person"
            kicker="Mi perfil"
            :member-active="memberActive ?? false"
            :can-update-profile="canUpdateProfile ?? false"
            :can-update-motorcycle="canUpdateMotorcycle ?? false"
            :edit-href="canUpdateProfile ? clubPath(`people/${person.id}/edit`) : null"
        />
        <PersonProfileSummary
            v-if="linked && form && person"
            class="!mt-0"
            :form="form"
            :pilots="pilots"
            :pilot-choices="pilotChoices"
            :can-edit="canUpdateSummary"
            :person-id="person.id"
        />
        <p v-else class="rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-cream/60">
            Este usuario aún no está vinculado a una ficha de integrante.
        </p>
    </AppLayout>
</template>
