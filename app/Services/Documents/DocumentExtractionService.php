<?php

namespace App\Services\Documents;

use App\Enums\DocumentScanStatus;
use App\Models\DocumentExtraction;
use App\Models\DocumentScan;
use App\Services\OCR\OcrService;
use Illuminate\Support\Facades\Storage;

class DocumentExtractionService
{
    public function __construct(private readonly OcrService $ocr)
    {
    }

    public function process(DocumentScan $scan): DocumentExtraction
    {
        $scan->forceFill([
            'status' => DocumentScanStatus::Processing,
            'started_at' => now(),
            'provider' => config('services.ocr.provider', 'null'),
        ])->save();

        $absolutePath = Storage::disk('scans')->path($scan->original_file);
        $result = $this->ocr->extract($absolutePath, $scan->documentType?->slug ?? 'unknown');

        $extraction = $scan->extraction()->updateOrCreate(
            ['document_scan_id' => $scan->id],
            [
                'raw_text' => $result['raw_text'],
                'extracted_data' => $result['extracted_data'],
                'confidence' => $result['confidence'],
                'provider' => $result['provider'],
                'model' => $result['model'],
            ]
        );

        $scan->forceFill([
            'status' => DocumentScanStatus::NeedsReview,
            'completed_at' => now(),
        ])->save();

        return $extraction;
    }
}
