<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import InscriptionFormFields from '@/components/InscriptionFormFields.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { PageProps } from '@/types';
import { inscriptionFormDefaults } from '@/lib/inscriptionForm';

type DocumentItem = {
    slug: string;
    name: string;
    id?: string | null;
    status?: string | null;
    expiration_date?: string | null;
    files: { id: string; filename: string; url: string }[];
};

const props = defineProps<{
    club: { name: string; city?: string | null; slug: string };
    token: string;
    reference?: string | null;
    status: string;
    can_edit: boolean;
    form: Record<string, any>;
    documents: DocumentItem[];
    pilots?: { id: string; name: string; document_number?: string | null }[];
    role?: 'driver' | 'passenger';
}>();

const page = usePage<PageProps>();
const fileError = computed(() => (page.props.errors as Record<string, string> | undefined)?.files ?? '');
const dataForm = useForm(inscriptionFormDefaults(props.form, props.role));
const uploading = ref<string | null>(null);
const currentRole = computed(() => dataForm.vehicle_role ?? props.role ?? 'driver');
const statusLabel: Record<string, string> = {
    submitted: 'Pendiente de verificación',
    verified: 'Verificada',
    access_granted: 'Con acceso',
    rejected: 'Rechazada',
};

const saveData = () => dataForm.put(`/i/${props.token}/solicitud`);

const replaceDocument = (item: DocumentItem, event: Event) => {
    const input = event.target as HTMLInputElement;
    const files = input.files ? Array.from(input.files) : [];
    input.value = '';

    if (!files.length) {
        return;
    }

    uploading.value = item.slug;
    router.post(`/i/${props.token}/solicitud/documentos`, {
        type: item.slug,
        files,
        expiration_date: item.expiration_date ?? '',
    }, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            uploading.value = null;
        },
    });
};
</script>

<template>
    <PublicLayout :club="club">
        <div class="mb-5">
            <p class="text-xs uppercase tracking-widest text-ember-400">Solicitud {{ reference }}</p>
            <h2 class="font-display text-3xl">{{ statusLabel[status] ?? status }}</h2>
            <p class="mt-1 text-sm text-cream/60">
                {{ can_edit
                    ? 'Puedes corregir tus datos y documentos mientras el club no haya verificado.'
                    : 'Esta solicitud ya no se puede modificar. El club ya la revisó.' }}
            </p>
        </div>

        <p v-if="page.props.flash?.success" class="mb-4 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ page.props.flash.success }}
        </p>

        <form v-if="can_edit" class="space-y-4" @submit.prevent="saveData">
            <InscriptionFormFields
                :form="dataForm"
                :pilots="pilots"
                :role="currentRole"
                step="all"
            />
            <p v-if="dataForm.errors.document_number" class="text-sm text-red-300">{{ dataForm.errors.document_number }}</p>
            <p v-if="dataForm.errors.email" class="text-sm text-red-300">{{ dataForm.errors.email }}</p>
            <p v-if="dataForm.errors.pilot_person_id" class="text-sm text-red-300">{{ dataForm.errors.pilot_person_id }}</p>
            <button class="btn-primary" :disabled="dataForm.processing">Guardar cambios</button>
        </form>

        <section class="panel mt-6 space-y-4 p-5">
            <h3 class="font-display text-xl">Documentos enviados</h3>
            <p v-if="fileError" class="rounded-xl border border-red-400/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">
                {{ fileError }}
            </p>
            <article v-for="item in documents" :key="item.slug" class="rounded-xl border border-white/10 p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ item.name }}</p>
                        <p class="text-xs uppercase text-cream/50">{{ item.files.length ? (item.status ?? 'pendiente') : 'Sin archivo' }}</p>
                    </div>
                    <label v-if="can_edit" class="btn-ghost cursor-pointer">
                        {{ uploading === item.slug ? 'Subiendo…' : (item.files.length ? 'Reemplazar' : 'Agregar') }}
                        <input
                            type="file"
                            accept="image/*,.pdf"
                            multiple
                            class="hidden"
                            :disabled="uploading === item.slug"
                            @change="replaceDocument(item, $event)"
                        />
                    </label>
                </div>
                <ul v-if="item.files.length" class="space-y-1 text-sm">
                    <li v-for="file in item.files" :key="file.id">
                        <a :href="file.url" class="text-ember-300 hover:underline" target="_blank" rel="noreferrer">
                            {{ file.filename }}
                        </a>
                    </li>
                </ul>
            </article>
        </section>

        <Link :href="`/i/${token}`" class="btn-ghost mt-5 inline-flex">Volver al enlace</Link>
    </PublicLayout>
</template>
