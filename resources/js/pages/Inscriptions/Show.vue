<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import InscriptionFormFields from '@/components/InscriptionFormFields.vue';
import InscriptionDocumentCard from '@/components/InscriptionDocumentCard.vue';
import MemberPhotos from '@/components/MemberPhotos.vue';
import PersonProfileSummary from '@/components/PersonProfileSummary.vue';
import type { InscriptionDocumentItem } from '@/components/InscriptionDocumentCard.vue';
import type { MemberMotorcycle } from '@/components/MemberPhotos.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useOrganization } from '@/composables/useOrganization';
import type { PageProps } from '@/types';
import { inscriptionFormDefaults } from '@/lib/inscriptionForm';

type DocumentItem = InscriptionDocumentItem & {
    id?: string | null;
    expiration_date?: string | null;
};

const props = defineProps<{
    application: {
        id: string;
        status: string;
        reference?: string | null;
        email?: string | null;
        extracted_data?: Record<string, any>;
        person?: {
            id: string;
            first_name: string;
            last_name: string;
            document_number: string;
            email?: string | null;
            photo_url?: string | null;
            motorcycles?: MemberMotorcycle[];
            documents?: { id: string; status: string; type?: { name: string } }[];
        } | null;
    };
    form: Record<string, any>;
    documents: DocumentItem[];
    pilots?: { id: string; name: string; document_number?: string | null }[];
    roles: { id: string; name: string; slug: string }[];
    canVerify: boolean;
    canEdit: boolean;
    memberActive: boolean;
    canUpdateProfile: boolean;
    canUpdateMotorcycle: boolean;
}>();

const { clubPath } = useOrganization();
const page = usePage<PageProps>();
const fileError = computed(() => (page.props.errors as Record<string, string> | undefined)?.files ?? '');
const dataForm = useForm(inscriptionFormDefaults(props.form));
const accessForm = useForm({
    email: props.application.email || props.application.person?.email || '',
    password: '',
    role_id: props.roles.find((role) => role.slug === 'miembro')?.id ?? props.roles[0]?.id ?? '',
});
const uploading = ref<string | null>(null);
const role = computed(() => dataForm.vehicle_role ?? props.form.vehicle_role ?? 'driver');
const displayName = computed(() => {
    const name = [
        props.form.first_name,
        props.form.middle_name,
        props.form.last_name,
        props.form.second_last_name,
    ].filter((part) => part).join(' ');

    return name || 'Solicitud';
});

const statusLabel: Record<string, string> = {
    draft: 'Borrador',
    ready_for_form: 'En formulario',
    submitted: 'Por verificar',
    verified: 'Verificada',
    access_granted: 'Con acceso',
    rejected: 'Rechazada',
};
const canApprove = computed(() => props.canVerify
    && ! props.memberActive
    && ['draft', 'ready_for_form', 'submitted', 'verified', 'access_granted'].includes(props.application.status));
const statusError = computed(() => (page.props.errors as Record<string, string> | undefined)?.status ?? '');
const verify = () => router.post(clubPath(`inscripciones/${props.application.id}/verificar`));
const approve = () => router.post(clubPath(`inscripciones/${props.application.id}/aprobar`));
const grant = () => accessForm.post(clubPath(`inscripciones/${props.application.id}/acceso`));
const saveData = () => dataForm.put(clubPath(`inscripciones/${props.application.id}`));

const replaceDocument = (item: DocumentItem, event: Event) => {
    const input = event.target as HTMLInputElement;
    const files = input.files ? Array.from(input.files) : [];
    input.value = '';

    if (!files.length) {
        return;
    }

    uploading.value = item.slug;
    router.post(clubPath(`inscripciones/${props.application.id}/documentos`), {
        type: item.slug,
        files,
        expiration_date: item.expiration_date ?? '',
    }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            uploading.value = null;
        },
    });
};
</script>

