<?php

namespace Tests\Unit;

use App\Services\Documents\ColombianOfficialDocumentValidator;
use PHPUnit\Framework\TestCase;

class ColombianOfficialDocumentValidatorTest extends TestCase
{
    public function test_text_detects_cedula_and_rejects_license_as_cedula(): void
    {
        $validator = new ColombianOfficialDocumentValidator;

        $this->assertSame('cedula', $validator->classifyText('REPUBLICA DE COLOMBIA CEDULA DE CIUDADANIA NUIP 1047'));
        $this->assertSame('licencia', $validator->classifyText('MINISTERIO DE TRANSPORTE LICENCIA DE CONDUCCION CATEGORIA A2'));
    }

    public function test_a_black_and_white_photo_is_accepted(): void
    {
        $validator = new ColombianOfficialDocumentValidator;
        $gray = $this->coloredJpeg(180, 180, 180);

        $accepted = $validator->validate($gray, 'cedula');

        @unlink($gray);

        $this->assertTrue($accepted['ok']);
    }

    private function coloredJpeg(int $r, int $g, int $b): string
    {
        $path = tempnam(sys_get_temp_dir(), 'amm').'.jpg';
        $image = imagecreatetruecolor(320, 200);
        imagefilledrectangle($image, 0, 0, 319, 199, imagecolorallocate($image, $r, $g, $b));
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        return $path;
    }
}
