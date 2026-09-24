<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

defineProps<{ roles: any[]; permissions: Record<string, any[]> }>();

const page = usePage<PageProps>();
const form = useForm({
    name: '',
    description: '',
    permissions: [] as string[],
});

const submit = () => form.post(`/app/${page.props.organization?.slug}/roles`, { preserveScroll: true });
</script>

<template>
    <AppLayout>
        <h1 class="font-display text-3xl">Roles y permisos</h1>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <article v-for="role in roles" :key="role.id" class="panel p-4">
                <p class="font-display text-xl">{{ role.name }}</p>
                <p class="text-xs text-cream/50">{{ role.is_system ? 'Rol de sistema' : 'Rol personalizado' }}</p>
                <p class="mt-2 text-sm text-cream/70">{{ role.permissions?.length }} permisos</p>
            </article>
        </div>
        <form class="panel mt-6 space-y-4 p-5" @submit.prevent="submit">
            <h2 class="font-display text-xl">Nuevo rol</h2>
            <input v-model="form.name" class="field" placeholder="Nombre del rol" required />
            <div v-for="(items, module) in permissions" :key="module">
                <p class="mb-2 text-xs uppercase tracking-widest text-cream/40">{{ module }}</p>
                <label v-for="permission in items" :key="permission.id" class="mb-1 flex items-center gap-2 text-sm">
                    <input v-model="form.permissions" type="checkbox" :value="permission.slug" />
                    {{ permission.name }}
                </label>
            </div>
            <button class="btn-primary">Crear rol</button>
        </form>
    </AppLayout>
</template>
