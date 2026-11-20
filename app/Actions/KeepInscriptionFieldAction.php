<?php

namespace App\Actions;

use App\Models\InscriptionApplication;
use App\Services\Inscriptions\InscriptionExtractionReview;
use App\Services\Inscriptions\InscriptionFormMapper;
use App\Services\Inscriptions\InscriptionLinkService;
use Illuminate\Validation\ValidationException;

class KeepInscriptionFieldAction
{
    public function __construct(
        private readonly InscriptionExtractionReview $review,
        private readonly InscriptionFormMapper $mapper,
        private readonly InscriptionLinkService $links,
    ) {}

    public function execute(InscriptionApplication $application, string $step, string $key, string $source, ?string $manual = null): void
    {
        $draft = $application->form_draft ?? [];
        $choice = $source === 'manual'
            ? $this->review->manualChoice($step, $key, (string) $manual)
            : $this->review->choice(
                $this->review->registeredFrom($draft),
                $application->uploads ?? [],
                $step,
                $key,
                $source,
            );

        abort_unless($choice !== null, 422);

        if (in_array('document_number', $choice['paths'], true) && $this->links->registeredPerson($choice['value']) !== null) {
            throw ValidationException::withMessages([
                'document_number' => InscriptionLinkService::DOCUMENT_ALREADY_REGISTERED,
            ]);
        }

        $kept = is_array($draft['kept'] ?? null) ? $draft['kept'] : [];
        $kept[$key] = [
            'source' => $source,
            'value' => $choice['value'],
            'paths' => $choice['paths'],
            'step' => $step,
        ];
        $draft['kept'] = $kept;

        $payload = ['form_draft' => $this->mapper->applyKept($draft)];

        if (in_array('document_number', $choice['paths'], true)) {
            $digits = preg_replace('/\D+/', '', $choice['value']) ?? '';

            if ($digits !== '') {
                $payload['document_number'] = $digits;
            }
        }

        $application->forceFill($payload)->save();
    }
}
