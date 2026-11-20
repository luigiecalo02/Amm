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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmitPublicInscriptionAction
{
    public function __construct(
        private readonly CreatePersonAction $createPerson,
        private readonly DocumentService $documents,
        private readonly InscriptionLinkService $links,
    ) {}

    public function execute(InscriptionApplication $application, Organization $organization, array $data): Person
    {
        if ($this->links->registeredPerson((string) ($data['document_number'] ?? '')) !== null) {
            throw ValidationException::withMessages([
                'document_number' => InscriptionLinkService::DOCUMENT_ALREADY_REGISTERED,
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
            $this->attachUploadedDocuments($application, $person, $motorcycle?->id, $data);

            $application->forceFill([
                'person_id' => $person->id,
                'email' => $data['email'] ?? $application->email,
                'document_number' => $this->links->normalizeDocumentNumber((string) ($data['document_number'] ?? '')),
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
        Person $person,
        ?string $motorcycleId,
        array $data,
    ): void {
        $uploads = $application->uploads ?? [];
        $types = DocumentType::query()
            ->whereIn('slug', ['cedula', 'licencia', 'tarjeta-propiedad', 'soat', 'tecnomecanica'])
            ->get()
            ->keyBy('slug');

        $vehicleUploads = array_values(array_filter([
            $uploads['tarjeta_propiedad_front'] ?? $uploads['tarjeta_propiedad'] ?? null,
            $uploads['tarjeta_propiedad_back'] ?? null,
            $uploads['soat'] ?? null,
            $uploads['tecnomecanica'] ?? null,
        ]));

        if (! $motorcycleId && $vehicleUploads !== []) {
            $motorcycle = $person->motorcycles()->create([
                'plate' => filled($data['motorcycle']['plate'] ?? null) ? $data['motorcycle']['plate'] : 'PENDIENTE',
                'brand' => filled($data['motorcycle']['brand'] ?? null) ? $data['motorcycle']['brand'] : 'Sin marca',
                'model' => filled($data['motorcycle']['line'] ?? $data['motorcycle']['model'] ?? null)
                    ? ($data['motorcycle']['line'] ?? $data['motorcycle']['model'])
                    : null,
                'year' => is_numeric($data['motorcycle']['year'] ?? null) ? (int) $data['motorcycle']['year'] : null,
                'color' => filled($data['motorcycle']['color'] ?? null) ? $data['motorcycle']['color'] : null,
                'engine_displacement' => is_numeric($data['motorcycle']['engine_displacement'] ?? null)
                    ? (int) $data['motorcycle']['engine_displacement']
                    : null,
                'vehicle_type' => filled($data['motorcycle']['vehicle_type'] ?? null) ? $data['motorcycle']['vehicle_type'] : 'motorcycle',
                'status' => 'active',
            ]);
            $motorcycleId = $motorcycle->id;
        }

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
            $files = array_values(array_filter(
                $group['files'],
                fn ($file) => is_array($file) && is_string($file['path'] ?? null) && Storage::disk('scans')->exists($file['path']),
            ));
            $payload = $group['payload'];

            if (! $type || $files === []) {
                continue;
            }

            if (array_key_exists('motorcycle_id', $payload) && ! $payload['motorcycle_id']) {
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
                $this->documents->copyFromDisk(
                    $document,
                    'scans',
                    $file['path'],
                    $file['filename'] ?? basename($file['path']),
                    $file['mime'] ?? null,
                );
            }
        }
    }
}
