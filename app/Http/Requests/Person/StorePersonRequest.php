<?php

namespace App\Http\Requests\Person;

use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;

class StorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [Person::class, $this->route('organization')]) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'birth_date' => $this->blankToNull('birth_date'),
            'email' => $this->blankToNull('email'),
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
            'motorcycle' => [
                ...($this->input('motorcycle') ?? []),
                'engine_displacement' => $this->blankToNull('motorcycle.engine_displacement'),
                'plate' => $this->blankToNull('motorcycle.plate'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', 'max:20'],
            'document_number' => ['required', 'string', 'max:40', 'unique:people,document_number'],
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
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
            'address' => ['nullable', 'string', 'max:180'],
            'neighborhood' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'department' => ['nullable', 'string', 'max:80'],
            'membership_number' => ['nullable', 'string', 'max:40'],
            'joined_at' => ['nullable', 'date'],
            'vehicle_role' => ['nullable', 'in:driver,passenger,both'],
            'notes' => ['nullable', 'string'],
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
            'health.medications' => ['nullable', 'string'],
            'health.allergies' => ['nullable', 'string'],
            'emergency_contact' => ['nullable', 'array'],
            'emergency_contact.name' => ['nullable', 'string', 'max:120'],
            'emergency_contact.phone' => ['nullable', 'string', 'max:30'],
            'emergency_contact.address' => ['nullable', 'string', 'max:180'],
            'emergency_contact.relationship' => ['nullable', 'string', 'max:40'],
            'motorcycle' => ['nullable', 'array'],
            'motorcycle.vehicle_type' => ['nullable', 'in:motorcycle,car'],
            'motorcycle.brand' => ['nullable', 'string', 'max:80'],
            'motorcycle.model' => ['nullable', 'string', 'max:80'],
            'motorcycle.engine_displacement' => ['nullable', 'integer', 'min:50', 'max:3000'],
            'motorcycle.plate' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function blankToNull(string $key): mixed
    {
        $value = $this->input($key);

        return $value === '' || $value === null ? null : $value;
    }
}
