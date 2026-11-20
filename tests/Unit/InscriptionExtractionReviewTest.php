<?php

namespace Tests\Unit;

use App\Services\Inscriptions\InscriptionExtractionReview;
use PHPUnit\Framework\TestCase;

class InscriptionExtractionReviewTest extends TestCase
{
    public function test_it_summarizes_one_side_without_a_verdict(): void
    {
        $review = (new InscriptionExtractionReview)->forLastAnalyzed(
            [
                'document_number' => '1047475332',
                'first_name' => 'Luigie',
                'last_name' => 'Calo',
                'second_last_name' => '',
            ],
            [
                'cedula_front' => [
                    'analyzed' => true,
                    'extracted' => [
                        'document_number' => '1047475332',
                        'first_name' => 'LUIGIE',
                        'last_name' => 'CALO',
                    ],
                ],
            ],
            'cedula_front',
        );

        $this->assertNotNull($review);
        $this->assertFalse($review['both_sides']);
        $this->assertNull($review['matches_registered']);
        $this->assertSame('match', $review['fields'][0]['status']);
        $this->assertStringContainsString('otro', $review['headline']);
    }

    public function test_it_matches_registered_data_when_front_and_back_agree(): void
    {
        $review = (new InscriptionExtractionReview)->forLastAnalyzed(
            [
                'document_number' => '1.047.475.332',
                'first_name' => 'Luigie',
                'last_name' => 'Calo',
                'second_last_name' => 'Pérez',
            ],
            [
                'cedula_front' => [
                    'analyzed' => true,
                    'extracted' => [
                        'document_number' => '1047475332',
                        'first_name' => 'LUIGIE',
                        'last_name' => 'CALO',
                        'second_last_name' => 'PEREZ',
                    ],
                ],
                'cedula_back' => [
                    'analyzed' => true,
                    'extracted' => [
                        'document_number' => '1047475332',
                        'blood_type' => 'O',
                        'rh' => 'positivo',
                    ],
                ],
            ],
            'cedula_back',
        );

        $this->assertTrue($review['both_sides']);
        $this->assertTrue($review['matches_registered']);
        $this->assertSame('match', collect($review['fields'])->firstWhere('key', 'document_number')['status']);
        $this->assertSame('O', collect($review['extracted'])->firstWhere('key', 'blood_type')['value']);
        $this->assertSame('LUIGIE', collect($review['extracted_front'])->firstWhere('key', 'first_name')['value']);
        $this->assertSame('O', collect($review['extracted_back'])->firstWhere('key', 'blood_type')['value']);
    }

    public function test_it_does_not_treat_empty_photos_as_a_match(): void
    {
        $review = (new InscriptionExtractionReview)->forLastAnalyzed(
            [
                'document_number' => '1143354429',
                'first_name' => 'Luis Guillermo',
                'last_name' => 'Calo',
                'second_last_name' => '',
            ],
            [
                'cedula_front' => ['analyzed' => true, 'extracted' => []],
                'cedula_back' => ['analyzed' => true, 'extracted' => []],
            ],
            'cedula_back',
        );

        $this->assertNull($review['matches_registered']);
        $this->assertSame('unread', collect($review['fields'])->firstWhere('key', 'document_number')['status']);
        $this->assertSame([], $review['extracted']);
        $this->assertStringContainsString('texto', $review['headline']);
    }

    public function test_it_detects_mismatch_between_back_and_registered_data(): void
    {
        $review = (new InscriptionExtractionReview)->forLastAnalyzed(
            [
                'document_number' => '1047475332',
                'first_name' => 'Luigie',
                'last_name' => 'Calo',
                'second_last_name' => '',
            ],
            [
                'cedula_front' => [
                    'analyzed' => true,
                    'extracted' => ['document_number' => '1047475332', 'first_name' => 'LUIGIE'],
                ],
                'cedula_back' => [
                    'analyzed' => true,
                    'extracted' => ['document_number' => '99999999'],
                ],
            ],
            'cedula_back',
        );

        $this->assertFalse($review['matches_registered']);
        $this->assertSame('mismatch', collect($review['fields'])->firstWhere('key', 'document_number')['status']);
    }

    public function test_it_compares_license_id_with_registered_document(): void
    {
        $review = (new InscriptionExtractionReview)->forLastAnalyzed(
            [
                'document_number' => '1047475332',
                'first_name' => 'Luigie',
                'last_name' => 'Calo',
                'second_last_name' => '',
            ],
            [
                'licencia_front' => [
                    'analyzed' => true,
                    'extracted' => ['license_document_number' => '1047475332'],
                ],
                'licencia_back' => [
                    'analyzed' => true,
                    'extracted' => ['license_document_number' => '1047475332'],
                ],
            ],
            'licencia_back',
        );

        $this->assertTrue($review['matches_registered']);
        $this->assertSame('1047475332', $review['fields'][0]['registered']);
        $this->assertSame('1047475332', $review['fields'][0]['front']);
    }

    public function test_a_mismatch_and_an_unread_field_can_choose_which_value_to_keep(): void
    {
        $review = (new InscriptionExtractionReview)->forLastAnalyzed(
            [
                'document_number' => '1047475332',
                'first_name' => 'Luigie',
                'last_name' => 'Calo',
                'second_last_name' => 'Pérez',
            ],
            [
                'cedula_front' => [
                    'analyzed' => true,
                    'extracted' => [
                        'document_number' => '1047475332',
                        'first_name' => 'ANA',
                        'last_name' => 'CALO',
                    ],
                ],
                'cedula_back' => [
                    'analyzed' => true,
                    'extracted' => ['document_number' => '1047475332'],
                ],
            ],
            'cedula_back',
        );

        $name = collect($review['fields'])->firstWhere('key', 'first_name');
        $this->assertSame('mismatch', $name['status']);
        $this->assertSame(['registered', 'front'], array_column($name['choices'], 'source'));

        $secondLastName = collect($review['fields'])->firstWhere('key', 'second_last_name');
        $this->assertSame('unread', $secondLastName['status']);
        $this->assertSame('registered', $secondLastName['choices'][0]['source']);
        $this->assertSame('Pérez', $secondLastName['choices'][0]['value']);

        $choice = (new InscriptionExtractionReview)->choice(
            [
                'document_number' => '1047475332',
                'first_name' => 'Luigie',
                'last_name' => 'Calo',
                'second_last_name' => 'Pérez',
            ],
            [
                'cedula_front' => [
                    'analyzed' => true,
                    'extracted' => ['first_name' => 'ANA', 'last_name' => 'CALO'],
                ],
                'cedula_back' => [
                    'analyzed' => true,
                    'extracted' => [],
                ],
            ],
            'cedula_back',
            'first_name',
            'front',
        );

        $this->assertSame('ANA', $choice['value']);
        $this->assertContains('first_name', $choice['paths']);
    }
}
