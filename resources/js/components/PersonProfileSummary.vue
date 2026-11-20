<script setup lang="ts">
import LicensePlate from '@/components/LicensePlate.vue';
import InscriptionFormFields from '@/components/InscriptionFormFields.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useOrganization } from '@/composables/useOrganization';
import { inscriptionFormDefaults } from '@/lib/inscriptionForm';

type Pilot = {
    id: string;
    name: string;
    document_type?: string | null;
    document_number?: string | null;
    phone?: string | null;
    email?: string | null;
    plate?: string | null;
    brand?: string | null;
    line?: string | null;
};

const props = defineProps<{
    form: Record<string, any>;
    email?: string | null;
    pilots?: Pilot[];
    pilotChoices?: Pilot[];
    canEdit?: boolean;
    personId?: string | null;
}>();

const { clubPath } = useOrganization();
const summaryIndex = ref(0);
const editing = ref(false);
const draft = useForm(inscriptionFormDefaults(props.form));
const role = computed(() => props.form.vehicle_role ?? 'driver');
const editableSteps: Record<string, string> = {
    Identidad: 'identity',
    Contacto: 'contact',
    Iglesia: 'church',
    Salud: 'health',
    Piloto: 'pilot',
};

const documentTypeLabel: Record<string, string> = {
    cc: 'Cédula de ciudadanía',
    ti: 'Tarjeta de identidad',
    ce: 'Cédula de extranjería',
    passport: 'Pasaporte',
};
const roleLabel: Record<string, string> = {
    driver: 'Piloto',
    passenger: 'Copiloto',
    both: 'Ambos',
};
const maritalLabel: Record<string, string> = {
    soltero: 'Soltero',
    casado: 'Casado',
    union_libre: 'Unión libre',
    viudo: 'Viudo',
};
const genderLabel: Record<string, string> = {
    masculino: 'Masculino',
    femenino: 'Femenino',
};
const vehicleTypeLabel: Record<string, string> = {
    motorcycle: 'Moto',
    car: 'Carro',
};

const shown = (value: unknown) => {
    if (value === true) {
        return 'Sí';
    }

    if (value === false) {
        return 'No';
    }

    if (value == null || value === '') {
        return '—';
    }

    return String(value);
};

const labeled = (value: unknown, labels: Record<string, string>) => {
    const text = shown(value);

    return labels[text] ?? text;
};

const licenseCategories = computed(() => {
    const items = Array.isArray(props.form.license_categories) ? props.form.license_categories : [];

    return items
        .map((item: { category?: string; expires_at?: string }) => [item?.category, item?.expires_at].filter(Boolean).join(' · '))
        .filter(Boolean)
        .join(', ');
});

type SummaryField = { label: string; value?: string; span?: boolean; heading?: boolean };

