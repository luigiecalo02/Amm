<?php

namespace App\Services\Documents;

use App\Models\DocumentType;
use App\Models\Organization;
use App\Models\Person;
use App\Services\Inscriptions\InscriptionDocumentCatalog;
use App\Services\People\MemberPhotoService;

final class DocumentLibrary
{
    public function __construct(
        private readonly InscriptionDocumentCatalog $catalog,
        private readonly MemberPhotoService $photos,
    ) {}

    /**
     * @return array{members: list<array<string, mixed>>, types: list<array<string, mixed>>}
     */
    public function forOrganization(Organization $organization, bool $canDownload): array
    {
        $fileUrl = fn (string $documentId, string $fileId) => $canDownload
            ? route('documents.download', [$organization, $documentId, $fileId])
            : '';

        $people = Person::query()
            ->inOrganization($organization->id)
            ->with([
                'documents.type',
                'documents.files',
                'documents.motorcycle',
                'motorcycles.documents.type',
                'motorcycles.documents.files',
                'memberships' => fn ($query) => $query->where('organization_id', $organization->id),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $members = $people->map(function (Person $person) use ($organization, $fileUrl): array {
            $this->photos->present($person, $organization);

            return [
                'id' => $person->id,
                'name' => $person->full_name,
                'document_number' => $person->document_number,
                'photo_url' => $person->getAttribute('photo_url'),
                'documents' => $this->catalog->forPerson($person, $fileUrl),
            ];
        })->values()->all();

        return [
            'members' => $members,
            'types' => $this->types($members),
        ];
    }

    /**
     * @param  callable(string $documentId, string $fileId): string  $fileUrl
     * @return array{types: list<array<string, mixed>>, motorcycles: list<array{id: string, plate: string, brand: string|null}>}
     */
    public function forPerson(Organization $organization, Person $person, callable $fileUrl): array
    {
        $person->load([
            'documents.type',
            'documents.files',
            'documents.motorcycle',
            'motorcycles.documents.type',
            'motorcycles.documents.files',
            'memberships' => fn ($query) => $query->where('organization_id', $organization->id),
        ]);
        $this->photos->present($person, $organization);

        $member = [
            'id' => $person->id,
            'name' => $person->full_name,
            'document_number' => $person->document_number,
            'photo_url' => $person->getAttribute('photo_url'),
            'documents' => $this->catalog->forPerson($person, $fileUrl),
        ];

        return [
            'types' => $this->types([$member]),
            'motorcycles' => $person->motorcycles
                ->map(fn ($motorcycle) => [
                    'id' => $motorcycle->id,
                    'plate' => $motorcycle->plate,
                    'brand' => $motorcycle->brand,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $members
     * @return list<array<string, mixed>>
     */
    private function types(array $members): array
    {
        $order = ['cedula', 'licencia', 'tarjeta-propiedad', 'soat', 'tecnomecanica', 'eps'];
        $grouped = [];

        foreach ($members as $member) {
            foreach ($member['documents'] as $document) {
                $slug = $document['slug'];
                $grouped[$slug][] = [
                    ...$document,
                    'member' => [
                        'id' => $member['id'],
                        'name' => $member['name'],
                        'document_number' => $member['document_number'],
                        'photo_url' => $member['photo_url'],
                    ],
                ];
            }
        }

        return DocumentType::query()
            ->where('active', true)
            ->get()
            ->sortBy(function (DocumentType $type) use ($order): int {
                $index = array_search($type->slug, $order, true);

                return $index === false ? 1000 : $index;
            })
            ->values()
            ->map(function (DocumentType $type) use ($grouped): array {
                $entries = $grouped[$type->slug] ?? [];

                return [
                    'id' => $type->id,
                    'slug' => $type->slug,
                    'name' => $type->name,
                    'entity_type' => $type->entity_type,
                    'count' => count($entries),
                    'entries' => array_values($entries),
                ];
            })
            ->all();
    }
}
