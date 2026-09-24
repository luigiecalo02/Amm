<?php

namespace App\Services\Documents;

use App\Models\DocumentType;
use InvalidArgumentException;

class DocumentValidationService
{
    public function assertAssociation(DocumentType $type, ?string $personId, ?string $motorcycleId): void
    {
        if ($type->isForPerson()) {
            if (! $personId) {
                throw new InvalidArgumentException('Este documento debe asociarse a una persona.');
            }

            if ($motorcycleId) {
                throw new InvalidArgumentException('Un documento de persona no puede asociarse a una motocicleta.');
            }

            return;
        }

        if ($type->isForMotorcycle()) {
            if (! $motorcycleId) {
                throw new InvalidArgumentException('Este documento debe asociarse a una motocicleta.');
            }

            if ($personId) {
                throw new InvalidArgumentException('Un documento de motocicleta no puede asociarse directamente a una persona.');
            }

            return;
        }

        throw new InvalidArgumentException('El tipo de documento no es válido.');
    }
}
