<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/PageHeader.vue';
import AlertPreferencesForm from '@/components/AlertPreferencesForm.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const props = defineProps<{
    club: {
        name: string;
        description?: string | null;
        email?: string | null;
        phone?: string | null;
        city?: string | null;
        department?: string | null;
        address?: string | null;
        country?: string | null;
    };
    canUpdate?: boolean;
    documentAlerts: { slug: string; label: string; days: number }[];
    birthdayAlertDays: number;
    documentAlertInterval: number;
    alertTime: string;
    alertPreferences: { documents: boolean; birthdays: boolean };
}>();

const page = usePage<PageProps>();
const slug = () => page.props.organization?.slug;

const form = useForm({
    name: props.club.name,
    description: props.club.description ?? '',
    email: props.club.email ?? '',
    phone: props.club.phone ?? '',
    city: props.club.city ?? '',
    department: props.club.department ?? '',
    address: props.club.address ?? '',
    country: props.club.country ?? '',
});

const notices = useForm({
    licencia: props.documentAlerts.find((item) => item.slug === 'licencia')?.days ?? 90,
    soat: props.documentAlerts.find((item) => item.slug === 'soat')?.days ?? 30,
    tecnomecanica: props.documentAlerts.find((item) => item.slug === 'tecnomecanica')?.days ?? 30,
    repeat_every: props.documentAlertInterval ?? 1,
    birthday_days: props.birthdayAlertDays,
    alert_time: props.alertTime,
});

const submit = () => form.put(`/app/${slug()}/settings`, { preserveScroll: true });
const saveNotices = () => notices.put(`/app/${slug()}/settings/avisos-club`, { preserveScroll: true });
</script>

<template>
    <AppLayout>
        <PageHeader
            kicker="Club"
            title="Configuración"
            description="Datos del club, el momento de los avisos y lo que tú quieres recibir."
        />

        <form class="panel space-y-5 p-5" @submit.prevent="submit">
            <div>
                <h2 class="font-display text-xl">Datos del club</h2>
                <p class="mt-1 text-sm text-cream/60">Nombre, contacto y ubicación que ve el club.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block text-sm text-cream/70 md:col-span-2">
                    Nombre
                    <input v-model="form.name" class="field mt-1" :disabled="!canUpdate" required />
                </label>
                <label class="block text-sm text-cream/70">
                    Correo
                    <input v-model="form.email" type="email" class="field mt-1" :disabled="!canUpdate" />
                </label>
                <label class="block text-sm text-cream/70">
                    Teléfono
                    <input v-model="form.phone" class="field mt-1" :disabled="!canUpdate" />
                </label>
                <label class="block text-sm text-cream/70">
                    Ciudad
                    <input v-model="form.city" class="field mt-1" :disabled="!canUpdate" />
                </label>
                <label class="block text-sm text-cream/70">
                    Departamento
                    <input v-model="form.department" class="field mt-1" :disabled="!canUpdate" />
                </label>
                <label class="block text-sm text-cream/70">
                    País
                    <input v-model="form.country" class="field mt-1" :disabled="!canUpdate" />
                </label>
                <label class="block text-sm text-cream/70">
                    Dirección
                    <input v-model="form.address" class="field mt-1" :disabled="!canUpdate" />
                </label>
                <label class="block text-sm text-cream/70 md:col-span-2">
                    Descripción
                    <textarea v-model="form.description" class="field mt-1" rows="3" :disabled="!canUpdate"></textarea>
                </label>
            </div>
            <button v-if="canUpdate" class="btn-primary" :disabled="form.processing">Guardar club</button>
        </form>

        <form class="panel space-y-5 p-5" @submit.prevent="saveNotices">
            <div>
                <h2 class="font-display text-xl">Avisos del club</h2>
                <p class="mt-1 text-sm text-cream/60">
                    Los días son la anticipación. Hay una sola hora para vencimientos y cumpleaños.
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <fieldset class="space-y-3 rounded-2xl border border-white/10 p-4">
                    <legend class="px-1 font-medium text-cream">Vencimientos</legend>
                    <p class="text-xs text-cream/50">
                        Llegan a director, subdirector, secretario, tesorero y al integrante. Se repiten hasta el día del vencimiento o hasta que el documento se actualice.
                    </p>
                    <label v-for="item in documentAlerts" :key="item.slug" class="block text-sm text-cream/70">
                        {{ item.label }}
                        <span class="text-cream/40">(días)</span>
                        <input
                            v-model.number="notices[item.slug as 'licencia' | 'soat' | 'tecnomecanica']"
                            type="number"
                            min="1"
                            max="365"
                            class="field mt-1"
                            :disabled="!canUpdate"
                            required
                        />
                    </label>
                    <label class="block text-sm text-cream/70">
                        Repetir cada
                        <span class="text-cream/40">(días)</span>
                        <input v-model.number="notices.repeat_every" type="number" min="1" max="30" class="field mt-1" :disabled="!canUpdate" required />
                    </label>
                    <p class="text-xs text-cream/40">1 es cada día. 2 es cada dos días.</p>
                </fieldset>

                <fieldset class="space-y-3 rounded-2xl border border-white/10 p-4">
                    <legend class="px-1 font-medium text-cream">Cumpleaños</legend>
                    <p class="text-xs text-cream/50">Llegan a director, subdirector, secretario, capellán y tesorero. El integrante no los recibe.</p>
                    <label class="block text-sm text-cream/70">
                        Anticipación
                        <span class="text-cream/40">(días)</span>
                        <input v-model.number="notices.birthday_days" type="number" min="1" max="365" class="field mt-1" :disabled="!canUpdate" required />
                    </label>
                </fieldset>
            </div>

            <label class="block max-w-xs text-sm text-cream/70">
                Hora del aviso
                <input v-model="notices.alert_time" type="time" class="field mt-1" :disabled="!canUpdate" required />
            </label>
            <button v-if="canUpdate" class="btn-primary" :disabled="notices.processing">Guardar avisos</button>
        </form>

        <section class="panel space-y-4 p-5">
            <div>
                <h2 class="font-display text-xl">Notificaciones</h2>
                <p class="mt-1 text-sm text-cream/60">
                    Activa o apaga lo que quieres recibir. El cambio se guarda al instante y solo afecta a tu usuario.
                </p>
            </div>
            <AlertPreferencesForm :documents="alertPreferences.documents" :birthdays="alertPreferences.birthdays" />
        </section>
    </AppLayout>
</template>
