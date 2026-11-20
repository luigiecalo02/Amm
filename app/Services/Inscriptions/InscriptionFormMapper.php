<?php

namespace App\Services\Inscriptions;

use App\Models\Document;
use App\Models\InscriptionApplication;
use App\Models\Person;

final class InscriptionFormMapper
{
    /**
     * @param  array<string, mixed>  $extracted
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function toForm(array $extracted, array $draft = []): array
    {
        $draftHealth = is_array($draft['health'] ?? null) ? $draft['health'] : [];
        $draftContact = is_array($draft['emergency_contact'] ?? null) ? $draft['emergency_contact'] : [];
        $draftMotorcycle = is_array($draft['motorcycle'] ?? null) ? $draft['motorcycle'] : [];
        $line = $this->pick(
            $extracted['line'] ?? $extracted['model'] ?? null,
            $draftMotorcycle['line'] ?? $draftMotorcycle['model'] ?? null,
        );

        $form = [
            'first_name' => $this->pick($extracted['first_name'] ?? null, $draft['first_name'] ?? null),
            'middle_name' => $this->pick($extracted['middle_name'] ?? null, $draft['middle_name'] ?? null),
            'last_name' => $this->pick($extracted['last_name'] ?? null, $draft['last_name'] ?? null),
            'second_last_name' => $this->pick($extracted['second_last_name'] ?? null, $draft['second_last_name'] ?? null),
            'document_type' => $this->pick($extracted['document_type'] ?? null, $draft['document_type'] ?? null, 'cc'),
            'document_number' => $this->pick($extracted['document_number'] ?? null, $draft['document_number'] ?? null),
            'document_issued_in' => $this->pick($extracted['document_issued_in'] ?? null, $draft['document_issued_in'] ?? null),
            'birth_date' => $this->pick($extracted['birth_date'] ?? null, $draft['birth_date'] ?? null),
            'birth_city' => $this->pick($extracted['birth_city'] ?? null, $draft['birth_city'] ?? null),
            'birth_department' => $this->pick($extracted['birth_department'] ?? null, $draft['birth_department'] ?? null),
            'gender' => $this->pick($extracted['gender'] ?? null, $draft['gender'] ?? null),
            'marital_status' => $this->pick($extracted['marital_status'] ?? null, $draft['marital_status'] ?? null, 'soltero'),
            'email' => $this->pick($extracted['email'] ?? null, $draft['email'] ?? null),
            'phone' => $this->pick($extracted['phone'] ?? null, $draft['phone'] ?? null),
            'address' => $this->pick($extracted['address'] ?? null, $draft['address'] ?? null),
            'neighborhood' => $this->pick($extracted['neighborhood'] ?? null, $draft['neighborhood'] ?? null),
            'city' => $this->pick($extracted['city'] ?? null, $draft['city'] ?? null),
            'department' => $this->pick($extracted['department'] ?? null, $draft['department'] ?? null),
            'vehicle_role' => $this->pick($draft['vehicle_role'] ?? null, null, 'driver'),
            'pilot_person_id' => $this->pick($draft['pilot_person_id'] ?? null, null),
            'metadata' => [
                'church_name' => $draft['metadata']['church_name'] ?? '',
                'church_district' => $draft['metadata']['church_district'] ?? '',
                'church_role' => $draft['metadata']['church_role'] ?? '',
                'baptized' => $draft['metadata']['baptized'] ?? false,
                'baptized_at' => $draft['metadata']['baptized_at'] ?? '',
            ],
            'health' => [
                'eps' => $this->pick(null, $draftHealth['eps'] ?? null),
                'blood_type' => $this->pick($extracted['blood_type'] ?? null, $draftHealth['blood_type'] ?? null),
                'rh' => $this->pick($extracted['rh'] ?? null, $draftHealth['rh'] ?? null),
                'weight_kg' => $this->pick(null, $draftHealth['weight_kg'] ?? null),
                'height_cm' => $this->pick($extracted['height_cm'] ?? null, $draftHealth['height_cm'] ?? null),
                'special_treatment' => $this->pick(null, $draftHealth['special_treatment'] ?? null),
                'medications' => $this->pick(null, $draftHealth['medications'] ?? null),
                'allergies' => $this->pick(null, $draftHealth['allergies'] ?? null),
            ],
            'emergency_contact' => [
                'name' => $this->pick(null, $draftContact['name'] ?? null),
                'phone' => $this->pick(null, $draftContact['phone'] ?? null),
                'address' => $this->pick(null, $draftContact['address'] ?? null),
                'relationship' => $this->pick(null, $draftContact['relationship'] ?? null),
            ],
            'motorcycle' => [
                'vehicle_type' => $this->pick(null, $draftMotorcycle['vehicle_type'] ?? null, 'motorcycle'),
                'brand' => $this->pick($extracted['brand'] ?? null, $draftMotorcycle['brand'] ?? null),
                'line' => $line,
                'model' => $this->pick($line, $draftMotorcycle['model'] ?? null),
                'year' => $this->pick($extracted['year'] ?? null, $draftMotorcycle['year'] ?? null),
                'color' => $this->pick($extracted['color'] ?? null, $draftMotorcycle['color'] ?? null),
                'engine_displacement' => $this->pick($extracted['engine_displacement'] ?? null, $draftMotorcycle['engine_displacement'] ?? null),
                'plate' => $this->pick($extracted['plate'] ?? null, $draftMotorcycle['plate'] ?? null),
                'owner_name' => $this->pick($extracted['owner_name'] ?? null, $draftMotorcycle['owner_name'] ?? null),
                'owner_document_number' => $this->pick($extracted['owner_document_number'] ?? null, $draftMotorcycle['owner_document_number'] ?? null),
            ],
            'license_document_number' => $this->pick($extracted['license_document_number'] ?? null, $draft['license_document_number'] ?? null),
            'license_categories' => $this->pick($extracted['license_categories'] ?? null, $draft['license_categories'] ?? null, []),
            'license_expiration' => $this->pick($extracted['license_expiration'] ?? null, $draft['license_expiration'] ?? null),
            'soat_expiration' => $this->pick($extracted['soat_expiration'] ?? null, $draft['soat_expiration'] ?? null),
            'tecnomecanica_expiration' => $this->pick($extracted['tecnomecanica_expiration'] ?? null, $draft['tecnomecanica_expiration'] ?? null),
            'registered' => is_array($draft['registered'] ?? null) ? $draft['registered'] : null,
            'kept' => is_array($draft['kept'] ?? null) ? $draft['kept'] : null,
        ];

        return $this->applyKept($form);
    }

    /**
     * @return array<string, mixed>
     */
    public function forApplication(InscriptionApplication $application): array
    {
        $form = $this->toForm($application->extracted_data ?? [], $application->form_draft ?? []);

        if ($application->person === null) {
            return $form;
        }

        return $this->fromPerson($application->person, $form);
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public function applyKept(array $form): array
    {
        $kept = $form['kept'] ?? null;

        if (! is_array($kept)) {
            return $form;
        }

        foreach ($kept as $choice) {
            if (! is_array($choice) || ! is_string($choice['value'] ?? null) || $choice['value'] === '') {
                continue;
            }

            foreach ($choice['paths'] ?? [] as $path) {
                if (is_string($path) && $path !== '') {
                    $form = $this->writePath($form, $path, $choice['value']);
                }
            }
        }

        return $form;
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function writePath(array $form, string $path, string $value): array
    {
        $segments = explode('.', $path);
        $cursor = &$form;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $cursor[$segment] = $value;
                break;
            }

            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }

        unset($cursor);

        return $form;
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

        $person->loadMissing([
            'memberships',
            'healthProfile',
            'emergencyContacts',
            'motorcycles.documents.type',
            'documents.type',
        ]);

        $membership = $person->memberships->first();
        $health = $person->healthProfile;
        $contact = $person->emergencyContacts->first();
        $motorcycle = $person->motorcycles->first();
        $meta = $membership?->metadata ?? [];
        $value = static fn (mixed $item) => $item ?? '';

        return $this->overlayDocumentDates($person, [
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
        ]);
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    private function overlayDocumentDates(Person $person, array $form): array
    {
        $license = $this->documentBySlug($person, 'licencia');
        $soat = $this->documentBySlug($person, 'soat');
        $tecnomecanica = $this->documentBySlug($person, 'tecnomecanica');

        if ($license) {
            $form['license_document_number'] = $this->pick(
                $form['license_document_number'] ?? null,
                $license->document_number,
            );
            $form['license_expiration'] = $this->pick(
                $form['license_expiration'] ?? null,
                $license->expiration_date?->toDateString(),
            );
        }

        if ($soat) {
            $form['soat_expiration'] = $this->pick(
                $form['soat_expiration'] ?? null,
                $soat->expiration_date?->toDateString(),
            );
        }

        if ($tecnomecanica) {
            $form['tecnomecanica_expiration'] = $this->pick(
                $form['tecnomecanica_expiration'] ?? null,
                $tecnomecanica->expiration_date?->toDateString(),
            );
        }

        return $form;
    }

    private function documentBySlug(Person $person, string $slug): ?Document
    {
        return $person->documents
            ->concat($person->motorcycles->flatMap->documents)
            ->first(fn (Document $document): bool => $document->type?->slug === $slug);
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

    private function pick(mixed $extracted, mixed $draft, mixed $default = ''): mixed
    {
        if ($this->hasValue($extracted)) {
            return $extracted;
        }

        if ($this->hasValue($draft)) {
            return $draft;
        }

        return $default;
    }

    private function hasValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && $value !== '';
    }
}
