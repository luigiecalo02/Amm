import type { InscriptionFormData, InscriptionLicenseCategory } from '@/types';

type Source = Record<string, any>;

const text = (value: unknown): string => (value == null ? '' : String(value));

const categories = (value: unknown): InscriptionLicenseCategory[] => {
    if (!Array.isArray(value)) {
        return [];
    }

    return value.map((item) => ({
        category: text(item?.category),
        expires_at: text(item?.expires_at),
    }));
};

export function inscriptionFormDefaults(source: Source = {}, role?: string): InscriptionFormData {
    return {
        first_name: text(source.first_name),
        middle_name: text(source.middle_name),
        last_name: text(source.last_name),
        second_last_name: text(source.second_last_name),
        document_type: text(source.document_type) || 'cc',
        document_number: text(source.document_number),
        document_issued_in: text(source.document_issued_in),
        birth_date: text(source.birth_date),
        birth_city: text(source.birth_city),
        birth_department: text(source.birth_department),
        gender: text(source.gender),
        marital_status: text(source.marital_status),
        email: text(source.email),
        phone: text(source.phone),
        address: text(source.address),
        neighborhood: text(source.neighborhood),
        city: text(source.city),
        department: text(source.department),
        vehicle_role: text(source.vehicle_role) || role || 'driver',
        pilot_person_id: text(source.pilot_person_id),
        license_document_number: text(source.license_document_number),
        license_expiration: text(source.license_expiration),
        license_categories: categories(source.license_categories),
        soat_expiration: text(source.soat_expiration),
        tecnomecanica_expiration: text(source.tecnomecanica_expiration),
        motorcycle: {
            vehicle_type: 'motorcycle',
            brand: '',
            line: '',
            model: '',
            year: '',
            color: '',
            engine_displacement: '',
            plate: '',
            owner_name: '',
            owner_document_number: '',
            ...(source.motorcycle ?? {}),
        },
        health: {
            eps: '',
            blood_type: '',
            rh: '',
            weight_kg: '',
            height_cm: '',
            special_treatment: '',
            medications: '',
            allergies: '',
            ...(source.health ?? {}),
        },
        emergency_contact: {
            name: '',
            phone: '',
            address: '',
            relationship: '',
            ...(source.emergency_contact ?? {}),
        },
        metadata: {
            church_name: '',
            church_district: '',
            church_role: '',
            baptized: false,
            baptized_at: '',
            ...(source.metadata ?? {}),
        },
    };
}
