<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PersonFichaCover from '@/components/PersonFichaCover.vue';
import PersonProfileSummary from '@/components/PersonProfileSummary.vue';
import { useOrganization } from '@/composables/useOrganization';

defineProps<{
    person: any;
    form: Record<string, any>;
    pilots?: { id: string; name: string; document_type?: string | null; document_number?: string | null; phone?: string | null; email?: string | null }[];
    pilotChoices?: { id: string; name: string; document_number?: string | null }[];
    memberActive: boolean;
    canUpdateProfile: boolean;
    canUpdateSummary?: boolean;
    canUpdateMotorcycle: boolean;
}>();
const { clubPath } = useOrganization();
</script>

<template>
    <AppLayout>
        <PersonFichaCover
            :person="person"
            :member-active="memberActive"
            :can-update-profile="canUpdateProfile"
            :can-update-motorcycle="canUpdateMotorcycle"
            :edit-href="canUpdateProfile ? clubPath(`people/${person.id}/edit`) : null"
        />
        <PersonProfileSummary
            class="!mt-0"
            :form="form"
            :pilots="pilots"
            :pilot-choices="pilotChoices"
            :can-edit="canUpdateSummary"
            :person-id="person.id"
        />
    </AppLayout>
</template>
