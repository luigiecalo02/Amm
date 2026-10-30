<?php

namespace Tests\Unit;

use App\Services\OCR\ColombianDocumentParser;
use PHPUnit\Framework\TestCase;

class ColombianDocumentParserTest extends TestCase
{
    public function test_it_reads_cedula_identity_and_health_fields(): void
    {
        $parser = new ColombianDocumentParser;
        $data = $parser->parse(
            'REPUBLICA DE COLOMBIA CEDULA DE CIUDADANIA NUIP 1047475332 NOMBRES LUIGIE ANDRES APELLIDOS CALO PEREZ FECHA DE NACIMIENTO 12/04/1990 SEXO M ESTATURA 1.78 G.S. O+',
            'cedula'
        );

        $this->assertSame('1047475332', $data['document_number']);
        $this->assertSame('LUIGIE', $data['first_name']);
        $this->assertSame('ANDRES', $data['middle_name']);
        $this->assertSame('CALO', $data['last_name']);
        $this->assertSame('PEREZ', $data['second_last_name']);
        $this->assertSame('1990-04-12', $data['birth_date']);
        $this->assertSame('masculino', $data['gender']);
        $this->assertSame(178, $data['height_cm']);
        $this->assertSame('O', $data['blood_type']);
        $this->assertSame('positivo', $data['rh']);
    }

    public function test_it_reads_dotted_cedula_numbers_and_spanish_months(): void
    {
        $parser = new ColombianDocumentParser;
        $data = $parser->parse(
            'REPUBLICA DE COLOMBIA CEDULA DE CIUDADANIA NUMERO 1.047.475.332 APELLIDOS CALO PEREZ NOMBRES LUIGIE ANDRES FECHA DE NACIMIENTO 12-ABR-1990 SEXO M ESTATURA 1,78 G.S. O+',
            'cedula'
        );

        $this->assertSame('1047475332', $data['document_number']);
        $this->assertSame('LUIGIE', $data['first_name']);
        $this->assertSame('CALO', $data['last_name']);
        $this->assertSame('1990-04-12', $data['birth_date']);
        $this->assertSame(178, $data['height_cm']);
    }

    public function test_it_reads_license_id_and_category_expirations(): void
    {
        $parser = new ColombianDocumentParser;
        $data = $parser->parse(
            'LICENCIA DE CONDUCCION NO 1047475332 CATEGORIA A2 VENCE 15/03/2028 A1 VENCE 10/01/2027',
            'licencia'
        );

        $this->assertSame('1047475332', $data['license_document_number']);
        $this->assertSame('2027-01-10', $data['license_expiration']);
        $this->assertCount(2, $data['license_categories']);
        $this->assertSame('A2', $data['license_categories'][0]['category']);
        $this->assertSame('2028-03-15', $data['license_categories'][0]['expires_at']);
    }

    public function test_it_reads_property_card_vehicle_and_owner(): void
    {
        $parser = new ColombianDocumentParser;
        $data = $parser->parse(
            'LICENCIA DE TRANSITO PLACA ABC12D MARCA YAMAHA LINEA NMAX MODELO 2023 COLOR NEGRO PROPIETARIO LUIGIE ANDRES CALO IDENTIFICACION 1047475332 CILINDRAJE 155',
            'tarjeta-propiedad'
        );

        $this->assertSame('ABC12D', $data['plate']);
        $this->assertSame('YAMAHA', $data['brand']);
        $this->assertSame('NMAX', $data['line']);
        $this->assertSame(2023, $data['year']);
        $this->assertSame('NEGRO', $data['color']);
        $this->assertSame('LUIGIE ANDRES CALO', $data['owner_name']);
        $this->assertSame('1047475332', $data['owner_document_number']);
        $this->assertSame(155, $data['engine_displacement']);
        $this->assertArrayNotHasKey('document_number', $data);
    }
}
