<script setup lang="ts">
export type PermissionItem = { id: string; slug: string; name: string };

defineProps<{
    catalog: Record<string, PermissionItem[]>;
    disabled?: boolean;
}>();

const selected = defineModel<string[]>({ required: true });
</script>

<template>
    <div class="space-y-4">
        <div v-for="(items, module) in catalog" :key="module">
            <p class="mb-2 text-xs uppercase tracking-widest text-cream/40">{{ module }}</p>
            <label v-for="permission in items" :key="permission.id" class="mb-1 flex items-center gap-2 text-sm">
                <input v-model="selected" type="checkbox" :value="permission.slug" :disabled="disabled" />
                {{ permission.name }}
            </label>
        </div>
    </div>
</template>
