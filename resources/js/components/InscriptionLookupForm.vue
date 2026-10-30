<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    token: string;
}>();

const form = useForm({
    document_number: '',
    reference: '',
});

const submit = () => form.post(`/i/${props.token}/consultar`);
</script>

<template>
    <section class="panel space-y-4 p-5">
        <div>
            <p class="text-xs uppercase tracking-widest text-ember-400">Ya enviaste tu inscripción</p>
            <h2 class="font-display text-2xl">Consultar solicitud</h2>
            <p class="mt-1 text-sm text-cream/60">
                Ingresa tu número de documento y el número de solicitud. Si sigue pendiente, puedes actualizar datos y documentos.
            </p>
        </div>
        <form class="grid gap-3 md:grid-cols-2" @submit.prevent="submit">
            <FormField label="Número de documento">
                <input v-model="form.document_number" class="field" required placeholder="Cédula" />
            </FormField>
            <FormField label="Número de solicitud">
                <input v-model="form.reference" class="field" required placeholder="AMM-2026-0001" />
            </FormField>
            <p v-if="form.errors.reference" class="text-sm text-red-300 md:col-span-2">{{ form.errors.reference }}</p>
            <p v-if="form.errors.document_number" class="text-sm text-red-300 md:col-span-2">{{ form.errors.document_number }}</p>
            <button class="btn-primary md:col-span-2" :disabled="form.processing">Consultar</button>
        </form>
    </section>
</template>
