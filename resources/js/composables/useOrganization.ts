import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

export function useOrganization() {
    const page = usePage<PageProps>();

    const organization = computed(() => page.props.organization);

    const slug = computed(() => {
        if (page.props.organization?.slug) {
            return page.props.organization.slug;
        }

        const match = page.url.match(/^\/app\/([^/?#]+)/);

        return match?.[1] && match[1] !== 'undefined' ? match[1] : '';
    });

    const clubPath = (path = ''): string => {
        const suffix = path ? `/${path.replace(/^\/+/, '')}` : '';

        return `/app/${slug.value}${suffix}`;
    };

    return { organization, slug, clubPath };
}
