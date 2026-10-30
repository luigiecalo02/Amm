<?php

namespace Tests\Unit;

use App\Services\Inscriptions\InscriptionExtractedDataMerger;
use App\Services\Inscriptions\InscriptionFormMapper;
use PHPUnit\Framework\TestCase;

class InscriptionFormMapperTest extends TestCase
{
    public function test_it_maps_document_fields_into_the_inscription_form(): void
    {
        $form = (new InscriptionFormMapper)->toForm([
            'first_name' => 'LUIGIE',
            'document_number' => '1047475332',
            'birth_date' => '1990-04-12',
            'blood_type' => 'O',
            'rh' => 'positivo',
            'height_cm' => 178,
            'gender' => 'masculino',
            'license_document_number' => '1047475332',
            'license_categories' => [['category' => 'A2', 'expires_at' => '2028-03-15']],
            'plate' => 'ABC12D',
            'brand' => 'YAMAHA',
            'line' => 'NMAX',
            'year' => 2023,
            'color' => 'NEGRO',
            'owner_name' => 'LUIGIE ANDRES CALO',
            'owner_document_number' => '1047475332',
        ]);

        $this->assertSame('LUIGIE', $form['first_name']);
        $this->assertSame(178, $form['health']['height_cm']);
        $this->assertSame('O', $form['health']['blood_type']);
        $this->assertSame('A2', $form['license_categories'][0]['category']);
        $this->assertSame('NMAX', $form['motorcycle']['line']);
        $this->assertSame(2023, $form['motorcycle']['year']);
        $this->assertSame('NEGRO', $form['motorcycle']['color']);
        $this->assertSame('LUIGIE ANDRES CALO', $form['motorcycle']['owner_name']);
        $this->assertNull($form['registered']);
    }

    public function test_it_preserves_the_registered_snapshot(): void
    {
        $registered = [
            'document_number' => '1047475332',
            'first_name' => 'Luigie',
            'last_name' => 'Calo',
            'second_last_name' => '',
            'document_type' => 'cc',
        ];

        $form = (new InscriptionFormMapper)->toForm(
            ['first_name' => 'LUIGIE'],
            ['registered' => $registered, 'vehicle_role' => 'driver'],
        );

        $this->assertSame($registered, $form['registered']);
        $this->assertSame('LUIGIE', $form['first_name']);
    }

    public function test_it_warns_when_license_or_owner_id_does_not_match_cedula(): void
    {
        $mapper = new InscriptionFormMapper;
        $warnings = $mapper->warnings([
            'document_number' => '1047475332',
            'license_document_number' => '99999999',
            'owner_document_number' => '11111111',
        ]);

        $this->assertCount(2, $warnings);
    }

    public function test_license_extraction_does_not_overwrite_cedula_number(): void
    {
        $merger = new InscriptionExtractedDataMerger;
        $merged = $merger->merge(
            ['document_number' => '1047475332', 'first_name' => 'LUIGIE'],
            ['document_number' => '99999999', 'license_document_number' => '1047475332'],
            'licencia'
        );

        $this->assertSame('1047475332', $merged['document_number']);
        $this->assertSame('1047475332', $merged['license_document_number']);
        $this->assertSame('LUIGIE', $merged['first_name']);
    }

    public function test_rebuild_replaces_previous_cedula_extraction(): void
    {
        $merger = new InscriptionExtractedDataMerger;
        $extracted = $merger->rebuild([
            'cedula_front' => [
                'analyzed' => true,
                'extracted' => ['document_number' => '99999999', 'first_name' => 'ANA'],
            ],
            'cedula_back' => [
                'analyzed' => true,
                'extracted' => ['blood_type' => 'O'],
            ],
        ], 'passenger');

        $this->assertSame('99999999', $extracted['document_number']);
        $this->assertSame('ANA', $extracted['first_name']);
        $this->assertSame('O', $extracted['blood_type']);
    }
}
