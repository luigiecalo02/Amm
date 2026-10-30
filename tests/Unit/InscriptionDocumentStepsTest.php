<?php

namespace Tests\Unit;

use App\Support\InscriptionDocumentSteps;
use PHPUnit\Framework\TestCase;

class InscriptionDocumentStepsTest extends TestCase
{
    public function test_license_and_property_card_require_both_sides(): void
    {
        $keys = InscriptionDocumentSteps::keys();

        $this->assertContains('cedula_front', $keys);
        $this->assertContains('cedula_back', $keys);
        $this->assertContains('licencia_front', $keys);
        $this->assertContains('licencia_back', $keys);
        $this->assertContains('tarjeta_propiedad_front', $keys);
        $this->assertContains('tarjeta_propiedad_back', $keys);
        $this->assertNotContains('licencia', $keys);
        $this->assertNotContains('tarjeta_propiedad', $keys);
    }

    public function test_card_steps_include_icon_and_frame(): void
    {
        $front = InscriptionDocumentSteps::find('licencia_front');

        $this->assertSame('licencia', $front['icon'] ?? null);
        $this->assertSame('id-card', $front['frame'] ?? null);
    }

    public function test_photo_step_is_for_the_registration_record(): void
    {
        $step = InscriptionDocumentSteps::find('photo');

        $this->assertSame('Foto del registro', $step['label'] ?? null);
        $this->assertSame('Foto del registro', $step['group_label'] ?? null);
        $this->assertStringContainsString('administración', $step['hint'] ?? '');
        $this->assertTrue($step['camera'] ?? false);
        $this->assertTrue(InscriptionDocumentSteps::find('soat')['camera'] ?? false);
        $this->assertTrue(InscriptionDocumentSteps::find('tecnomecanica')['camera'] ?? false);
    }

    public function test_passenger_only_needs_cedula_sides(): void
    {
        $keys = InscriptionDocumentSteps::keys('passenger');

        $this->assertSame(['cedula_front', 'cedula_back'], $keys);
        $this->assertContains('licencia_front', InscriptionDocumentSteps::keys('driver'));
    }

    public function test_back_side_is_nested_under_the_front_document(): void
    {
        $front = InscriptionDocumentSteps::find('cedula_front');
        $back = InscriptionDocumentSteps::mate('cedula_front');

        $this->assertSame('front', $front['side'] ?? null);
        $this->assertSame('cedula', $front['group'] ?? null);
        $this->assertSame('cedula_back', $back['key'] ?? null);
        $this->assertSame('back', $back['side'] ?? null);
        $this->assertSame('cedula', $back['group'] ?? null);
    }
}
