<?php

namespace App\Support;

use App\Models\Organization;
use RuntimeException;

class OrganizationContext
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function current(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?string
    {
        return $this->organization?->id;
    }

    public function require(): Organization
    {
        if (! $this->organization) {
            throw new RuntimeException('No hay una organización activa en el contexto.');
        }

        return $this->organization;
    }
}
