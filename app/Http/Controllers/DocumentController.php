<?php

namespace App\Http\Controllers;

use App\Http\Requests\Document\StoreDocumentRequest;
use App\Jobs\ProcessDocumentScanJob;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentScan;
use App\Models\DocumentType;
use App\Models\Motorcycle;
use App\Models\Organization;
use App\Models\Person;
use App\Services\Documents\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents)
    {
    }

    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', [Document::class, $organization]);

        $documents = Document::query()
            ->inOrganization($organization->id)
            ->with(['type', 'person', 'motorcycle', 'files'])
            ->when($request->string('q')->toString(), function ($query, string $search): void {
                $query->where('document_number', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'documentTypes' => DocumentType::query()->where('active', true)->orderBy('name')->get(),
            'people' => Person::query()->inOrganization($organization->id)->orderBy('last_name')->get(['id', 'first_name', 'last_name']),
            'motorcycles' => Motorcycle::query()->inOrganization($organization->id)->with('person:id,first_name,last_name')->get(['id', 'person_id', 'plate', 'brand']),
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    public function expiring(Organization $organization): Response
    {
        $this->authorize('viewAny', [Document::class, $organization]);

        return Inertia::render('Documents/Expiring', [
            'documents' => Document::query()
                ->inOrganization($organization->id)
                ->with(['type', 'person', 'motorcycle'])
                ->expiringSoon(45)
                ->orderBy('expiration_date')
                ->get(),
        ]);
    }

    public function store(StoreDocumentRequest $request, Organization $organization): RedirectResponse
    {
        $this->documents->create(
            $organization,
            $request->validated(),
            $request->file('file'),
            $request->user()
        );

        return back()->with('success', 'Documento registrado.');
    }

    public function download(Organization $organization, Document $document, DocumentFile $file): StreamedResponse
    {
        $this->authorize('download', [$document, $organization]);

        abort_unless($file->document_id === $document->id, 404);

        return $this->documents->download($file, $organization, request()->user());
    }

    public function scan(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('scan', [Document::class, $organization]);

        $data = $request->validate([
            'document_type_id' => ['required', 'exists:document_types,id'],
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
        ]);

        $path = $request->file('file')->store('pending', 'scans');

        $scan = DocumentScan::query()->create([
            'document_type_id' => $data['document_type_id'],
            'original_file' => $path,
            'status' => 'pending',
            'provider' => config('services.ocr.provider'),
        ]);

        ProcessDocumentScanJob::dispatch($scan->id);

        return back()->with('success', 'El escaneo quedó en cola para revisión. El OCR se implementará en la siguiente fase.');
    }
}
