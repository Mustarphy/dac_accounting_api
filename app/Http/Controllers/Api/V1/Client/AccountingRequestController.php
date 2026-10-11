<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Enums\InformationRequestStatus;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\CreateAccountingRequestRequest;
use App\Http\Requests\Client\ListAccountingRequestsRequest;
use App\Http\Resources\Client\AccountingRequestDetailResource;
use App\Http\Resources\Client\AccountingRequestResource;
use App\Models\AccountingRequest;
use App\Services\Client\AccountingRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingRequestController extends Controller
{
    public function __construct(private readonly AccountingRequestService $requests) {}

    public function index(ListAccountingRequestsRequest $request): JsonResponse
    {
        $query = AccountingRequest::query()
            ->where('client_id', $request->user()->id)
            ->withCount(['informationRequests as pending_information_requests_count' => function ($q) {
                $q->where('status', InformationRequestStatus::Pending);
            }])
            ->latest('created_at');

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->validated('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate(15);

        return $this->success([
            'requests' => AccountingRequestResource::collection($paginator->items()),
            'pagination' => $this->paginationMeta($paginator),
        ], 'OK');
    }

    public function store(CreateAccountingRequestRequest $request): JsonResponse
    {
        $accountingRequest = $this->requests->create($request->user(), [
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'serviceType' => ServiceType::from($request->validated('serviceType')),
        ]);

        return $this->success(
            ['request' => new AccountingRequestResource($accountingRequest)],
            'Request submitted.',
            201
        );
    }

    public function show(Request $request, AccountingRequest $accountingRequest): JsonResponse
    {
        $this->authorize('view', $accountingRequest);

        $accountingRequest->load([
            'documents' => fn ($q) => $q->latest('created_at'),
            'informationRequests' => fn ($q) => $q->latest('created_at'),
            'activities.actor',
        ]);

        return $this->success(
            ['request' => new AccountingRequestDetailResource($accountingRequest)],
            'OK'
        );
    }

    private function paginationMeta($paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
