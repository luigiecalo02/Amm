<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import FormField from '@/components/FormField.vue';
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useOrganization } from '@/composables/useOrganization';

const { clubPath } = useOrganization();

const form = useForm({
    first_name: '',
    last_name: '',
    second_last_name: '',
    document_type: 'cc',
    document_number: '',
    document_issued_in: '',
    birth_date: '',
    birth_city: '',
    birth_department: '',
    gender: 'masculino',
    marital_status: 'soltero',
    email: '',
    phone: '',
    address: '',
    neighborhood: '',
    city: '',
    department: '',
    membership_number: '',
    vehicle_role: 'driver',
    metadata: {
        church_name: '',
        church_district: '',
        church_role: '',
        baptized: false,
        baptized_at: '',
    },
    health: {
        eps: '',
        blood_type: '',
        rh: 'positivo',
        weight_kg: '',
        height_cm: '',
        special_treatment: '',
        medications: '',
        allergies: '',
    },
    emergency_contact: {
        name: '',
        phone: '',
        address: '',
        relationship: '',
    },
    motorcycle: {
        vehicle_type: 'motorcycle',
        brand: '',
        model: '',
        engine_displacement: '',
        plate: '',
    },
});

watch(
    () => form.metadata.baptized,
    (baptized) => {
        if (!baptized) {
            form.metadata.baptized_at = '';
        }
    },
);

const submit = () => form.post(clubPath('people'));
</script>

