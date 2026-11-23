<script setup lang="ts">
import MemberAvatar from '@/components/MemberAvatar.vue';
import MemberPhotos from '@/components/MemberPhotos.vue';
import { useShrinkToFit } from '@/composables/useShrinkToFit';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = withDefaults(defineProps<{
    person: any;
    kicker?: string;
    editHref?: string | null;
    memberActive: boolean;
    canUpdateProfile: boolean;
    canUpdateMotorcycle: boolean;
}>(), {
    kicker: 'Ficha de inscripción',
    editHref: null,
});

const hasMotorcycle = computed(() => (props.person.motorcycles ?? []).length > 0);
const photoInput = ref<HTMLInputElement | null>(null);
const nameHeading = ref<HTMLElement | null>(null);
const fullName = computed(() => [props.person.first_name, props.person.last_name, props.person.second_last_name].filter(Boolean).join(' '));

useShrinkToFit(nameHeading, fullName);

const pickProfilePhoto = (saveProfile: (file: File) => void, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    input.value = '';

    if (file) {
        saveProfile(file);
    }
};
</script>

<template>
    <MemberPhotos
        :person="person"
        :member-active="memberActive"
        :can-update-profile="canUpdateProfile"
        :can-update-motorcycle="canUpdateMotorcycle"
        :show-profile="false"
    >
        <template #default="{ canEditProfile, saveProfile, processing, error }">
            <div class="relative" :class="hasMotorcycle ? 'px-4' : ''">
                <Link
                    v-if="editHref"
                    :href="editHref"
                    class="btn-ghost absolute right-4 top-0 z-10 -translate-y-[calc(100%+0.5rem)] bg-ink-950/40 backdrop-blur-sm"
                >
                    Editar
                </Link>
                <div
                    class="flex items-center gap-3"
                    :class="hasMotorcycle ? 'translate-y-1/2' : 'justify-between'"
                >
                    <span class="relative h-16 w-16 shrink-0 md:h-24 md:w-24">
                        <MemberAvatar
                            :src="person.photo_url"
                            :alt="`${person.first_name} ${person.last_name}`"
                            class="h-16 w-16 shadow-[0_0_0_4px_#0B0F14] md:h-24 md:w-24"
                            :class="{ 'opacity-60': processing }"
                        />
                        <button
                            v-if="canEditProfile"
                            type="button"
                            class="absolute -bottom-0.5 -right-0.5 flex h-7 w-7 items-center justify-center rounded-full bg-ember-500 text-ink-950 shadow ring-2 ring-[#0B0F14] hover:bg-ember-400 md:h-8 md:w-8"
                            :disabled="processing"
                            :aria-label="processing ? 'Guardando foto…' : 'Actualizar foto de perfil'"
                            title="Actualizar foto"
                            @click="photoInput?.click()"
                        >
                            <svg v-if="!processing" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                <path fill-rule="evenodd" d="M1 8a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 018.07 3h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0016.07 6H17a2 2 0 012 2v7a2 2 0 01-2 2H3a2 2 0 01-2-2V8zm13.5 3a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM10 9a2 2 0 100 4 2 2 0 000-4z" clip-rule="evenodd" />
                            </svg>
                            <span v-else class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-ink-950/30 border-t-ink-950" />
                        </button>
                        <input
                            ref="photoInput"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="pickProfilePhoto(saveProfile, $event)"
                        />
                    </span>
                    <div class="flex min-w-0 items-center gap-2.5">
                        <div class="min-w-0 leading-none">
                            <p class="text-[11px] font-medium uppercase tracking-[0.18em] text-ember-400">{{ kicker }}</p>
                            <h1 ref="nameHeading" class="mt-0.5 font-display text-[1.85rem] leading-none text-cream drop-shadow-[0_1px_8px_rgba(0,0,0,0.65)]">
                                {{ fullName }}
                            </h1>
                            <p class="mt-1 text-sm leading-none text-cream/50 drop-shadow-[0_1px_8px_rgba(0,0,0,0.65)]">
                                {{ person.document_type?.toUpperCase() }} {{ person.document_number }}
                            </p>
                        </div>
                        <img
                            src="/images/amm-logo.png"
                            alt="AMM"
                            class="h-[3.75rem] w-[3.75rem] shrink-0 object-contain drop-shadow-[0_1px_8px_rgba(0,0,0,0.65)]"
                        />
                    </div>
                </div>
                <p v-if="error" class="mt-2 text-sm text-red-300">{{ error }}</p>
            </div>
        </template>
    </MemberPhotos>
</template>
