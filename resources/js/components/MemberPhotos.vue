<script setup lang="ts">
import PhotoPicker from '@/components/PhotoPicker.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, useSlots } from 'vue';
import { useOrganization } from '@/composables/useOrganization';
import type { PageProps } from '@/types';

export type MemberMotorcycle = {
    id: string;
    plate?: string | null;
    brand?: string | null;
    model?: string | null;
    year?: number | string | null;
    color?: string | null;
    engine_displacement?: number | string | null;
    vehicle_type?: string | null;
    photo_url?: string | null;
    owner_name?: string | null;
};

const props = withDefaults(defineProps<{
    person: {
        id: string;
        photo_url?: string | null;
        motorcycles?: MemberMotorcycle[];
    };
    memberActive: boolean;
    canUpdateProfile: boolean;
    canUpdateMotorcycle: boolean;
    showProfile?: boolean;
}>(), {
    showProfile: true,
});

const slots = useSlots();
const { clubPath } = useOrganization();
const page = usePage<PageProps>();
const errors = computed(() => (page.props.errors as Record<string, string> | undefined) ?? {});
const motorcycles = computed(() => props.person.motorcycles ?? []);
const profileVisible = computed(() => props.showProfile);
const hasCover = computed(() => Boolean(slots.default));
const profileForm = useForm<{ photo: File | null }>({ photo: null });
const motorcycleForm = useForm<{ motorcycle_id: string; motorcycle_photo: File | null }>({
    motorcycle_id: '',
    motorcycle_photo: null,
});

const saveProfile = (file: File) => {
    profileForm.photo = file;
    profileForm.post(clubPath(`people/${props.person.id}/fotos`), {
        forceFormData: true,
        preserveScroll: true,
    });
};

const saveMotorcycle = (motorcycleId: string, file: File) => {
    motorcycleForm.motorcycle_id = motorcycleId;
    motorcycleForm.motorcycle_photo = file;
    motorcycleForm.post(clubPath(`people/${props.person.id}/fotos`), {
        forceFormData: true,
        preserveScroll: true,
    });
};

const profileSlot = computed(() => ({
    canEditProfile: props.memberActive && props.canUpdateProfile,
    processing: profileForm.processing,
    error: profileForm.errors.photo || errors.value.photo || '',
    saveProfile,
}));
</script>

<template>
    <p v-if="!memberActive" class="text-sm text-cream/60">
        Aprueba la solicitud para activar al integrante. Después podrás actualizar su foto de perfil y la de la moto.
    </p>
    <p v-else-if="errors.status" class="text-sm text-red-300">{{ errors.status }}</p>

    <div class="space-y-4">
        <div
            v-for="(moto, index) in motorcycles"
            :key="moto.id"
            class="relative"
            :class="index === 0 && hasCover ? 'mb-16' : ''"
        >
            <PhotoPicker
                :current-url="moto.photo_url"
                alt="Foto de la moto"
                placeholder="/images/motorcycle-placeholder.svg"
                shape="banner"
                :can-edit="memberActive && canUpdateMotorcycle"
                :processing="motorcycleForm.processing && motorcycleForm.motorcycle_id === moto.id"
                :error="motorcycleForm.motorcycle_id === moto.id ? (motorcycleForm.errors.motorcycle_photo || errors.motorcycle_photo) : ''"
                @save="saveMotorcycle(moto.id, $event)"
            >
                <template v-if="index === 0 && hasCover" #overlay>
                    <div
                        class="pointer-events-none absolute inset-0 bg-[radial-gradient(85%_80%_at_0%_100%,rgba(8,10,14,0.95)_0%,rgba(8,10,14,0.7)_34%,rgba(8,10,14,0.28)_58%,transparent_76%)]"
                    />
                </template>
            </PhotoPicker>
            <div v-if="index === 0 && hasCover" class="pointer-events-none absolute inset-x-0 bottom-0 z-20">
                <div class="pointer-events-auto">
                    <slot v-bind="profileSlot" />
                </div>
            </div>
        </div>

        <template v-if="!motorcycles.length">
            <p v-if="memberActive" class="text-sm text-cream/50">Este integrante no tiene moto registrada.</p>
            <slot v-bind="profileSlot" />
        </template>

        <div v-if="profileVisible" class="max-w-56">
            <PhotoPicker
                label="Cambiar foto de perfil"
                :current-url="person.photo_url"
                alt="Foto de perfil"
                shape="portrait"
                :can-edit="memberActive && canUpdateProfile"
                :processing="profileForm.processing"
                :error="profileForm.errors.photo || errors.photo"
                @save="saveProfile"
            />
        </div>
    </div>
</template>
