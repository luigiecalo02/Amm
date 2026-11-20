<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import { usePwaInstall } from '@/composables/usePwaInstall';

const page = usePage<PageProps>();
const form = useForm({
    email: '',
    password: '',
    remember: true,
});
const { canInstall, hint, install } = usePwaInstall();

const submit = () => {
    form.post('/login');
};
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-[radial-gradient(circle_at_top,_#243044,_#07090D_60%)] px-4 py-10">
        <div class="w-full max-w-md panel p-8 text-center">
            <img
                src="/images/amm-logo.png"
                alt="AMM Adventist Motorcycle Ministry"
                class="mx-auto h-44 w-44 object-contain"
            />
            <p class="mt-5 font-display text-xs uppercase tracking-[0.3em] text-ember-400">Adventist Motorcycle Ministry</p>
            <h1 class="mt-2 font-display text-4xl">AMM · ADNM</h1>
            <p class="mt-2 text-sm text-cream/60">Entra con el correo y la contraseña del club.</p>

            <div v-if="page.props.flash?.error" class="mt-4 rounded-xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-left text-sm text-red-200">
                {{ page.props.flash.error }}
            </div>

            <form class="mt-8 space-y-4 text-left" @submit.prevent="submit">
                <label class="block text-sm">
                    <span class="mb-1 block text-cream/70">Correo</span>
                    <input v-model="form.email" type="email" class="field" required />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-300">{{ form.errors.email }}</p>
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-cream/70">Contraseña</span>
                    <input v-model="form.password" type="password" class="field" required />
                </label>
                <button class="btn-primary w-full" :disabled="form.processing">Entrar</button>
            </form>

            <div v-if="canInstall" class="mt-6 border-t border-white/10 pt-5">
                <button type="button" class="btn-primary w-full" @click="install">
                    Instalar esta aplicación
                </button>
                <p class="mt-2 text-xs text-cream/50">
                    {{ hint || 'Instálala en tu celular o computadora para abrirla como una app.' }}
                </p>
            </div>
        </div>
    </div>
</template>
