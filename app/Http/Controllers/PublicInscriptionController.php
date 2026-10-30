<?php

namespace App\Http\Controllers;

use App\Actions\ReplaceInscriptionDocumentAction;
use App\Actions\SubmitPublicInscriptionAction;
use App\Actions\UpdatePendingInscriptionAction;
use App\Enums\InscriptionApplicationStatus;
use App\Http\Requests\Inscription\LookupInscriptionRequest;
use App\Http\Requests\Inscription\ReplacePublicInscriptionDocumentRequest;
use App\Http\Requests\Inscription\StoreInscriptionBasicsRequest;
use App\Http\Requests\Inscription\SubmitPublicInscriptionRequest;
use App\Http\Requests\Inscription\UpdatePublicInscriptionRequest;
use App\Http\Requests\Inscription\UploadInscriptionDocumentsRequest;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentScan;
use App\Models\DocumentType;
use App\Models\InscriptionApplication;
use App\Models\InscriptionLink;
use App\Services\Documents\ColombianOfficialDocumentValidator;
use App\Services\Documents\DocumentExtractionService;
use App\Services\Inscriptions\InscriptionDocumentCatalog;
use App\Services\Inscriptions\InscriptionExtractedDataMerger;
use App\Services\Inscriptions\InscriptionExtractionReview;
use App\Services\Inscriptions\InscriptionFormMapper;
use App\Services\Inscriptions\InscriptionLinkService;
use App\Support\InscriptionDocumentSteps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicInscriptionController extends Controller
{
    public function __construct(
        private readonly InscriptionLinkService $links,
        private readonly DocumentExtractionService $extractions,
        private readonly InscriptionFormMapper $mapper,
        private readonly InscriptionExtractedDataMerger $extractedMerger,
        private readonly ColombianOfficialDocumentValidator $documentKinds,
        private readonly InscriptionExtractionReview $extractionReview,
        private readonly InscriptionDocumentCatalog $documentsCatalog,
    ) {
    }

    public function show(string $token): Response|RedirectResponse
    {
        $inscriptionLink = $this->link($token);
        $application = $this->links->resolveApplication($inscriptionLink);

        if ($application->status === InscriptionApplicationStatus::Submitted
            || $application->status === InscriptionApplicationStatus::Verified
            || $application->status === InscriptionApplicationStatus::AccessGranted) {
            return redirect()->route('public.inscription.thanks', $inscriptionLink->token);
        }

        if (! $this->mapper->hasBasics($application->form_draft)) {
            return $this->basicsPage($inscriptionLink, $application);
        }

        return Inertia::render('Public/InscriptionUpload', $this->wizardPayload($inscriptionLink, $application));
    }

    public function editBasics(string $token): Response|RedirectResponse
    {
        $inscriptionLink = $this->link($token);
        $application = $this->links->resolveApplication($inscriptionLink);

        if ($application->status === InscriptionApplicationStatus::Submitted
            || $application->status === InscriptionApplicationStatus::Verified
            || $application->status === InscriptionApplicationStatus::AccessGranted) {
            return redirect()->route('public.inscription.thanks', $inscriptionLink->token);
        }

        abort_unless($application->canEditForm(), 403);

        return $this->basicsPage($inscriptionLink, $application);
    }

    public function storeBasics(StoreInscriptionBasicsRequest $request, string $token): RedirectResponse
    {
        $inscriptionLink = $this->link($token);
        $application = $this->links->resolveApplication($inscriptionLink);

        abort_unless($application->canEditForm(), 403);

        $previousRole = $application->form_draft['vehicle_role'] ?? null;
        $editing = $this->mapper->hasBasics($application->form_draft);
        $validated = $request->validated();
        $form = $this->mapper->toForm($application->extracted_data ?? [], [
            ...($application->form_draft ?? []),
            ...$validated,
        ]);
        $form['document_type'] = $validated['document_type'];
        $form['document_number'] = $validated['document_number'];
        $form['first_name'] = $validated['first_name'];
        $form['last_name'] = $validated['last_name'];
        $form['second_last_name'] = $validated['second_last_name'] ?? '';
        $form['vehicle_role'] = $validated['vehicle_role'];
        $form['registered'] = [
            'document_type' => $validated['document_type'],
            'document_number' => $validated['document_number'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'second_last_name' => $validated['second_last_name'] ?? '',
        ];

        $payload = ['form_draft' => $form];

        if ($previousRole && $previousRole !== $validated['vehicle_role']) {
            $payload['status'] = InscriptionApplicationStatus::Draft;
        }

        $application->forceFill($payload)->save();

        return redirect()
            ->route('public.inscription.show', $inscriptionLink->token)
            ->with('success', $editing
                ? 'Tus datos quedaron actualizados. Revisa que coincidan con los documentos.'
                : 'Datos guardados. Puedes subir documentos o pasar al formulario.');
    }

    public function upload(
        UploadInscriptionDocumentsRequest $request,
        string $token,
    ): RedirectResponse {
        $inscriptionLink = $this->link($token);
        $application = $this->links->resolveApplication($inscriptionLink);

        abort_unless($application->canEditForm(), 403);

        $role = $application->form_draft['vehicle_role'] ?? 'driver';
        $step = InscriptionDocumentSteps::find($request->validated('step'));
        abort_unless($step && in_array($step['key'], InscriptionDocumentSteps::keys($role), true), 422);

        $file = $request->file('file');
        abort_unless($file, 422);

        $uploads = $application->uploads ?? [];
        $previousPath = is_string($uploads[$step['key']]['path'] ?? null) ? $uploads[$step['key']]['path'] : null;
        $path = $file->store('inscriptions/'.$application->id, 'scans');

        if ($step['validate'] && $step['type']) {
            $verdict = $this->documentKinds->validate(Storage::disk('scans')->path($path), $step['type']);

            if (! $verdict['ok']) {
                Storage::disk('scans')->delete($path);

                return redirect()
                    ->route('public.inscription.show', $inscriptionLink->token)
                    ->withErrors(['file' => $verdict['message']]);
            }
        }

        $upload = [
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'analyzed' => false,
            'extracted' => [],
            'kind' => $step['type'],
        ];

        if ($step['analyze'] && $step['type']) {
            $type = DocumentType::query()->where('slug', $step['type'])->firstOrFail();
            $scan = DocumentScan::query()->create([
                'document_type_id' => $type->id,
                'original_file' => $path,
                'status' => 'pending',
                'provider' => 'heuristic',
            ]);
            $extraction = $this->extractions->process($scan, $request->validated('ocr_text'));
            $stepExtracted = $extraction->extracted_data ?? [];
            $upload['scan_id'] = $scan->id;
            $upload['analyzed'] = true;
            $upload['extracted'] = $stepExtracted;
        }

        $uploads[$step['key']] = $upload;

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('scans')->delete($previousPath);
        }

        $extracted = $this->extractedMerger->rebuild($uploads, $role);

        $application->forceFill([
            'uploads' => $uploads,
            'extracted_data' => $extracted,
            'form_draft' => $this->mapper->toForm($extracted, $application->form_draft ?? []),
        ])->save();

        $replacing = $previousPath !== null;

        return redirect()
            ->route('public.inscription.show', $inscriptionLink->token)
            ->with('success', $step['analyze']
                ? ($upload['extracted'] === []
                    ? ($replacing
                        ? 'Reemplazamos la imagen. No se pudo leer texto; inténtalo de nuevo o completa el formulario.'
                        : 'Guardamos la imagen. No se pudo leer texto; puedes seguir y completar el formulario.')
                    : ($replacing
                        ? 'Volvimos a leer este documento. Revisa el resumen.'
                        : 'Leímos este documento. Revisa el resumen.'))
                : ($replacing ? 'Reemplazamos el archivo.' : 'Documento adjunto. Continúa con el siguiente.'))
            ->with('analyzed', $step['analyze'] ? $step['key'] : null);
    }

    public function form(string $token): Response|RedirectResponse
    {
        $inscriptionLink = $this->link($token);
        $application = $this->links->resolveApplication($inscriptionLink);

        if (! $application->canEditForm()) {
            return redirect()->route('public.inscription.thanks', $inscriptionLink->token);
        }

        if (! $this->mapper->hasBasics($application->form_draft)) {
            return redirect()->route('public.inscription.show', $inscriptionLink->token);
        }

        $form = $application->form_draft ?: $this->mapper->toForm($application->extracted_data ?? [], $application->form_draft ?? []);
        $registered = $this->extractionReview->registeredFrom($form);
        unset($form['registered']);

        return Inertia::render('Public/InscriptionForm', [
            'club' => $this->clubPayload($inscriptionLink),
            'token' => $inscriptionLink->token,
            'form' => $form,
            'filled' => $this->mapper->filledKeys($application->extracted_data ?? []),
            'warnings' => [
                ...$this->mapper->warnings($application->extracted_data ?? []),
                ...$this->extractionReview->registeredWarnings($registered, $application->extracted_data ?? []),
            ],
            'pilots' => $this->links->pilots($inscriptionLink->organization),
            'role' => $application->form_draft['vehicle_role'] ?? 'driver',
            'wizard' => 'form',
        ]);
    }

    public function continueToForm(string $token): RedirectResponse
    {
        $inscriptionLink = $this->link($token);
        $application = $this->links->resolveApplication($inscriptionLink);
        abort_unless($application->canEditForm(), 403);

        abort_unless($this->mapper->hasBasics($application->form_draft), 422);

        $application->forceFill([
            'status' => InscriptionApplicationStatus::ReadyForForm,
        ])->save();

        return redirect()->route('public.inscription.form', $inscriptionLink->token);
    }

    public function submitQuick(string $token, SubmitPublicInscriptionAction $submit): RedirectResponse
    {
        $inscriptionLink = $this->link($token);
        $application = $this->links->resolveApplication($inscriptionLink);
        abort_unless($application->canEditForm(), 403);
        abort_unless(($application->form_draft['vehicle_role'] ?? null) === 'passenger', 403);

        $role = 'passenger';
        abort_unless(InscriptionDocumentSteps::nextIncomplete($application->uploads ?? [], $role) === null, 422);

        $data = $this->mapper->toForm($application->extracted_data ?? [], $application->form_draft ?? []);
        $data['city'] = $data['city'] ?: $inscriptionLink->organization->city;
        $data['pilot_person_id'] = null;

        $submit->execute($application, $inscriptionLink->organization, $data);
        $this->links->rememberLookup($inscriptionLink, $application->fresh());

        return redirect()->route('public.inscription.thanks', $inscriptionLink->token);
    }

    public function store(
        SubmitPublicInscriptionRequest $request,
        string $token,
        SubmitPublicInscriptionAction $submit,
    ): RedirectResponse {
        $inscriptionLink = $this->link($token);
        $application = $this->links->resolveApplication($inscriptionLink);

        abort_unless($application->canEditForm(), 403);

        $submit->execute($application, $inscriptionLink->organization, $request->validated());
        $this->links->rememberLookup($inscriptionLink, $application->fresh());

        return redirect()->route('public.inscription.thanks', $inscriptionLink->token);
    }

    public function thanks(string $token): Response
    {
        $inscriptionLink = InscriptionLink::query()->where('token', $token)->firstOrFail();
        $application = $this->links->lookedUpApplication($inscriptionLink)
            ?? $this->links->sessionApplication($inscriptionLink);

        return Inertia::render('Public/InscriptionThanks', [
            'club' => $this->clubPayload($inscriptionLink),
            'token' => $inscriptionLink->token,
            'reference' => $application?->reference,
            'document_number' => $application?->person?->document_number
                ?? $application?->form_draft['document_number']
                ?? null,
            'can_update' => $application?->isPendingReview() ?? false,
        ]);
    }

    public function lookup(LookupInscriptionRequest $request, string $token): RedirectResponse
    {
        $inscriptionLink = InscriptionLink::query()->where('token', $token)->firstOrFail();
        $application = $this->links->lookup(
            $inscriptionLink,
            $request->validated('document_number'),
            $request->validated('reference'),
        );

        if ($application === null) {
            return back()->withErrors([
                'reference' => 'No encontramos una solicitud con esa cédula y ese número.',
            ]);
        }

        $this->links->rememberLookup($inscriptionLink, $application);

        return redirect()->route('public.inscription.request', $inscriptionLink->token);
    }

    public function request(string $token): Response|RedirectResponse
    {
        $inscriptionLink = InscriptionLink::query()->where('token', $token)->firstOrFail();
        $application = $this->links->lookedUpApplication($inscriptionLink)
            ?? $this->links->sessionApplication($inscriptionLink);

        if ($application === null || $application->canEditForm()) {
            return redirect()
                ->route('public.inscription.show', $inscriptionLink->token)
                ->withErrors(['reference' => 'Consulta tu solicitud con el número de documento y el número de solicitud.']);
        }

        $application->load([
            'person.documents.type',
            'person.documents.files',
            'person.motorcycles.documents.type',
            'person.motorcycles.documents.files',
            'person.healthProfile',
            'person.emergencyContacts',
            'person.memberships' => fn ($query) => $query->where('organization_id', $inscriptionLink->organization_id),
        ]);

        $canEdit = $application->isPendingReview();

        return Inertia::render('Public/InscriptionRequest', [
            'club' => $this->clubPayload($inscriptionLink),
            'token' => $inscriptionLink->token,
            'reference' => $application->reference,
            'status' => $application->status->value,
            'can_edit' => $canEdit,
            'form' => $this->mapper->fromPerson($application->person, $application->form_draft ?? []),
            'documents' => $this->documentsCatalog->forApplication(
                $application,
                fn (string $documentId, string $fileId) => route('public.inscription.request.file', [
                    $inscriptionLink->token,
                    $documentId,
                    $fileId,
                ]),
            ),
            'pilots' => $this->links->pilots($inscriptionLink->organization),
            'role' => $application->form_draft['vehicle_role'] ?? 'driver',
        ]);
    }

    public function updateRequest(
        UpdatePublicInscriptionRequest $request,
        string $token,
        UpdatePendingInscriptionAction $update,
    ): RedirectResponse {
        $inscriptionLink = InscriptionLink::query()->where('token', $token)->firstOrFail();
        $application = $this->links->lookedUpApplication($inscriptionLink);
        abort_unless($application?->isPendingReview(), 403);

        $update->execute($application, $inscriptionLink->organization, $request->validated());

        return back()->with('success', 'Actualizamos tus datos. Siguen pendientes de verificación.');
    }

    public function updateRequestDocument(
        ReplacePublicInscriptionDocumentRequest $request,
        string $token,
        ReplaceInscriptionDocumentAction $replace,
    ): RedirectResponse {
        $inscriptionLink = InscriptionLink::query()->where('token', $token)->firstOrFail();
        $application = $this->links->lookedUpApplication($inscriptionLink);
        abort_unless($application?->isPendingReview(), 403);

        $replace->execute($application, $request->validated(), $request->file('files', []));

        return back()->with('success', 'Actualizamos el documento. Sigue pendiente de verificación.');
    }

    public function downloadRequestFile(string $token, Document $document, DocumentFile $file): StreamedResponse
    {
        $inscriptionLink = InscriptionLink::query()->where('token', $token)->firstOrFail();
        $application = $this->links->lookedUpApplication($inscriptionLink);
        abort_unless($application, 403);
        abort_unless($file->document_id === $document->id, 404);

        $personId = $application->person_id;
        $motorcycleIds = $application->person?->motorcycles()->pluck('id') ?? collect();
        abort_unless(
            ($document->person_id && $document->person_id === $personId)
            || ($document->motorcycle_id && $motorcycleIds->contains($document->motorcycle_id)),
            403
        );

        return Storage::disk($file->disk)->response($file->path, $file->filename, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function preview(string $token, string $step): StreamedResponse
    {
        $inscriptionLink = $this->link($token);
        $application = $this->links->resolveApplication($inscriptionLink);
        abort_unless(in_array($step, InscriptionDocumentSteps::keys(), true), 404);

        $upload = $application->uploads[$step] ?? null;
        $path = $upload['path'] ?? null;
        abort_unless(is_string($path) && Storage::disk('scans')->exists($path), 404);

        return Storage::disk('scans')->response($path, $upload['filename'] ?? $step, [
            'Content-Type' => $upload['mime'] ?? 'image/jpeg',
        ]);
    }

    private function link(string $token): InscriptionLink
    {
        $inscriptionLink = InscriptionLink::query()->where('token', $token)->firstOrFail();
        abort_unless($inscriptionLink->isUsable(), 404);

        return $inscriptionLink;
    }

    /**
     * @return array<string, mixed>
     */
    private function wizardPayload(InscriptionLink $inscriptionLink, InscriptionApplication $application): array
    {
        $uploads = $application->uploads ?? [];
        $role = $application->form_draft['vehicle_role'] ?? 'driver';
        $steps = InscriptionDocumentSteps::forRole($role);
        $current = InscriptionDocumentSteps::nextIncomplete($uploads, $role);
        $lastAnalyzedKey = collect($uploads)
            ->filter(fn ($upload) => is_array($upload) && ! empty($upload['analyzed']))
            ->keys()
            ->last();
        $complete = $current === null;
        $registered = $this->extractionReview->registeredFrom($application->form_draft ?? []);

        return [
            'club' => $this->clubPayload($inscriptionLink),
            'token' => $inscriptionLink->token,
            'role' => $role,
            'steps' => collect($steps)->map(fn (array $step) => [
                ...$step,
                'done' => ! empty($uploads[$step['key']]),
                'preview_url' => ! empty($uploads[$step['key']])
                    ? route('public.inscription.preview', [$inscriptionLink->token, $step['key']])
                    : null,
            ])->values(),
            'current' => $current,
            'documents_complete' => $complete,
            'progress' => [
                'done' => count(array_intersect_key($uploads, array_flip(array_column($steps, 'key')))),
                'total' => count($steps),
            ],
            'review' => $this->extractionReview->forLastAnalyzed(
                $registered,
                $uploads,
                is_string($lastAnalyzedKey) ? $lastAnalyzedKey : null,
                $application->extracted_data ?? [],
            ),
            'wizard' => 'documents',
        ];
    }

    private function basicsPage(InscriptionLink $inscriptionLink, InscriptionApplication $application): Response
    {
        $draft = $application->form_draft ?? [];
        $registered = $this->extractionReview->registeredFrom($draft);

        return Inertia::render('Public/InscriptionBasics', [
            'club' => $this->clubPayload($inscriptionLink),
            'token' => $inscriptionLink->token,
            'from_later_step' => $this->mapper->hasBasics($draft),
            'wizard' => 'basics',
            'form' => [
                'document_type' => $registered['document_type'] !== '' ? $registered['document_type'] : 'cc',
                'document_number' => $registered['document_number'],
                'first_name' => $registered['first_name'],
                'last_name' => $registered['last_name'],
                'second_last_name' => $registered['second_last_name'],
                'vehicle_role' => $draft['vehicle_role'] ?? '',
            ],
        ]);
    }

    /**
     * @return array{name: string, city: string|null, slug: string}
     */
    private function clubPayload(InscriptionLink $inscriptionLink): array
    {
        $organization = $inscriptionLink->organization;

        return [
            'name' => $organization->name,
            'city' => $organization->city,
            'slug' => $organization->slug,
        ];
    }
}
