<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Enums\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UploadDocumentRequest;
use App\Http\Resources\Client\RequestDocumentResource;
use App\Models\AccountingRequest;
use App\Models\RequestDocument;
use App\Services\Client\DocumentUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentUploadService $uploads) {}

    public function store(UploadDocumentRequest $request, AccountingRequest $accountingRequest): JsonResponse
    {
        $this->authorize('createDocument', $accountingRequest);

        $document = $this->uploads->store(
            $accountingRequest,
            $request->user(),
            $request->file('file'),
            DocumentCategory::from($request->validated('category')),
        );

        return $this->success(['document' => new RequestDocumentResource($document)], 'Document uploaded.', 201);
    }

    /**
     * The "Document Centre" — every document across every one of the
     * client's requests, not scoped to a single request.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RequestDocument::query()
            ->whereHas('accountingRequest', fn ($q) => $q->where('client_id', $request->user()->id))
            ->with('uploadedBy')
            ->latest('created_at');

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($accountingRequestId = $request->query('requestId')) {
            $query->where('accounting_request_id', $accountingRequestId);
        }

        $paginator = $query->paginate(20);

        return $this->success([
            'documents' => RequestDocumentResource::collection($paginator->items()),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'OK');
    }
}
