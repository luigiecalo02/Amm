<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3';

defineProps<{
    organizations: { id: string; name: string; slug: string; city?: string; department?: string }[];
}>();

const form = useForm({
    name: '',
    city: '',
    department: '',
});

const submit = () => form.post('/organizations');
</script>

<template>
    <div class="mx-auto max-w-4xl px-4 py-10">
        <p class="font-display text-xs uppercase tracking-[0.3em] text-ember-400">Tus clubes</p>
        <h1 class="mt-2 font-display text-4xl">Organizaciones</h1>
        <div class="mt-8 grid gap-4 md:grid-cols-2">
            <Link
                v-for="org in organizations"
                :key="org.id"
                :href="`/app/${org.slug}`"
                class="panel p-5 transition hover:border-ember-500/40"
            >
                <p class="font-display text-2xl">{{ org.name }}</p>
                <p class="mt-1 text-sm text-cream/50">{{ org.city }} {{ org.department }}</p>
            </Link>
        </div>

        <form class="panel mt-8 space-y-4 p-5" @submit.prevent="submit">
            <h2 class="font-display text-xl">Crear un club</h2>
            <input v-model="form.name" class="field" placeholder="Nombre del club" required />
            <div class="grid gap-3 md:grid-cols-2">
                <input v-model="form.city" class="field" placeholder="Ciudad" />
                <input v-model="form.department" class="field" placeholder="Departamento" />
            </div>
            <button class="btn-primary" :disabled="form.processing">Crear organización</button>
        </form>
    </div>
</template>
