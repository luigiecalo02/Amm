<?php

namespace App\Http\Requests\Inscription;

use App\Support\InscriptionDocumentSteps;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadInscriptionDocumentsRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
            'ocr_text' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