const summarySections = computed(() => {
    const form = props.form;
    const sections: { title: string; fields: SummaryField[] }[] = [
        {
            title: 'Identidad',
            fields: [
                { label: 'Nombre', value: shown(form.first_name) },
                { label: 'Segundo nombre', value: shown(form.middle_name) },
                { label: 'Primer apellido', value: shown(form.last_name) },
                { label: 'Segundo apellido', value: shown(form.second_last_name) },
                { label: 'Tipo de documento', value: labeled(form.document_type, documentTypeLabel) },
                { label: 'Número de documento', value: shown(form.document_number) },
                { label: 'Expedida en', value: shown(form.document_issued_in) },
            ],
        },
        {
            title: 'Contacto',
            fields: [
                { label: 'Fecha de nacimiento', value: shown(form.birth_date) },
                { label: 'Municipio de nacimiento', value: shown(form.birth_city) },
                { label: 'Departamento de nacimiento', value: shown(form.birth_department) },
                { label: 'Sexo', value: labeled(form.gender, genderLabel) },
                { label: 'Estado civil', value: labeled(form.marital_status, maritalLabel) },
                { label: 'Correo', value: shown(form.email || props.email) },
                { label: 'Teléfono', value: shown(form.phone) },
                { label: 'Dirección de residencia', value: shown(form.address), span: true },
                { label: 'Barrio', value: shown(form.neighborhood) },
                { label: 'Municipio', value: shown(form.city) },
                { label: 'Departamento', value: shown(form.department) },
            ],
        },
        {
            title: 'Iglesia',
            fields: [
                { label: 'Iglesia a la que pertenece', value: shown(form.metadata?.church_name) },
                { label: 'Distrito', value: shown(form.metadata?.church_district) },
                { label: 'Cargo en la iglesia', value: shown(form.metadata?.church_role) },
                { label: 'Bautizado', value: shown(form.metadata?.baptized) },
                ...(form.metadata?.baptized
                    ? [{ label: 'Fecha de bautismo', value: shown(form.metadata?.baptized_at) }]
                    : []),
            ],
        },
        {
            title: 'Salud',
            fields: [
                { label: 'EPS', value: shown(form.health?.eps) },
                { label: 'Tipo de sangre', value: shown(form.health?.blood_type) },
                { label: 'RH', value: labeled(form.health?.rh, { positivo: 'Positivo', negativo: 'Negativo' }) },
                { label: 'Peso (kg)', value: shown(form.health?.weight_kg) },
                { label: 'Altura (cm)', value: shown(form.health?.height_cm) },
                { label: '¿Está realizando algún tratamiento especial?', value: shown(form.health?.special_treatment), span: true },
                { label: '¿Está tomando algún medicamento especial?', value: shown(form.health?.medications), span: true },
                { label: '¿Tiene alguna alergia?', value: shown(form.health?.allergies), span: true },
                { label: 'Contacto de emergencia', heading: true },
                { label: 'Nombre', value: shown(form.emergency_contact?.name) },
                { label: 'Teléfono', value: shown(form.emergency_contact?.phone) },
                { label: 'Dirección', value: shown(form.emergency_contact?.address) },
                { label: 'Parentesco', value: shown(form.emergency_contact?.relationship) },
            ],
        },
    ];

    if (role.value === 'passenger') {
        const pilot = props.pilots?.find((item) => item.id === form.pilot_person_id);

        sections.push({
            title: 'Piloto',
            fields: [
                { label: 'Nombre', value: shown(pilot?.name) },
                { label: 'Tipo de documento', value: labeled(pilot?.document_type, documentTypeLabel) },
                { label: 'Número de documento', value: shown(pilot?.document_number) },
                { label: 'Teléfono', value: shown(pilot?.phone) },
                { label: 'Correo', value: shown(pilot?.email) },
                { label: 'Marca', value: shown(form.motorcycle?.brand) },
                { label: 'Línea', value: shown(form.motorcycle?.line || form.motorcycle?.model) },
            ],
        });

        return sections;
    }

    sections.push(
        {
            title: 'Licencia',
            fields: [
                { label: 'Identificación de la licencia', value: shown(form.license_document_number) },
                { label: 'Vigencia general', value: shown(form.license_expiration) },
                { label: 'Categorías', value: shown(licenseCategories.value), span: true },
            ],
        },
        {
            title: 'Vehículo',
            fields: [
                { label: 'Tipo de vehículo', value: labeled(form.motorcycle?.vehicle_type, vehicleTypeLabel) },
                { label: 'Rol en el club', value: roleLabel[role.value] ?? shown(role.value) },
                { label: 'Marca', value: shown(form.motorcycle?.brand) },
                { label: 'Línea', value: shown(form.motorcycle?.line || form.motorcycle?.model) },
                { label: 'Modelo (año)', value: shown(form.motorcycle?.year) },
                { label: 'Color', value: shown(form.motorcycle?.color) },
                { label: 'Cilindraje', value: shown(form.motorcycle?.engine_displacement) },
                { label: 'Propietario', value: shown(form.motorcycle?.owner_name) },
                { label: 'Identificación del propietario', value: shown(form.motorcycle?.owner_document_number) },
                { label: 'Vence SOAT', value: shown(form.soat_expiration) },
                { label: 'Vence tecnomecánica', value: shown(form.tecnomecanica_expiration) },
            ],
        },
    );

    return sections;
});

