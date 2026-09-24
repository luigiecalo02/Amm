<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: 'director@amm.test',
    password: 'password',
    remember: true,
});

const submit = () => {
    form.post('/login');
};
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-[radial-gradient(circle_at_top,_#243044,_#07090D_60%)] px-4">
        <div class="w-full max-w-md panel p-8">
            <p class="font-display text-xs uppercase tracking-[0.3em] text-ember-400">Plataforma de clubes</p>
            <h1 class="mt-2 font-display text-4xl">AMM</h1>
            <p class="mt-2 text-sm text-cream/60">Entra para gestionar personas, motos y vencimientos del club.</p>

            <form class="mt-8 space-y-4" @submit.prevent="submit">
                <label class="block text-sm">
                    <span class="mb-1 block text-cream/70">Correo</span>
                    <input v-model="form.email" type="email" class="field" required />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-300">{{ form.errors.email }}</p>
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-cream/70">Contraseña</span>
                    <input v-model="form.password" type="password" class="field" required />
                </label>
                <button class="btn-primary w-full" :disabled="form.processing">Entrar al club</button>
            </form>
        </div>
    </div>
</template>
