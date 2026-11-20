<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useWebPush } from '@/composables/useWebPush';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const open = ref(false);
const notifications = computed(() => page.props.notifications ?? { unread_count: 0, items: [] });
const push = useWebPush(page.props.vapidPublicKey ?? null);
const countLabel = computed(() => notifications.value.unread_count > 9 ? '9+' : String(notifications.value.unread_count));
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative rounded-xl px-2 py-1 text-cream/70 hover:text-cream"
            aria-label="Avisos"
            @click="open = !open"
        >
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M6 9a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9" stroke-linejoin="round" />
                <path d="M10 20a2 2 0 0 0 4 0" stroke-linecap="round" />
            </svg>
            <span
                v-if="notifications.unread_count"
                class="absolute -right-1 -top-1 rounded-full bg-ember-400 px-1.5 text-[10px] font-medium text-ink-900"
            >
                {{ countLabel }}
            </span>
        </button>

        <div v-if="open" class="absolute right-0 z-30 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-2xl border border-white/10 bg-ink-900 p-3 shadow-xl">
            <p class="px-1 text-xs uppercase tracking-widest text-cream/50">Avisos</p>
            <button
                v-if="push.supported.value && !push.enabled.value"
                type="button"
                class="btn-primary mt-2 w-full"
                :disabled="push.enabling.value || push.blocked.value"
                @click="push.enable()"
            >
                {{ push.blocked.value ? 'Avisos bloqueados en el navegador' : 'Activar avisos en este celular' }}
            </button>
            <button
                v-else-if="push.enabled.value"
                type="button"
                class="btn-ghost mt-2 w-full"
                :disabled="push.enabling.value"
                @click="push.disable()"
            >
                Desactivar avisos en este dispositivo
            </button>
            <div class="mt-2 space-y-1">
                <Link
                    v-for="item in notifications.items"
                    :key="item.id"
                    :href="`/notificaciones/${item.id}`"
                    class="block rounded-xl px-2 py-2 hover:bg-white/5"
                    @click="open = false"
                >
                    <p class="text-sm font-medium text-cream">{{ item.title }}</p>
                    <p class="text-xs text-cream/60">{{ item.body }}</p>
                </Link>
                <p v-if="!notifications.items.length" class="px-2 py-3 text-sm text-cream/50">No hay avisos nuevos.</p>
            </div>
        </div>
    </div>
</template>
