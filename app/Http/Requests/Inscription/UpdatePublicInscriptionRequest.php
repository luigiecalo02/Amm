<?php

namespace App\Http\Requests\Inscription;

use App\Models\InscriptionApplication;
use App\Models\InscriptionLink;
use App\Models\Organization;
use App\Models\Person;
use App\Services\Inscriptions\InscriptionLinkService;

class UpdatePublicInscriptionRequest extends UpdatePendingInscriptionRequest
{
    public function authorize(): bool
    {
        $application = $this->lookedUpApplication();

        return $application?->isPendingReview() ?? false;
    }

    protected function personBeingUpdated(): ?Person
    {
        return $this->lookedUpApplication()?->person;
    }

    protected function organizationBeingUpdated(): ?Organization
    {
        return $this->lookedUpApplication()?->organization;
    }

    private function lookedUpApplication(): ?InscriptionApplication
    {
        $token = $this->route('token');

        if (! is_string($token)) {
            return null;
        }

        $link = InscriptionLink::query()->where('token', $token)->first();

        return $link ? app(InscriptionLinkService::class)->lookedUpApplication($link) : null;
    }
}
