<?php

namespace App\Http\Requests\Inscription;

use App\Support\InscriptionDocumentSteps;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KeepInscriptionFieldRequest extends FormRequest
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
            'step' => ['required', 'string', Rule::in(InscriptionDocumentSteps::keys())],
            'key' => ['required', 'string', 'max:80'],
            'source' => ['required', 'in:registered,front,back,manual'],
            'value' => ['required_if:source,manual', 'nullable', 'string', 'max:180'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('source') !== 'manual') {
            return;
        }

        $this->merge([
            'value' => trim((string) $this->input('value', '')),
        ]);
    }
}
