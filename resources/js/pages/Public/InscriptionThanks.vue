<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import InscriptionLookupForm from '@/components/InscriptionLookupForm.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    club: { name: string; city?: string | null; slug: string };
    token: string;
    reference?: string | null;
    status?: string | null;
    document_number?: string | null;
    can_update?: boolean;
    can_continue?: boolean;
}>();

const restart = useForm({});
const startNew = () => restart.post(`/i/${props.token}/nueva`);

const statusCopy = computed(() => {
    const club = props.club.name;

    const copies: Record<string, { title: string; message: string }> = {
        draft: {
            title: 'Registro sin enviar',
            message: 'Esta solicitud está empezada y todavía no se ha enviado al club.',
        },
        ready_for_form: {
            title: 'Registro sin enviar',
            message: 'Los documentos ya están listos. Falta enviar el formulario.',
        },
        submitted: {
            title: 'Inscripción enviada',
            message: `Quedaste registrado en ${club} pendiente de verificación. Un encargado del club revisará tus documentos.`,
        },
        verified: {
            title: 'Solicitud verificada',
            message: `El club ya verificó tu solicitud en ${club}.`,
        },
        access_granted: {
            title: 'Acceso otorgado',
            message: `Ya tienes acceso en ${club}.`,
        },
        rejected: {
            title: 'Solicitud no aprobada',
            message: 'El club no aprobó esta solicitud. Puedes crear un registro nuevo y empezar de cero.',
        },
    };

    return copies[props.status ?? ''] ?? {
        title: 'Consulta tu inscripción',
        message: 'Si ya enviaste una solicitud, consúltala con tu documento. Si no aparece, crea un registro nuevo.',
    };
});
</script>

<template>
    <PublicLayout :club="club">
        <div class="panel mb-6 p-6">
            <h2 class="font-display text-2xl">{{ statusCopy.title }}</h2>
            <p class="mt-3 text-sm text-cream/70">{{ statusCopy.message }}</p>
            <div v-if="reference" class="mt-5 rounded-xl border border-ember-400/30 bg-ember-500/10 p-4">
                <p class="text-xs uppercase tracking-widest text-ember-400">Estado actual</p>
                <p class="mt-2 font-display text-2xl">{{ reference }}</p>
                <p class="mt-1 text-sm text-cream/70">Número de solicitud</p>
                <p v-if="document_number" class="mt-3 text-sm text-cream/70">
                    Documento: <span class="text-cream">{{ document_number }}</span>
                </p>
                <p v-if="can_update" class="mt-3 text-sm text-cream/60">
                    Con tu número de documento puedes consultar o actualizar tu solicitud mientras esté pendiente.
                </p>
                <Link v-if="can_update" :href="`/i/${token}/solicitud`" class="btn-primary mt-4 inline-flex">
                    Actualizar datos o documentos
                </Link>
                <Link v-if="can_continue" :href="`/i/${token}`" class="btn-primary mt-4 inline-flex">
                    Continuar este registro
                </Link>
            </div>
            <button
                v-if="!reference || status === 'rejected'"
                type="button"
                class="btn-ghost mt-4"
                :disabled="restart.processing"
                @click="startNew"
            >
                Crear un registro nuevo
            </button>
        </div>

        <InscriptionLookupForm :token="token" />
    </PublicLayout>
</template>
