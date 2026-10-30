<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import FormField from '@/components/FormField.vue';
import InscriptionWizardNav from '@/components/InscriptionWizardNav.vue';
import InscriptionLookupForm from '@/components/InscriptionLookupForm.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    club: { name: string; city?: string | null; slug: string };
    token: string;
    from_later_step?: boolean;
    form: {
        document_type: string;
        document_number: string;
        first_name: string;
        last_name: string;
        second_last_name?: string;
        vehicle_role: string;
    };
}>();

const form = useForm({
    document_type: props.form.document_type || 'cc',
    document_number: props.form.document_number || '',
    first_name: props.form.first_name || '',
    last_name: props.form.last_name || '',
    second_last_name: props.form.second_last_name || '',
    vehicle_role: props.form.vehicle_role || '',
});

const submit = () => form.post(`/i/${props.token}/datos`);
</script>

<template>
    <PublicLayout :club="club">
        <InscriptionWizardNav :token="token" current="basics" :basics-done="from_later_step" />
        <section class="panel p-5">
            <h2 class="font-display text-2xl">{{ from_later_step ? 'Revisa tus datos' : 'Paso 1 · Datos básicos' }}</h2>
            <p class="mt-1 mb-5 text-sm text-cream/60">
                {{ from_later_step
                    ? 'Confirma que el número y los nombres estén bien. Estos son los que contrastamos con los documentos.'
                    : 'Primero dinos quién eres y si vienes como piloto o copiloto. Luego puedes subir documentos o ir al formulario.' }}
            </p>

            <form class="grid gap-3 md:grid-cols-2" @submit.prevent="submit">
                <FormField label="Tipo de documento">
                    <select v-model="form.document_type" class="field" required>
                        <option value="cc">Cédula de ciudadanía</option>
                        <option value="ti">Tarjeta de identidad</option>
                        <option value="ce">Cédula de extranjería</option>
                        <option value="passport">Pasaporte</option>
                    </select>
                </FormField>
                <FormField label="Número de documento">
                    <input v-model="form.document_number" class="field" required />
                </FormField>
                <FormField label="Nombres">
                    <input v-model="form.first_name" class="field" required />
                </FormField>
                <FormField label="Primer apellido">
                    <input v-model="form.last_name" class="field" required />
                </FormField>
                <FormField label="Segundo apellido">
                    <input v-model="form.second_last_name" class="field" />
                </FormField>
                <FormField label="¿Eres piloto o copiloto?" span>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="rounded-xl border px-4 py-3 text-left"
                            :class="form.vehicle_role === 'driver' ? 'border-ember-400 bg-ember-500/15 text-ember-200' : 'border-white/10 bg-white/5'"
                            @click="form.vehicle_role = 'driver'"
                        >
                            <p class="font-medium">Piloto</p>
                            <p class="text-xs text-cream/50">Puedes adjuntar cédula, licencia, tarjeta, SOAT y tecnomecánica. No es obligatorio.</p>
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border px-4 py-3 text-left"
                            :class="form.vehicle_role === 'passenger' ? 'border-ember-400 bg-ember-500/15 text-ember-200' : 'border-white/10 bg-white/5'"
                            @click="form.vehicle_role = 'passenger'"
                        >
                            <p class="font-medium">Copiloto</p>
                            <p class="text-xs text-cream/50">La cédula es opcional. Si completas el formulario, elige un piloto del club.</p>
                        </button>
                    </div>
                </FormField>
                <p v-if="form.errors.vehicle_role" class="text-sm text-red-300 md:col-span-2">{{ form.errors.vehicle_role }}</p>
                <button class="btn-primary md:col-span-2" :disabled="form.processing || !form.vehicle_role">
                    {{ from_later_step ? 'Guardar y volver' : 'Continuar' }}
                </button>
            </form>
        </section>

        <div class="mt-6">
            <InscriptionLookupForm :token="token" />
        </div>
    </PublicLayout>
</template>
