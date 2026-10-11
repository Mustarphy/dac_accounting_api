<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CreateInformationRequestRequest;
use App\Http\Resources\Client\InformationRequestResource;
use App\Models\AccountingRequest;
use App\Services\Staff\InformationRequestCreationService;
use Illuminate\Http\JsonResponse;

class AdminInformationRequestController extends Controller
{
    public function __construct(private readonly InformationRequestCreationService $creator) {}

    public function store(CreateInformationRequestRequest $request, AccountingRequest $accountingRequest): JsonResponse
    {
        $informationRequest = $this->creator->create(
            $accountingRequest,
            $request->user(),
            $request->validated('message'),
        );

        return $this->success(
            ['informationRequest' => new InformationRequestResource($informationRequest)],
            'Information request sent to client.',
            201
        );
    }
}
