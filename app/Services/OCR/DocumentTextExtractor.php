<?php

namespace App\Services\OCR;

use Symfony\Component\Process\Process;

final class DocumentTextExtractor
{
    /**
     * @return list<string>
     */
    public function candidates(string $absolutePath, ?string $hintText = null): array
    {
        $texts = [];
        $hint = trim((string) $hintText);

        if ($hint !== '') {
            $texts[] = $hint;
        }

        $pdf = $this->fromPdf($absolutePath);

        if ($pdf !== '') {
            $texts[] = $pdf;
        }

        $image = $this->fromImage($absolutePath);

        if ($image !== '') {
            $texts[] = $image;
        }

        return array_values(array_unique($texts));
    }

    public function fromPdf(string $absolutePath): string
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

    public function fromImage(string $absolutePath): string
    {
        if (! $this->isImage($absolutePath)) {
            return '';
        }

        $binary = $this->tesseractBinary();

        if ($binary === null) {
            return '';
        }

        $prepared = $this->prepareImage($absolutePath);
        $languages = (string) config('services.ocr.languages', 'spa');

        try {
            foreach (array_unique([$languages, 'spa', 'eng']) as $lang) {
                $text = $this->runTesseract($binary, $prepared, $lang);

                if ($text !== '') {
                    return $text;
                }
            }
        } finally {
            if ($prepared !== $absolutePath && is_file($prepared)) {
                @unlink($prepared);
            }
        }

        return '';
    }

    private function runTesseract(string $binary, string $imagePath, string $lang): string
    {
        $process = new Process([
            $binary,
            $imagePath,
            'stdout',
            '-l',
            $lang,
            '--psm',
            '6',
        ]);
        $process->setTimeout(90);
        $process->run();

        if (! $process->isSuccessful()) {
            return '';
        }

        return trim($process->getOutput());
    }

    private function prepareImage(string $absolutePath): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $absolutePath;
        }

        $image = @imagecreatefromstring((string) file_get_contents($absolutePath));

        if ($image === false) {
            return $absolutePath;
        }

        imagefilter($image, IMG_FILTER_GRAYSCALE);
        imagefilter($image, IMG_FILTER_CONTRAST, -25);

        $width = imagesx($image);
        $height = imagesy($image);
        $minSide = min($width, $height);
        $scale = $minSide > 0 && $minSide < 1100 ? 1100 / $minSide : 1;

        if ($scale > 1) {
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

            if ($resized !== false) {
                imagedestroy($image);
                $image = $resized;
            }
        }

        $prepared = tempnam(sys_get_temp_dir(), 'amm-ocr').'.png';
        imagepng($image, $prepared);
        imagedestroy($image);

        return $prepared;
    }

    private function isImage(string $absolutePath): bool
    {
        if (! is_file($absolutePath)) {
            return false;
        }

        $mime = mime_content_type($absolutePath) ?: '';

        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        return (bool) preg_match('/\.(jpe?g|png|webp|bmp|tif{1,2})$/i', $absolutePath);
    }

    private function tesseractBinary(): ?string
    {
        $configured = config('services.ocr.tesseract');

        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $candidates = PHP_OS_FAMILY === 'Windows'
            ? [
                'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
                'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            ]
            : [
                '/usr/bin/tesseract',
                '/usr/local/bin/tesseract',
            ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $finder = new Process([PHP_OS_FAMILY === 'Windows' ? 'where' : 'which', 'tesseract']);
        $finder->setTimeout(5);
        $finder->run();

        if (! $finder->isSuccessful()) {
            return null;
        }

        $path = trim(strtok($finder->getOutput(), "\r\n") ?: '');

        return $path !== '' && is_file($path) ? $path : null;
    }
}
