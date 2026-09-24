import { usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

export function usePermissions() {
    const page = usePage<PageProps>();

    const can = (permission: string): boolean => {
        const permissions = page.props.permissions ?? [];
        return permissions.includes('*') || permissions.includes(permission);
    };

    return { can };
}
