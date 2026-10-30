<?php

namespace App\Http\Requests\Inscription;

use App\Models\InscriptionLink;
use App\Services\Inscriptions\InscriptionLinkService;

class ReplacePublicInscriptionDocumentRequest extends ReplacePendingInscriptionDocumentRequest
{
    public function authorize(): bool
    {
        $token = $this->route('token');

        if (! is_string($token)) {
            return false;
        }

        $link = InscriptionLink::query()->where('token', $token)->first();
        $application = $link ? app(InscriptionLinkService::class)->lookedUpApplication($link) : null;

        return $application?->isPendingReview() ?? false;
    }
}
