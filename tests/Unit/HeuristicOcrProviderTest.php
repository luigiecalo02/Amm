<?php

namespace Tests\Unit;

use App\Services\OCR\ColombianDocumentParser;
use App\Services\OCR\DocumentTextExtractor;
use App\Services\OCR\HeuristicOcrProvider;
use Tests\TestCase;

class HeuristicOcrProviderTest extends TestCase
{
    public function test_it_parses_client_ocr_text_from_a_photo(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'amm');
        file_put_contents($path, 'not-a-pdf');

        $result = (new HeuristicOcrProvider(
            new ColombianDocumentParser,
            new DocumentTextExtractor,
        ))->extract(
            $path,
            'cedula',
            'REPUBLICA DE COLOMBIA CEDULA DE CIUDADANIA NUMERO 1.047.475.332 NOMBRES LUIGIE ANDRES APELLIDOS CALO PEREZ FECHA DE NACIMIENTO 12-ABR-1990',
        );

        unlink($path);

        $this->assertSame('1047475332', $result['extracted_data']['document_number'] ?? null);
        $this->assertSame('LUIGIE', $result['extracted_data']['first_name'] ?? null);
        $this->assertSame('CALO', $result['extracted_data']['last_name'] ?? null);
        $this->assertSame('1990-04-12', $result['extracted_data']['birth_date'] ?? null);
    }

    public function test_it_still_reads_embedded_pdf_text(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'amm').'.pdf';
        file_put_contents($path, "%PDF-1.4\n(CEDULA DE CIUDADANIA NUIP 1047475332 NOMBRES LUIGIE APELLIDOS CALO)\n");

        $result = (new HeuristicOcrProvider(
            new ColombianDocumentParser,
            new DocumentTextExtractor,
        ))->extract($path, 'cedula');

        unlink($path);

        $this->assertSame('1047475332', $result['extracted_data']['document_number'] ?? null);
        $this->assertSame('LUIGIE', $result['extracted_data']['first_name'] ?? null);
    }
}
