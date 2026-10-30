<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    googleUser: { name: string | null; email: string | null; avatar: string | null };
    defaults: { first_name: string; last_name: string };
    organizations: { id: string; name: string; slug: string; city?: string | null }[];
    selectedClub: string;
}>();

const selected = props.organizations.find((org) => org.slug === props.selectedClub) ?? props.organizations[0];

const form = useForm({
    organization_id: selected?.id ?? '',
    first_name: props.defaults.first_name,
    last_name: props.defaults.last_name,
    second_last_name: '',
    document_type: 'cc',
    document_number: '',
    document_issued_in: '',
    birth_date: '',
    gender: 'masculino',
    phone: '',
    city: '',
    address: '',
    vehicle_role: 'driver',
    motorcycle: {
        vehicle_type: 'motorcycle',
        brand: '',
        model: '',
        engine_displacement: '',
        plate: '',
    },
});

const submit = () => form.post('/registro');
</script>

<template>
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,_#243044,_#07090D_60%)] px-4 py-10">
        <div class="mx-auto w-full max-w-3xl panel p-6 md:p-8">
            <div class="flex items-center gap-4">
                <img src="/images/amm-logo.png" alt="AMM" class="h-20 w-20 rounded-full bg-white object-contain p-1" />
                <div>
                    <p class="font-display text-xs uppercase tracking-[0.3em] text-ember-400">Inscripción</p>
                    <h1 class="font-display text-3xl">Completa tus datos</h1>
                    <p class="text-sm text-cream/60">Entraste con Google: {{ googleUser.email }}</p>
                </div>
            </div>

            <form class="mt-8 space-y-6" @submit.prevent="submit">
                <section class="grid gap-3 md:grid-cols-2">
                    <FormField label="Club">
                        <select v-model="form.organization_id" class="field" required>
                            <option v-for="org in organizations" :key="org.id" :value="org.id">
                                {{ org.name }}
                            </option>
                        </select>
                    </FormField>
                    <FormField label="Correo Google">
                        <input :value="googleUser.email || ''" class="field opacity-70" disabled />
                    </FormField>
                    <FormField label="Nombres">
                        <input v-model="form.first_name" class="field" required />
                    </FormField>
                    <FormField label="Apellidos">
                        <input v-model="form.last_name" class="field" required />
                    </FormField>
                    <FormField label="Tipo de documento">
                        <select v-model="form.document_type" class="field">
                            <option value="cc">Cédula</option>
                            <option value="ti">TI</option>
                            <option value="ce">CE</option>
                            <option value="passport">Pasaporte</option>
                        </select>
                    </FormField>
                    <FormField label="Número de documento">
                        <input v-model="form.document_number" class="field" required />
                        <p v-if="form.errors.document_number" class="mt-1 text-xs text-red-300">{{ form.errors.document_number }}</p>
                    </FormField>
                    <FormField label="Expedida en">
                        <input v-model="form.document_issued_in" class="field" />
                    </FormField>
                    <FormField label="Fecha de nacimiento">
                        <input v-model="form.birth_date" type="date" class="field" />
                    </FormField>
                    <FormField label="Teléfono">
                        <input v-model="form.phone" class="field" required />
                    </FormField>
                    <FormField label="Municipio">
                        <input v-model="form.city" class="field" required />
                    </FormField>
                    <FormField label="Dirección" span>
                        <input v-model="form.address" class="field" />
                    </FormField>
                </section>

                <section class="grid gap-3 md:grid-cols-2">
                    <h2 class="font-display text-xl md:col-span-2">Tu moto</h2>
                    <FormField label="Rol">
                        <select v-model="form.vehicle_role" class="field">
                            <option value="driver">Conductor</option>
                            <option value="passenger">Acompañante</option>
                        </select>
                    </FormField>
                    <FormField label="Marca">
                        <input v-model="form.motorcycle.brand" class="field" />
                    </FormField>
                    <FormField label="Placa">
                        <input v-model="form.motorcycle.plate" class="field" />
                    </FormField>
                    <FormField label="Cilindraje">
                        <input v-model="form.motorcycle.engine_displacement" type="number" class="field" />
                    </FormField>
                </section>

                <button class="btn-primary w-full" :disabled="form.processing">Guardar inscripción</button>
            </form>
        </div>
    </div>
</template>