<template>
    <AppLayout>
        <Link :href="clubPath('inscripciones')" class="text-sm text-cream/60 hover:text-cream">← Inscripciones</Link>
        <PageHeader
            class="mt-3"
            :kicker="application.reference
                ? `Verificación · ${application.reference} · ${statusLabel[application.status] ?? application.status}`
                : `Verificación · ${statusLabel[application.status] ?? application.status}`"
            :title="displayName"
            :description="canEdit
                ? 'La solicitud está pendiente. Puedes corregir datos y documentos antes de verificar.'
                : 'Revisa los datos digitados y los documentos adjuntos.'"
        />

        <div class="mb-5 flex flex-wrap gap-2">
            <button v-if="canApprove" class="btn-primary" type="button" @click="approve">Aprobar solicitud</button>
            <button v-if="canVerify && application.status === 'submitted'" class="btn-ghost" type="button" @click="verify">Verificar documentación</button>
            <Link :href="clubPath('inscripciones')" class="btn-ghost">Otras solicitudes</Link>
        </div>
        <p v-if="statusError" class="mb-4 text-sm text-red-300">{{ statusError }}</p>

        <div v-if="!canEdit" class="mb-6 space-y-4">
            <PersonProfileSummary :form="form" :email="application.email" :pilots="pilots" />
            <section class="panel space-y-4 p-5 text-sm text-cream/80">
                <h2 class="font-display text-xl text-cream">Documentos</h2>
                <p v-if="!documents.length" class="text-cream/50">Aún no hay documentos en esta solicitud.</p>
                <InscriptionDocumentCard v-for="item in documents" :key="item.slug" :item="item" />
            </section>
        </div>

        <form v-if="canEdit" class="mb-6 space-y-4" @submit.prevent="saveData">
            <InscriptionFormFields
                :form="dataForm"
                :pilots="pilots"
                :role="role"
                step="all"
            />
            <p v-if="dataForm.errors.document_number" class="text-sm text-red-300">{{ dataForm.errors.document_number }}</p>
            <p v-if="dataForm.errors.email" class="text-sm text-red-300">{{ dataForm.errors.email }}</p>
            <p v-if="dataForm.errors.pilot_person_id" class="text-sm text-red-300">{{ dataForm.errors.pilot_person_id }}</p>
            <button class="btn-primary" :disabled="dataForm.processing">Guardar datos del inscrito</button>
        </form>

        <section v-if="canEdit" class="panel mb-6 space-y-4 p-5">
            <h2 class="font-display text-xl">Documentos</h2>
            <p class="text-sm text-cream/60">Mientras esté pendiente puedes reemplazar o agregar archivos. Quedan otra vez por verificar.</p>
            <p v-if="fileError" class="rounded-xl border border-red-400/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">
                {{ fileError }}
            </p>
            <InscriptionDocumentCard v-for="item in documents" :key="item.slug" :item="item">
                <template #actions>
                    <label class="btn-ghost cursor-pointer">
                        {{ uploading === item.slug ? 'Subiendo…' : (item.files.length ? 'Reemplazar' : 'Agregar') }}
                        <input
                            type="file"
                            accept="image/*,.pdf"
                            multiple
                            class="hidden"
                            :disabled="uploading === item.slug"
                            @change="replaceDocument(item, $event)"
                        />
                    </label>
                </template>
            </InscriptionDocumentCard>
        </section>

        <section v-if="application.person" class="panel mb-6 space-y-4 p-5">
            <h2 class="font-display text-xl">Fotos del integrante</h2>
            <MemberPhotos
                :person="application.person"
                :member-active="memberActive"
                :can-update-profile="canUpdateProfile"
                :can-update-motorcycle="canUpdateMotorcycle"
            />
        </section>

        <form v-if="canVerify && application.status === 'verified'" class="panel grid gap-3 p-5 md:grid-cols-2" @submit.prevent="grant">
            <h2 class="font-display text-xl md:col-span-2">Otorgar acceso</h2>
            <input v-model="accessForm.email" type="email" class="field" placeholder="Correo" required />
            <input v-model="accessForm.password" type="password" class="field" placeholder="Contraseña (opcional, se genera si va vacía)" />
            <select v-model="accessForm.role_id" class="field md:col-span-2">
                <option v-for="roleOption in roles" :key="roleOption.id" :value="roleOption.id">{{ roleOption.name }}</option>
            </select>
            <button class="btn-primary md:col-span-2" :disabled="accessForm.processing">Crear usuario del club</button>
        </form>
    </AppLayout>
</template>
