<?php

namespace App\Jobs;

use App\Enums\DocumentScanStatus;
use App\Models\DocumentScan;
use App\Services\Documents\DocumentExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessDocumentScanJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public string $documentScanId)
    {
        $this->onQueue('ocr');
    }

    public function handle(DocumentExtractionService $extractions): void
    {
        $scan = DocumentScan::query()->with('documentType')->findOrFail($this->documentScanId);
        $extractions->process($scan);
    }

    public function failed(?Throwable $exception): void
    {
        DocumentScan::query()->whereKey($this->documentScanId)->update([
            'status' => DocumentScanStatus::Failed->value,
            'error_message' => $exception?->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
