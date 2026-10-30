<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import RolePermissionCatalog from '@/components/RolePermissionCatalog.vue';
import type { PermissionItem } from '@/components/RolePermissionCatalog.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { useOrganization } from '@/composables/useOrganization';
import { usePermissions } from '@/composables/usePermissions';
import type { PageProps } from '@/types';

type RoleItem = {
    id: string;
    name: string;
    slug?: string;
    description?: string | null;
    is_system?: boolean;
    permissions?: PermissionItem[];
};

const props = defineProps<{
    roles: RoleItem[];
    catalog: Record<string, PermissionItem[]>;
}>();

const page = usePage<PageProps>();
const { clubPath } = useOrganization();
const { can } = usePermissions();
const selectedId = ref(props.roles[0]?.id ?? '');

const editForm = useForm({
    name: '',
    description: '',
    permissions: [] as string[],
});

const selectedRole = computed(() => props.roles.find((role) => role.id === selectedId.value) ?? null);
const isClubSuperAdmin = computed(() => selectedRole.value?.slug === 'superadministrador');

const fillEditForm = (role: RoleItem | null) => {
    if (!role) {
        return;
    }

    editForm.name = role.name;
    editForm.description = role.description ?? '';
    editForm.permissions = (role.permissions ?? []).map((permission) => permission.slug);
    editForm.clearErrors();
};

watch(selectedRole, (role) => fillEditForm(role), { immediate: true });
watch(() => props.roles, () => fillEditForm(selectedRole.value));

const submitUpdate = () => {
    if (!selectedId.value) {
        return;
    }

    editForm.put(clubPath(`roles/${selectedId.value}`), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <PageHeader
                kicker="Roles"
                title="Roles del club"
                :description="`Edita los roles de ${page.props.organization?.name}. Superadministrador ya trae todos los permisos del club.`"
            />
            <Link v-if="can('roles.create')" :href="clubPath('roles/create')" class="btn-primary shrink-0">Nuevo rol</Link>
        </div>

        <div class="mt-2 grid gap-4 lg:grid-cols-[16rem_1fr]">
            <aside class="space-y-2">
                <button
                    v-for="role in roles"
                    :key="role.id"
                    type="button"
                    class="panel w-full p-4 text-left transition hover:border-ember-500/40"
                    :class="{ 'border-ember-500/50 bg-ember-500/10': role.id === selectedId }"
                    @click="selectedId = role.id"
                >
                    <p class="font-display text-lg">{{ role.name }}</p>
                    <p class="text-xs text-cream/50">{{ role.permissions?.length ?? 0 }} permisos</p>
                </button>
            </aside>

            <section v-if="selectedRole" class="panel p-5">
                <form class="space-y-4" @submit.prevent="submitUpdate">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-ember-400">
                                {{ isClubSuperAdmin ? 'Todos los permisos' : selectedRole.is_system ? 'Rol de sistema' : 'Rol personalizado' }}
                            </p>
                            <h2 class="font-display text-2xl">{{ selectedRole.name }}</h2>
                        </div>
                        <button v-if="can('roles.update') && !isClubSuperAdmin" class="btn-primary" :disabled="editForm.processing">
                            Guardar permisos
                        </button>
                    </div>

                    <p v-if="isClubSuperAdmin" class="rounded-xl border border-ember-400/30 bg-ember-500/10 px-4 py-3 text-sm text-cream/80">
                        Este rol tiene todos los permisos del club por defecto y no se puede recortar.
                    </p>
                    <input v-model="editForm.name" class="field" placeholder="Nombre del rol" :disabled="!can('roles.update') || isClubSuperAdmin" />
                    <input v-model="editForm.description" class="field" placeholder="Descripción" :disabled="!can('roles.update') || isClubSuperAdmin" />
                    <RolePermissionCatalog v-model="editForm.permissions" :catalog="catalog" :disabled="!can('roles.update') || isClubSuperAdmin" />
                    <p v-if="editForm.errors.name" class="text-sm text-red-300">{{ editForm.errors.name }}</p>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
