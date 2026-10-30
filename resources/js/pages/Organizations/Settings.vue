<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const props = defineProps<{ club: any; canUpdate?: boolean }>();
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
            <input v-model="form.name" class="field md:col-span-2" placeholder="Nombre" :disabled="!canUpdate" />
            <input v-model="form.email" class="field" placeholder="Correo" :disabled="!canUpdate" />
            <input v-model="form.phone" class="field" placeholder="Teléfono" :disabled="!canUpdate" />
            <input v-model="form.city" class="field" placeholder="Ciudad" :disabled="!canUpdate" />
            <input v-model="form.department" class="field" placeholder="Departamento" :disabled="!canUpdate" />
            <textarea v-model="form.description" class="field md:col-span-2" placeholder="Descripción" :disabled="!canUpdate"></textarea>
            <button v-if="canUpdate" class="btn-primary md:col-span-2" :disabled="form.processing">Guardar club</button>
        </form>
    </AppLayout>
</template>
