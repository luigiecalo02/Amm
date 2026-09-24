<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

defineProps<{ users: any[]; roles: any[] }>();

const page = usePage<PageProps>();
const form = useForm({
    name: '',
    email: '',
    password: '',
    role_id: '',
});

const submit = () => form.post(`/app/${page.props.organization?.slug}/users`, { preserveScroll: true });
</script>

<template>
    <AppLayout>
        <h1 class="font-display text-3xl">Usuarios del club</h1>
        <div class="mt-4 space-y-3">
            <article v-for="user in users" :key="user.id" class="panel p-4">
                <p class="font-medium">{{ user.name }}</p>
                <p class="text-sm text-cream/50">{{ user.email }}</p>
                <p class="mt-1 text-xs uppercase text-ember-400">
                    {{ user.organization_roles?.map((item: any) => item.role?.name).join(', ') }}
                </p>
            </article>
        </div>
        <form class="panel mt-6 grid gap-3 p-5 md:grid-cols-2" @submit.prevent="submit">
            <h2 class="font-display text-xl md:col-span-2">Agregar usuario</h2>
            <input v-model="form.name" class="field" placeholder="Nombre" required />
            <input v-model="form.email" type="email" class="field" placeholder="Correo" required />
            <input v-model="form.password" type="password" class="field" placeholder="Contraseña" required />
            <select v-model="form.role_id" class="field" required>
                <option value="" disabled>Rol</option>
                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
            </select>
            <button class="btn-primary md:col-span-2">Crear acceso</button>
        </form>
    </AppLayout>
</template>