<template>
    <AppLayout>
        <h1 class="font-display text-3xl">Formulario de inscripción</h1>
        <p class="mt-1 text-sm text-cream/60">Mismos datos del formato AMM Cartagena: persona, iglesia, salud, emergencia y vehículo.</p>

        <form class="mt-6 space-y-6" @submit.prevent="submit">
            <section class="panel grid gap-3 p-5 md:grid-cols-2">
                <h2 class="font-display text-xl md:col-span-2">I. Información personal</h2>
                <FormField label="Nombres">
                    <input v-model="form.first_name" class="field" required />
                </FormField>
                <FormField label="Primer apellido">
                    <input v-model="form.last_name" class="field" required />
                </FormField>
                <FormField label="Segundo apellido">
                    <input v-model="form.second_last_name" class="field" />
                </FormField>
                <FormField label="Tipo de documento">
                    <select v-model="form.document_type" class="field">
                        <option value="cc">Cédula de ciudadanía</option>
                        <option value="ti">Tarjeta de identidad</option>
                        <option value="ce">Cédula de extranjería</option>
                        <option value="passport">Pasaporte</option>
                    </select>
                </FormField>
                <FormField label="Número de documento">
                    <input v-model="form.document_number" class="field" required />
                </FormField>
                <FormField label="Expedida en">
                    <input v-model="form.document_issued_in" class="field" />
                </FormField>
                <FormField label="Fecha de nacimiento">
                    <input v-model="form.birth_date" type="date" class="field" />
                </FormField>
                <FormField label="Municipio de nacimiento">
                    <input v-model="form.birth_city" class="field" />
                </FormField>
                <FormField label="Departamento de nacimiento">
                    <input v-model="form.birth_department" class="field" />
                </FormField>
                <FormField label="Sexo">
                    <select v-model="form.gender" class="field">
                        <option value="masculino">Masculino</option>
                        <option value="femenino">Femenino</option>
                    </select>
                </FormField>
                <FormField label="Estado civil">
                    <select v-model="form.marital_status" class="field">
                        <option value="soltero">Soltero</option>
                        <option value="casado">Casado</option>
                        <option value="union_libre">Unión libre</option>
                        <option value="viudo">Viudo</option>
                    </select>
                </FormField>
                <FormField label="Correo">
                    <input v-model="form.email" type="email" class="field" />
                </FormField>
                <FormField label="Teléfono">
                    <input v-model="form.phone" class="field" />
                </FormField>
                <FormField label="Dirección de residencia" span>
                    <input v-model="form.address" class="field" />
                </FormField>
                <FormField label="Barrio">
                    <input v-model="form.neighborhood" class="field" />
                </FormField>
                <FormField label="Municipio">
                    <input v-model="form.city" class="field" />
                </FormField>
                <FormField label="Departamento">
                    <input v-model="form.department" class="field" />
                </FormField>
                <FormField label="Iglesia a la que pertenece">
                    <input v-model="form.metadata.church_name" class="field" />
                </FormField>
                <FormField label="Distrito">
                    <input v-model="form.metadata.church_district" class="field" />
                </FormField>
                <FormField label="Cargo en la iglesia">
                    <input v-model="form.metadata.church_role" class="field" />
                </FormField>
                <FormField label="Bautizado">
                    <select v-model="form.metadata.baptized" class="field">
                        <option :value="true">Sí</option>
                        <option :value="false">No</option>
                    </select>
                </FormField>
                <FormField v-if="form.metadata.baptized" label="Fecha de bautismo">
                    <input v-model="form.metadata.baptized_at" type="date" class="field" />
                </FormField>
                <FormField label="Peso (kg)">
                    <input v-model="form.health.weight_kg" type="number" step="0.1" class="field" />
                </FormField>
                <FormField label="Altura (cm)">
                    <input v-model="form.health.height_cm" type="number" step="0.1" class="field" />
                </FormField>
            </section>

            <section class="panel grid gap-3 p-5 md:grid-cols-2">
                <h2 class="font-display text-xl md:col-span-2">II. Información de salud</h2>
                <FormField label="EPS">
                    <input v-model="form.health.eps" class="field" />
                </FormField>
                <FormField label="Tipo de sangre">
                    <select v-model="form.health.blood_type" class="field">
                        <option value="">Seleccione</option>
                        <option value="O">O</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="AB">AB</option>
                    </select>
                </FormField>
                <FormField label="RH">
                    <select v-model="form.health.rh" class="field">
                        <option value="positivo">Positivo</option>
                        <option value="negativo">Negativo</option>
                    </select>
                </FormField>
                <FormField label="¿Está realizando algún tratamiento especial?" span>
                    <input v-model="form.health.special_treatment" class="field" />
                </FormField>
                <FormField label="¿Está tomando algún medicamento especial?" span>
                    <input v-model="form.health.medications" class="field" />
                </FormField>
                <FormField label="¿Tiene alguna alergia?" span>
                    <input v-model="form.health.allergies" class="field" />
                </FormField>
            </section>

            <section class="panel grid gap-3 p-5 md:grid-cols-2">
                <h2 class="font-display text-xl md:col-span-2">Contacto de emergencia</h2>
                <FormField label="Nombre">
                    <input v-model="form.emergency_contact.name" class="field" />
                </FormField>
                <FormField label="Teléfono">
                    <input v-model="form.emergency_contact.phone" class="field" />
                </FormField>
                <FormField label="Dirección">
                    <input v-model="form.emergency_contact.address" class="field" />
                </FormField>
                <FormField label="Parentesco">
                    <input v-model="form.emergency_contact.relationship" class="field" />
                </FormField>
            </section>

            <section class="panel grid gap-3 p-5 md:grid-cols-2">
                <h2 class="font-display text-xl md:col-span-2">III. Información del vehículo</h2>
                <FormField label="Tipo de vehículo">
                    <select v-model="form.motorcycle.vehicle_type" class="field">
                        <option value="motorcycle">Moto</option>
                        <option value="car">Carro</option>
                    </select>
                </FormField>
                <FormField label="Rol en el club">
                    <select v-model="form.vehicle_role" class="field">
                        <option value="driver">Conductor</option>
                        <option value="passenger">Acompañante</option>
                        <option value="both">Ambos</option>
                    </select>
                </FormField>
                <FormField label="Marca">
                    <input v-model="form.motorcycle.brand" class="field" />
                </FormField>
                <FormField label="Modelo">
                    <input v-model="form.motorcycle.model" class="field" />
                </FormField>
                <FormField label="Cilindraje">
                    <input v-model="form.motorcycle.engine_displacement" type="number" class="field" />
                </FormField>
                <FormField label="Placa">
                    <input v-model="form.motorcycle.plate" class="field" />
                </FormField>
            </section>

            <p v-if="form.errors.document_number" class="text-sm text-red-300">{{ form.errors.document_number }}</p>
            <button class="btn-primary" :disabled="form.processing">Guardar inscripción</button>
        </form>
    </AppLayout>
</template>
