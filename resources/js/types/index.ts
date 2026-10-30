export type PermissionSlug = string;

export interface AuthUser {
    id: string;
    name: string;
    email: string;
    is_platform_admin: boolean;
}

export interface OrganizationSummary {
    id: string;
    name: string;
    slug: string;
    city?: string | null;
}

export interface Person {
    id: string;
    document_type: string;
    document_number: string;
    first_name: string;
    middle_name?: string | null;
    last_name: string;
    second_last_name?: string | null;
    full_name?: string;
    phone?: string | null;
    email?: string | null;
    city?: string | null;
    status: string;
}

export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
}

export interface CurrentRole {
    name: string;
    scope: 'platform' | 'organization';
}

export interface PageProps {
    auth: { user: AuthUser | null };
    organization: OrganizationSummary | null;
    current_role?: CurrentRole | null;
    can_switch_clubs?: boolean;
    permissions: PermissionSlug[];
    googleEnabled?: boolean;
    flash: { success?: string | null; error?: string | null; analyzed?: string | null };
}
