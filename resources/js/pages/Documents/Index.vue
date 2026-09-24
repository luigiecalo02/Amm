<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const props = defineProps<{
    documents: { data: any[] };
    documentTypes: any[];
    people: any[];
    motorcycles: any[];
}>();

const page = usePage<PageProps>();
const form = useForm({
    document_type_id: '',
    person_id: '',
    motorcycle_id: '',
    document_number: '',
    issue_date: '',
    expiration_date: '',
    file: null as File | null,
});

const selectedType = () => props.documentTypes.find((type) => type.id === form.document_type_id);

const submit = () => {
    form.transform((data) => ({
        ...data,
        person_id: selectedType()?.entity_type === 'person' ? data.person_id : null,
        motorcycle_id: selectedType()?.entity_type === 'motorcycle' ? data.motorcycle_id : null,
    })).post(`/app/${page.props.organization?.slug}/documents`, { forceFormData: true, preserveScroll: true });
};
</script>

<template>
    <AppLayout>
        <h1 class="font-display text-3xl">Documentos</h1>
        <div class="mt-4 space-y-3">
            <article v-for="doc in documents.data" :key="doc.id" class="panel flex items-center justify-between p-4">
                <div>
                    <p class="font-medium">{{ doc.type?.name }} · {{ doc.document_number || 'Sin número' }}</p>
                    <p class="text-sm text-cream/50">
                        {{ doc.person?.first_name }} {{ doc.person?.last_name }} {{ doc.motorcycle?.plate }}
                    </p>
                </div>
                <span class="text-sm text-ember-400">{{ doc.expiration_date || 'Vigente' }}</span>
            </article>
        </div>

        <form class="panel mt-6 grid gap-3 p-5 md:grid-cols-2" @submit.prevent="submit">
            <h2 class="font-display text-xl md:col-span-2">Registrar documento</h2>
            <select v-model="form.document_type_id" class="field" required>
                <option value="" disabled>Tipo</option>
                <option v-for="type in documentTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
            </select>
            <input v-model="form.document_number" class="field" placeholder="Número" />
            <select v-if="selectedType()?.entity_type === 'person'" v-model="form.person_id" class="field">
                <option value="">Persona</option>
                <option v-for="person in people" :key="person.id" :value="person.id">{{ person.first_name }} {{ person.last_name }}</option>
            </select>
            <select v-if="selectedType()?.entity_type === 'motorcycle'" v-model="form.motorcycle_id" class="field">
                <option value="">Motocicleta</option>
                <option v-for="moto in motorcycles" :key="moto.id" :value="moto.id">{{ moto.plate }} · {{ moto.brand }}</option>
            </select>
            <input v-model="form.expiration_date" type="date" class="field" />
            <input class="field" type="file" @change="form.file = ($event.target as HTMLInputElement).files?.[0] || null" />
            <button class="btn-primary md:col-span-2">Guardar documento</button>
        </form>
    </AppLayout>
</template>
