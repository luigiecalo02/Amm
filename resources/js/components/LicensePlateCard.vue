<script setup lang="ts">
import LicensePlate from '@/components/LicensePlate.vue';
import MemberAvatar from '@/components/MemberAvatar.vue';
import { computed, ref } from 'vue';

export type PlateRider = {
    role: string;
    name: string;
    photo_url?: string | null;
};

const props = defineProps<{
    plate: string;
    caption?: string | null;
    riders?: PlateRider[];
}>();

const flipped = ref(false);
const riders = computed(() => props.riders ?? []);
</script>

<template>
    <button
        type="button"
        class="block w-full cursor-pointer appearance-none border-0 bg-transparent p-0 text-left [perspective:1100px]"
        :aria-pressed="flipped"
        :aria-label="flipped
            ? `Volver a la placa ${plate}`
            : `Voltear placa ${plate} para ver piloto y copiloto`"
        @click="flipped = !flipped"
    >
        <div
            class="relative w-full duration-500 ease-out [transform-style:preserve-3d]"
            :class="flipped ? '[transform:rotateY(180deg)]' : '[transform:rotateY(0deg)]'"
            style="transition-property: transform"
        >
            <div class="[backface-visibility:hidden]">
                <LicensePlate class="min-h-[8.5rem]" :plate="plate" :caption="caption" />
            </div>
            <div
                class="absolute inset-0 flex items-center justify-center [backface-visibility:hidden] [transform:rotateY(180deg)]"
            >
                <div
                    class="flex h-full w-full items-center justify-center gap-8 rounded-md bg-[#f5c400] px-4 text-black shadow-[0_0_0_3px_#111,inset_0_0_0_3px_#f5c400,inset_0_0_0_5px_#111]"
                >
                    <figure
                        v-for="(rider, index) in riders"
                        :key="`${rider.role}-${rider.name}`"
                        class="flex min-w-0 max-w-[46%] flex-col items-center"
                        :class="flipped ? 'animate-[plate-pop_0.45s_ease-out_both]' : 'opacity-0'"
                        :style="{ animationDelay: flipped ? `${180 + index * 90}ms` : '0ms' }"
                    >
                        <MemberAvatar
                            :src="rider.photo_url"
                            :alt="rider.name"
                            class="h-16 w-16 ring-2 ring-black"
                        />
                        <figcaption class="mt-1 text-[10px] font-bold uppercase tracking-[0.18em] leading-none">
                            {{ rider.role }}
                        </figcaption>
                        <p class="mt-0.5 max-w-full truncate text-[10px] font-bold uppercase leading-none" :title="rider.name">
                            {{ rider.name }}
                        </p>
                    </figure>
                    <p v-if="!riders.length" class="text-center text-[11px] font-bold uppercase tracking-[0.18em]">
                        Sin piloto ni copiloto
                    </p>
                </div>
            </div>
        </div>
    </button>
</template>

<style scoped>
@keyframes plate-pop {
    from {
        opacity: 0;
        transform: scale(0.72);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }
}
</style>
