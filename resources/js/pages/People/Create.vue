<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const form = useForm({
    document_type: 'cc',
    document_number: '',
    document_issued_in: '',
    first_name: '',
    last_name: '',
    second_last_name: '',
    birth_date: '',
    birth_city: '',
    birth_department: '',
    gender: 'masculino',
    marital_status: '',
    phone: '',
    email: '',
    address: '',
    neighborhood: '',
    city: '',
    department: '',
    membership_number: '',
    vehicle_role: 'driver',
    metadata: { church_name: '', church_district: '', baptized: false },
    health: { eps: '', blood_type: '', rh: '', weight_kg: '', height_cm: '', special_treatment: '', medications: '', allergies: '' },
    emergency_contact: { name: '', phone: '', address: '', relationship: '' },
});

const submit = () => form.post(`/app/${page.props.organization?.slug}/people`);
</script>

<template>
    <AppLayout>
        <h1 class="font-display text-3xl">Inscripción al club</h1>
        <p class="mt-1 text-sm text-cream/60">Persona, salud, contacto de emergencia y vínculo al club en un solo flujo.</p>

        <form class="mt-6 space-y-6" @submit.prevent="submit">
            <section class="panel grid gap-3 p-5 md:grid-cols-2">
                <h2 class="font-display text-xl md:col-span-2">Información personal</h2>
                <input v-model="form.first_name" class="field" placeholder="Nombres" required />
                <input v-model="form.last_name" class="field" placeholder="Primer apellido" required />
                <input v-model="form.second_last_name" class="field" placeholder="Segundo apellido" />
                <input v-model="form.document_number" class="field" placeholder="Número de documento" required />
                <input v-model="form.document_issued_in" class="field" placeholder="Expedido en" />
                <input v-model="form.birth_date" type="date" class="field" />
                <input v-model="form.phone" class="field" placeholder="Teléfono" />
                <input v-model="form.email" type="email" class="field" placeholder="Correo" />
                <input v-model="form.address" class="field md:col-span-2" placeholder="Dirección" />
                <input v-model="form.neighborhood" class="field" placeholder="Barrio" />
                <input v-model="form.city" class="field" placeholder="Municipio" />
            </section>

            <section class="panel grid gap-3 p-5 md:grid-cols-2">
                <h2 class="font-display text-xl md:col-span-2">Salud</h2>
                <input v-model="form.health.eps" class="field" placeholder="EPS" />
                <input v-model="form.health.blood_type" class="field" placeholder="Tipo de sangre" />
                <input v-model="form.health.rh" class="field" placeholder="RH" />
                <input v-model="form.health.allergies" class="field" placeholder="Alergias" />
            </section>

            <section class="panel grid gap-3 p-5 md:grid-cols-2">
                <h2 class="font-display text-xl md:col-span-2">Contacto de emergencia</h2>
                <input v-model="form.emergency_contact.name" class="field" placeholder="Nombre" />
                <input v-model="form.emergency_contact.phone" class="field" placeholder="Teléfono" />
                <input v-model="form.emergency_contact.relationship" class="field" placeholder="Parentesco" />
                <input v-model="form.emergency_contact.address" class="field" placeholder="Dirección" />
            </section>

            <button class="btn-primary" :disabled="form.processing">Guardar inscripción</button>
        </form>
    </AppLayout>
</template>
