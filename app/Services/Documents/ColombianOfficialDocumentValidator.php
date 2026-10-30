<?php

namespace App\Services\Documents;

final class ColombianOfficialDocumentValidator
{
    /**
     * @var array<string, list<string>>
     */
    private const TEXT_MARKERS = [
        'cedula' => [
            'CEDULA DE CIUDADANIA',
            'CEDULA DE CIUDADANÍA',
            'REGISTRADURIA NACIONAL',
            'NUIP',
            'CEDULA',
            'CÉDULA',
        ],
        'licencia' => [
            'LICENCIA DE CONDUCCION',
            'LICENCIA DE CONDUCCIÓN',
            'MINISTERIO DE TRANSPORTE',
            'CATEGORIA',
            'CATEGORÍA',
        ],
        'tarjeta-propiedad' => [
            'LICENCIA DE TRANSITO',
            'LICENCIA DE TRÁNSITO',
            'TARJETA DE PROPIEDAD',
            'RUNT',
        ],
        'soat' => [
            'SEGURO OBLIGATORIO',
            'SOAT',
        ],
        'tecnomecanica' => [
            'TECNOMECANICA',
            'TECNOMECÁNICA',
            'TECNO-MECANICA',
            'REVISION TECNICO MECANICA',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'cedula' => 'cédula',
        'licencia' => 'licencia de conducción',
        'tarjeta-propiedad' => 'tarjeta de propiedad',
        'soat' => 'SOAT',
        'tecnomecanica' => 'tecnomecánica',
    ];

    /**
     * @return array{ok: bool, detected: string|null, message: string|null}
     */
    public function validate(string $absolutePath, string $expectedType): array
    {
        $textKind = $this->classifyText($this->readText($absolutePath));

        if ($textKind !== null && $textKind !== $expectedType) {
            return $this->reject($expectedType, $textKind);
        }

        return [
            'ok' => true,
            'detected' => $textKind,
            'message' => null,
        ];
    }

    public function classifyText(string $text): ?string
    {
        $normalized = $this->normalize($text);

        if ($normalized === '') {
            return null;
        }

        $scores = [];

        foreach (self::TEXT_MARKERS as $type => $markers) {
            $score = 0;
            foreach ($markers as $marker) {
                if (str_contains($normalized, $this->normalize($marker))) {
                    $score += mb_strlen($marker);
                }
            }
            if ($score > 0) {
                $scores[$type] = $score;
            }
        }

        if ($scores === []) {
            return null;
        }

        arsort($scores);
        $winner = array_key_first($scores);

        return $winner !== null && $scores[$winner] >= 4 ? $winner : null;
    }

    /**
     * @return array{ok: false, detected: string, message: string}
     */
    private function reject(string $expectedType, string $detected): array
    {
        $expected = self::LABELS[$expectedType] ?? $expectedType;
        $found = self::LABELS[$detected] ?? $detected;

        return [
            'ok' => false,
            'detected' => $detected,
            'message' => "Esta foto parece {$found}, no una {$expected} oficial. Sube el documento correcto.",
        ];
    }

    private function readText(string $absolutePath): string
    {
        if (! is_file($absolutePath)) {
            return '';
        }

        $contents = (string) file_get_contents($absolutePath);

        if (! str_starts_with($contents, '%PDF')) {
            return '';
        }

        preg_match_all('/\((?:\\\\.|[^\\\\)]){2,}\)/', $contents, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $chunk) => stripcslashes(trim($chunk, '()')))
            ->filter(fn (string $chunk) => mb_strlen($chunk) > 1)
            ->implode(' ');
    }

    private function normalize(string $text): string
    {
        $text = mb_strtoupper($text);
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
