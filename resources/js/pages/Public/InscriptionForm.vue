<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import InscriptionFormFields from '@/components/InscriptionFormFields.vue';
import InscriptionWizardNav from '@/components/InscriptionWizardNav.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    club: { name: string; city?: string | null; slug: string };
    token: string;
    form: Record<string, any>;
    filled: string[];
    warnings?: string[];
    pilots?: { id: string; name: string; document_number?: string | null }[];
    role?: 'driver' | 'passenger';
}>();

const form = useForm({
    ...props.form,
    vehicle_role: props.form.vehicle_role ?? props.role ?? 'driver',
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

const role = computed(() => props.role ?? form.vehicle_role ?? 'driver');
const isPassenger = computed(() => role.value === 'passenger');
const stepMessage = ref('');

const steps = computed(() => {
    const items = [
        { key: 'identity', label: 'Identidad' },
        { key: 'contact', label: 'Contacto' },
        { key: 'church', label: 'Iglesia' },
        { key: 'health', label: 'Salud' },
    ];

    if (isPassenger.value) {
        items.push({ key: 'pilot', label: 'Piloto' });
    } else {
        items.push({ key: 'license', label: 'Licencia' });
        items.push({ key: 'vehicle', label: 'Vehículo' });
    }

    return items;
});

const stepIndex = ref(0);
const current = computed(() => steps.value[stepIndex.value] ?? steps.value[0]);
const isFirst = computed(() => stepIndex.value === 0);
const isLast = computed(() => stepIndex.value === steps.value.length - 1);

const goTo = (index: number) => {
    stepIndex.value = Math.min(Math.max(index, 0), steps.value.length - 1);
    stepMessage.value = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const validateCurrent = (): boolean => {
    if (current.value.key === 'identity') {
        if (! form.first_name || ! form.last_name || ! form.document_number) {
            stepMessage.value = 'Completa nombres, primer apellido y número de documento.';
            return false;
        }
    }

    if (current.value.key === 'contact' && ! isPassenger.value) {
        if (! form.email || ! form.phone || ! form.city) {
            stepMessage.value = 'Completa correo, teléfono y municipio.';
            return false;
        }
    }

    if (current.value.key === 'pilot' && ! form.pilot_person_id) {
        stepMessage.value = 'Elige un piloto ya inscrito en el club.';
        return false;
    }

    return true;
};

const next = () => {
    if (! validateCurrent()) {
        return;
    }

    goTo(stepIndex.value + 1);
};

const back = () => goTo(stepIndex.value - 1);

const submit = () => {
    if (! validateCurrent()) {
        return;
    }

    form.post(`/i/${props.token}/formulario`);
};

watch(
    () => form.errors,
    (errors) => {
        if (errors.pilot_person_id) {
            goTo(steps.value.findIndex((step) => step.key === 'pilot'));
            return;
        }
        if (errors.email) {
            goTo(steps.value.findIndex((step) => step.key === 'contact'));
            return;
        }
        if (errors.document_number) {
            goTo(steps.value.findIndex((step) => step.key === 'identity'));
            return;
        }
        if (errors.license_document_number) {
            goTo(steps.value.findIndex((step) => step.key === 'license'));
        }
    },
);
</script>

<template>
    <PublicLayout :club="club">
        <InscriptionWizardNav :token="token" current="form" basics-done />
        <p class="mb-4 text-sm text-cream/60">
            Completa el formato AMM paso a paso. Si subiste documentos, los campos en ámbar vienen de lo que se pudo leer.
        </p>
        <div v-if="warnings?.length" class="mb-4 space-y-2">
            <p v-for="warning in warnings" :key="warning" class="rounded-xl border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                {{ warning }}
            </p>
        </div>

        <ol class="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-6">
            <li v-for="(item, index) in steps" :key="item.key">
                <button
                    type="button"
                    class="flex w-full items-center gap-2 rounded-xl border px-2 py-2 text-left text-xs transition"
                    :class="index === stepIndex
                        ? 'border-ember-400 bg-ember-500/15 text-ember-200'
                        : index < stepIndex
                            ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100'
                            : 'border-white/10 bg-white/5 text-cream/50'"
                    @click="goTo(index)"
                >
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-current">{{ index + 1 }}</span>
                    <span class="truncate">{{ item.label }}</span>
                </button>
            </li>
        </ol>

        <form class="space-y-4" novalidate @submit.prevent="submit">
            <InscriptionFormFields
                :form="form"
                :filled="filled"
                :pilots="pilots"
                :role="role"
                :step="current.key"
            />
            <p v-if="stepMessage" class="text-sm text-red-300">{{ stepMessage }}</p>
            <p v-if="form.errors.document_number" class="text-sm text-red-300">{{ form.errors.document_number }}</p>
            <p v-if="form.errors.license_document_number" class="text-sm text-red-300">{{ form.errors.license_document_number }}</p>
            <p v-if="form.errors.pilot_person_id" class="text-sm text-red-300">{{ form.errors.pilot_person_id }}</p>
            <p v-if="form.errors.email" class="text-sm text-red-300">{{ form.errors.email }}</p>
            <div class="flex flex-col gap-2 sm:flex-row sm:justify-between">
                <button type="button" class="btn-ghost" :disabled="isFirst" @click="back">Atrás</button>
                <button v-if="!isLast" type="button" class="btn-primary" @click="next">Siguiente</button>
                <button v-else class="btn-primary" :disabled="form.processing">Enviar inscripción</button>
            </div>
        </form>
    </PublicLayout>
</template>
