<?php

namespace App\Services\Inscriptions;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\InscriptionApplication;
use App\Models\Person;
use App\Support\InscriptionDocumentSteps;

final class InscriptionDocumentCatalog
{
    /**
     * @param  callable(string $documentId, string $fileId): string  $fileUrl
     * @param  callable(string $step): string|null  $scanUrl
     * @return list<array<string, mixed>>
     */
    public function forApplication(InscriptionApplication $application, callable $fileUrl, ?callable $scanUrl = null): array
    {
        $person = $application->person;
        $person?->loadMissing(['documents.type', 'documents.files', 'motorcycles.documents.type', 'motorcycles.documents.files', 'memberships']);

        $role = $application->form_draft['vehicle_role']
            ?? $person?->memberships->first()?->vehicle_role
            ?? 'driver';
        $slugs = $role === 'passenger'
            ? ['cedula']
            : ['cedula', 'licencia', 'tarjeta-propiedad', 'soat', 'tecnomecanica'];

        $types = DocumentType::query()->whereIn('slug', $slugs)->orderBy('name')->get()->keyBy('slug');
        $existing = $person
            ? $person->documents
                ->concat($person->motorcycles->flatMap->documents)
                ->filter(fn ($document) => $document->type)
                ->keyBy(fn ($document) => $document->type->slug)
            : collect();

        return collect($slugs)->map(function (string $slug) use ($types, $existing, $fileUrl, $application, $scanUrl) {
            $type = $types->get($slug);
            $document = $existing->get($slug);
            $files = $this->filesPayload($document, $fileUrl, $slug);

            if ($files === [] && $scanUrl) {
                $files = $this->uploadFiles($application, $slug, $scanUrl);
            }

            return [
                'slug' => $slug,
                'name' => $type?->name ?? $slug,
                'id' => $document?->id,
                'status' => $document?->status?->value ?? $document?->status,
                'expiration_date' => $document?->expiration_date?->toDateString(),
                'files' => $files,
            ];
        })->values()->all();
    }

    /**
     * @param  callable(string $documentId, string $fileId): string  $fileUrl
     * @return list<array<string, mixed>>
     */
    public function forPerson(Person $person, callable $fileUrl): array
    {
        $person->loadMissing([
            'documents.type',
            'documents.files',
            'documents.motorcycle',
            'motorcycles.documents.type',
            'motorcycles.documents.files',
        ]);

        $order = ['cedula', 'licencia', 'tarjeta-propiedad', 'soat', 'tecnomecanica', 'eps'];

        return $person->documents
            ->concat($person->motorcycles->flatMap->documents)
            ->filter(fn (Document $document) => $document->type)
            ->unique('id')
            ->sortBy(function (Document $document) use ($order) {
                $index = array_search($document->type->slug, $order, true);

                return $index === false ? 1000 : $index;
            })
            ->values()
            ->map(fn (Document $document) => [
                'slug' => $document->type->slug,
                'name' => $document->type->name,
                'id' => $document->id,
                'status' => $document->status?->value ?? $document->status,
                'document_number' => $document->document_number,
                'issue_date' => $document->issue_date?->toDateString(),
                'expiration_date' => $document->expiration_date?->toDateString(),
                'metadata' => $document->metadata ?? [],
                'motorcycle' => $document->motorcycle ? [
                    'id' => $document->motorcycle->id,
                    'plate' => $document->motorcycle->plate,
                    'brand' => $document->motorcycle->brand,
                ] : null,
                'files' => $this->filesPayload($document, $fileUrl, $document->type->slug),
            ])
            ->all();
    }

    /**
     * @param  callable(string $documentId, string $fileId): string  $fileUrl
     * @return list<array{id: string, filename: string, mime_type: string|null, url: string, side: string|null, label: string|null}>
     */
    private function filesPayload(?Document $document, callable $fileUrl, string $slug = ''): array
    {
        if ($document === null) {
            return [];
        }

        $twoSided = $this->isTwoSided($slug);

        return $document->files
            ->values()
            ->map(function ($file, int $index) use ($fileUrl, $document, $twoSided) {
                $side = $file->side ?: ($twoSided ? ($index === 0 ? 'front' : ($index === 1 ? 'back' : null)) : null);

                return [
                    'id' => $file->id,
                    'filename' => $file->filename,
                    'mime_type' => $file->mime_type,
                    'url' => $fileUrl((string) $document->id, (string) $file->id),
                    ...$this->sideMeta($side),
                ];
            })
            ->all();
    }

    /**
     * @param  callable(string $step): string  $scanUrl
     * @return list<array{id: string, filename: string, mime_type: string|null, url: string, side: string|null, label: string|null}>
     */
    private function uploadFiles(InscriptionApplication $application, string $typeSlug, callable $scanUrl): array
    {
        $files = [];

        foreach (InscriptionDocumentSteps::all() as $step) {
            if (($step['type'] ?? null) !== $typeSlug) {
                continue;
            }

            $upload = $application->uploads[$step['key']] ?? null;
            $path = is_array($upload) ? ($upload['path'] ?? null) : null;

            if (! is_string($path) || $path === '') {
                continue;
            }

            $files[] = [
                'id' => $step['key'],
                'filename' => $upload['filename'] ?? $step['label'],
                'mime_type' => $upload['mime'] ?? null,
                'url' => $scanUrl($step['key']),
                ...$this->sideMeta($step['side'] ?? null),
            ];
        }

        return $files;
    }

    /**
     * @return array{side: string|null, label: string|null}
     */
    private function sideMeta(?string $side): array
    {
        return match ($side) {
            'front' => ['side' => 'front', 'label' => 'Cara'],
            'back' => ['side' => 'back', 'label' => 'Respaldo'],
            default => ['side' => $side, 'label' => null],
        };
    }

    private function isTwoSided(string $slug): bool
    {
        return in_array($slug, ['cedula', 'licencia', 'tarjeta-propiedad'], true);
    }
}
