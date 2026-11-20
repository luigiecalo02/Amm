<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';
import type { PageProps } from '@/types';

type RoleItem = { id: string; name: string };
type ClubUser = {
    id: string;
    name: string;
    email: string;
    person?: { phone?: string | null } | null;
    organization_roles?: { role_id?: string; role?: { id: string; name: string } }[];
};

type SharedAccess = {
    message: string;
    copied: boolean;
};

const props = defineProps<{ users: ClubUser[]; roles: RoleItem[] }>();

const page = usePage<PageProps>();
const { can } = usePermissions();
const roleByUser = reactive<Record<string, string>>({});
const accessByUser = reactive<Record<string, { email: string; password: string }>>({});
const savingId = ref<string | null>(null);
const sharedByUser = reactive<Record<string, SharedAccess>>({});

const syncUsers = (users: ClubUser[]) => {
    users.forEach((user) => {
        roleByUser[user.id] = user.organization_roles?.[0]?.role_id
            ?? user.organization_roles?.[0]?.role?.id
            ?? '';

        if (! accessByUser[user.id]) {
            accessByUser[user.id] = { email: user.email, password: '' };
        }
    });
};

watch(() => props.users, syncUsers, { immediate: true, deep: true });

const form = useForm({
    name: '',
    email: '',
    password: '',
    role_id: '',
});

const submit = () => form.post(`/app/${page.props.organization?.slug}/users`, { preserveScroll: true });

const credentials = useForm({
    role_id: '',
    email: '',
    password: '',
});

const saveRole = (user: ClubUser) => {
    router.put(
        `/app/${page.props.organization?.slug}/users/${user.id}`,
        { role_id: roleByUser[user.id] },
        { preserveScroll: true },
    );
};

const whatsappNumber = (phone?: string | null) => {
    const digits = (phone ?? '').replace(/\D/g, '');

    if (digits.length === 10) {
        return `57${digits}`;
    }

    if (digits.startsWith('57') && digits.length >= 12) {
        return digits;
    }

    return digits.length > 10 ? digits : '';
};

const accessMessage = (user: ClubUser, email: string, password: string) => {
    const club = page.props.organization?.name ?? 'AMM';
    const login = `${window.location.origin}/login`;

    return [
        `Hola ${user.name}. Te comparto tu acceso a ${club}.`,
        '',
        `Usuario: ${email}`,
        `Contraseña: ${password}`,
        '',
        `Entra aquí: ${login}`,
    ].join('\n');
};

const saveAccess = (user: ClubUser, share = false) => {
    const email = accessByUser[user.id]?.email ?? user.email;
    const password = accessByUser[user.id]?.password ?? '';

    if (share && password.trim().length < 8) {
        return;
    }

    savingId.value = user.id;
    credentials.clearErrors();
    credentials.role_id = roleByUser[user.id];
    credentials.email = email;
    credentials.password = password;
    credentials.put(`/app/${page.props.organization?.slug}/users/${user.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (share && password.trim() !== '') {
                sharedByUser[user.id] = {
                    message: accessMessage(user, email, password),
                    copied: false,
                };
            }

            if (accessByUser[user.id]) {
                accessByUser[user.id].password = '';
            }
        },
    });
};

const copyMessage = async (user: ClubUser) => {
    const shared = sharedByUser[user.id];

    if (! shared) {
        return;
    }

    await navigator.clipboard.writeText(shared.message);
    shared.copied = true;
    window.setTimeout(() => {
        if (sharedByUser[user.id]) {
            sharedByUser[user.id].copied = false;
        }
    }, 2000);
};

const whatsappHref = (user: ClubUser) => {
    const shared = sharedByUser[user.id];

    if (! shared) {
        return '#';
    }

    const phone = whatsappNumber(user.person?.phone);
    const text = encodeURIComponent(shared.message);

    return phone ? `https://wa.me/${phone}?text=${text}` : `https://wa.me/?text=${text}`;
};
</script>

<template>
    <AppLayout>
        <h1 class="font-display text-3xl">Usuarios del club</h1>
        <p class="mt-1 text-sm text-cream/50">
            Cada usuario ve solo este club, sus miembros y los permisos de su rol. El superadministrador de plataforma no se agrega aquí.
        </p>

        <div class="mt-4 space-y-3">
            <article v-for="user in users" :key="user.id" class="panel space-y-4 p-4">
                <div class="grid gap-3 md:grid-cols-[1fr_14rem] md:items-center">
                    <div>
                        <p class="font-medium">{{ user.name }}</p>
                        <p v-if="!can('users.update')" class="text-sm text-cream/50">{{ user.email }}</p>
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
                </div>
                <form
                    v-if="can('users.update') && accessByUser[user.id]"
                    class="grid gap-3 border-t border-white/10 pt-4 md:grid-cols-[1fr_1fr_auto] md:items-end"
                    @submit.prevent="saveAccess(user)"
                >
                    <label class="block text-sm text-cream/70">
                        Correo de ingreso
                        <input v-model="accessByUser[user.id].email" type="email" class="field mt-1" autocomplete="off" required />
                    </label>
                    <label class="block text-sm text-cream/70">
                        Nueva contraseña
                        <input
                            v-model="accessByUser[user.id].password"
                            type="password"
                            class="field mt-1"
                            autocomplete="new-password"
                            placeholder="Vacía para conservar la actual"
                        />
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button class="btn-primary" :disabled="credentials.processing && savingId === user.id">Guardar ingreso</button>
                        <button
                            type="button"
                            class="btn-ghost"
                            :disabled="(accessByUser[user.id].password.trim().length < 8) || (credentials.processing && savingId === user.id)"
                            @click="saveAccess(user, true)"
                        >
                            Armar mensaje
                        </button>
                    </div>
                    <p v-if="savingId === user.id && credentials.errors.email" class="text-sm text-red-300 md:col-span-3">{{ credentials.errors.email }}</p>
                    <p v-if="savingId === user.id && credentials.errors.password" class="text-sm text-red-300 md:col-span-3">{{ credentials.errors.password }}</p>
                    <div v-if="sharedByUser[user.id]" class="space-y-2 md:col-span-3">
                        <p class="text-sm text-cream/70">Mensaje listo para copiar y enviar por WhatsApp.</p>
                        <textarea class="field min-h-32" readonly :value="sharedByUser[user.id].message"></textarea>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn-primary" @click="copyMessage(user)">
                                {{ sharedByUser[user.id].copied ? 'Copiado' : 'Copiar mensaje' }}
                            </button>
                            <a class="btn-ghost" :href="whatsappHref(user)" target="_blank" rel="noopener">Enviar por WhatsApp</a>
                        </div>
                    </div>
                </form>
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
