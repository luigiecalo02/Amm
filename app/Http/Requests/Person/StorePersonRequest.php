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
        ];
    }
}
