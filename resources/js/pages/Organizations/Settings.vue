<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const props = defineProps<{ club: any }>();
const page = usePage<PageProps>();

const form = useForm({
    name: props.club.name,
    description: props.club.description,
    email: props.club.email,
    phone: props.club.phone,
    city: props.club.city,
    department: props.club.department,
    address: props.club.address,
    country: props.club.country,
});

const submit = () => form.put(`/app/${page.props.organization?.slug}/settings`);
</script>

<template>
    <AppLayout>
        <h1 class="font-display text-3xl">Configuración del club</h1>
        <form class="panel mt-6 grid gap-3 p-5 md:grid-cols-2" @submit.prevent="submit">
            <input v-model="form.name" class="field md:col-span-2" placeholder="Nombre" />
            <input v-model="form.email" class="field" placeholder="Correo" />
            <input v-model="form.phone" class="field" placeholder="Teléfono" />
            <input v-model="form.city" class="field" placeholder="Ciudad" />
            <input v-model="form.department" class="field" placeholder="Departamento" />
            <textarea v-model="form.description" class="field md:col-span-2" placeholder="Descripción"></textarea>
            <button class="btn-primary md:col-span-2">Guardar club</button>
        </form>
    </AppLayout>
</template>
