<?php

namespace App\Services\Inscriptions;

use App\Support\InscriptionDocumentSteps;

final class InscriptionExtractedDataMerger
{
    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function merge(array $current, array $incoming, string $documentType): array
    {
        $incoming = $this->scoped($incoming, $documentType);

        foreach ($incoming as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if ($key === 'license_categories') {
                $current[$key] = $this->mergeCategories($current[$key] ?? [], $value);

                continue;
            }

            if (! array_key_exists($key, $current) || $current[$key] === null || $current[$key] === '') {
                $current[$key] = $value;
            }
        }

        return $current;
    }

    /**
     * @param  array<string, array{analyzed?: bool, extracted?: array<string, mixed>}>  $uploads
     * @return array<string, mixed>
     */
    public function rebuild(array $uploads, ?string $role = null): array
    {
        $extracted = [];

        foreach (InscriptionDocumentSteps::forRole($role) as $step) {
            $upload = $uploads[$step['key']] ?? null;

            if (! is_array($upload) || empty($upload['analyzed']) || ! $step['type']) {
                continue;
            }

            $extracted = $this->merge($extracted, is_array($upload['extracted'] ?? null) ? $upload['extracted'] : [], $step['type']);
        }

        return $extracted;
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function scoped(array $incoming, string $documentType): array
    {
        if ($documentType === 'licencia') {
            if (isset($incoming['document_number']) && empty($incoming['license_document_number'])) {
                $incoming['license_document_number'] = $incoming['document_number'];
            }

            unset($incoming['document_number']);
        }

        if ($documentType === 'tarjeta-propiedad') {
            if (isset($incoming['document_number']) && empty($incoming['owner_document_number'])) {
                $incoming['owner_document_number'] = $incoming['document_number'];
            }

            unset($incoming['document_number']);
        }

        return $incoming;
    }

    /**
     * @param  array<int, array{category?: string, expires_at?: string|null}>  $current
     * @param  array<int, array{category?: string, expires_at?: string|null}>  $incoming
     * @return array<int, array{category: string, expires_at: string|null}>
     */
    private function mergeCategories(array $current, array $incoming): array
    {
        $merged = [];

        foreach ([...$current, ...$incoming] as $row) {
            $code = strtoupper(trim((string) ($row['category'] ?? '')));

            if ($code === '') {
                continue;
            }

            $expires = $row['expires_at'] ?? null;
            $merged[$code] = [
                'category' => $code,
                'expires_at' => $expires ?: ($merged[$code]['expires_at'] ?? null),
            ];
        }

        return array_values($merged);
    }
}
