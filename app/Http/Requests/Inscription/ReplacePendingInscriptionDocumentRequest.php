<?php

namespace App\Http\Requests\Inscription;

use App\Models\InscriptionApplication;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplacePendingInscriptionDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');
        $application = $this->route('application');

        return $organization instanceof Organization
            && $application instanceof InscriptionApplication
            && $application->organization_id === $organization->id
            && $application->isPendingReview()
            && ($this->user()?->hasPermission('inscriptions.verify', $organization) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['cedula', 'licencia', 'tarjeta-propiedad', 'soat', 'tecnomecanica'])],
            'files' => ['required', 'array', 'min:1', 'max:4'],
            'files.*' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
            'expiration_date' => ['nullable', 'date'],
        ];
    }
}
