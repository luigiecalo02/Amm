<?php

namespace App\Http\Requests\Inscription;

use App\Services\Inscriptions\InscriptionLinkService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInscriptionBasicsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'in:cc,ti,ce,passport'],
            'document_number' => ['required', 'string', 'max:40'],
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'second_last_name' => ['nullable', 'string', 'max:80'],
            'vehicle_role' => ['required', 'in:driver,passenger'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $taken = app(InscriptionLinkService::class)->registeredPerson((string) $this->input('document_number'));

            if ($taken !== null) {
                $validator->errors()->add('document_number', InscriptionLinkService::DOCUMENT_ALREADY_REGISTERED);
            }
        });
    }
}
