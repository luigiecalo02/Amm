<script setup lang="ts">
export type PermissionItem = {
    id: string;
    slug: string;
    name: string;
    description?: string | null;
};

const props = defineProps<{
    catalog: Record<string, PermissionItem[]>;
    disabled?: boolean;
}>();

const selected = defineModel<string[]>({ required: true });

const moduleLabel: Record<string, string> = {
    people: 'Personas',
    documents: 'Documentos',
    motorcycles: 'Motos',
    members: 'Miembros',
    users: 'Usuarios',
    roles: 'Roles',
    organization: 'Club',
    reports: 'Reportes',
    inscriptions: 'Inscripciones',
};

const selectedCount = (items: PermissionItem[]) => (
    items.filter((item) => selected.value.includes(item.slug)).length
);

const moduleComplete = (items: PermissionItem[]) => (
    items.length > 0 && selectedCount(items) === items.length
);

const toggleModule = (items: PermissionItem[]) => {
    if (props.disabled) {
        return;
    }

    const slugs = items.map((item) => item.slug);

    if (moduleComplete(items)) {
        selected.value = selected.value.filter((slug) => ! slugs.includes(slug));
        return;
    }

    selected.value = [...new Set([...selected.value, ...slugs])];
};
</script>

<template>
    <div class="space-y-5">
        <section
            v-for="(items, module) in catalog"
            :key="module"
            class="rounded-2xl border border-white/10 bg-ink-900/40 p-4"
        >
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-ember-400">
                        {{ moduleLabel[module] ?? module }}
                    </p>
                    <p class="text-xs text-cream/40">
                        {{ selectedCount(items) }} de {{ items.length }} activos
                    </p>
                </div>
                <button
                    type="button"
                    class="text-xs font-medium text-ember-300 hover:text-ember-200 disabled:text-cream/30"
                    :disabled="disabled"
                    @click="toggleModule(items)"
                >
                    {{ moduleComplete(items) ? 'Quitar todos' : 'Marcar todos' }}
                </button>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <label
                    v-for="permission in items"
                    :key="permission.id"
                    class="group flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-ink-950/50 px-3 py-2.5 transition hover:border-ember-400/35 has-[:checked]:border-ember-400/50 has-[:checked]:bg-ember-500/10 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60"
                >
                    <input
                        v-model="selected"
                        type="checkbox"
                        class="peer sr-only"
                        :value="permission.slug"
                        :disabled="disabled"
                    />
                    <span
                        class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-white/25 bg-ink-900 transition peer-checked:border-ember-400 peer-checked:bg-ember-500 peer-focus-visible:ring-2 peer-focus-visible:ring-ember-400/50 peer-checked:[&_svg]:opacity-100"
                        aria-hidden="true"
                    >
                        <svg class="h-3 w-3 text-ink-950 opacity-0" viewBox="0 0 12 12" fill="none">
                            <path d="M2.2 6.2 4.7 8.7 9.8 3.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-medium leading-5 text-cream">{{ permission.name }}</span>
                        <span v-if="permission.description" class="mt-0.5 block text-xs leading-4 text-cream/45">
                            {{ permission.description }}
                        </span>
                    </span>
                </label>
            </div>
        </section>
    </div>
</template>
