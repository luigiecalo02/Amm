<?php

namespace App\Actions;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\InscriptionApplication;
use App\Services\Documents\DocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ReplaceInscriptionDocumentAction
{
    public function __construct(private readonly DocumentService $documents)
    {
    }

    /**
     * @param  array{type: string, expiration_date?: string|null}  $data
     * @param  list<UploadedFile>  $files
     */
    public function execute(InscriptionApplication $application, array $data, array $files): Document
    {
        abort_unless($application->isPendingReview(), 403);
        abort_unless($application->person_id, 404);

        $person = $application->person()->with('motorcycles')->firstOrFail();
        $type = DocumentType::query()->where('slug', $data['type'])->firstOrFail();
        $needsMotorcycle = in_array($type->slug, ['tarjeta-propiedad', 'soat', 'tecnomecanica'], true);
        $motorcycle = $person->motorcycles()->first();

        if ($needsMotorcycle && $motorcycle === null) {
            throw ValidationException::withMessages([
                'files' => 'Guarda primero los datos del vehículo para adjuntar este documento.',
            ]);
        }

        $document = $needsMotorcycle
            ? $motorcycle->documents()->where('document_type_id', $type->id)->first()
            : $person->documents()->where('document_type_id', $type->id)->first();

        if ($document === null) {
            $document = Document::query()->create([
                'document_type_id' => $type->id,
                'person_id' => $needsMotorcycle ? null : $person->id,
                'motorcycle_id' => $needsMotorcycle ? $motorcycle?->id : null,
                'document_number' => $needsMotorcycle ? null : $person->document_number,
                'expiration_date' => $data['expiration_date'] ?? null,
                'status' => DocumentStatus::PendingReview,
            ]);
        }

        $this->documents->replaceFiles($document, $files);
        $document->forceFill([
            'expiration_date' => $data['expiration_date'] ?? $document->expiration_date,
            'status' => DocumentStatus::PendingReview,
            'verified_at' => null,
            'verified_by' => null,
        ])->save();

        return $document;
    }
}
