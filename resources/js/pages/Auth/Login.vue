<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const form = useForm({
    email: '',
    password: '',
    remember: true,
});

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
                class="mx-auto h-44 w-44 rounded-full bg-white object-contain p-1 shadow-[0_0_0_6px_rgba(248,244,236,0.12)]"
            />
            <p class="mt-5 font-display text-xs uppercase tracking-[0.3em] text-ember-400">Adventist Motorcycle Ministry</p>
            <h1 class="mt-2 font-display text-4xl">AMM · ADNM</h1>
            <p class="mt-2 text-sm text-cream/60">Regístrate con Google y completa tus datos de inscripción.</p>

            <div v-if="page.props.flash?.error" class="mt-4 rounded-xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-left text-sm text-red-200">
                {{ page.props.flash.error }}
            </div>

            <a href="/auth/google?club=amm-cartagena" class="btn-primary mt-8 flex w-full items-center justify-center gap-3">
                <span class="flex h-6 w-6 items-center justify-center rounded bg-white text-sm font-bold text-[#4285F4]">G</span>
                Continuar con Google
            </a>
            <p class="mt-3 text-xs text-cream/50">Usa tu cuenta Google para inscribirte al club o entrar.</p>

            <div class="my-6 flex items-center gap-3 text-xs uppercase tracking-widest text-cream/30">
                <span class="h-px flex-1 bg-white/10" />
                o con correo del club
                <span class="h-px flex-1 bg-white/10" />
            </div>

            <form class="space-y-4 text-left" @submit.prevent="submit">
                <label class="block text-sm">
                    <span class="mb-1 block text-cream/70">Correo</span>
                    <input v-model="form.email" type="email" class="field" required />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-300">{{ form.errors.email }}</p>
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-cream/70">Contraseña</span>
                    <input v-model="form.password" type="password" class="field" required />
                </label>
                <button class="btn-ghost w-full" :disabled="form.processing">Entrar como administrador</button>
            </form>
        </div>
    </div>
</template>
