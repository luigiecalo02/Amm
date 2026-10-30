<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import InscriptionLookupForm from '@/components/InscriptionLookupForm.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    club: { name: string; city?: string | null; slug: string };
    token: string;
    reference?: string | null;
    document_number?: string | null;
    can_update?: boolean;
}>();
</script>

<template>
    <PublicLayout :club="club">
        <div class="panel mb-6 p-6">
            <h2 class="font-display text-2xl">Inscripción enviada</h2>
            <p class="mt-3 text-sm text-cream/70">
                Quedaste registrado en {{ club.name }} pendiente de verificación. Un encargado del club revisará tus documentos.
            </p>
            <div v-if="reference" class="mt-5 rounded-xl border border-ember-400/30 bg-ember-500/10 p-4">
                <p class="text-xs uppercase tracking-widest text-ember-400">Guarda estos datos</p>
                <p class="mt-2 font-display text-2xl">{{ reference }}</p>
                <p class="mt-1 text-sm text-cream/70">Número de solicitud</p>
                <p v-if="document_number" class="mt-3 text-sm text-cream/70">
                    Documento: <span class="text-cream">{{ document_number }}</span>
                </p>
                <p class="mt-3 text-sm text-cream/60">
                    Con la cédula y este número puedes consultar o actualizar tu solicitud mientras esté pendiente.
                </p>
                <Link v-if="can_update" :href="`/i/${token}/solicitud`" class="btn-primary mt-4 inline-flex">
                    Actualizar datos o documentos
                </Link>
            </div>
        </div>

        <InscriptionLookupForm :token="token" />
    </PublicLayout>
</template>
