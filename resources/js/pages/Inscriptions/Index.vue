<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useOrganization } from '@/composables/useOrganization';

type LinkItem = {
    id: string;
    name?: string | null;
    url: string;
    is_active: boolean;
    uses_count: number;
    expires_at?: string | null;
};

type ApplicationItem = {
    id: string;
    status: string;
    reference?: string | null;
    email?: string | null;
    submitted_at?: string | null;
    person?: { first_name?: string; last_name?: string; document_number?: string } | null;
};

defineProps<{
    links: LinkItem[];
    applications: ApplicationItem[];
    canCreate: boolean;
    canVerify: boolean;
}>();

const { clubPath } = useOrganization();
const copied = ref('');
const form = useForm({
    name: '',
    expires_in_days: 30,
});

const submit = () => form.post(clubPath('inscripciones/links'), { preserveScroll: true });

const copy = async (url: string) => {
    await navigator.clipboard.writeText(url);
    copied.value = url;
};

const remove = (item: LinkItem) => {
    if (! window.confirm(`¿Eliminar “${item.name || 'este enlace'}”? Nadie más podrá inscribirse con ese link.`)) {
        return;
    }

    router.delete(clubPath(`inscripciones/links/${item.id}`), { preserveScroll: true });
};

const statusLabel: Record<string, string> = {
    draft: 'Cargando',
    ready_for_form: 'En formulario',
    submitted: 'Por verificar',
    verified: 'Verificada',
    access_granted: 'Con acceso',
    rejected: 'Rechazada',
};
</script>

<template>
    <AppLayout>
        <PageHeader
            kicker="Inscripciones"
            title="Enlace de inscripción"
            description="Genera un link. Quien lo abra sube cédula, licencia, SOAT, tecnomecánica y foto; el club verifica y luego otorga acceso."
        />

        <form v-if="canCreate" class="panel mb-6 grid gap-3 p-5 md:grid-cols-[1fr_8rem_auto]" @submit.prevent="submit">
            <input v-model="form.name" class="field" placeholder="Nombre del enlace (opcional)" />
            <input v-model="form.expires_in_days" type="number" min="0" class="field" placeholder="Días" />
            <button class="btn-primary" :disabled="form.processing">Crear enlace</button>
        </form>

        <section class="mb-8 space-y-3">
            <h2 class="font-display text-xl">Enlaces</h2>
            <article v-for="item in links" :key="item.id" class="panel flex flex-col gap-2 p-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="font-medium">{{ item.name || 'Inscripción' }}</p>
                    <p class="break-all text-xs text-cream/50">{{ item.url }}</p>
                    <p class="mt-1 text-xs text-ember-400">
                        {{ item.is_active ? 'Activo' : 'Inactivo' }} · {{ item.uses_count }} usos
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="btn-ghost" @click="copy(item.url)">
                        {{ copied === item.url ? 'Copiado' : 'Copiar link' }}
                    </button>
                    <button v-if="canCreate" class="btn-ghost" @click="remove(item)">Eliminar</button>
                </div>
            </article>
            <p v-if="!links.length" class="text-sm text-cream/50">Aún no hay enlaces.</p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl">Solicitudes</h2>
            <Link
                v-for="application in applications"
                :key="application.id"
                :href="clubPath(`inscripciones/${application.id}`)"
                class="panel block p-4"
            >
                <p class="font-medium">
                    {{ application.person?.first_name }} {{ application.person?.last_name }}
                    <span class="text-sm text-cream/50">{{ application.email }}</span>
                </p>
                <p class="text-xs uppercase text-ember-400">
                    {{ statusLabel[application.status] ?? application.status }}
                    <span v-if="application.reference"> · {{ application.reference }}</span>
                </p>
            </Link>
            <p v-if="!applications.length" class="text-sm text-cream/50">Nadie se ha inscrito por enlace todavía.</p>
        </section>
    </AppLayout>
</template>
