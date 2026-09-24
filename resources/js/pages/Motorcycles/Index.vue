<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

defineProps<{
    motorcycles: { data: any[] };
    people: any[];
}>();

const page = usePage<PageProps>();
const form = useForm({
    person_id: '',
    plate: '',
    brand: '',
    model: '',
    year: '',
    engine_displacement: '',
    vehicle_type: 'motorcycle',
});

const submit = () => form.post(`/app/${page.props.organization?.slug}/motorcycles`, { preserveScroll: true });
</script>

<template>
    <AppLayout>
        <h1 class="font-display text-3xl">Motocicletas</h1>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
            <article v-for="moto in motorcycles.data" :key="moto.id" class="panel p-4">
                <p class="font-display text-2xl">{{ moto.plate }}</p>
                <p class="text-sm text-cream/60">{{ moto.brand }} {{ moto.model }} · {{ moto.engine_displacement }} cc</p>
                <p class="mt-2 text-sm">{{ moto.person?.first_name }} {{ moto.person?.last_name }}</p>
            </article>
        </div>

        <form class="panel mt-6 grid gap-3 p-5 md:grid-cols-2" @submit.prevent="submit">
            <h2 class="font-display text-xl md:col-span-2">Registrar moto</h2>
            <select v-model="form.person_id" class="field" required>
                <option value="" disabled>Propietario</option>
                <option v-for="person in people" :key="person.id" :value="person.id">
                    {{ person.first_name }} {{ person.last_name }}
                </option>
            </select>
            <input v-model="form.plate" class="field" placeholder="Placa" required />
            <input v-model="form.brand" class="field" placeholder="Marca" required />
            <input v-model="form.model" class="field" placeholder="Modelo" />
            <input v-model="form.engine_displacement" class="field" placeholder="Cilindraje" />
            <button class="btn-primary md:col-span-2">Guardar motocicleta</button>
        </form>
    </AppLayout>
</template>
