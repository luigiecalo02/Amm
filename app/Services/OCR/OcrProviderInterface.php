<?php

namespace App\Services\OCR;

interface OcrProviderInterface
{
    /**
     * @return array{raw_text: string|null, extracted_data: array<string, mixed>, confidence: float|null, provider: string, model: string|null}
     */
    public function extract(string $absolutePath, string $documentTypeSlug): array;
}
