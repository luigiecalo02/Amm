<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use App\Models\Organization;
use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;

class SaveOwnDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $person = $this->owner();
        $organization = $this->route('organization');

        if (! $person || ! $organization instanceof Organization || ! $person->belongsToOrganization($organization->id)) {
            return false;
        }

        $document = $this->route('document');

        if (! $document instanceof Document) {
            return true;
        }

        return $document->belongsToOrganization($organization->id) && $this->owns($person, $document);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'exists:document_types,slug'],
            'motorcycle_id' => ['nullable', 'exists:motorcycles,id'],
            'document_number' => ['nullable', 'string', 'max:80'],
            'issue_date' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
            'side' => ['nullable', 'in:front,back'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'document_number' => $this->blankToNull('document_number'),
            'issue_date' => $this->blankToNull('issue_date'),
            'expiration_date' => $this->blankToNull('expiration_date'),
            'motorcycle_id' => $this->blankToNull('motorcycle_id'),
            'side' => $this->blankToNull('side'),
        ]);
    }

    private function blankToNull(string $key): mixed
    {
        $value = $this->input($key);

        return $value === '' ? null : $value;
    }

    private function owner(): ?Person
    {
        return $this->user()?->person;
    }

    private function owns(Person $person, Document $document): bool
    {
        if ($document->person_id === $person->id) {
            return true;
        }

        return $document->motorcycle?->person_id === $person->id;
    }
}
