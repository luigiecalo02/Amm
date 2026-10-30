<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->person_id === null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'birth_date' => $this->blankToNull('birth_date'),
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
            'organization_id' => ['required', 'exists:organizations,id'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'second_last_name' => ['nullable', 'string', 'max:80'],
            'document_type' => ['required', 'in:cc,ti,ce,passport'],
            'document_number' => ['required', 'string', 'max:40'],
            'document_issued_in' => ['nullable', 'string', 'max:80'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:masculino,femenino'],
            'phone' => ['required', 'string', 'max:30'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:180'],
            'vehicle_role' => ['nullable', 'in:driver,passenger,both'],
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
