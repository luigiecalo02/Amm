<?php

namespace Tests\Unit;

use App\Models\DocumentType;
use App\Services\Documents\DocumentValidationService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DocumentValidationServiceTest extends TestCase
{
    public function test_person_document_requires_person_id(): void
    {
        $service = new DocumentValidationService;
        $type = new DocumentType(['entity_type' => 'person']);

        $this->expectException(InvalidArgumentException::class);
        $service->assertAssociation($type, null, 'moto-id');
    }

    public function test_motorcycle_document_requires_motorcycle_id(): void
    {
        $service = new DocumentValidationService;
        $type = new DocumentType(['entity_type' => 'motorcycle']);

        $this->expectException(InvalidArgumentException::class);
        $service->assertAssociation($type, 'person-id', null);
    }

    public function test_valid_person_association_passes(): void
    {
        $service = new DocumentValidationService;
        $type = new DocumentType(['entity_type' => 'person']);

        $service->assertAssociation($type, 'person-id', null);

        $this->assertTrue(true);
    }
}
