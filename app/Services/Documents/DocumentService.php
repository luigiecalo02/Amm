<?php

namespace App\Services\Documents;

use App\Enums\DocumentStatus;
use App\Events\DocumentCreated;
use App\Events\DocumentDownloaded;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentService
{
    public function __construct(private readonly DocumentValidationService $validator) {}

    public function create(Organization $organization, array $data, ?UploadedFile $file = null, ?User $actor = null): Document
    {
        $type = DocumentType::query()->findOrFail($data['document_type_id']);
        $this->validator->assertAssociation($type, $data['person_id'] ?? null, $data['motorcycle_id'] ?? null);

        return DB::transaction(function () use ($organization, $data, $file, $actor, $type): Document {
            $document = Document::query()->create([
                'document_type_id' => $type->id,
                'person_id' => $data['person_id'] ?? null,
                'motorcycle_id' => $data['motorcycle_id'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'issue_date' => $data['issue_date'] ?? null,
                'expiration_date' => $data['expiration_date'] ?? null,
                'status' => $this->resolveStatus($data['expiration_date'] ?? null),
                'metadata' => $data['metadata'] ?? [],
            ]);

            if ($file) {
                $this->storeFile($document, $file);
            }

            DocumentCreated::dispatch($document, $organization, $actor);

            return $document->load(['type', 'files', 'person', 'motorcycle']);
        });
    }

    public function update(Document $document, array $data, ?UploadedFile $file = null): Document
    {
        $type = isset($data['document_type_id'])
            ? DocumentType::query()->findOrFail($data['document_type_id'])
            : $document->type;

        $this->validator->assertAssociation(
            $type,
            $data['person_id'] ?? $document->person_id,
            $data['motorcycle_id'] ?? $document->motorcycle_id
        );

        $document->fill($data);

        if (array_key_exists('expiration_date', $data)) {
            $document->status = $this->resolveStatus($data['expiration_date']);
        }

        $document->save();

        if ($file) {
            $this->storeFile($document, $file);
        }

        return $document->refresh()->load(['type', 'files']);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function replaceFiles(Document $document, array $files): void
    {
        foreach ($document->files as $file) {
            Storage::disk($file->disk)->delete($file->path);
            $file->delete();
        }

        foreach ($files as $file) {
            $this->storeFile($document, $file);
        }
    }

    public function replaceFileOnSide(Document $document, UploadedFile $file, ?string $side): void
    {
        if ($side === null || $side === '') {
            $this->replaceFiles($document, [$file]);

            return;
        }

        $document->load('files');
        $existing = $this->fileOnSide($document, $side);

        if ($existing) {
            Storage::disk($existing->disk)->delete($existing->path);
            $existing->delete();
        }

        $this->storeFile($document, $file, $side);
    }

    public function storeFile(Document $document, UploadedFile $file, ?string $side = null): DocumentFile
    {
        $disk = (string) config('filesystems.default', 'documents');
        $path = $file->store($document->id, $disk);

        return $document->files()->create([
            'disk' => $disk,
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'side' => $side,
            'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'checksum' => hash_file('sha256', $file->getRealPath() ?: $file->getPathname()),
            'created_at' => now(),
        ]);
    }

    private function fileOnSide(Document $document, string $side): ?DocumentFile
    {
        $labeled = $document->files->first(fn (DocumentFile $file): bool => $file->side === $side);

        if ($labeled) {
            return $labeled;
        }

        $unlabeled = $document->files
            ->filter(fn (DocumentFile $file): bool => ! filled($file->side))
            ->values();

        return match ($side) {
            'front' => $unlabeled->get(0),
            'back' => $unlabeled->get(1),
            default => null,
        };
    }

    public function copyFromDisk(
        Document $document,
        string $fromDisk,
        string $fromPath,
        string $filename,
        ?string $mime = null,
    ): ?DocumentFile {
        if (! Storage::disk($fromDisk)->exists($fromPath)) {
            return null;
        }

        $contents = Storage::disk($fromDisk)->get($fromPath);

        if ($contents === null || $contents === '') {
            return null;
        }

        $disk = (string) config('filesystems.default', 'documents');
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $path = $document->id.'/'.uniqid('doc', true).($extension !== '' ? '.'.$extension : '');

        Storage::disk($disk)->put($path, $contents);

        return $document->files()->create([
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename !== '' ? $filename : basename($fromPath),
            'mime_type' => $mime ?: 'application/octet-stream',
            'size' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'created_at' => now(),
        ]);
    }

    public function download(DocumentFile $file, Organization $organization, User $actor): StreamedResponse
    {
        DocumentDownloaded::dispatch($file->document, $organization, $actor);

        return Storage::disk($file->disk)->response($file->path, $file->filename, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
        ]);
    }

    private function resolveStatus(?string $expirationDate): DocumentStatus
    {
        if ($expirationDate && now()->toDateString() > $expirationDate) {
            return DocumentStatus::Expired;
        }

        return DocumentStatus::Valid;
    }
}
