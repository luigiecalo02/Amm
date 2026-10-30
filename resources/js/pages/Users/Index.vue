<script setup lang="ts">
import { reactive, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';
import type { PageProps } from '@/types';

type RoleItem = { id: string; name: string };
type ClubUser = {
    id: string;
    name: string;
    email: string;
    organization_roles?: { role_id?: string; role?: { id: string; name: string } }[];
};

const props = defineProps<{ users: ClubUser[]; roles: RoleItem[] }>();

const page = usePage<PageProps>();
const { can } = usePermissions();
const roleByUser = reactive<Record<string, string>>({});

const syncRoles = (users: ClubUser[]) => {
    users.forEach((user) => {
        roleByUser[user.id] = user.organization_roles?.[0]?.role_id
            ?? user.organization_roles?.[0]?.role?.id
            ?? '';
    });
};

watch(() => props.users, syncRoles, { immediate: true, deep: true });

const form = useForm({
    name: '',
    email: '',
    password: '',
    role_id: '',
});

const submit = () => form.post(`/app/${page.props.organization?.slug}/users`, { preserveScroll: true });

const saveRole = (user: ClubUser) => {
    router.put(
        `/app/${page.props.organization?.slug}/users/${user.id}`,
        { role_id: roleByUser[user.id] },
        { preserveScroll: true },
    );
};
</script>

<template>
    <AppLayout>
        <h1 class="font-display text-3xl">Usuarios del club</h1>
        <p class="mt-1 text-sm text-cream/50">
            Cada usuario ve solo este club, sus miembros y los permisos de su rol. El superadministrador de plataforma no se agrega aquí.
        </p>

        <div class="mt-4 space-y-3">
            <article v-for="user in users" :key="user.id" class="panel grid gap-3 p-4 md:grid-cols-[1fr_14rem_auto] md:items-center">
                <div>
                    <p class="font-medium">{{ user.name }}</p>
                    <p class="text-sm text-cream/50">{{ user.email }}</p>
                </div>
                <select
                    v-if="can('users.update')"
                    v-model="roleByUser[user.id]"
                    class="field"
                    @change="saveRole(user)"
                >
                    <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                </select>
                <p v-else class="text-xs uppercase text-ember-400">
                    {{ user.organization_roles?.map((item) => item.role?.name).join(', ') }}
                </p>
            </article>
            <p v-if="!users.length" class="text-sm text-cream/50">Aún no hay usuarios en este club.</p>
        </div>

        <form v-if="can('users.create')" class="panel mt-6 grid gap-3 p-5 md:grid-cols-2" @submit.prevent="submit">
            <h2 class="font-display text-xl md:col-span-2">Agregar usuario</h2>
            <input v-model="form.name" class="field" placeholder="Nombre" required />
            <input v-model="form.email" type="email" class="field" placeholder="Correo" required />
            <input v-model="form.password" type="password" class="field" placeholder="Contraseña" required />
            <select v-model="form.role_id" class="field" required>
                <option value="" disabled>Rol</option>
                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
            </select>
            <p v-if="form.errors.email" class="text-sm text-red-300 md:col-span-2">{{ form.errors.email }}</p>
            <button class="btn-primary md:col-span-2" :disabled="form.processing">Crear acceso</button>
        </form>
    </AppLayout>
</template>
