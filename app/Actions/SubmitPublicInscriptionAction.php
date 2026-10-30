<?php

namespace App\Actions;

use App\Enums\DocumentStatus;
use App\Enums\InscriptionApplicationStatus;
use App\Enums\MembershipStatus;
use App\Enums\PersonStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\InscriptionApplication;
use App\Models\Organization;
use App\Models\Person;
use App\Services\Documents\DocumentService;
use App\Services\Inscriptions\InscriptionLinkService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmitPublicInscriptionAction
{
    public function __construct(
        private readonly CreatePersonAction $createPerson,
        private readonly DocumentService $documents,
        private readonly InscriptionLinkService $links,
    ) {
    }

    public function execute(InscriptionApplication $application, Organization $organization, array $data): Person
    {
        if (Person::query()->where('document_number', $data['document_number'])->exists()) {
            throw ValidationException::withMessages([
                'document_number' => 'Ya existe una persona con esa cédula. Pide revisión al club.',
            ]);
        }

        return DB::transaction(function () use ($application, $organization, $data): Person {
            $person = $this->createPerson->execute($organization, [
                ...$data,
                'email' => $data['email'] ?? $application->email,
                'status' => PersonStatus::PendingReview,
                'membership_status' => MembershipStatus::Pending,
                'metadata' => [
                    ...($data['metadata'] ?? []),
                    'license_document_number' => $data['license_document_number'] ?? null,
                    'license_categories' => $data['license_categories'] ?? [],
                    'vehicle_owner_name' => $data['motorcycle']['owner_name'] ?? null,
                    'vehicle_owner_document' => $data['motorcycle']['owner_document_number'] ?? null,
                    'pilot_person_id' => $data['pilot_person_id'] ?? null,
                ],
            ]);

            $motorcycle = $person->motorcycles()->first();
            $this->attachUploadedDocuments($application, $organization, $person, $motorcycle?->id, $data);

            $application->forceFill([
                'person_id' => $person->id,
                'email' => $data['email'] ?? $application->email,
                'form_draft' => $data,
                'status' => InscriptionApplicationStatus::Submitted,
                'submitted_at' => now(),
            ])->save();

            $this->links->assignReference($application);
            $application->link()->increment('uses_count');

            return $person;
        });
    }

    private function attachUploadedDocuments(
        InscriptionApplication $application,
        Organization $organization,
        Person $person,
        ?string $motorcycleId,
        array $data,
    ): void {
        $uploads = $application->uploads ?? [];
        $types = DocumentType::query()
            ->whereIn('slug', ['cedula', 'licencia', 'tarjeta-propiedad', 'soat', 'tecnomecanica'])
            ->get()
            ->keyBy('slug');

        $groups = [
            'cedula' => [
                'files' => array_values(array_filter([
                    $uploads['cedula_front'] ?? null,
                    $uploads['cedula_back'] ?? null,
                ])),
                'payload' => ['person_id' => $person->id, 'document_number' => $data['document_number'] ?? null],
            ],
            'licencia' => [
                'files' => array_values(array_filter([
                    $uploads['licencia_front'] ?? $uploads['licencia'] ?? null,
                    $uploads['licencia_back'] ?? null,
                ])),
                'payload' => ['person_id' => $person->id, 'document_number' => $data['document_number'] ?? null, 'expiration_date' => $data['license_expiration'] ?? null],
            ],
            'tarjeta-propiedad' => [
                'files' => array_values(array_filter([
                    $uploads['tarjeta_propiedad_front'] ?? $uploads['tarjeta_propiedad'] ?? null,
                    $uploads['tarjeta_propiedad_back'] ?? null,
                ])),
                'payload' => ['motorcycle_id' => $motorcycleId],
            ],
            'soat' => [
                'files' => array_values(array_filter([$uploads['soat'] ?? null])),
                'payload' => ['motorcycle_id' => $motorcycleId, 'expiration_date' => $data['soat_expiration'] ?? null],
            ],
            'tecnomecanica' => [
                'files' => array_values(array_filter([$uploads['tecnomecanica'] ?? null])),
                'payload' => ['motorcycle_id' => $motorcycleId, 'expiration_date' => $data['tecnomecanica_expiration'] ?? null],
            ],
        ];

        foreach ($groups as $slug => $group) {
            $type = $types->get($slug);
            $files = $group['files'];
            $payload = $group['payload'];

            if (! $type || $files === []) {
                continue;
            }

            if (isset($payload['motorcycle_id']) && ! $payload['motorcycle_id']) {
                continue;
            }

            $document = Document::query()->create([
                'document_type_id' => $type->id,
                'person_id' => $payload['person_id'] ?? null,
                'motorcycle_id' => $payload['motorcycle_id'] ?? null,
                'document_number' => $payload['document_number'] ?? null,
                'expiration_date' => $payload['expiration_date'] ?? null,
                'status' => DocumentStatus::PendingReview,
            ]);

            foreach ($files as $file) {
                $this->copyScanToDocument($document, $file);
            }
        }
    }

    /**
     * @param  array{path?: string, filename?: string, mime?: string}  $file
     */
    private function copyScanToDocument(Document $document, array $file): void
    {
        $path = $file['path'] ?? null;

        if (! $path || ! Storage::disk('scans')->exists($path)) {
            return;
        }

        $absolute = Storage::disk('scans')->path($path);
        $uploaded = new UploadedFile($absolute, $file['filename'] ?? basename($path), $file['mime'] ?? null, null, true);
        $this->documents->storeFile($document, $uploaded);
    }
}
