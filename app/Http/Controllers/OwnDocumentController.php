<?php

namespace App\Http\Controllers;

use App\Http\Requests\Document\SaveOwnDocumentRequest;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentType;
use App\Models\Motorcycle;
use App\Models\Organization;
use App\Models\Person;
use App\Services\Documents\DocumentLibrary;
use App\Services\Documents\DocumentService;
use App\Services\People\MemberPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnDocumentController extends Controller
{
    public function __construct(
        private readonly DocumentLibrary $library,
        private readonly DocumentService $documents,
        private readonly MemberPhotoService $photos,
    ) {}

    public function index(Organization $organization): Response
    {
        $person = $this->ownerIn($organization);

        if ($person === null) {
            return Inertia::render('Documents/Mine', [
                'linked' => false,
                'name' => request()->user()?->name,
                'photo_url' => null,
                'types' => [],
                'motorcycles' => [],
            ]);
        }

        $payload = $this->library->forPerson(
            $organization,
            $person,
            fn (string $documentId, string $fileId) => route('documents.mine.file', [$organization, $documentId, $fileId]),
        );

        return Inertia::render('Documents/Mine', [
            'linked' => true,
            'name' => $person->full_name,
            'photo_url' => $this->photos->portraitUrl($person, route('profile.photo', $organization)),
            'types' => $payload['types'],
            'motorcycles' => $payload['motorcycles'],
        ]);
    }

    public function store(SaveOwnDocumentRequest $request, Organization $organization): RedirectResponse
    {
        $person = $this->ownerIn($organization);
        abort_unless($person, 403);

        $type = DocumentType::query()->where('slug', $request->validated('slug'))->where('active', true)->firstOrFail();
        $motorcycle = $this->motorcycleFor($person, $type, $request->validated('motorcycle_id'));
        $document = $this->existing($person, $type, $motorcycle);

        if ($document) {
            $this->save($document, $request);

            return back()->with('success', 'Documento actualizado.');
        }

        $document = $this->documents->create($organization, [
            'document_type_id' => $type->id,
            'person_id' => $type->isForPerson() ? $person->id : null,
            'motorcycle_id' => $type->isForMotorcycle() ? $motorcycle?->id : null,
            'document_number' => $request->validated('document_number'),
            'issue_date' => $request->validated('issue_date'),
            'expiration_date' => $request->validated('expiration_date'),
        ], null, $request->user());

        $this->attachFiles($document, $request);

        return back()->with('success', 'Documento actualizado.');
    }

    public function update(SaveOwnDocumentRequest $request, Organization $organization, Document $document): RedirectResponse
    {
        $type = $document->type;
        abort_unless($type && $type->slug === $request->validated('slug'), 422);

        $this->save($document, $request);

        return back()->with('success', 'Documento actualizado.');
    }

    public function file(Organization $organization, Document $document, DocumentFile $file): StreamedResponse
    {
        $person = $this->ownerIn($organization);
        abort_unless($person && $this->owns($person, $document), 403);
        abort_unless($file->document_id === $document->id, 404);

        return $this->documents->download($file, $organization, request()->user());
    }

    private function save(Document $document, SaveOwnDocumentRequest $request): void
    {
        $this->documents->update($document, [
            'document_number' => $request->validated('document_number'),
            'issue_date' => $request->validated('issue_date'),
            'expiration_date' => $request->validated('expiration_date'),
        ]);
        $this->attachFiles($document, $request);
    }

    private function attachFiles(Document $document, SaveOwnDocumentRequest $request): void
    {
        $file = $request->file('file');

        if ($file instanceof UploadedFile) {
            $this->documents->replaceFileOnSide($document, $file, $request->validated('side'));

            return;
        }

        $files = collect($request->file('files', []))
            ->filter(fn ($item) => $item instanceof UploadedFile)
            ->values()
            ->all();

        if ($files !== []) {
            $this->documents->replaceFiles($document, $files);
        }
    }

    private function existing(Person $person, DocumentType $type, ?Motorcycle $motorcycle): ?Document
    {
        $query = Document::query()->where('document_type_id', $type->id);

        if ($type->isForPerson()) {
            return $query->where('person_id', $person->id)->first();
        }

        if ($motorcycle === null) {
            return null;
        }

        return $query->where('motorcycle_id', $motorcycle->id)->first();
    }

    private function motorcycleFor(Person $person, DocumentType $type, ?string $motorcycleId): ?Motorcycle
    {
        if (! $type->isForMotorcycle()) {
            return null;
        }

        $motorcycles = $person->motorcycles()->get();

        if ($motorcycleId) {
            $motorcycle = $motorcycles->firstWhere('id', $motorcycleId);
            abort_unless($motorcycle, 403);

            return $motorcycle;
        }

        abort_if($motorcycles->count() !== 1, 422, 'Elige la moto de este documento.');

        return $motorcycles->first();
    }

    private function ownerIn(Organization $organization): ?Person
    {
        $person = request()->user()?->person;

        if (! $person || ! $person->belongsToOrganization($organization->id)) {
            return null;
        }

        return $person;
    }

    private function owns(Person $person, Document $document): bool
    {
        $document->loadMissing('motorcycle');

        if ($document->person_id === $person->id) {
            return true;
        }

        return $document->motorcycle?->person_id === $person->id;
    }
}
