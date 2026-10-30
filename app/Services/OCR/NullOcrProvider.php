<?php

namespace App\Services\OCR;

class NullOcrProvider implements OcrProviderInterface
{
    public function extract(string $absolutePath, string $documentTypeSlug, ?string $hintText = null): array
    {
        return [
            'raw_text' => $hintText !== null && trim($hintText) !== '' ? trim($hintText) : null,
            'extracted_data' => [],
            'confidence' => null,
            'provider' => 'null',
            'model' => null,
        ];
    }
}
