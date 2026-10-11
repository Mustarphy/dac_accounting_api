<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\RespondToInformationRequestRequest;
use App\Http\Resources\Client\InformationRequestResource;
use App\Models\InformationRequest;
use App\Services\Client\InformationRequestResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InformationRequestController extends Controller
{
    public function __construct(private readonly InformationRequestResponseService $responses) {}

    public function index(Request $request): JsonResponse
    {
        $query = InformationRequest::query()
            ->whereHas('accountingRequest', fn ($q) => $q->where('client_id', $request->user()->id))
            ->latest('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->success([
            'informationRequests' => InformationRequestResource::collection($query->get()),
        ], 'OK');
    }

    public function respond(RespondToInformationRequestRequest $request, InformationRequest $informationRequest): JsonResponse
    {
        $this->authorize('view', $informationRequest->accountingRequest);

        $informationRequest = $this->responses->respond(
            $informationRequest,
            $request->user(),
            $request->validated('message'),
        );

        return $this->success(
            ['informationRequest' => new InformationRequestResource($informationRequest)],
            'Response submitted.'
        );
    }
}
