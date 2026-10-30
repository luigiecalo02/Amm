<?php

namespace App\Services\Inscriptions;

use App\Models\DocumentType;
use App\Models\InscriptionApplication;

final class InscriptionDocumentCatalog
{
    /**
     * @param  callable(string $documentId, string $fileId): string  $fileUrl
     * @return list<array<string, mixed>>
     */
    public function forApplication(InscriptionApplication $application, callable $fileUrl): array
    {
        $person = $application->person;

        if ($person === null) {
            return [];
        }

        $person->loadMissing(['documents.type', 'documents.files', 'motorcycles.documents.type', 'motorcycles.documents.files', 'memberships']);

        $role = $application->form_draft['vehicle_role'] ?? $person->memberships->first()?->vehicle_role ?? 'driver';
        $slugs = $role === 'passenger'
            ? ['cedula']
            : ['cedula', 'licencia', 'tarjeta-propiedad', 'soat', 'tecnomecanica'];

        $types = DocumentType::query()->whereIn('slug', $slugs)->orderBy('name')->get()->keyBy('slug');
        $existing = $person->documents
            ->concat($person->motorcycles->flatMap->documents)
            ->filter(fn ($document) => $document->type)
            ->keyBy(fn ($document) => $document->type->slug);

        return collect($slugs)->map(function (string $slug) use ($types, $existing, $fileUrl) {
            $type = $types->get($slug);
            $document = $existing->get($slug);

            return [
                'slug' => $slug,
                'name' => $type?->name ?? $slug,
                'id' => $document?->id,
                'status' => $document?->status?->value ?? $document?->status,
                'expiration_date' => $document?->expiration_date?->toDateString(),
                'files' => $document?->files?->map(fn ($file) => [
                    'id' => $file->id,
                    'filename' => $file->filename,
                    'url' => $fileUrl((string) $document->id, (string) $file->id),
                ])->values()->all() ?? [],
            ];
        })->values()->all();
    }
}
