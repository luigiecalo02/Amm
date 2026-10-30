<?php

namespace App\Services\Inscriptions;

use App\Models\Person;

final class InscriptionFormMapper
{
    /**
     * @param  array<string, mixed>  $extracted
     * @return array<string, mixed>
     */
    public function toForm(array $extracted, array $draft = []): array
    {
        $line = $extracted['line'] ?? $extracted['model'] ?? $draft['motorcycle']['line'] ?? '';

        return [
            'first_name' => $extracted['first_name'] ?? $draft['first_name'] ?? '',
            'middle_name' => $extracted['middle_name'] ?? $draft['middle_name'] ?? '',
            'last_name' => $extracted['last_name'] ?? $draft['last_name'] ?? '',
            'second_last_name' => $extracted['second_last_name'] ?? $draft['second_last_name'] ?? '',
            'document_type' => $extracted['document_type'] ?? $draft['document_type'] ?? 'cc',
            'document_number' => $extracted['document_number'] ?? $draft['document_number'] ?? '',
            'document_issued_in' => $extracted['document_issued_in'] ?? '',
            'birth_date' => $extracted['birth_date'] ?? '',
            'birth_city' => $extracted['birth_city'] ?? '',
            'birth_department' => $extracted['birth_department'] ?? '',
            'gender' => $extracted['gender'] ?? '',
            'marital_status' => $extracted['marital_status'] ?? 'soltero',
            'email' => $extracted['email'] ?? $draft['email'] ?? '',
            'phone' => $extracted['phone'] ?? $draft['phone'] ?? '',
            'address' => $extracted['address'] ?? $draft['address'] ?? '',
            'neighborhood' => $extracted['neighborhood'] ?? $draft['neighborhood'] ?? '',
            'city' => $extracted['city'] ?? $draft['city'] ?? '',
            'department' => $extracted['department'] ?? $draft['department'] ?? '',
            'vehicle_role' => $draft['vehicle_role'] ?? 'driver',
            'pilot_person_id' => $draft['pilot_person_id'] ?? '',
            'metadata' => [
                'church_name' => $draft['metadata']['church_name'] ?? '',
                'church_district' => $draft['metadata']['church_district'] ?? '',
                'church_role' => $draft['metadata']['church_role'] ?? '',
                'baptized' => $draft['metadata']['baptized'] ?? false,
                'baptized_at' => $draft['metadata']['baptized_at'] ?? '',
            ],
            'health' => [
                'eps' => '',
                'blood_type' => $extracted['blood_type'] ?? '',
                'rh' => $extracted['rh'] ?? '',
                'weight_kg' => '',
                'height_cm' => $extracted['height_cm'] ?? '',
                'special_treatment' => '',
                'medications' => '',
                'allergies' => '',
            ],
            'emergency_contact' => [
                'name' => '',
                'phone' => '',
                'address' => '',
                'relationship' => '',
            ],
            'motorcycle' => [
                'vehicle_type' => 'motorcycle',
                'brand' => $extracted['brand'] ?? '',
                'line' => $line,
                'model' => $line,
                'year' => $extracted['year'] ?? '',
                'color' => $extracted['color'] ?? '',
                'engine_displacement' => $extracted['engine_displacement'] ?? '',
                'plate' => $extracted['plate'] ?? '',
                'owner_name' => $extracted['owner_name'] ?? '',
                'owner_document_number' => $extracted['owner_document_number'] ?? '',
            ],
            'license_document_number' => $extracted['license_document_number'] ?? '',
            'license_categories' => $extracted['license_categories'] ?? [],
            'license_expiration' => $extracted['license_expiration'] ?? '',
            'soat_expiration' => $extracted['soat_expiration'] ?? '',
            'tecnomecanica_expiration' => $extracted['tecnomecanica_expiration'] ?? '',
            'registered' => is_array($draft['registered'] ?? null) ? $draft['registered'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function fromPerson(?Person $person, array $draft = []): array
    {
        if ($person === null) {
            return $this->toForm([], $draft);
        }

        $membership = $person->memberships->first();
        $health = $person->healthProfile;
        $contact = $person->emergencyContacts->first();
        $motorcycle = $person->motorcycles->first();
        $meta = $membership?->metadata ?? [];
        $value = static fn (mixed $item) => $item ?? '';

        return [
            'first_name' => $value($person->first_name),
            'middle_name' => $value($person->middle_name),
            'last_name' => $value($person->last_name),
            'second_last_name' => $value($person->second_last_name),
            'document_type' => $value($person->document_type) ?: 'cc',
            'document_number' => $value($person->document_number),
            'document_issued_in' => $value($person->document_issued_in),
            'birth_date' => $person->birth_date?->toDateString() ?? '',
            'birth_city' => $value($person->birth_city),
            'birth_department' => $value($person->birth_department),
            'gender' => $value($person->gender),
            'marital_status' => $value($person->marital_status) ?: 'soltero',
            'email' => $value($person->email),
            'phone' => $value($person->phone),
            'address' => $value($person->address),
            'neighborhood' => $value($person->neighborhood),
            'city' => $value($person->city),
            'department' => $value($person->department),
            'vehicle_role' => $membership?->vehicle_role ?: ($draft['vehicle_role'] ?? 'driver'),
            'pilot_person_id' => $meta['pilot_person_id'] ?? $draft['pilot_person_id'] ?? '',
            'metadata' => [
                'church_name' => $meta['church_name'] ?? $draft['metadata']['church_name'] ?? '',
                'church_district' => $meta['church_district'] ?? $draft['metadata']['church_district'] ?? '',
                'church_role' => $meta['church_role'] ?? $draft['metadata']['church_role'] ?? '',
                'baptized' => $meta['baptized'] ?? $draft['metadata']['baptized'] ?? false,
                'baptized_at' => $meta['baptized_at'] ?? $draft['metadata']['baptized_at'] ?? '',
            ],
            'health' => [
                'eps' => $value($health?->eps),
                'blood_type' => $value($health?->blood_type),
                'rh' => $value($health?->rh),
                'weight_kg' => $value($health?->weight_kg),
                'height_cm' => $value($health?->height_cm),
                'special_treatment' => $value($health?->special_treatment),
                'medications' => $value($health?->medications),
                'allergies' => $value($health?->allergies),
            ],
            'emergency_contact' => [
                'name' => $value($contact?->name),
                'phone' => $value($contact?->phone),
                'address' => $value($contact?->address),
                'relationship' => $value($contact?->relationship),
            ],
            'motorcycle' => [
                'vehicle_type' => $motorcycle?->vehicle_type ?: 'motorcycle',
                'brand' => $value($motorcycle?->brand),
                'line' => $value($motorcycle?->model),
                'model' => $value($motorcycle?->model),
                'year' => $value($motorcycle?->year),
                'color' => $value($motorcycle?->color),
                'engine_displacement' => $value($motorcycle?->engine_displacement),
                'plate' => $value($motorcycle?->plate),
                'owner_name' => $meta['vehicle_owner_name'] ?? $draft['motorcycle']['owner_name'] ?? '',
                'owner_document_number' => $meta['vehicle_owner_document'] ?? $draft['motorcycle']['owner_document_number'] ?? '',
            ],
            'license_document_number' => $meta['license_document_number'] ?? $draft['license_document_number'] ?? '',
            'license_categories' => $meta['license_categories'] ?? $draft['license_categories'] ?? [],
            'license_expiration' => $draft['license_expiration'] ?? '',
            'soat_expiration' => $draft['soat_expiration'] ?? '',
            'tecnomecanica_expiration' => $draft['tecnomecanica_expiration'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @return array<int, string>
     */
    public function filledKeys(array $extracted): array
    {
        return array_keys(array_filter(
            $extracted,
            fn ($value) => $value !== null && $value !== '' && $value !== []
        ));
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @return array<int, string>
     */
    public function warnings(array $extracted): array
    {
        $warnings = [];
        $cedula = preg_replace('/\D+/', '', (string) ($extracted['document_number'] ?? '')) ?? '';
        $license = preg_replace('/\D+/', '', (string) ($extracted['license_document_number'] ?? '')) ?? '';
        $owner = preg_replace('/\D+/', '', (string) ($extracted['owner_document_number'] ?? '')) ?? '';

        if ($cedula !== '' && $license !== '' && $cedula !== $license) {
            $warnings[] = 'La identificación de la licencia no coincide con la cédula.';
        }

        if ($cedula !== '' && $owner !== '' && $cedula !== $owner) {
            $warnings[] = 'La identificación del propietario en la tarjeta no coincide con la cédula.';
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>|null  $draft
     */
    public function hasBasics(?array $draft): bool
    {
        return filled($draft['first_name'] ?? null)
            && filled($draft['last_name'] ?? null)
            && filled($draft['document_number'] ?? null)
            && in_array($draft['vehicle_role'] ?? null, ['driver', 'passenger'], true);
    }
}
