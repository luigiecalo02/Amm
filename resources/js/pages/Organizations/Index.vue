<script setup lang="ts">
import { useForm, Link, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

defineProps<{
    organizations: {
        id: string;
        name: string;
        slug: string;
        city?: string | null;
        department?: string | null;
        email?: string | null;
        phone?: string | null;
        status?: string;
        members_count?: number;
    }[];
    canCreate: boolean;
}>();

const page = usePage<PageProps>();
const form = useForm({
    name: '',
    city: '',
    department: '',
    email: '',
    phone: '',
    address: '',
});

const submit = () => form.post('/organizations');
</script>

<template>
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,_#1A2332,_#0B0F14_55%)] px-4 py-8">
        <div class="mx-auto max-w-5xl">
            <header class="mb-8 flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <img src="/images/amm-logo.png" alt="AMM" class="h-16 w-16 rounded-full bg-white object-contain p-1" />
                    <div>
                        <p class="font-display text-xs uppercase tracking-[0.3em] text-ember-400">
                            {{ page.props.current_role?.name ?? 'AMM' }}
                        </p>
                        <h1 class="font-display text-4xl">{{ canCreate ? 'Todos los clubes' : 'Tu club' }}</h1>
                    </div>
                </div>
                <Link href="/logout" method="post" as="button" class="btn-ghost">Salir</Link>
            </header>

            <div v-if="page.props.flash?.success" class="mb-4 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                {{ page.props.flash.success }}
            </div>

            <p class="mb-6 text-sm text-cream/60">
                <template v-if="canCreate">Como superadministrador puedes ver todos los clubes y crear nuevos.</template>
                <template v-else>Solo puedes entrar al club al que perteneces y a lo que permita tu rol.</template>
            </p>

            <section class="grid gap-4 md:grid-cols-2">
                <Link
                    v-for="org in organizations"
                    :key="org.id"
                    :href="`/app/${org.slug}`"
                    class="panel p-5 transition hover:border-ember-500/40"
                >
                    <p class="font-display text-2xl">{{ org.name }}</p>
                    <p class="mt-1 text-sm text-cream/50">{{ org.city }} {{ org.department }}</p>
                    <p class="mt-3 text-sm text-ember-400">{{ org.members_count ?? 0 }} miembros</p>
                </Link>
                <p v-if="!organizations.length" class="text-cream/50">Aún no hay clubes registrados.</p>
            </section>

            <form v-if="canCreate" class="panel mt-8 grid gap-3 p-5 md:grid-cols-2" @submit.prevent="submit">
                <h2 class="font-display text-2xl md:col-span-2">Crear un club</h2>
                <input v-model="form.name" class="field md:col-span-2" placeholder="Nombre del club" required />
                <input v-model="form.city" class="field" placeholder="Ciudad" />
                <input v-model="form.department" class="field" placeholder="Departamento" />
                <input v-model="form.email" type="email" class="field" placeholder="Correo del club" />
                <input v-model="form.phone" class="field" placeholder="Teléfono" />
                <input v-model="form.address" class="field md:col-span-2" placeholder="Dirección" />
                <p v-if="form.errors.name" class="text-sm text-red-300 md:col-span-2">{{ form.errors.name }}</p>
                <button class="btn-primary md:col-span-2" :disabled="form.processing">Crear club</button>
            </form>
        </div>
    </div>
</template>
