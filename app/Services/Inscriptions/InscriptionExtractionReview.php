<?php

namespace App\Services\Inscriptions;

use App\Support\InscriptionDocumentSteps;

final class InscriptionExtractionReview
{
    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'document_number' => 'Número de documento',
        'document_type' => 'Tipo de documento',
        'first_name' => 'Nombres',
        'middle_name' => 'Segundo nombre',
        'last_name' => 'Primer apellido',
        'second_last_name' => 'Segundo apellido',
        'birth_date' => 'Fecha de nacimiento',
        'blood_type' => 'Tipo de sangre',
        'rh' => 'RH',
        'gender' => 'Sexo',
        'height_cm' => 'Estatura',
        'plate' => 'Placa',
        'brand' => 'Marca',
        'model' => 'Modelo',
        'line' => 'Línea',
        'year' => 'Modelo',
        'color' => 'Color',
        'engine_displacement' => 'Cilindraje',
        'owner_name' => 'Propietario',
        'owner_document_number' => 'Identificación del propietario',
        'license_document_number' => 'Identificación de la licencia',
        'license_expiration' => 'Vence licencia',
        'license_categories' => 'Categorías',
        'soat_expiration' => 'Vence SOAT',
        'tecnomecanica_expiration' => 'Vence tecnomecánica',
    ];

    /**
     * @param  array<string, mixed>  $draft
     * @return array{document_type: string, document_number: string, first_name: string, last_name: string, second_last_name: string}
     */
    public function registeredFrom(array $draft): array
    {
        $snapshot = is_array($draft['registered'] ?? null) ? $draft['registered'] : [];

        return [
            'document_type' => (string) ($snapshot['document_type'] ?? $draft['document_type'] ?? 'cc'),
            'document_number' => (string) ($snapshot['document_number'] ?? $draft['document_number'] ?? ''),
            'first_name' => (string) ($snapshot['first_name'] ?? $draft['first_name'] ?? ''),
            'last_name' => (string) ($snapshot['last_name'] ?? $draft['last_name'] ?? ''),
            'second_last_name' => (string) ($snapshot['second_last_name'] ?? $draft['second_last_name'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $registered
     * @param  array<string, mixed>  $uploads
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>|null
     */
    public function forLastAnalyzed(array $registered, array $uploads, ?string $lastKey, array $merged = []): ?array
    {
        if ($lastKey === null || empty($uploads[$lastKey]['analyzed'])) {
            return null;
        }

        $step = InscriptionDocumentSteps::find($lastKey);

        if ($step === null) {
            return null;
        }

        $mate = InscriptionDocumentSteps::mate($lastKey);
        $frontKey = $step['side'] === 'back' ? ($mate['key'] ?? null) : $lastKey;
        $backKey = $step['side'] === 'back' ? $lastKey : ($mate['key'] ?? null);

        if ($step['side'] === null) {
            $frontKey = $lastKey;
            $backKey = null;
        }

        $front = is_array($uploads[$frontKey]['extracted'] ?? null) ? $uploads[$frontKey]['extracted'] : [];
        $back = $backKey !== null && is_array($uploads[$backKey]['extracted'] ?? null)
            ? $uploads[$backKey]['extracted']
            : [];
        $hasMate = $mate !== null;
        $bothSides = $hasMate
            && $frontKey !== null
            && $backKey !== null
            && ! empty($uploads[$frontKey])
            && ! empty($uploads[$backKey]);

        $combined = $this->combineExtracted($front, $back, $merged);
        $fields = $this->identityFields($step['type'], $registered, $front, $back);
        $matches = $this->matchesRegistered($fields, $bothSides);

        return [
            'step' => $lastKey,
            'label' => $step['label'],
            'group' => $step['group'],
            'group_label' => $step['group_label'],
            'has_mate' => $hasMate,
            'both_sides' => $bothSides,
            'matches_registered' => $matches,
            'headline' => $this->headline($hasMate, $bothSides, $matches, $combined !== []),
            'fields' => $fields,
            'extracted' => $this->displayRows($combined),
            'extracted_front' => $this->displayRows($front),
            'extracted_back' => $this->displayRows($back),
        ];
    }

    /**
     * @param  array<string, mixed>  $registered
     * @param  array<string, mixed>  $extracted
     * @return list<string>
     */
    public function registeredWarnings(array $registered, array $extracted): array
    {
        $warnings = [];
        $pairs = [
            ['document_number', 'document_number', 'el número de documento'],
            ['first_name', 'first_name', 'los nombres'],
            ['last_name', 'last_name', 'el primer apellido'],
            ['document_number', 'license_document_number', 'la identificación de la licencia'],
            ['document_number', 'owner_document_number', 'la identificación del propietario'],
        ];

        foreach ($pairs as [$registeredKey, $extractedKey, $label]) {
            $left = $this->normalize((string) ($registered[$registeredKey] ?? ''), $registeredKey);
            $right = $this->normalize((string) ($extracted[$extractedKey] ?? ''), $extractedKey);

            if ($left === '' || $right === '' || $left === $right) {
                continue;
            }

            $warnings[] = "Lo leído en {$label} no coincide con lo que registraste al inicio.";
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param  array<string, mixed>  $registered
     * @param  array<string, mixed>  $front
     * @param  array<string, mixed>  $back
     * @return list<array{key: string, label: string, registered: string|null, front: string|null, back: string|null, status: string}>
     */
    private function identityFields(?string $type, array $registered, array $front, array $back): array
    {
        $map = match ($type) {
            'cedula' => [
                ['key' => 'document_number', 'registered' => 'document_number', 'extracted' => 'document_number'],
                ['key' => 'first_name', 'registered' => 'first_name', 'extracted' => 'first_name'],
                ['key' => 'last_name', 'registered' => 'last_name', 'extracted' => 'last_name'],
                ['key' => 'second_last_name', 'registered' => 'second_last_name', 'extracted' => 'second_last_name'],
            ],
            'licencia' => [
                ['key' => 'document_number', 'registered' => 'document_number', 'extracted' => 'license_document_number'],
            ],
            'tarjeta-propiedad' => [
                ['key' => 'document_number', 'registered' => 'document_number', 'extracted' => 'owner_document_number'],
                ['key' => 'owner_name', 'registered' => 'full_name', 'extracted' => 'owner_name'],
            ],
            default => [],
        };

        $fields = [];

        foreach ($map as $field) {
            $registeredValue = $field['registered'] === 'full_name'
                ? $this->fullName($registered)
                : ($registered[$field['registered']] ?? null);
            $frontValue = $this->scalar($front[$field['extracted']] ?? null);
            $backValue = $this->scalar($back[$field['extracted']] ?? null);

            $fields[] = [
                'key' => $field['key'],
                'label' => self::LABELS[$field['key']] ?? $field['key'],
                'registered' => $this->scalar($registeredValue),
                'front' => $frontValue,
                'back' => $backValue,
                'status' => $this->status($registeredValue, $frontValue, $backValue, $field['key']),
            ];
        }

        foreach (['middle_name', 'birth_date', 'gender', 'blood_type', 'rh', 'height_cm'] as $extra) {
            if (! isset($front[$extra]) && ! isset($back[$extra])) {
                continue;
            }

            $frontValue = $this->scalar($front[$extra] ?? null);
            $backValue = $this->scalar($back[$extra] ?? null);

            $fields[] = [
                'key' => $extra,
                'label' => self::LABELS[$extra] ?? $extra,
                'registered' => null,
                'front' => $frontValue,
                'back' => $backValue,
                'status' => $this->status(null, $frontValue, $backValue, $extra),
            ];
        }

        return $fields;
    }

    /**
     * @param  list<array{status: string}>  $fields
     */
    private function matchesRegistered(array $fields, bool $bothSides): ?bool
    {
        if (! $bothSides || $fields === []) {
            return null;
        }

        $statuses = array_column($fields, 'status');

        if (in_array('mismatch', $statuses, true)) {
            return false;
        }

        if (in_array('match', $statuses, true)) {
            return true;
        }

        return null;
    }

    private function headline(bool $hasMate, bool $bothSides, ?bool $matches, bool $hasExtracted): string
    {
        if ($bothSides) {
            return match ($matches) {
                true => 'Frente y reverso coinciden con los datos que registraste.',
                false => 'Hay diferencias entre lo registrado y lo que leímos en frente o reverso.',
                default => 'No pudimos leer texto en las fotos. Puedes continuar y completar los datos a mano.',
            };
        }

        if ($hasMate) {
            return $hasExtracted
                ? 'Leímos este lado. Sube el otro para contrastar frente, reverso y lo que registraste.'
                : 'Guardamos este lado. Sube el otro para contrastar con tus datos.';
        }

        return $hasExtracted
            ? 'Estos son los datos que leímos en el documento.'
            : 'No se pudo leer texto en este documento.';
    }

    private function status(mixed $registered, mixed $front, mixed $back, string $key): string
    {
        $registeredValue = $this->normalize((string) ($registered ?? ''), $key);
        $read = array_values(array_filter(
            [
                $this->normalize((string) ($front ?? ''), $key),
                $this->normalize((string) ($back ?? ''), $key),
            ],
            fn (string $value) => $value !== ''
        ));

        if ($read === []) {
            return 'unread';
        }

        $compared = $registeredValue !== '' ? [$registeredValue, ...$read] : $read;

        return count(array_unique($compared)) === 1 ? 'match' : 'mismatch';
    }

    /**
     * @param  array<string, mixed>  ...$sources
     * @return array<string, mixed>
     */
    private function combineExtracted(array ...$sources): array
    {
        $combined = [];

        foreach ($sources as $source) {
            foreach ($source as $key => $value) {
                if ($this->scalar($value) === null) {
                    continue;
                }

                $combined[$key] = $value;
            }
        }

        return $combined;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, label: string, value: string}>
     */
    private function displayRows(array $data): array
    {
        $rows = [];

        foreach ($data as $key => $value) {
            $display = $this->scalar($value);

            if ($display === null) {
                continue;
            }

            $rows[] = [
                'key' => (string) $key,
                'label' => self::LABELS[$key] ?? (string) $key,
                'value' => $display,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $registered
     */
    private function fullName(array $registered): string
    {
        return trim(implode(' ', array_filter([
            $registered['first_name'] ?? '',
            $registered['last_name'] ?? '',
            $registered['second_last_name'] ?? '',
        ], fn ($part) => filled($part))));
    }

    private function scalar(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            if ($value === []) {
                return null;
            }

            $parts = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    $category = $item['category'] ?? '';
                    $expires = $item['expires_at'] ?? '';
                    $parts[] = trim($category.($expires !== '' && $expires !== null ? " ({$expires})" : ''));
                    continue;
                }

                $parts[] = (string) $item;
            }

            $joined = implode(', ', array_filter($parts));

            return $joined !== '' ? $joined : null;
        }

        return (string) $value;
    }

    private function normalize(string $value, string $key): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (str_contains($key, 'document_number') || $key === 'document_number') {
            return preg_replace('/\D+/', '', $value) ?? '';
        }

        $value = mb_strtoupper($value);
        $value = strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
