<?php

namespace App\Actions;

use App\Models\InscriptionApplication;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeleteOpenInscriptionAction
{
    public function execute(InscriptionApplication $application): void
    {
        if (! $application->isOpen()) {
            throw new HttpException(403, 'Solo se pueden borrar solicitudes abiertas.');
        }

        foreach ($application->uploads ?? [] as $upload) {
            $path = is_array($upload) ? ($upload['path'] ?? null) : null;

            if (is_string($path) && $path !== '') {
                Storage::disk('scans')->delete($path);
            }
        }

        $application->delete();
    }
}
