<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const props = defineProps<{ person: any }>();
const page = usePage<PageProps>();

const form = useForm({
    document_type: props.person.document_type,
    document_number: props.person.document_number,
    first_name: props.person.first_name,
    last_name: props.person.last_name,
    second_last_name: props.person.second_last_name,
    phone: props.person.phone,
    email: props.person.email,
    address: props.person.address,
    city: props.person.city,
    health: props.person.health_profile || {},
    emergency_contact: props.person.emergency_contacts?.[0] || {},
});

const submit = () => form.put(`/app/${page.props.organization?.slug}/people/${props.person.id}`);
</script>

<template>
    <AppLayout>
        <h1 class="font-display text-3xl">Editar persona</h1>
        <form class="panel mt-6 grid gap-3 p-5 md:grid-cols-2" @submit.prevent="submit">
            <input v-model="form.first_name" class="field" placeholder="Nombres" />
            <input v-model="form.last_name" class="field" placeholder="Apellido" />
            <input v-model="form.phone" class="field" placeholder="Teléfono" />
            <input v-model="form.email" class="field" placeholder="Correo" />
            <input v-model="form.address" class="field md:col-span-2" placeholder="Dirección" />
            <button class="btn-primary md:col-span-2">Guardar cambios</button>
        </form>
    </AppLayout>
</template>
