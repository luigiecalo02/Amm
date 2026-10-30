<script setup lang="ts">
defineProps<{
    club?: { name: string; city?: string | null };
}>();
</script>

<template>
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,_#1A2332,_#0B0F14_55%)] px-4 py-8">
        <div class="mx-auto max-w-4xl">
            <header class="mb-6 flex items-center gap-4">
                <img src="/images/amm-logo.png" alt="AMM" class="h-16 w-16 rounded-full bg-white object-contain p-1" />
                <div>
                    <p class="font-display text-xs uppercase tracking-[0.3em] text-ember-400">Inscripción AMM</p>
                    <h1 class="font-display text-3xl">{{ club?.name ?? 'Adventist Motorcycle Ministry' }}</h1>
                    <p v-if="club?.city" class="text-sm text-cream/50">{{ club.city }}</p>
                </div>
            </header>
            <slot />
        </div>
    </div>
</template>
