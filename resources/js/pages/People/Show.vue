<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

defineProps<{ person: any }>();
const page = usePage<PageProps>();
</script>

<template>
    <AppLayout>
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-ember-400">Ficha</p>
                <h1 class="font-display text-4xl">{{ person.first_name }} {{ person.last_name }}</h1>
                <p class="text-cream/60">{{ person.document_type?.toUpperCase() }} {{ person.document_number }} · {{ person.phone }}</p>
            </div>
            <Link :href="`/app/${page.props.organization?.slug}/people/${person.id}/edit`" class="btn-ghost">Editar</Link>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <section class="panel p-5">
                <h2 class="font-display text-xl">Salud</h2>
                <p class="mt-2 text-sm text-cream/70">EPS: {{ person.health_profile?.eps || '—' }}</p>
                <p class="text-sm text-cream/70">Sangre: {{ person.health_profile?.blood_type }} {{ person.health_profile?.rh }}</p>
                <p class="text-sm text-cream/70">Alergias: {{ person.health_profile?.allergies || 'Ninguna' }}</p>
            </section>
            <section class="panel p-5">
                <h2 class="font-display text-xl">Emergencia</h2>
                <div v-for="contact in person.emergency_contacts" :key="contact.id" class="mt-2 text-sm text-cream/70">
                    {{ contact.name }} · {{ contact.phone }} · {{ contact.relationship }}
                </div>
            </section>
            <section class="panel p-5">
                <h2 class="font-display text-xl">Motocicletas</h2>
                <p v-for="moto in person.motorcycles" :key="moto.id" class="mt-2 text-sm">{{ moto.brand }} {{ moto.model }} · {{ moto.plate }}</p>
            </section>
            <section class="panel p-5">
                <h2 class="font-display text-xl">Documentos</h2>
                <p v-for="doc in person.documents" :key="doc.id" class="mt-2 text-sm">
                    {{ doc.type?.name }} · {{ doc.document_number }} · {{ doc.expiration_date || 'Sin vencimiento' }}
                </p>
            </section>
        </div>
    </AppLayout>
</template>
