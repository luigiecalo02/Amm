<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    token: string;
}>();

const form = useForm({
    document_number: '',
});
const restart = useForm({});

const submit = () => form.post(`/i/${props.token}/consultar`);
const startNew = () => restart.post(`/i/${props.token}/nueva`);
</script>

<template>
    <section class="panel space-y-4 p-5">
        <div>
            <p class="text-xs uppercase tracking-widest text-ember-400">Ya enviaste tu inscripción</p>
            <h2 class="font-display text-2xl">Consultar solicitud</h2>
            <p class="mt-1 text-sm text-cream/60">
                Ingresa tu número de documento. Si ya está registrada, verás el estado actual. Si no aparece, puedes empezar un registro nuevo.
            </p>
        </div>
        <form class="space-y-3" @submit.prevent="submit">
            <FormField label="Número de documento">
                <input v-model="form.document_number" class="field" required placeholder="Cédula" />
            </FormField>
            <p v-if="form.errors.document_number" class="text-sm text-red-300">{{ form.errors.document_number }}</p>
            <div class="flex flex-wrap gap-2">
                <button class="btn-primary" :disabled="form.processing">Consultar</button>
                <button
                    v-if="form.errors.document_number"
                    type="button"
                    class="btn-ghost"
                    :disabled="restart.processing"
                    @click="startNew"
                >
                    Crear un registro nuevo
                </button>
            </div>
        </form>
    </section>
</template>
