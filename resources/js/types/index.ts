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

export type PageProps = {
    auth: { user: AuthUser | null };
    organization: OrganizationSummary | null;
    current_role?: CurrentRole | null;
    can_switch_clubs?: boolean;
    permissions: PermissionSlug[];
    googleEnabled?: boolean;
    flash: { success?: string | null; error?: string | null; analyzed?: string | null };
} & Record<string, unknown>;

export type InscriptionLicenseCategory = {
    category: string;
    expires_at: string;
};

export type InscriptionFormData = {
    first_name: string;
    middle_name: string;
    last_name: string;
    second_last_name: string;
    document_type: string;
    document_number: string;
    document_issued_in: string;
    birth_date: string;
    birth_city: string;
    birth_department: string;
    gender: string;
    marital_status: string;
    email: string;
    phone: string;
    address: string;
    neighborhood: string;
    city: string;
    department: string;
    vehicle_role: string;
    pilot_person_id: string;
    license_document_number: string;
    license_expiration: string;
    license_categories: InscriptionLicenseCategory[];
    soat_expiration: string;
    tecnomecanica_expiration: string;
    motorcycle: {
        vehicle_type: string;
        brand: string;
        line: string;
        model: string;
        year: string;
        color: string;
        engine_displacement: string;
        plate: string;
        owner_name: string;
        owner_document_number: string;
    };
    health: {
        eps: string;
        blood_type: string;
        rh: string;
        weight_kg: string;
        height_cm: string;
        special_treatment: string;
        medications: string;
        allergies: string;
    };
    emergency_contact: {
        name: string;
        phone: string;
        address: string;
        relationship: string;
    };
    metadata: {
        church_name: string;
        church_district: string;
        church_role: string;
        baptized: boolean;
        baptized_at: string;
    };
};
