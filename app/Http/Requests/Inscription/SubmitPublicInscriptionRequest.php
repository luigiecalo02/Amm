<?php

namespace App\Http\Requests\Inscription;

use App\Models\InscriptionLink;
use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitPublicInscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'birth_date' => $this->blankToNull('birth_date'),
            'email' => $this->blankToNull('email'),
            'license_expiration' => $this->blankToNull('license_expiration'),
            'soat_expiration' => $this->blankToNull('soat_expiration'),
            'tecnomecanica_expiration' => $this->blankToNull('tecnomecanica_expiration'),
            'metadata' => [
                ...($this->input('metadata') ?? []),
                'baptized' => filter_var($this->input('metadata.baptized'), FILTER_VALIDATE_BOOLEAN),
                'baptized_at' => $this->blankToNull('metadata.baptized_at'),
            ],
            'health' => [
                ...($this->input('health') ?? []),
                'weight_kg' => $this->blankToNull('health.weight_kg'),
                'height_cm' => $this->blankToNull('health.height_cm'),
            ],
            'license_document_number' => $this->blankToNull('license_document_number'),
            'motorcycle' => [
                ...($this->input('motorcycle') ?? []),
                'engine_displacement' => $this->blankToNull('motorcycle.engine_displacement'),
                'plate' => $this->blankToNull('motorcycle.plate'),
                'year' => $this->blankToNull('motorcycle.year'),
                'line' => $this->blankToNull('motorcycle.line'),
                'color' => $this->blankToNull('motorcycle.color'),
                'owner_name' => $this->blankToNull('motorcycle.owner_name'),
                'owner_document_number' => $this->blankToNull('motorcycle.owner_document_number'),
            ],
            'license_categories' => collect($this->input('license_categories', []))
                ->map(fn ($row) => [
                    'category' => strtoupper(trim((string) ($row['category'] ?? ''))),
                    'expires_at' => ($row['expires_at'] ?? '') === '' ? null : $row['expires_at'],
                ])
                ->filter(fn ($row) => $row['category'] !== '')
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', 'max:20'],
            'document_number' => ['required', 'string', 'max:40'],
            'document_issued_in' => ['nullable', 'string', 'max:80'],
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'second_last_name' => ['nullable', 'string', 'max:80'],
            'birth_date' => ['nullable', 'date'],
            'birth_city' => ['nullable', 'string', 'max:80'],
            'birth_department' => ['nullable', 'string', 'max:80'],
            'gender' => ['nullable', 'string', 'max:20'],
            'marital_status' => ['nullable', 'string', 'max:30'],
            'phone' => [$this->isPassenger() ? 'nullable' : 'required', 'string', 'max:30'],
            'email' => [$this->isPassenger() ? 'nullable' : 'required', 'email'],
            'address' => ['nullable', 'string', 'max:180'],
            'neighborhood' => ['nullable', 'string', 'max:80'],
            'city' => [$this->isPassenger() ? 'nullable' : 'required', 'string', 'max:80'],
            'department' => ['nullable', 'string', 'max:80'],
            'vehicle_role' => ['nullable', 'in:driver,passenger,both'],
            'pilot_person_id' => [$this->isPassenger() ? 'required' : 'nullable', 'string'],
            'license_document_number' => ['nullable', 'string', 'max:40'],
            'license_categories' => ['nullable', 'array'],
            'license_categories.*.category' => ['required_with:license_categories', 'string', 'max:5'],
            'license_categories.*.expires_at' => ['nullable', 'date'],
            'license_expiration' => ['nullable', 'date'],
            'soat_expiration' => ['nullable', 'date'],
            'tecnomecanica_expiration' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'metadata.church_name' => ['nullable', 'string', 'max:120'],
            'metadata.church_district' => ['nullable', 'string', 'max:80'],
            'metadata.church_role' => ['nullable', 'string', 'max:80'],
            'metadata.baptized' => ['nullable', 'boolean'],
            'metadata.baptized_at' => ['nullable', 'date'],
            'health' => ['nullable', 'array'],
            'health.eps' => ['nullable', 'string', 'max:80'],
            'health.blood_type' => ['nullable', 'string', 'max:8'],
            'health.rh' => ['nullable', 'string', 'max:16'],
            'health.weight_kg' => ['nullable', 'numeric'],
            'health.height_cm' => ['nullable', 'numeric'],
            'health.special_treatment' => ['nullable', 'string'],
            'health.allergies' => ['nullable', 'string'],
            'health.medications' => ['nullable', 'string'],
            'emergency_contact' => ['nullable', 'array'],
            'emergency_contact.name' => ['nullable', 'string', 'max:120'],
            'emergency_contact.phone' => ['nullable', 'string', 'max:30'],
            'emergency_contact.address' => ['nullable', 'string', 'max:180'],
            'emergency_contact.relationship' => ['nullable', 'string', 'max:40'],
            'motorcycle' => ['nullable', 'array'],
            'motorcycle.vehicle_type' => ['nullable', 'in:motorcycle,car'],
            'motorcycle.brand' => ['nullable', 'string', 'max:80'],
            'motorcycle.line' => ['nullable', 'string', 'max:80'],
            'motorcycle.model' => ['nullable', 'string', 'max:80'],
            'motorcycle.year' => ['nullable', 'integer', 'min:1970', 'max:2100'],
            'motorcycle.color' => ['nullable', 'string', 'max:40'],
            'motorcycle.engine_displacement' => ['nullable', 'integer', 'min:50', 'max:3000'],
            'motorcycle.plate' => ['nullable', 'string', 'max:20'],
            'motorcycle.owner_name' => ['nullable', 'string', 'max:120'],
            'motorcycle.owner_document_number' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $cedula = preg_replace('/\D+/', '', (string) $this->input('document_number')) ?? '';
            $license = preg_replace('/\D+/', '', (string) $this->input('license_document_number')) ?? '';

            if ($cedula !== '' && $license !== '' && $cedula !== $license) {
                $validator->errors()->add(
                    'license_document_number',
                    'La identificación de la licencia debe coincidir con la cédula.'
                );
            }

            $pilotId = $this->input('pilot_person_id');
            $link = InscriptionLink::query()->where('token', $this->route('token'))->first();

            if ($this->isPassenger() && $pilotId && $link) {
                $exists = OrganizationMember::query()
                    ->where('organization_id', $link->organization_id)
                    ->where('person_id', $pilotId)
                    ->whereIn('vehicle_role', ['driver', 'both'])
                    ->exists();

                if (! $exists) {
                    $validator->errors()->add('pilot_person_id', 'Elige un piloto ya inscrito en este club.');
                }
            }
        });
    }

    private function isPassenger(): bool
    {
        return $this->input('vehicle_role') === 'passenger';
    }

    private function blankToNull(string $key): mixed
    {
        $value = $this->input($key);

        return $value === '' || $value === null ? null : $value;
    }
}
