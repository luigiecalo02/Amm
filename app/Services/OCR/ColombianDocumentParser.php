<?php

namespace App\Services\OCR;

final class ColombianDocumentParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $text, string $documentTypeSlug): array
    {
        $normalized = $this->normalize($text);

        return match ($documentTypeSlug) {
            'cedula' => $this->parseCedula($normalized),
            'licencia' => $this->parseLicencia($normalized),
            'tarjeta-propiedad' => $this->parseTarjetaPropiedad($normalized),
            'soat' => $this->parseSoat($normalized),
            'tecnomecanica' => $this->parseTecnomecanica($normalized),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCedula(string $text): array
    {
        $data = [];
        $number = $this->documentNumber($text);

        if ($number !== null) {
            $data['document_number'] = $number;
            $data['document_type'] = 'cc';
        }

        $birth = $this->firstDate($text, ['FECHA DE NACIMIENTO', 'NACIMIENTO', 'F.? NAC']);

        if ($birth !== null) {
            $data['birth_date'] = $birth;
        }

        if (preg_match('/(?:G\.?\s*S\.?|GRUPO SANGU[IÍ]NEO|TIPO DE SANGRE|RH)\s*[:\-]?\s*(AB|A|B|O)\s*([\+\-]|POSITIVO|NEGATIVO)?/u', $text, $match)
            || preg_match('/\b(AB|A|B|O)\s*([\+\-]|POSITIVO|NEGATIVO)\b/', $text, $match)) {
            $data['blood_type'] = strtoupper($match[1]);
            $rh = strtoupper($match[2] ?? '');
            $data['rh'] = str_contains($rh, '-') || str_contains($rh, 'NEG')
                ? 'negativo'
                : 'positivo';
        }

        if (preg_match('/SEXO\s*[:\-]?\s*(M|F|MASCULINO|FEMENINO|HOMBRE|MUJER)\b/u', $text, $match)
            || preg_match('/\b(MASCULINO|FEMENINO|HOMBRE|MUJER)\b/u', $text, $match)) {
            $gender = strtoupper($match[1]);
            $data['gender'] = in_array($gender, ['F', 'FEMENINO', 'MUJER'], true) ? 'femenino' : 'masculino';
        }

        if (preg_match('/ESTATURA\s*[:\-]?\s*(\d)[,\.](\d{2})/', $text, $match)) {
            $data['height_cm'] = ((int) $match[1] * 100) + (int) $match[2];
        } elseif (preg_match('/ESTATURA\s*[:\-]?\s*(\d{2,3})\s*(?:CM)?\b/', $text, $match)) {
            $data['height_cm'] = (int) $match[1];
        }

        if (preg_match('/NOMBRES?\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ ]{3,60}?)(?=\s+(?:APELLIDOS?|FECHA|SEXO|ESTATURA|G\.?\s*S|NUIP|N[UÚ]MERO)|$)/u', $text, $match)) {
            $names = preg_split('/\s+/', trim($match[1])) ?: [];
            $data['first_name'] = $names[0] ?? null;
            $data['middle_name'] = $names[1] ?? null;
        }

        if (preg_match('/APELLIDOS?\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ ]{3,60}?)(?=\s+(?:NOMBRES?|FECHA|SEXO|ESTATURA|G\.?\s*S|NUIP|N[UÚ]MERO)|$)/u', $text, $match)) {
            $last = preg_split('/\s+/', trim($match[1])) ?: [];
            $data['last_name'] = $last[0] ?? null;
            $data['second_last_name'] = $last[1] ?? null;
        }

        return array_filter($data, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function parseLicencia(string $text): array
    {
        $data = [];

        $number = $this->documentNumber($text);

        if ($number !== null) {
            $data['license_document_number'] = $number;
        }

        $categories = [];
        if (preg_match_all('/\b(A[12]|B[12]|C[123])\b(?:\s*(?:VENCE|VIGENCIA|HASTA|:)\s*(\d{2}[\/\-]\d{2}[\/\-]\d{4}))?/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $code = $match[1];
                $categories[$code] = [
                    'category' => $code,
                    'expires_at' => isset($match[2]) && $match[2] !== '' ? $this->toDate($match[2]) : ($categories[$code]['expires_at'] ?? null),
                ];
            }
        }

        if ($categories !== []) {
            $data['license_categories'] = array_values($categories);
            $dates = array_values(array_filter(array_column($data['license_categories'], 'expires_at')));
            if ($dates !== []) {
                $data['license_expiration'] = min($dates);
            }
        }

        if (empty($data['license_expiration'])) {
            $expiration = $this->firstDate($text, ['VENCE', 'VENCIMIENTO', 'HASTA']);

            if ($expiration !== null) {
                $data['license_expiration'] = $expiration;
            }
        }

        return array_filter($data, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTarjetaPropiedad(string $text): array
    {
        $data = [];

        if (preg_match('/PLACA\s*[:\-]?\s*([A-Z]{3}\s?\d{2,3}[A-Z]?)\b/', $text, $match)
            || preg_match('/\b([A-Z]{3}\s?\d{3}|[A-Z]{3}\s?\d{2}[A-Z])\b/', $text, $match)) {
            $data['plate'] = str_replace(' ', '', $match[1]);
        }

        if (preg_match('/MARCA\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ0-9\-]+)/u', $text, $match)) {
            $data['brand'] = trim($match[1]);
        }

        if (preg_match('/L[IÍ]NEA\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ0-9\-]+(?:\s+(?!MODELO|COLOR|MARCA|PLACA|PROPIETARIO|CILINDRAJE|IDENTIFIC)[A-ZÁÉÍÓÚÑ0-9\-]+)?)/u', $text, $match)) {
            $data['line'] = trim($match[1]);
        }

        if (preg_match('/MODELO\s*[:\-]?\s*((?:19|20)\d{2})\b/', $text, $match)) {
            $data['year'] = (int) $match[1];
        } elseif (preg_match('/MODELO\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ0-9\-]+)/u', $text, $match)) {
            $data['model'] = trim($match[1]);
        }

        if (preg_match('/COLOR\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ]+)/u', $text, $match)) {
            $data['color'] = trim($match[1]);
        }

        if (preg_match('/(?:PROPIETARIO|TITULAR)\s*[:\-]?\s*([A-ZÁÉÍÓÚÑ ]{5,80}?)(?=\s+(?:IDENTIFIC|C\.?C\.?|NIT|PLACA|MARCA)|$)/u', $text, $match)) {
            $data['owner_name'] = trim($match[1]);
        }

        if (preg_match('/(?:IDENTIFICACI[OÓ]N|C\.?C\.?|NIT|CEDULA|C[EÉ]DULA)\s*(?:DEL PROPIETARIO)?\s*[:\-]?\s*(\d{6,15})\b/u', $text, $match)) {
            $data['owner_document_number'] = $match[1];
        }

        if (preg_match('/CILINDRAJE\s*[:\-]?\s*(\d{2,4})/', $text, $match)) {
            $data['engine_displacement'] = (int) $match[1];
        }

        return array_filter($data, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSoat(string $text): array
    {
        $data = [];

        if (preg_match('/\b([A-Z]{3}\s?\d{3}|[A-Z]{3}\s?\d{2}[A-Z])\b/', $text, $match)) {
            $data['plate'] = str_replace(' ', '', $match[1]);
        }

        $expiration = $this->firstDate($text, ['VIGENCIA', 'VENCE', 'HASTA']);

        if ($expiration !== null) {
            $data['soat_expiration'] = $expiration;
        }

        return array_filter($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTecnomecanica(string $text): array
    {
        $data = [];

        if (preg_match('/\b([A-Z]{3}\s?\d{3}|[A-Z]{3}\s?\d{2}[A-Z])\b/', $text, $match)) {
            $data['plate'] = str_replace(' ', '', $match[1]);
        }

        $expiration = $this->firstDate($text, ['VENCE', 'VENCIMIENTO', 'HASTA']);

        if ($expiration !== null) {
            $data['tecnomecanica_expiration'] = $expiration;
        }

        return array_filter($data);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtoupper($text);
        $text = str_replace(["\r", "\n", "\t"], ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function documentNumber(string $text): ?string
    {
        if (preg_match('/(?:NUIP|N[UÚ]MERO|NO\.?|C\.?C\.?)\s*[:\-]?\s*([\d\. ]{6,18})/u', $text, $match)) {
            $digits = $this->onlyDigits($match[1]);

            if ($this->isDocumentNumber($digits)) {
                return $digits;
            }
        }

        if (preg_match('/\b(\d{1,3}(?:\.\d{3}){2,3})\b/', $text, $match)) {
            $digits = $this->onlyDigits($match[1]);

            if ($this->isDocumentNumber($digits)) {
                return $digits;
            }
        }

        if (preg_match('/\b(\d{6,10})\b/', $text, $match) && $this->isDocumentNumber($match[1])) {
            return $match[1];
        }

        return null;
    }

    /**
     * @param  list<string>  $labels
     */
    private function firstDate(string $text, array $labels): ?string
    {
        $label = implode('|', $labels);
        $pattern = $this->datePattern();

        if (preg_match('/(?:'.$label.')\s*[:\-]?\s*'.$pattern.'/u', $text, $match)) {
            return $this->toDate($match[1]);
        }

        if (preg_match('/\b'.$pattern.'\b/u', $text, $match)) {
            return $this->toDate($match[1]);
        }

        return null;
    }

    private function datePattern(): string
    {
        return '(\d{1,2}[\/\-\s](?:\d{1,2}|ENE|FEB|MAR|ABR|MAY|JUN|JUL|AGO|SEP|SET|OCT|NOV|DIC)[\/\-\s]\d{4}|\d{4}[\/\-]\d{2}[\/\-]\d{2})';
    }

    private function toDate(string $value): ?string
    {
        $value = trim(strtoupper($value));
        $months = [
            'ENE' => '01',
            'FEB' => '02',
            'MAR' => '03',
            'ABR' => '04',
            'MAY' => '05',
            'JUN' => '06',
            'JUL' => '07',
            'AGO' => '08',
            'SEP' => '09',
            'SET' => '09',
            'OCT' => '10',
            'NOV' => '11',
            'DIC' => '12',
        ];

        if (preg_match('/^(\d{1,2})[\/\-\s]([A-Z]{3})[\/\-\s](\d{4})$/', $value, $match)) {
            $month = $months[$match[2]] ?? null;

            if ($month === null || ! checkdate((int) $month, (int) $match[1], (int) $match[3])) {
                return null;
            }

            return sprintf('%04d-%s-%02d', $match[3], $month, $match[1]);
        }

        $value = str_replace('-', '/', $value);
        $parts = explode('/', $value);

        if (count($parts) !== 3) {
            return null;
        }

        if (strlen($parts[0]) === 4) {
            [$year, $month, $day] = $parts;
        } else {
            [$day, $month, $year] = $parts;
        }

        if (! checkdate((int) $month, (int) $day, (int) $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function isDocumentNumber(string $digits): bool
    {
        $length = strlen($digits);

        return $length >= 6 && $length <= 10;
    }
}
