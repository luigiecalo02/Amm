<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useOrganization } from '@/composables/useOrganization';

const props = defineProps<{ person: any }>();
const { clubPath } = useOrganization();
const membership = computed(() => props.person.memberships?.[0] ?? {});
const church = computed(() => membership.value.metadata ?? {});
</script>

<template>
    <AppLayout>
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-widest text-ember-400">Ficha de inscripción</p>
                <h1 class="font-display text-4xl">{{ person.first_name }} {{ person.last_name }} {{ person.second_last_name }}</h1>
                <p class="text-cream/60">{{ person.document_type?.toUpperCase() }} {{ person.document_number }} · {{ person.phone }}</p>
            </div>
            <Link :href="clubPath(`people/${person.id}/edit`)" class="btn-ghost">Editar</Link>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <section class="panel p-5 text-sm text-cream/80">
                <h2 class="font-display text-xl text-cream">I. Información personal</h2>
                <p class="mt-3">Nacimiento: {{ person.birth_date || '—' }} · {{ person.birth_city }} {{ person.birth_department }}</p>
                <p>Sexo: {{ person.gender }} · Estado civil: {{ person.marital_status }}</p>
                <p>Correo: {{ person.email || '—' }}</p>
                <p>Dirección: {{ person.address }} · {{ person.neighborhood }} · {{ person.city }}</p>
                <p>Iglesia: {{ church.church_name || '—' }} · Distrito: {{ church.church_district || '—' }}</p>
                <p>Cargo: {{ church.church_role || '—' }} · Bautizado: {{ church.baptized ? 'Sí' : 'No' }}</p>
            </section>
            <section class="panel p-5 text-sm text-cream/80">
                <h2 class="font-display text-xl text-cream">II. Salud</h2>
                <p class="mt-3">EPS: {{ person.health_profile?.eps || '—' }}</p>
                <p>Sangre: {{ person.health_profile?.blood_type }} {{ person.health_profile?.rh }}</p>
                <p>Peso / altura: {{ person.health_profile?.weight_kg || '—' }} kg · {{ person.health_profile?.height_cm || '—' }} cm</p>
                <p>Tratamiento: {{ person.health_profile?.special_treatment || 'Ninguno' }}</p>
                <p>Medicamentos: {{ person.health_profile?.medications || 'Ninguno' }}</p>
                <p>Alergias: {{ person.health_profile?.allergies || 'Ninguna' }}</p>
            </section>
            <section class="panel p-5 text-sm text-cream/80">
                <h2 class="font-display text-xl text-cream">Contacto de emergencia</h2>
                <div v-for="contact in person.emergency_contacts" :key="contact.id" class="mt-3">
                    <p>{{ contact.name }} · {{ contact.relationship }}</p>
                    <p>{{ contact.phone }} · {{ contact.address }}</p>
                </div>
            </section>
            <section class="panel p-5 text-sm text-cream/80">
                <h2 class="font-display text-xl text-cream">III. Vehículo</h2>
                <p class="mt-3">Rol: {{ membership.vehicle_role || '—' }}</p>
                <p v-for="moto in person.motorcycles" :key="moto.id">
                    {{ moto.vehicle_type === 'car' ? 'Carro' : 'Moto' }} · {{ moto.brand }} {{ moto.model }} · {{ moto.engine_displacement }} cc · {{ moto.plate }}
                </p>
            </section>
            <section class="panel p-5 text-sm text-cream/80 lg:col-span-2">
                <h2 class="font-display text-xl text-cream">Documentos</h2>
                <p v-for="doc in person.documents" :key="doc.id" class="mt-2">
                    {{ doc.type?.name }} · {{ doc.document_number }} · {{ doc.expiration_date || 'Sin vencimiento' }}
                </p>
            </section>
        </div>
    </AppLayout>
</template>
