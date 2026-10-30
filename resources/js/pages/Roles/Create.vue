<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import RolePermissionCatalog from '@/components/RolePermissionCatalog.vue';
import type { PermissionItem } from '@/components/RolePermissionCatalog.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { useOrganization } from '@/composables/useOrganization';

const props = defineProps<{
    catalog: Record<string, PermissionItem[]>;
}>();

const { clubPath } = useOrganization();
const form = useForm({
    name: '',
    description: '',
    permissions: [] as string[],
});

const applySuperAdmin = () => {
    form.name = 'Superadministrador';
    form.description = 'Tiene todos los permisos del club';
    form.permissions = Object.values(props.catalog).flat().map((permission) => permission.slug);
};

const submit = () => form.post(clubPath('roles'));
</script>

<template>
    <AppLayout>
        <div class="mb-4">
            <Link :href="clubPath('roles')" class="text-sm text-cream/60 hover:text-cream">← Volver a roles</Link>
        </div>
        <PageHeader
            kicker="Roles"
            title="Nuevo rol"
            description="Este rol solo aplicará a este club y a los usuarios que lo tengan."
        />

        <form class="panel space-y-4 p-5" @submit.prevent="submit">
            <button type="button" class="btn-ghost" @click="applySuperAdmin">
                Superadministrador (todos los permisos)
            </button>
            <input v-model="form.name" class="field" placeholder="Nombre del rol" required />
            <input v-model="form.description" class="field" placeholder="Descripción" />
            <RolePermissionCatalog v-model="form.permissions" :catalog="catalog" />
            <p v-if="form.errors.name" class="text-sm text-red-300">{{ form.errors.name }}</p>
            <button class="btn-primary" :disabled="form.processing">Crear rol</button>
        </form>
    </AppLayout>
</template>
