<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { PageProps } from '@/types';

const props = defineProps<{
    people: { data: any[] };
    filters: { q?: string };
}>();

const page = usePage<PageProps>();
const q = ref(props.filters.q ?? '');
const slug = () => page.props.organization?.slug;

const search = () => {
    router.get(`/app/${slug()}/people`, { q: q.value }, { preserveState: true, replace: true });
};
</script>

<template>
    <AppLayout>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-widest text-ember-400">Personas</p>
                <h1 class="font-display text-3xl">Miembros y conductores</h1>
            </div>
            <Link :href="`/app/${slug()}/people/create`" class="btn-primary">Nueva inscripción</Link>
        </div>

        <form class="mt-4" @submit.prevent="search">
            <input v-model="q" class="field" placeholder="Buscar por nombre, cédula o teléfono" />
        </form>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <Link
                v-for="person in people.data"
                :key="person.id"
                :href="`/app/${slug()}/people/${person.id}`"
                class="panel p-4"
            >
                <p class="font-display text-xl">{{ person.first_name }} {{ person.last_name }}</p>
                <p class="text-sm text-cream/50">{{ person.document_type?.toUpperCase() }} {{ person.document_number }}</p>
                <p class="mt-2 text-sm text-cream/70">{{ person.phone }} · {{ person.city }}</p>
            </Link>
        </div>
    </AppLayout>
</template>
