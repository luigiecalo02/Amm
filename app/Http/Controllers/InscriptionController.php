<?php

namespace App\Http\Controllers;

use App\Actions\ApproveInscriptionAction;
use App\Actions\DeleteOpenInscriptionAction;
use App\Actions\GrantInscriptionAccessAction;
use App\Actions\ReplaceInscriptionDocumentAction;
use App\Actions\UpdatePendingInscriptionAction;
use App\Enums\DocumentStatus;
use App\Enums\InscriptionApplicationStatus;
use App\Http\Requests\Inscription\GrantInscriptionAccessRequest;
use App\Http\Requests\Inscription\ReplacePendingInscriptionDocumentRequest;
use App\Http\Requests\Inscription\StoreInscriptionLinkRequest;
use App\Http\Requests\Inscription\UpdatePendingInscriptionRequest;
use App\Models\InscriptionApplication;
use App\Models\InscriptionLink;
use App\Models\Organization;
use App\Models\Role;
use App\Services\Inscriptions\InscriptionDocumentCatalog;
use App\Services\Inscriptions\InscriptionFormMapper;
use App\Services\Inscriptions\InscriptionLinkService;
use App\Services\People\MemberPhotoService;
use App\Support\InscriptionDocumentSteps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InscriptionController extends Controller
{
    public function __construct(
        private readonly InscriptionLinkService $links,
        private readonly InscriptionFormMapper $mapper,
        private readonly InscriptionDocumentCatalog $documentsCatalog,
        private readonly MemberPhotoService $photos,
    ) {}

    public function index(Organization $organization): Response
    {
        $this->authorizePermission($organization, 'inscriptions.view');

        return Inertia::render('Inscriptions/Index', [
            'links' => InscriptionLink::query()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->latest()
                ->get()
                ->map(fn (InscriptionLink $link) => [
                    ...$link->toArray(),
                    'url' => $link->publicUrl(),
                ]),
            'applications' => InscriptionApplication::query()
                ->where('organization_id', $organization->id)
                ->with('person')
                ->latest()
                ->limit(80)
                ->get()
                ->filter(fn (InscriptionApplication $application) => $application->hasStarted())
                ->take(40)
                ->values()
                ->map(fn (InscriptionApplication $application) => [
                    'id' => $application->id,
                    'status' => $application->status->value,
                    'reference' => $application->reference,
                    'email' => $application->email ?? $application->form_draft['email'] ?? null,
                    'submitted_at' => $application->submitted_at,
                    'display_name' => $application->displayName(),
                    'document_number' => $application->document_number ?? $application->person?->document_number,
                    'can_delete' => $application->isOpen(),
                    'person' => $application->person,
                ]),
            'canCreate' => request()->user()?->hasPermission('inscriptions.create', $organization) ?? false,
            'canVerify' => request()->user()?->hasPermission('inscriptions.verify', $organization) ?? false,
        ]);
    }

    public function store(StoreInscriptionLinkRequest $request, Organization $organization): RedirectResponse
    {
        $this->links->create($organization, $request->user(), $request->validated());

        return back()->with('success', 'Enlace creado. Compártelo para que suban cédula, licencia, SOAT y tecnomecánica.');
    }

    public function deactivate(Organization $organization, InscriptionLink $inscriptionLink): RedirectResponse
    {
        return $this->destroy($organization, $inscriptionLink);
    }

    public function destroy(Organization $organization, InscriptionLink $inscriptionLink): RedirectResponse
    {
        $this->authorizePermission($organization, 'inscriptions.create');
        abort_unless($inscriptionLink->organization_id === $organization->id, 404);

        if ($inscriptionLink->applications()->exists()) {
            $inscriptionLink->forceFill(['is_active' => false])->save();

            return back()->with('success', 'El enlace quedó cerrado. Nadie más puede inscribirse; las solicitudes ya recibidas se conservan.');
        }

        $inscriptionLink->delete();

        return back()->with('success', 'El enlace se eliminó. Nadie más puede inscribirse con él.');
    }

    public function destroyApplication(
        Organization $organization,
        InscriptionApplication $application,
        DeleteOpenInscriptionAction $delete,
    ): RedirectResponse {
        $this->authorizePermission($organization, 'inscriptions.verify');
        abort_unless($application->organization_id === $organization->id, 404);

        $delete->execute($application);

        return redirect()
            ->route('inscriptions.index', $organization)
            ->with('success', 'La solicitud abierta se eliminó.');
    }

    public function show(Organization $organization, InscriptionApplication $application): Response
    {
        $this->authorizePermission($organization, 'inscriptions.view');
        abort_unless($application->organization_id === $organization->id, 404);

        $application->load([
            'person.documents.type',
            'person.documents.files',
            'person.motorcycles.documents.type',
            'person.motorcycles.documents.files',
            'person.healthProfile',
            'person.emergencyContacts',
            'person.memberships' => fn ($query) => $query->where('organization_id', $organization->id),
        ]);

        $canVerify = request()->user()?->hasPermission('inscriptions.verify', $organization) ?? false;
        $canEdit = $canVerify && $application->isPendingReview();

        if ($application->person) {
            $this->photos->present($application->person, $organization);
        }

        return Inertia::render('Inscriptions/Show', [
            'application' => $application,
            'form' => $this->mapper->forApplication($application),
            'documents' => $this->documentsCatalog->forApplication(
                $application,
                fn (string $documentId, string $fileId) => route('documents.download', [$organization, $documentId, $fileId]),
                fn (string $step) => route('inscriptions.scan', [$organization, $application, $step]),
            ),
            'pilots' => $this->links->pilots($organization),
            'roles' => Role::query()->forOrganization($organization->id)->orderBy('name')->get(['id', 'name', 'slug']),
            'canVerify' => $canVerify,
            'canEdit' => $canEdit,
            'memberActive' => $application->person?->isActiveIn($organization->id) ?? false,
            'canUpdateProfile' => $application->person
                ? (request()->user()?->can('update', [$application->person, $organization]) ?? false)
                : false,
            'canUpdateMotorcycle' => request()->user()?->hasPermission('motorcycles.update', $organization) ?? false,
        ]);
    }

    public function previewScan(
        Organization $organization,
        InscriptionApplication $application,
        string $step,
    ): StreamedResponse {
        $this->authorizePermission($organization, 'inscriptions.view');
        abort_unless($application->organization_id === $organization->id, 404);
        abort_unless(in_array($step, InscriptionDocumentSteps::keys(), true), 404);

        $upload = $application->uploads[$step] ?? null;
        $path = is_array($upload) ? ($upload['path'] ?? null) : null;
        abort_unless(is_string($path) && Storage::disk('scans')->exists($path), 404);

        return Storage::disk('scans')->response($path, $upload['filename'] ?? $step, [
            'Content-Type' => $upload['mime'] ?? 'application/octet-stream',
        ]);
    }

    public function update(
        UpdatePendingInscriptionRequest $request,
        Organization $organization,
        InscriptionApplication $application,
        UpdatePendingInscriptionAction $update,
    ): RedirectResponse {
        abort_unless($application->organization_id === $organization->id, 404);

        $update->execute($application, $organization, $request->validated());

        return back()->with('success', 'Datos del inscrito actualizados.');
    }

    public function storeDocument(
        ReplacePendingInscriptionDocumentRequest $request,
        Organization $organization,
        InscriptionApplication $application,
        ReplaceInscriptionDocumentAction $replace,
    ): RedirectResponse {
        abort_unless($application->organization_id === $organization->id, 404);

        $replace->execute($application, $request->validated(), $request->file('files', []));

        return back()->with('success', 'Documento actualizado. Sigue pendiente de verificación.');
    }

    public function verify(Organization $organization, InscriptionApplication $application): RedirectResponse
    {
        $this->authorizePermission($organization, 'inscriptions.verify');
        abort_unless($application->organization_id === $organization->id, 404);
        abort_unless($application->status === InscriptionApplicationStatus::Submitted, 403);

        $application->person?->documents()->update([
            'status' => DocumentStatus::Valid,
            'verified_at' => now(),
            'verified_by' => request()->user()?->id,
        ]);

        $application->forceFill([
            'status' => InscriptionApplicationStatus::Verified,
            'verified_at' => now(),
            'verified_by' => request()->user()?->id,
        ])->save();

        return back()->with('success', 'Documentación verificada. Ya puedes otorgar acceso al club.');
    }

    public function approve(
        Organization $organization,
        InscriptionApplication $application,
        ApproveInscriptionAction $approve,
    ): RedirectResponse {
        $this->authorizePermission($organization, 'inscriptions.verify');
        abort_unless($application->organization_id === $organization->id, 404);
        $actor = request()->user();
        abort_unless($actor !== null, 403);

        $approve->execute($application, $actor);

        return back()->with('success', 'Solicitud aprobada. El integrante quedó activo y ya puedes actualizar su foto de perfil y la de la moto.');
    }

    public function grantAccess(
        GrantInscriptionAccessRequest $request,
        Organization $organization,
        InscriptionApplication $application,
        GrantInscriptionAccessAction $grant,
    ): RedirectResponse {
        abort_unless($application->organization_id === $organization->id, 404);

        $result = $grant->execute($application, $organization, $request->user(), $request->validated());

        return back()->with(
            'success',
            'Acceso creado para '.$result['user']->email.'. Contraseña temporal: '.$result['password']
        );
    }

    private function authorizePermission(Organization $organization, string $permission): void
    {
        abort_unless(
            request()->user()?->hasPermission($permission, $organization),
            403,
            'No tienes permiso para esta acción.'
        );
    }
}
