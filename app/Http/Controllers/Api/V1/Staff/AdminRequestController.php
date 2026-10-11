<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\InformationRequestStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateRequestStatusRequest;
use App\Http\Resources\Staff\AdminAccountingRequestDetailResource;
use App\Http\Resources\Staff\AdminAccountingRequestResource;
use App\Models\AccountingRequest;
use App\Services\Staff\RequestStatusTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff see every client's requests — there is no per-record ownership
 * check here the way AccountingRequestPolicy enforces for clients. The
 * boundary for this whole controller is the `role:admin,staff`
 * middleware on its route group, not a per-request authorize() call.
 */
class AdminRequestController extends Controller
{
    public function __construct(private readonly RequestStatusTransitionService $transitions) {}

    public function index(Request $request): JsonResponse
    {
        $query = AccountingRequest::query()
            ->with('client')
            ->withCount([
                'informationRequests as pending_information_requests_count' => fn ($q) => $q->where('status', InformationRequestStatus::Pending),
                'documents as unreviewed_documents_count' => fn ($q) => $q->where('source', 'client')->whereNull('review_status'),
            ])
            ->latest('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($clientId = $request->query('clientId')) {
            $query->where('client_id', $clientId);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($request->boolean('needsAttention')) {
            $query->where(function ($q) {
                $q->whereHas('informationRequests', fn ($ir) => $ir->where('status', InformationRequestStatus::Pending))
                    ->orWhereHas('documents', fn ($d) => $d->where('source', 'client')->whereNull('review_status'));
            });
        }

        $paginator = $query->paginate(20);

        return $this->success([
            'requests' => AdminAccountingRequestResource::collection($paginator->items()),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'OK');
    }

    public function show(AccountingRequest $accountingRequest): JsonResponse
    {
        $accountingRequest->load([
            'client',
            'documents' => fn ($q) => $q->latest('created_at')->with('uploadedBy'),
            'informationRequests' => fn ($q) => $q->latest('created_at'),
            'internalNotes.author',
            'activities.actor',
        ]);

        return $this->success(
            ['request' => new AdminAccountingRequestDetailResource($accountingRequest)],
            'OK'
        );
    }

    public function updateStatus(UpdateRequestStatusRequest $request, AccountingRequest $accountingRequest): JsonResponse
    {
        $updated = $this->transitions->transition(
            $accountingRequest,
            RequestStatus::from($request->validated('status')),
            $request->user(),
        );

        return $this->success(['request' => new AdminAccountingRequestResource($updated->load('client'))], 'Status updated.');
    }
}