const activeSummary = computed(() => summarySections.value[summaryIndex.value] ?? summarySections.value[0]);
const plate = computed(() => String(props.form.motorcycle?.plate ?? '').trim());
const plateCaption = computed(() => {
    const owner = String(props.form.motorcycle?.owner_name ?? '').trim();

    if (owner) {
        return owner;
    }

    return [props.form.first_name, props.form.middle_name, props.form.last_name, props.form.second_last_name]
        .filter(Boolean)
        .join(' ');
});
const showPlate = computed(() => plate.value !== '' && ['Vehículo', 'Piloto'].includes(activeSummary.value?.title ?? '') && !editing.value);
const editStep = computed(() => editableSteps[activeSummary.value?.title ?? ''] ?? '');
const canEditSection = computed(() => Boolean(props.canEdit && props.personId && editStep.value));
const choices = computed(() => (props.pilotChoices?.length ? props.pilotChoices : props.pilots) ?? []);
const previewPilot = computed(() => choices.value.find((item) => item.id === draft.pilot_person_id));
const pilotPreview = computed(() => [
    { label: 'Tipo de documento', value: labeled(previewPilot.value?.document_type, documentTypeLabel) },
    { label: 'Número de documento', value: shown(previewPilot.value?.document_number) },
    { label: 'Teléfono', value: shown(previewPilot.value?.phone) },
    { label: 'Correo', value: shown(previewPilot.value?.email) },
    { label: 'Placa', value: shown(previewPilot.value?.plate) },
    { label: 'Marca', value: shown(previewPilot.value?.brand) },
    { label: 'Línea', value: shown(previewPilot.value?.line) },
]);
const saveErrors = computed(() => Object.values(draft.errors).map((error) => (
    Array.isArray(error) ? String(error[0]) : String(error)
)).filter((error) => error !== ''));

const startEdit = () => {
    draft.defaults(inscriptionFormDefaults(props.form));
    draft.reset();
    draft.clearErrors();
    editing.value = true;
};

const cancelEdit = () => {
    editing.value = false;
    draft.clearErrors();
};

const save = () => {
    if (! props.personId) {
        return;
    }

    draft.put(clubPath(`people/${props.personId}/resumen`), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false;
        },
    });
};

watch(summaryIndex, () => {
    editing.value = false;
});
</script>

<template>
    <div class="space-y-4">
        <ol class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-6">
            <li v-for="(section, index) in summarySections" :key="section.title">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-xl border px-2 py-2 text-left text-xs transition"
                    :class="index === summaryIndex
                        ? 'border-ember-400 bg-ember-500/15 text-ember-200'
                        : 'border-white/10 bg-white/5 text-cream/50 hover:border-white/25'"
                    @click="summaryIndex = index"
                >
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-current">{{ index + 1 }}</span>
                    <span class="truncate">{{ section.title }}</span>
                </button>
            </li>
        </ol>
        <section v-if="activeSummary" class="panel p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-display text-xl text-cream">{{ activeSummary.title }}</h2>
                <button v-if="canEditSection && !editing" type="button" class="btn-ghost" @click="startEdit">Editar</button>
            </div>
            <template v-if="editing && editStep">
                <InscriptionFormFields
                    class="mt-4"
                    :form="draft"
                    :step="editStep"
                    :role="role"
                    :pilots="choices"
                    plain
                />
                <dl v-if="editStep === 'pilot'" class="mt-4 grid gap-3 md:grid-cols-2">
                    <div v-for="field in pilotPreview" :key="field.label">
                        <dt class="text-xs uppercase tracking-widest text-cream/45">{{ field.label }}</dt>
                        <dd class="mt-1 text-sm text-cream/80">{{ field.value }}</dd>
                    </div>
                </dl>
                <p v-for="error in saveErrors" :key="error" class="mt-3 text-sm text-red-300">{{ error }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" class="btn-primary" :disabled="draft.processing" @click="save">
                        {{ draft.processing ? 'Guardando…' : 'Guardar' }}
                    </button>
                    <button type="button" class="btn-ghost" :disabled="draft.processing" @click="cancelEdit">Cancelar</button>
                </div>
            </template>
            <template v-else>
            <LicensePlate
                v-if="showPlate"
                class="mx-auto mt-4 w-full max-w-md"
                :plate="plate"
                :caption="plateCaption"
            />
            <dl class="mt-3 grid gap-3 md:grid-cols-2">
                <template v-for="field in activeSummary.fields" :key="`${activeSummary.title}-${field.label}`">
                    <h3 v-if="field.heading" class="font-display text-lg text-cream md:col-span-2">{{ field.label }}</h3>
                    <div v-else :class="field.span ? 'md:col-span-2' : ''">
                        <dt class="text-xs uppercase tracking-widest text-cream/45">{{ field.label }}</dt>
                        <dd class="mt-1 text-sm text-cream/80">{{ field.value }}</dd>
                    </div>
                </template>
            </dl>
            </template>
        </section>
    </div>
</template>
