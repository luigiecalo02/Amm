import { defineStore } from 'pinia';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

export const useOrganizationStore = defineStore('organization', () => {
    const page = usePage<PageProps>();
    const current = computed(() => page.props.organization);

    return { current };
});
