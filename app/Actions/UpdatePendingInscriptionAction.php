<?php

namespace App\Actions;

use App\Models\InscriptionApplication;
use App\Models\Organization;
use App\Models\Person;

class UpdatePendingInscriptionAction
{
    public function __construct(private readonly UpdatePersonAction $people)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(InscriptionApplication $application, Organization $organization, array $data): Person
    {
        abort_unless($application->isPendingReview(), 403, 'Solo se puede editar una inscripción pendiente.');
        abort_unless($application->person_id, 404);

        $person = $this->people->execute($application->person()->firstOrFail(), $organization, $data);

        $application->forceFill([
            'email' => $data['email'] ?? $application->email,
            'form_draft' => [
                ...($application->form_draft ?? []),
                ...$data,
            ],
        ])->save();

        $this->syncDocumentMeta($person, $data);

        return $person;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncDocumentMeta(Person $person, array $data): void
    {
        $person->load(['documents.type', 'motorcycles.documents.type']);
        $documents = $person->documents->concat($person->motorcycles->flatMap->documents);

        $updates = [
            'cedula' => [
                'document_number' => $data['document_number'] ?? null,
            ],
            'licencia' => [
                'document_number' => $data['license_document_number'] ?? $data['document_number'] ?? null,
                'expiration_date' => $data['license_expiration'] ?? null,
            ],
            'soat' => [
                'expiration_date' => $data['soat_expiration'] ?? null,
            ],
            'tecnomecanica' => [
                'expiration_date' => $data['tecnomecanica_expiration'] ?? null,
            ],
        ];

        foreach ($documents as $document) {
            $slug = $document->type?->slug;
            $payload = $updates[$slug] ?? null;

            if (! is_array($payload)) {
                continue;
            }

            $document->fill(array_filter($payload, fn ($value) => $value !== null && $value !== ''))->save();
        }
    }
}
