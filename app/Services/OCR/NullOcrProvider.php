<?php

namespace App\Services\OCR;

class NullOcrProvider implements OcrProviderInterface
{
    public function extract(string $absolutePath, string $documentTypeSlug): array
    {
        return [
            'raw_text' => null,
            'extracted_data' => [],
            'confidence' => null,
            'provider' => 'null',
            'model' => null,
        ];
    }
}
