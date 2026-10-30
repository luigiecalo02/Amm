<?php

namespace App\Services\OCR;

class HeuristicOcrProvider implements OcrProviderInterface
{
    public function __construct(
        private readonly ColombianDocumentParser $parser,
        private readonly DocumentTextExtractor $texts,
    ) {
    }

    public function extract(string $absolutePath, string $documentTypeSlug, ?string $hintText = null): array
    {
        $best = [
            'raw_text' => null,
            'extracted_data' => [],
            'confidence' => null,
            'provider' => 'heuristic',
            'model' => 'colombian-document-parser',
        ];

        foreach ($this->texts->candidates($absolutePath, $hintText) as $rawText) {
            $extracted = $this->parser->parse($rawText, $documentTypeSlug);

            if (count($extracted) < count($best['extracted_data'])) {
                continue;
            }

            if (count($extracted) === count($best['extracted_data']) && mb_strlen($rawText) <= mb_strlen((string) $best['raw_text'])) {
                continue;
            }

            $best = [
                'raw_text' => $rawText,
                'extracted_data' => $extracted,
                'confidence' => $extracted === [] ? null : 0.62,
                'provider' => 'heuristic',
                'model' => 'colombian-document-parser',
            ];
        }

        return $best;
    }
}
