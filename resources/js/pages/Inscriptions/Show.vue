<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import InscriptionFormFields from '@/components/InscriptionFormFields.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useOrganization } from '@/composables/useOrganization';
import type { PageProps } from '@/types';

type DocumentItem = {
    slug: string;
    name: string;
    id?: string | null;
    status?: string | null;
    expiration_date?: string | null;
    files: { id: string; filename: string; url: string }[];
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
            documents?: { id: string; status: string; type?: { name: string } }[];
        } | null;
    };
    form: Record<string, any>;
    documents: DocumentItem[];
    pilots?: { id: string; name: string; document_number?: string | null }[];
    roles: { id: string; name: string; slug: string }[];
    canVerify: boolean;
    canEdit: boolean;
}>();

const { clubPath } = useOrganization();
const page = usePage<PageProps>();
const fileError = computed(() => (page.props.errors as Record<string, string> | undefined)?.files ?? '');
const dataForm = useForm({
    ...props.form,
    vehicle_role: props.form.vehicle_role ?? 'driver',
    pilot_person_id: props.form.pilot_person_id ?? '',
    license_categories: props.form.license_categories ?? [],
    motorcycle: {
        vehicle_type: 'motorcycle',
        brand: '',
        line: '',
        model: '',
        year: '',
        color: '',
        engine_displacement: '',
        plate: '',
        owner_name: '',
        owner_document_number: '',
        ...(props.form.motorcycle ?? {}),
    },
    health: {
        eps: '',
        blood_type: '',
        rh: '',
        weight_kg: '',
        height_cm: '',
        special_treatment: '',
        medications: '',
        allergies: '',
        ...(props.form.health ?? {}),
    },
    emergency_contact: {
        name: '',
        phone: '',
        address: '',
        relationship: '',
        ...(props.form.emergency_contact ?? {}),
    },
    metadata: {
        church_name: '',
        church_district: '',
        church_role: '',
        baptized: false,
        baptized_at: '',
        ...(props.form.metadata ?? {}),
    },
});
const accessForm = useForm({
    email: props.application.email || props.application.person?.email || '',
    password: '',
    role_id: props.roles.find((role) => role.slug === 'miembro')?.id ?? props.roles[0]?.id ?? '',
});
const uploading = ref<string | null>(null);
const role = computed(() => dataForm.vehicle_role ?? 'driver');

const verify = () => router.post(clubPath(`inscripciones/${props.application.id}/verificar`));
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
            :kicker="application.reference ? `Verificación · ${application.reference}` : 'Verificación'"
            :title="`${application.person?.first_name ?? ''} ${application.person?.last_name ?? 'Solicitud'}`"
            :description="canEdit
                ? 'La solicitud está pendiente. Puedes corregir datos y documentos antes de verificar.'
                : 'Revisa los documentos. Si están correctos, verifica y luego otorga acceso al club.'"
        />

        <section v-if="!canEdit" class="panel mb-4 p-5">
            <p class="text-sm text-cream/70">Cédula {{ application.person?.document_number }} · {{ application.email }}</p>
            <ul class="mt-3 space-y-1 text-sm">
                <li v-for="document in application.person?.documents ?? []" :key="document.id">
                    {{ document.type?.name }} — {{ document.status }}
                </li>
            </ul>
        </section>

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
            <article v-for="item in documents" :key="item.slug" class="rounded-xl border border-white/10 p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ item.name }}</p>
                        <p class="text-xs uppercase text-cream/50">{{ item.files.length ? (item.status ?? 'pendiente') : 'Sin archivo' }}</p>
                    </div>
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
                </div>
                <ul v-if="item.files.length" class="space-y-1 text-sm">
                    <li v-for="file in item.files" :key="file.id">
                        <a :href="file.url" class="text-ember-300 hover:underline" target="_blank" rel="noreferrer">
                            {{ file.filename }}
                        </a>
                    </li>
                </ul>
            </article>
        </section>

        <div v-if="canVerify && application.status === 'submitted'" class="mb-4">
            <button class="btn-primary" @click="verify">Verificar documentación</button>
        </div>

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
